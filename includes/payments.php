<?php
/**
 * Sharan Foundation — Unified Payment Gateway Helper
 *
 * Wraps Razorpay, Stripe, and PayPal REST APIs without requiring Composer/SDKs.
 * Pure cURL + JSON — works on any standard PHP host with cURL enabled.
 *
 * For each gateway we expose:
 *   - create_order()           ← creates a pending order/intent on the gateway side
 *   - verify_payment()         ← validates the response after donor completes checkout
 *   - create_subscription()    ← (optional) for recurring auto-charge
 *   - charge_subscription()    ← used by the cron to take recurring payment
 *
 * SANDBOX vs LIVE mode is read from settings.gateway_mode.
 */

require_once __DIR__ . '/../config/database.php';

// ========================================================
// Gateway config loader
// ========================================================
function gateway_config(): array {
    global $pdo;
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try { $cfg = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch() ?: []; }
    catch (Throwable $e) { $cfg = []; }
    return $cfg;
}

function gateway_mode(): string {
    return gateway_config()['gateway_mode'] ?? 'sandbox';
}

function is_gateway_enabled(string $gw): bool {
    $cfg = gateway_config();
    return !empty($cfg[$gw . '_enabled']);
}

function has_gateway_credentials(string $gw): bool {
    $cfg = gateway_config();
    switch ($gw) {
        case 'razorpay': return !empty($cfg['razorpay_key_id']) && !empty($cfg['razorpay_key_secret']) && stripos($cfg['razorpay_key_id'], 'rzp_') === 0 && strlen($cfg['razorpay_key_secret']) >= 8;
        case 'stripe':   return !empty($cfg['stripe_secret_key']) && stripos($cfg['stripe_secret_key'], 'sk_') === 0 && strlen($cfg['stripe_secret_key']) >= 20;
        case 'paypal':   return !empty($cfg['paypal_client_id']) && !empty($cfg['paypal_client_secret']) && strlen($cfg['paypal_client_id']) >= 20;
    }
    return false;
}

/** HTTP helper using cURL — returns [decoded_body, http_status, raw_body] */
function gateway_http(string $url, string $method = 'POST', $body = null, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($raw === false) return [['error' => 'cURL failed: ' . $err], 0, ''];
    $decoded = json_decode($raw, true);
    return [is_array($decoded) ? $decoded : ['raw' => $raw], (int)$status, $raw];
}

