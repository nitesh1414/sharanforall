<?php
/**
 * Sharan Foundation — Payment Initialization Endpoint
 *
 * The donate.php form posts here. We:
 *  1. Save a pending `donations` row (gives us a donation_id to track)
 *  2. Ask the selected gateway to create an order/intent/session
 *  3. Return the gateway-specific data the frontend needs to launch checkout
 *     (JSON for Razorpay/PayPal popup, redirect URL for Stripe Checkout)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/payments.php';

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);

// Basic validation
$required = ['donor_name', 'email', 'phone', 'amount', 'gateway'];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) fail("Missing required field: $f");
}
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) fail('Invalid email address');
$amount = (float) preg_replace('/[^0-9.]/', '', $_POST['amount']);
if ($amount < 1) fail('Amount must be at least 1');

$gateway = strtolower($_POST['gateway']);
if (!in_array($gateway, ['razorpay', 'stripe', 'paypal'])) fail('Invalid gateway selected');
if (!is_gateway_enabled($gateway))        fail(ucfirst($gateway) . ' is not enabled');
if (!has_gateway_credentials($gateway))   fail(ucfirst($gateway) . ' is not configured — please contact admin');

// Build donation data (save as pending first — admin can see all attempts)
$currency      = in_array($_POST['currency'] ?? '', ['INR','GBP','USD']) ? $_POST['currency'] : 'INR';
$donation_type = in_array($_POST['donation_type'] ?? '', ['one-time','monthly','yearly']) ? $_POST['donation_type'] : 'one-time';
$frequency     = in_array($_POST['frequency'] ?? '', ['weekly','monthly','quarterly','yearly']) ? $_POST['frequency'] : ($donation_type === 'yearly' ? 'yearly' : 'monthly');
$is_recurring  = $donation_type !== 'one-time';

$data = [
    'donor_name'     => trim($_POST['donor_name']),
    'email'          => trim($_POST['email']),
    'phone'          => trim($_POST['phone']),
    'address'        => trim($_POST['address'] ?? ''),
    'city'           => trim($_POST['city'] ?? ''),
    'state'          => trim($_POST['state'] ?? ''),
    'country'        => trim($_POST['country'] ?? 'India'),
    'pincode'        => trim($_POST['pincode'] ?? ''),
    'pan_number'     => trim(strtoupper($_POST['pan_number'] ?? '')),
    'amount'         => $amount,
    'currency'       => $currency,
    'donation_type'  => $donation_type,
    'purpose'        => trim($_POST['purpose'] ?? 'Where Most Needed'),
    'message'        => trim($_POST['message'] ?? ''),
    'payment_method' => $gateway,
    'gateway'        => $gateway,
    'payment_status' => 'pending',
    'is_anonymous'     => isset($_POST['is_anonymous']) ? 1 : 0,
    'receipt_required' => isset($_POST['receipt_required']) ? 1 : 0,
    'newsletter_optin' => isset($_POST['newsletter_optin']) ? 1 : 0,
];

try {
    $cols  = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO donations ($cols) VALUES ($place)")->execute($data);
    $donation_id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}

// Build base URL helpers
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'];
$base  = $proto . '://' . $host . BASE_URL;

$notes = [
    'donation_id' => $donation_id,
    'donor_email' => $data['email'],
    'purpose'     => $data['purpose'],
];

// ============================================================
// RAZORPAY  — checkout popup
// ============================================================
if ($gateway === 'razorpay') {
    $result = Razorpay::create_order($amount, $currency, $notes);
    if (!$result['ok']) fail($result['error'], 502);

    // Save gateway order id to our donations row
    $pdo->prepare("UPDATE donations SET gateway_order_id=?, gateway_response_raw=? WHERE id=?")
        ->execute([$result['order_id'], json_encode($result['raw']), $donation_id]);

    $cfg = gateway_config();
    echo json_encode([
        'ok'          => true,
        'gateway'     => 'razorpay',
        'donation_id' => $donation_id,
        // Frontend will use these to launch the Razorpay popup
        'key_id'      => $cfg['razorpay_key_id'],
        'order_id'    => $result['order_id'],
        'amount'      => (int)round($amount * 100),
        'currency'    => $currency,
        'name'        => 'Sharan Foundation',
        'description' => 'Donation — ' . $data['purpose'],
        'prefill'     => [
            'name'    => $data['donor_name'],
            'email'   => $data['email'],
            'contact' => $data['phone'],
        ],
        'callback_url' => $base . 'api/payment/razorpay_success.php?donation_id=' . $donation_id,
    ]);
    exit;
}

// ============================================================
// STRIPE  — hosted checkout redirect
// ============================================================
if ($gateway === 'stripe') {
    $success_url = $base . 'api/payment/stripe_success.php?donation_id=' . $donation_id . '&session_id={CHECKOUT_SESSION_ID}';
    $cancel_url  = $base . 'pages/donate.php?cancelled=1';

    $stripe_interval = $frequency === 'weekly' ? 'week' : ($frequency === 'yearly' ? 'year' : 'month');
    $result = Stripe::create_checkout_session($amount, $currency, $data['email'], $data['purpose'],
                                              $success_url, $cancel_url, $is_recurring, $stripe_interval);
    if (!$result['ok']) fail($result['error'], 502);

    $pdo->prepare("UPDATE donations SET gateway_order_id=?, gateway_response_raw=? WHERE id=?")
        ->execute([$result['session_id'], json_encode($result['raw']), $donation_id]);

    echo json_encode([
        'ok'           => true,
        'gateway'      => 'stripe',
        'donation_id'  => $donation_id,
        'redirect_url' => $result['checkout_url'],
        'session_id'   => $result['session_id'],
    ]);
    exit;
}

// ============================================================
// PAYPAL  — order then redirect to approval URL
// ============================================================
if ($gateway === 'paypal') {
    $return_url = $base . 'api/payment/paypal_success.php?donation_id=' . $donation_id;
    $cancel_url = $base . 'pages/donate.php?cancelled=1';

    $result = PayPal::create_order($amount, $currency, 'Sharan Foundation — ' . $data['purpose'], $return_url, $cancel_url);
    if (!$result['ok']) fail($result['error'], 502);

    $pdo->prepare("UPDATE donations SET gateway_order_id=?, gateway_response_raw=? WHERE id=?")
        ->execute([$result['order_id'], json_encode($result['raw']), $donation_id]);

    echo json_encode([
        'ok'           => true,
        'gateway'      => 'paypal',
        'donation_id'  => $donation_id,
        'redirect_url' => $result['approve_url'],
        'order_id'     => $result['order_id'],
    ]);
    exit;
}

fail('Unhandled gateway', 500);
