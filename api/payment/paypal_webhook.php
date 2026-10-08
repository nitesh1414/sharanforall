<?php
/**
 * PayPal webhook receiver.
 * Configure in PayPal dashboard → Apps & Credentials → Webhooks.
 * Subscribe to: CHECKOUT.ORDER.APPROVED, PAYMENT.CAPTURE.COMPLETED,
 *               PAYMENT.CAPTURE.DENIED, BILLING.SUBSCRIPTION.* events
 * URL: https://sharanforall.org/api/payment/paypal_webhook.php
 */

require_once __DIR__ . '/_helpers.php';

$payload = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

$sig_ok = PayPal::verify_webhook($headers, $payload);

$event      = json_decode($payload, true) ?: [];
$event_type = $event['event_type'] ?? 'unknown';
$event_id   = $event['id']         ?? null;
$resource   = $event['resource']   ?? [];

if (!$sig_ok) {
    log_webhook($pdo, 'paypal', $event_type, $event_id, $payload, false, 'signature failed');
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$result = '';
$donation_id = null;

try {
    if ($event_type === 'PAYMENT.CAPTURE.COMPLETED') {
        // Find donation via the order ID (custom_id or related order id)
        $payment_id = $resource['id'] ?? null;
        $order_id   = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if ($order_id) {
            $stmt = $pdo->prepare("SELECT id FROM donations WHERE gateway_order_id=?");
            $stmt->execute([$order_id]);
            $donation_id = (int)($stmt->fetchColumn() ?: 0);
            if ($donation_id) {
                finalize_paid_donation($pdo, $donation_id, 'paypal', $payment_id, [
                    'gateway_order_id'     => $order_id,
                    'gateway_response_raw' => json_encode($resource),
                ]);
                $result = 'donation #' . $donation_id . ' completed via webhook';
            }
        }
    }
    elseif ($event_type === 'PAYMENT.CAPTURE.DENIED') {
        $order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if ($order_id) {
            $stmt = $pdo->prepare("SELECT id FROM donations WHERE gateway_order_id=?");
            $stmt->execute([$order_id]);
            $donation_id = (int)($stmt->fetchColumn() ?: 0);
            if ($donation_id) {
                mark_donation_failed($pdo, $donation_id, 'PayPal capture denied', json_encode($resource));
                $result = 'donation #' . $donation_id . ' marked failed';
            }
        }
    }
    elseif (strpos($event_type, 'BILLING.SUBSCRIPTION.') === 0) {
        $sub_id = $resource['id'] ?? null;
        if ($sub_id) {
            $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE gateway_subscription_id=?");
            $stmt->execute([$sub_id]);
            $rec = $stmt->fetch();
            if ($rec) {
                if ($event_type === 'BILLING.SUBSCRIPTION.CANCELLED') {
                    $pdo->prepare("UPDATE recurring_donations SET status='cancelled', cancelled_at=NOW() WHERE id=?")
                        ->execute([$rec['id']]);
                    $result = 'PayPal subscription cancelled';
                }
            }
        }
    }
} catch (Throwable $e) {
    $result = 'exception: ' . $e->getMessage();
}

log_webhook($pdo, 'paypal', $event_type, $event_id, $payload, true, $result, $donation_id);

http_response_code(200);
echo 'ok';