// ========================================================
// RAZORPAY  (India — INR primary)
// API docs: https://razorpay.com/docs/api/
// ========================================================
class Razorpay {
    public static function create_order(float $amount, string $currency, array $notes = []): array {
        $cfg = gateway_config();
        $payload = [
            'amount'   => (int)round($amount * 100),   // Razorpay uses paise
            'currency' => strtoupper($currency),
            'receipt'  => 'rcpt_' . time() . '_' . random_int(1000, 9999),
            'notes'    => $notes,
        ];
        $auth = base64_encode($cfg['razorpay_key_id'] . ':' . $cfg['razorpay_key_secret']);
        [$body, $http] = gateway_http('https://api.razorpay.com/v1/orders', 'POST', json_encode($payload),
            ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
        if ($http >= 200 && $http < 300 && !empty($body['id'])) {
            return ['ok' => true, 'order_id' => $body['id'], 'amount' => $amount, 'currency' => $currency, 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['error']['description'] ?? ('Razorpay order creation failed (HTTP ' . $http . ')'), 'raw' => $body];
    }

    /** Verify the signature returned by Razorpay Checkout after donor pays */
    public static function verify_payment(string $order_id, string $payment_id, string $signature): bool {
        $cfg = gateway_config();
        $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, $cfg['razorpay_key_secret']);
        return hash_equals($expected, $signature);
    }

    /** Fetch full payment details (after successful checkout) */
    public static function fetch_payment(string $payment_id): array {
        $cfg = gateway_config();
        $auth = base64_encode($cfg['razorpay_key_id'] . ':' . $cfg['razorpay_key_secret']);
        [$body, $http] = gateway_http('https://api.razorpay.com/v1/payments/' . urlencode($payment_id), 'GET', null,
            ['Authorization: Basic ' . $auth]);
        return ['ok' => $http >= 200 && $http < 300, 'data' => $body];
    }

    /** Verify webhook signature (X-Razorpay-Signature header) */
    public static function verify_webhook(string $payload, string $signature): bool {
        $cfg = gateway_config();
        if (empty($cfg['razorpay_webhook_secret'])) return false;
        $expected = hash_hmac('sha256', $payload, $cfg['razorpay_webhook_secret']);
        return hash_equals($expected, $signature);
    }

    /** Create a subscription (for recurring) — requires a plan_id created via dashboard or API */
    public static function create_subscription(string $plan_id, int $total_count = 12, array $notes = []): array {
        $cfg = gateway_config();
        $auth = base64_encode($cfg['razorpay_key_id'] . ':' . $cfg['razorpay_key_secret']);
        $payload = ['plan_id' => $plan_id, 'total_count' => $total_count, 'customer_notify' => 1, 'notes' => $notes];
        [$body, $http] = gateway_http('https://api.razorpay.com/v1/subscriptions', 'POST', json_encode($payload),
            ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
        if ($http >= 200 && $http < 300 && !empty($body['id'])) {
            return ['ok' => true, 'subscription_id' => $body['id'], 'short_url' => $body['short_url'] ?? null, 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['error']['description'] ?? 'subscription creation failed', 'raw' => $body];
    }
}

// ========================================================
// STRIPE  (Global cards)
// API docs: https://stripe.com/docs/api
// ========================================================
class Stripe {
    /** Create a Checkout Session (hosted checkout — recommended) */
    public static function create_checkout_session(float $amount, string $currency, string $donor_email, string $purpose, string $success_url, string $cancel_url, bool $recurring = false, string $interval = 'month'): array {
        $cfg = gateway_config();
        $minor = strtolower($currency) === 'jpy' ? (int)$amount : (int)round($amount * 100);  // most currencies use cents

        $payload = [
            'mode'                  => $recurring ? 'subscription' : 'payment',
            'customer_email'        => $donor_email,
            'success_url'           => $success_url,
            'cancel_url'            => $cancel_url,
            'metadata[purpose]'     => $purpose,
            'line_items[0][quantity]' => 1,
        ];
        if ($recurring) {
            // For subscriptions: create inline price with recurring interval
            $payload['line_items[0][price_data][currency]']               = strtolower($currency);
            $payload['line_items[0][price_data][unit_amount]']            = $minor;
            $payload['line_items[0][price_data][product_data][name]']     = 'Sharan Foundation — ' . $purpose . ' (' . $interval . 'ly)';
            $payload['line_items[0][price_data][recurring][interval]']    = $interval; // day/week/month/year
        } else {
            $payload['line_items[0][price_data][currency]']           = strtolower($currency);
            $payload['line_items[0][price_data][unit_amount]']        = $minor;
            $payload['line_items[0][price_data][product_data][name]'] = 'Sharan Foundation — ' . $purpose;
        }

        [$body, $http] = gateway_http('https://api.stripe.com/v1/checkout/sessions', 'POST', http_build_query($payload),
            ['Authorization: Bearer ' . $cfg['stripe_secret_key'], 'Content-Type: application/x-www-form-urlencoded']);
        if ($http >= 200 && $http < 300 && !empty($body['id'])) {
            return ['ok' => true, 'session_id' => $body['id'], 'checkout_url' => $body['url'], 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['error']['message'] ?? 'Stripe session creation failed', 'raw' => $body];
    }

    /** Retrieve a checkout session (after the donor returns from Stripe) */
    public static function fetch_session(string $session_id): array {
        $cfg = gateway_config();
        [$body, $http] = gateway_http('https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id) . '?expand[]=payment_intent', 'GET', null,
            ['Authorization: Bearer ' . $cfg['stripe_secret_key']]);
        return ['ok' => $http >= 200 && $http < 300, 'data' => $body];
    }

    /** Verify webhook signature (per Stripe spec) */
    public static function verify_webhook(string $payload, string $signature_header): bool {
        $cfg = gateway_config();
        $secret = $cfg['stripe_webhook_secret'] ?? '';
        if (!$secret) return false;
        // Stripe header: t=timestamp,v1=signature[,v0=...]
        $parts = [];
        foreach (explode(',', $signature_header) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) $parts[trim($kv[0])] = trim($kv[1]);
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;
        $signed_payload = $parts['t'] . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    /** Off-session charge using a saved customer (for cron auto-charge) */
    public static function charge_customer(string $customer_id, string $payment_method_id, float $amount, string $currency, string $description = ''): array {
        $cfg = gateway_config();
        $minor = strtolower($currency) === 'jpy' ? (int)$amount : (int)round($amount * 100);
        $payload = [
            'amount'         => $minor,
            'currency'       => strtolower($currency),
            'customer'       => $customer_id,
            'payment_method' => $payment_method_id,
            'off_session'    => 'true',
            'confirm'        => 'true',
            'description'    => $description,
        ];
        [$body, $http] = gateway_http('https://api.stripe.com/v1/payment_intents', 'POST', http_build_query($payload),
            ['Authorization: Bearer ' . $cfg['stripe_secret_key'], 'Content-Type: application/x-www-form-urlencoded']);
        if ($http >= 200 && $http < 300 && ($body['status'] ?? '') === 'succeeded') {
            return ['ok' => true, 'txn_id' => $body['id'], 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['error']['message'] ?? ('Stripe charge failed: ' . ($body['status'] ?? 'unknown')), 'raw' => $body];
    }
}

// ========================================================
// PAYPAL  (Global)
// REST API docs: https://developer.paypal.com/api/rest/
// ========================================================
class PayPal {
    public static function api_base(): string {
        return gateway_mode() === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    /** Get an OAuth access token (cached in session for ~30 min) */
    public static function access_token(): ?string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $key = 'paypal_token_' . gateway_mode();
        if (!empty($_SESSION[$key]) && !empty($_SESSION[$key.'_exp']) && time() < $_SESSION[$key.'_exp']) {
            return $_SESSION[$key];
        }
        $cfg = gateway_config();
        $auth = base64_encode($cfg['paypal_client_id'] . ':' . $cfg['paypal_client_secret']);
        [$body, $http] = gateway_http(self::api_base() . '/v1/oauth2/token', 'POST', 'grant_type=client_credentials',
            ['Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded']);
        if ($http >= 200 && $http < 300 && !empty($body['access_token'])) {
            $_SESSION[$key]         = $body['access_token'];
            $_SESSION[$key.'_exp']  = time() + (int)($body['expires_in'] ?? 1800) - 60;
            return $body['access_token'];
        }
        return null;
    }

    public static function create_order(float $amount, string $currency, string $description, string $return_url, string $cancel_url): array {
        $token = self::access_token();
        if (!$token) return ['ok' => false, 'error' => 'PayPal auth failed'];

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount'      => ['currency_code' => strtoupper($currency), 'value' => number_format($amount, 2, '.', '')],
                'description' => $description,
            ]],
            'application_context' => [
                'brand_name'    => 'Sharan Foundation',
                'user_action'   => 'PAY_NOW',
                'return_url'    => $return_url,
                'cancel_url'    => $cancel_url,
            ],
        ];
        [$body, $http] = gateway_http(self::api_base() . '/v2/checkout/orders', 'POST', json_encode($payload),
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);

        if (in_array($http, [200, 201]) && !empty($body['id'])) {
            // Find approval URL
            $approve_url = null;
            foreach (($body['links'] ?? []) as $link) {
                if (($link['rel'] ?? '') === 'approve') { $approve_url = $link['href']; break; }
            }
            return ['ok' => true, 'order_id' => $body['id'], 'approve_url' => $approve_url, 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['message'] ?? 'PayPal order creation failed', 'raw' => $body];
    }

    /** Capture an approved order (after donor returns) */
    public static function capture_order(string $order_id): array {
        $token = self::access_token();
        if (!$token) return ['ok' => false, 'error' => 'PayPal auth failed'];
        [$body, $http] = gateway_http(self::api_base() . '/v2/checkout/orders/' . urlencode($order_id) . '/capture', 'POST', '{}',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        if (in_array($http, [200, 201]) && ($body['status'] ?? '') === 'COMPLETED') {
            $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
            return ['ok' => true, 'txn_id' => $capture['id'] ?? $order_id, 'amount' => $capture['amount']['value'] ?? null, 'raw' => $body];
        }
        return ['ok' => false, 'error' => $body['message'] ?? ('PayPal capture failed: ' . ($body['status'] ?? 'unknown')), 'raw' => $body];
    }

    /** Verify webhook (calls PayPal verification API) */
    public static function verify_webhook(array $headers, string $payload): bool {
        $token = self::access_token();
        $cfg = gateway_config();
        if (!$token || empty($cfg['paypal_webhook_id'])) return false;
        $verification = [
            'auth_algo'         => $headers['paypal-auth-algo']         ?? $headers['Paypal-Auth-Algo']         ?? '',
            'cert_url'          => $headers['paypal-cert-url']          ?? $headers['Paypal-Cert-Url']          ?? '',
            'transmission_id'   => $headers['paypal-transmission-id']   ?? $headers['Paypal-Transmission-Id']   ?? '',
            'transmission_sig'  => $headers['paypal-transmission-sig']  ?? $headers['Paypal-Transmission-Sig']  ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? $headers['Paypal-Transmission-Time'] ?? '',
            'webhook_id'        => $cfg['paypal_webhook_id'],
            'webhook_event'     => json_decode($payload, true),
        ];
        [$body, $http] = gateway_http(self::api_base() . '/v1/notifications/verify-webhook-signature', 'POST', json_encode($verification),
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        return $http === 200 && ($body['verification_status'] ?? '') === 'SUCCESS';
    }
}

// ========================================================
// Helper: list gateways available for a given currency
// ========================================================
function available_gateways(string $currency = 'INR'): array {
    $list = [];
    $currency = strtoupper($currency);

    // All 3 gateways available for all currencies (per chosen scope).
    // Razorpay officially supports INR + a few international currencies; we allow it for all
    // and trust the dashboard config to reject if unsupported.
    if (is_gateway_enabled('razorpay') && has_gateway_credentials('razorpay')) {
        $list['razorpay'] = [
            'name'    => 'Razorpay',
            'desc'    => 'Cards, UPI, Net Banking, Wallets',
            'icon'    => '💳',
            'best_for'=> $currency === 'INR' ? 'Recommended for India' : 'International',
        ];
    }
    if (is_gateway_enabled('stripe') && has_gateway_credentials('stripe')) {
        $list['stripe'] = [
            'name'    => 'Stripe',
            'desc'    => 'Credit / Debit Cards, Apple Pay, Google Pay',
            'icon'    => '💳',
            'best_for'=> 'International cards',
        ];
    }
    if (is_gateway_enabled('paypal') && has_gateway_credentials('paypal')) {
        $list['paypal'] = [
            'name'    => 'PayPal',
            'desc'    => 'PayPal balance, Cards via PayPal',
            'icon'    => '🅿️',
            'best_for'=> 'PayPal account holders',
        ];
    }
    return $list;
}
