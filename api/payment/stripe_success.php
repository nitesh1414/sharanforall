<?php
/**
 * Stripe Checkout success return.
 * URL pattern: ?donation_id=X&session_id=cs_...
 * We fetch the session, verify payment_status is 'paid', then finalize.
 */

require_once __DIR__ . '/_helpers.php';

$donation_id = (int)($_GET['donation_id'] ?? 0);
$session_id  = $_GET['session_id'] ?? '';

if (!$donation_id || !$session_id || strpos($session_id, 'cs_') !== 0) {
    flash_set('error', '❌ Invalid Stripe session.');
    redirect_donor(BASE_URL . 'pages/donate.php');
}

$result = Stripe::fetch_session($session_id);
if (!$result['ok']) {
    mark_donation_failed($pdo, $donation_id, 'Failed to fetch session: ' . ($result['data']['error']['message'] ?? 'unknown'), json_encode($result['data']));
    flash_set('error', '❌ Could not verify payment with Stripe. Please contact us if you were charged.');
    redirect_donor(BASE_URL . 'pages/donate.php?failed=1');
}

$session = $result['data'];
$paid    = ($session['payment_status'] ?? '') === 'paid';
$pi      = $session['payment_intent'] ?? null;
$payment_id = is_array($pi) ? ($pi['id'] ?? null) : $pi;

if (!$paid) {
    // For subscription mode, status may be 'paid' once the first invoice is processed
    if (($session['mode'] ?? '') === 'subscription' && ($session['status'] ?? '') === 'complete') {
        $paid = true;
        $payment_id = $session['subscription'] ?? $payment_id;
    }
}

if (!$paid) {
    mark_donation_failed($pdo, $donation_id, 'Stripe session not paid: ' . ($session['payment_status'] ?? ''), json_encode($session));
    flash_set('error', '⏳ Your payment is still processing. We will confirm by email soon.');
    redirect_donor(BASE_URL . 'pages/donate.php');
}

// Compute fee if available
$fee = 0;
if (is_array($pi) && !empty($pi['charges']['data'][0]['balance_transaction'])) {
    // balance_transaction may need a separate fetch in production; left as 0 here.
}

$extra = [
    'gateway_order_id'      => $session_id,
    'gateway_response_raw'  => json_encode($session),
    'gateway_fee'           => $fee,
];
// If this was a subscription checkout, save the subscription/customer IDs for the cron auto-charger
if (($session['mode'] ?? '') === 'subscription') {
    $extra['gateway_subscription_id'] = $session['subscription'] ?? null;
    $extra['gateway_customer_id']     = $session['customer']     ?? null;
}

finalize_paid_donation($pdo, $donation_id, 'stripe', $payment_id, $extra);

redirect_donor(BASE_URL . 'pages/donation-success.php?donation_id=' . $donation_id);
