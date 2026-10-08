<?php
/**
 * Stripe webhook receiver.
 * Configure in Stripe dashboard → Developers → Webhooks. Subscribe to:
 *   checkout.session.completed, invoice.payment_succeeded, invoice.payment_failed,
 *   customer.subscription.deleted
 * URL: https://sharanforall.org/api/payment/stripe_webhook.php
 */

require_once __DIR__ . '/_helpers.php';

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$sig_ok    = Stripe::verify_webhook($payload, $signature);

$event = json_decode($payload, true) ?: [];
$event_type = $event['type'] ?? 'unknown';
$event_id   = $event['id']   ?? null;
$object     = $event['data']['object'] ?? [];

if (!$sig_ok) {
    log_webhook($pdo, 'stripe', $event_type, $event_id, $payload, false, 'signature failed');
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$result = '';
$donation_id = null;

try {
    if ($event_type === 'checkout.session.completed') {
        $session_id = $object['id'] ?? null;
        $stmt = $pdo->prepare("SELECT id FROM donations WHERE gateway_order_id=?");
        $stmt->execute([$session_id]);
        $donation_id = (int)($stmt->fetchColumn() ?: 0);
        if ($donation_id) {
            $extra = ['gateway_order_id' => $session_id, 'gateway_response_raw' => json_encode($object)];
            if (($object['mode'] ?? '') === 'subscription') {
                $extra['gateway_subscription_id'] = $object['subscription'] ?? null;
                $extra['gateway_customer_id']     = $object['customer']     ?? null;
            }
            finalize_paid_donation($pdo, $donation_id, 'stripe', $object['payment_intent'] ?? $object['subscription'] ?? $session_id, $extra);
            $result = 'donation #' . $donation_id . ' completed';
        } else {
            $result = 'no matching donation for session ' . $session_id;
        }
    }
    elseif ($event_type === 'invoice.payment_succeeded') {
        // Recurring subscription charge succeeded
        $sub_id = $object['subscription'] ?? null;
        if ($sub_id) {
            $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE gateway_subscription_id=?");
            $stmt->execute([$sub_id]);
            $rec = $stmt->fetch();
            if ($rec) {
                require_once __DIR__ . '/../../cron/recurring_donations.php';
                finalize_successful_charge($pdo, $rec, $object['payment_intent'] ?? $object['id'] ?? null, 'webhook invoice.payment_succeeded');
                $result = 'recurring #' . $rec['id'] . ' charged';
            }
        }
    }
    elseif ($event_type === 'invoice.payment_failed') {
        $sub_id = $object['subscription'] ?? null;
        if ($sub_id) {
            $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE gateway_subscription_id=?");
            $stmt->execute([$sub_id]);
            $rec = $stmt->fetch();
            if ($rec) {
                require_once __DIR__ . '/../../cron/recurring_donations.php';
                finalize_failed_charge($pdo, $rec, 'Stripe invoice.payment_failed');
                $result = 'recurring #' . $rec['id'] . ' failed';
            }
        }
    }
    elseif ($event_type === 'customer.subscription.deleted') {
        $sub_id = $object['id'] ?? null;
        if ($sub_id) {
            $pdo->prepare("UPDATE recurring_donations SET status='cancelled', cancelled_at=NOW() WHERE gateway_subscription_id=?")
                ->execute([$sub_id]);
            $result = 'subscription cancelled';
        }
    }
} catch (Throwable $e) {
    $result = 'exception: ' . $e->getMessage();
}

log_webhook($pdo, 'stripe', $event_type, $event_id, $payload, true, $result, $donation_id);

http_response_code(200);
echo 'ok';
