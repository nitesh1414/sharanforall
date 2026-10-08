<?php
/**
 * Razorpay webhook receiver.
 * Subscribe to: payment.captured, payment.failed, subscription.charged, subscription.cancelled
 * Set webhook URL in Razorpay dashboard:
 *   https://sharanforall.org/api/payment/razorpay_webhook.php
 */

require_once __DIR__ . '/_helpers.php';

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$sig_ok    = Razorpay::verify_webhook($payload, $signature);

$event = json_decode($payload, true) ?: [];
$event_type = $event['event']    ?? 'unknown';
$event_id   = $event['id']       ?? null;
$entity     = $event['payload']['payment']['entity'] ?? $event['payload']['subscription']['entity'] ?? [];

if (!$sig_ok) {
    log_webhook($pdo, 'razorpay', $event_type, $event_id, $payload, false, 'signature failed');
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$result = '';
$donation_id = null;

try {
    // payment.captured → mark donation completed
    if ($event_type === 'payment.captured') {
        $order_id   = $entity['order_id']   ?? null;
        $payment_id = $entity['id']         ?? null;
        if ($order_id) {
            $stmt = $pdo->prepare("SELECT id FROM donations WHERE gateway_order_id=? OR gateway_payment_id=?");
            $stmt->execute([$order_id, $payment_id]);
            $donation_id = (int)($stmt->fetchColumn() ?: 0);
            if ($donation_id) {
                finalize_paid_donation($pdo, $donation_id, 'razorpay', $payment_id, [
                    'gateway_order_id' => $order_id,
                    'gateway_fee'      => isset($entity['fee']) ? $entity['fee'] / 100 : 0,
                ]);
                $result = 'donation #' . $donation_id . ' marked completed';
            } else {
                $result = 'no matching donation';
            }
        }
    }
    elseif ($event_type === 'payment.failed') {
        $order_id = $entity['order_id'] ?? null;
        if ($order_id) {
            $stmt = $pdo->prepare("SELECT id FROM donations WHERE gateway_order_id=?");
            $stmt->execute([$order_id]);
            $donation_id = (int)($stmt->fetchColumn() ?: 0);
            if ($donation_id) {
                mark_donation_failed($pdo, $donation_id, 'Razorpay reports failed: ' . ($entity['error_description'] ?? 'unknown'), json_encode($entity));
                $result = 'donation #' . $donation_id . ' marked failed';
            }
        }
    }
    elseif ($event_type === 'subscription.charged') {
        $sub_id = $event['payload']['subscription']['entity']['id'] ?? null;
        $payment_id = $event['payload']['payment']['entity']['id']   ?? null;
        $amount = (($event['payload']['payment']['entity']['amount'] ?? 0) / 100);
        if ($sub_id) {
            $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE gateway_subscription_id=?");
            $stmt->execute([$sub_id]);
            $rec = $stmt->fetch();
            if ($rec) {
                // Use the cron's finaliser
                require_once __DIR__ . '/../../cron/recurring_donations.php'; // exposes finalize_successful_charge
                finalize_successful_charge($pdo, $rec, $payment_id, 'webhook subscription.charged');
                $result = 'recurring #' . $rec['id'] . ' charged via subscription';
            }
        }
    }
    elseif ($event_type === 'subscription.cancelled' || $event_type === 'subscription.completed') {
        $sub_id = $event['payload']['subscription']['entity']['id'] ?? null;
        if ($sub_id) {
            $pdo->prepare("UPDATE recurring_donations SET status=? WHERE gateway_subscription_id=?")
                ->execute([$event_type === 'subscription.cancelled' ? 'cancelled' : 'expired', $sub_id]);
            $result = 'subscription ' . $event_type;
        }
    }
} catch (Throwable $e) {
    $result = 'exception: ' . $e->getMessage();
}

log_webhook($pdo, 'razorpay', $event_type, $event_id, $payload, true, $result, $donation_id);

http_response_code(200);
echo 'ok';
