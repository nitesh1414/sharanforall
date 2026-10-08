<?php
/**
 * PayPal return URL — donor approved the payment and PayPal redirects here
 * with ?token=ORDER_ID&PayerID=...  (plus our donation_id).
 * We capture the order to actually take the money.
 */

require_once __DIR__ . '/_helpers.php';

$donation_id = (int)($_GET['donation_id'] ?? 0);
$order_id    = $_GET['token'] ?? '';  // PayPal returns the order ID in 'token'
$payer_id    = $_GET['PayerID'] ?? '';

if (!$donation_id || !$order_id) {
    flash_set('error', '❌ Invalid PayPal return.');
    redirect_donor(BASE_URL . 'pages/donate.php');
}

$result = PayPal::capture_order($order_id);
if (!$result['ok']) {
    mark_donation_failed($pdo, $donation_id, 'PayPal capture failed: ' . $result['error'], json_encode($result['raw'] ?? []));
    flash_set('error', '❌ PayPal payment capture failed: ' . $result['error']);
    redirect_donor(BASE_URL . 'pages/donate.php?failed=1');
}

finalize_paid_donation($pdo, $donation_id, 'paypal', $result['txn_id'], [
    'gateway_order_id'      => $order_id,
    'gateway_response_raw'  => json_encode($result['raw']),
]);

redirect_donor(BASE_URL . 'pages/donation-success.php?donation_id=' . $donation_id);
