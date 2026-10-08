<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'pages/donate.php');
}

// ---- Validation ----
$required = ['donor_name','email','phone','amount','payment_method'];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) {
        flash_set('error', t('fill_required'));
        redirect(BASE_URL . 'pages/donate.php#donation-form');
    }
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect(BASE_URL . 'pages/donate.php#donation-form');
}

$amount = (float)preg_replace('/[^0-9.]/', '', $_POST['amount']);
if ($amount < 1) {
    flash_set('error', t('invalid_amount'));
    redirect(BASE_URL . 'pages/donate.php#donation-form');
}

// ---- Data ----
$data = [
    'donor_name'       => trim($_POST['donor_name']),
    'email'            => trim($_POST['email']),
    'phone'            => trim($_POST['phone']),
    'address'          => trim($_POST['address'] ?? ''),
    'city'             => trim($_POST['city'] ?? ''),
    'state'            => trim($_POST['state'] ?? ''),
    'country'          => trim($_POST['country'] ?? 'India'),
    'pincode'          => trim($_POST['pincode'] ?? ''),
    'pan_number'       => trim(strtoupper($_POST['pan_number'] ?? '')),
    'amount'           => $amount,
    'currency'         => in_array($_POST['currency'] ?? '', ['INR','GBP','USD']) ? $_POST['currency'] : 'INR',
    'donation_type'    => in_array($_POST['donation_type'] ?? '', ['one-time','monthly','yearly']) ? $_POST['donation_type'] : 'one-time',
    'purpose'          => trim($_POST['purpose'] ?? 'Where Most Needed'),
    'message'          => trim($_POST['message'] ?? ''),
    'payment_method'   => in_array($_POST['payment_method'] ?? '', ['upi','razorpay','stripe','paypal','bank_transfer','cheque','cash','other']) ? $_POST['payment_method'] : 'bank_transfer',
    'payment_status'   => 'pending',  // admin marks as completed after verifying
    'transaction_id'   => trim($_POST['transaction_id'] ?? '') ?: null,
    'is_anonymous'     => isset($_POST['is_anonymous']) ? 1 : 0,
    'receipt_required' => isset($_POST['receipt_required']) ? 1 : 0,
    'newsletter_optin' => isset($_POST['newsletter_optin']) ? 1 : 0,
];

try {
    $cols  = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO donations ($cols) VALUES ($place)")->execute($data);
    $donation_id = (int)$pdo->lastInsertId();

    // Newsletter opt-in
    if ($data['newsletter_optin']) {
        try {
            $pdo->prepare("INSERT INTO subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE status='active'")
                ->execute([$data['email']]);
        } catch (Throwable $e) { /* silent */ }
    }

    // ============ RECURRING DONATION SETUP ============
    // If donation_type is monthly/yearly, create a recurring_donations profile.
    if (in_array($data['donation_type'], ['monthly','yearly']) || !empty($_POST['is_recurring'])) {
        $freq = $data['donation_type'] === 'yearly' ? 'yearly' : 'monthly';
        // Allow explicit frequency override from form
        if (!empty($_POST['frequency']) && in_array($_POST['frequency'], ['weekly','monthly','quarterly','yearly'])) {
            $freq = $_POST['frequency'];
        }
        $next_date = next_charge_date(date('Y-m-d'), $freq);

        $rec_data = [
            'donor_name'       => $data['donor_name'],
            'email'            => $data['email'],
            'phone'            => $data['phone'],
            'country'          => $data['country'],
            'pan_number'       => $data['pan_number'] ?: null,
            'amount'           => $data['amount'],
            'currency'         => $data['currency'],
            'frequency'        => $freq,
            'purpose'          => $data['purpose'],
            'payment_method'   => $data['payment_method'],
            'status'           => 'active',
            'start_date'       => date('Y-m-d'),
            'next_charge_date' => $next_date,
            'manage_token'     => bin2hex(random_bytes(16)),
            'created_donation_id' => $donation_id,
        ];
        try {
            $cols2  = implode(',', array_keys($rec_data));
            $place2 = ':' . implode(',:', array_keys($rec_data));
            $pdo->prepare("INSERT INTO recurring_donations ($cols2) VALUES ($place2)")->execute($rec_data);
            // Send activation email
            try { notify_recurring_started($rec_data); } catch (Throwable $e) { /* silent */ }
        } catch (Throwable $e) { /* log but don't block */ }
    }

    // Send standard notification emails (admin alert + donor thank-you with payment instructions)
    try { notify_new_donation($data); } catch (Throwable $e) { /* email failure shouldn't block */ }

    flash_set('success', t('thank_donation'));
} catch (Exception $ex) {
    flash_set('error', 'Submission failed: ' . $ex->getMessage());
}

/** Calculate the next charge date from a base date and frequency. */
function next_charge_date($from_date, $frequency) {
    $modifiers = [
        'weekly'    => '+1 week',
        'monthly'   => '+1 month',
        'quarterly' => '+3 months',
        'yearly'    => '+1 year',
    ];
    $mod = $modifiers[$frequency] ?? '+1 month';
    return date('Y-m-d', strtotime($from_date . ' ' . $mod));
}

redirect(BASE_URL . 'pages/donate.php?submitted=1#donation-form');
