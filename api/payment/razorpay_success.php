<?php
/**
 * Razorpay success callback.
 *
 * Razorpay Checkout POSTs back here with:
 *   razorpay_order_id, razorpay_payment_id, razorpay_signature
 * (or our donation_id from the redirect URL).
 */

require_once __DIR__ . '/_helpers.php';

$donation_id = (int)($_GET['donation_id'] ?? $_POST['donation_id'] ?? 0);
$order_id    = $_POST['razorpay_order_id']   ?? $_GET['razorpay_order_id']   ?? '';
$payment_id  = $_POST['razorpay_payment_id'] ?? $_GET['razorpay_payment_id'] ?? '';
$signature   = $_POST['razorpay_signature']  ?? $_GET['razorpay_signature']  ?? '';

if (!$donation_id || !$order_id || !$payment_id || !$signature) {
    flash_set('error', '❌ Payment data missing. Please contact us if you were charged.');
    redirect_donor(BASE_URL . 'pages/donate.php');
}

// Verify signature
if (!Razorpay::verify_payment($order_id, $payment_id, $signature)) {
    mark_donation_failed($pdo, $donation_id, 'Signature verification failed', json_encode($_POST));
    flash_set('error', '❌ Payment signature verification failed. Please contact us if you were charged.');
    redirect_donor(BASE_URL . 'pages/donate.php?failed=1');
}

// Optional: fetch full payment details for fee info
$details = Razorpay::fetch_payment($payment_id);
$fee = isset($details['data']['fee']) ? ((float)$details['data']['fee']) / 100 : 0;

// Finalize
finalize_paid_donation($pdo, $donation_id, 'razorpay', $payment_id, [
    'gateway_order_id'      => $order_id,
    'gateway_signature'     => $signature,
    'gateway_fee'           => $fee,
    'gateway_response_raw'  => json_encode($details['data'] ?? $_POST),
]);

redirect_donor(BASE_URL . 'pages/donation-success.php?donation_id=' . $donation_id);
