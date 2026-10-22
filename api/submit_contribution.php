<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(BASE_URL.'pages/fundraisers.php');

$fundraiser_id = (int)($_POST['fundraiser_id'] ?? 0);
$amount = (float)preg_replace('/[^0-9.]/', '', $_POST['amount'] ?? '');
$donor_name = trim($_POST['donor_name'] ?? '');
$email = trim($_POST['email'] ?? '');

if (!$fundraiser_id || $amount < 1) {
    flash_set('error', t('invalid_amount'));
    redirect(BASE_URL.'pages/fundraisers.php');
}
$f = $pdo->prepare("SELECT * FROM fundraisers WHERE id=? AND status='active'");
$f->execute([$fundraiser_id]);
$f = $f->fetch();
if (!$f) {
    flash_set('error', 'Fundraiser not found or not currently active.');
    redirect(BASE_URL.'pages/fundraisers.php');
}

$is_anon = isset($_POST['is_anonymous']) ? 1 : 0;
if (!$is_anon) {
    if (!$donor_name) { flash_set('error', t('fill_required')); redirect(BASE_URL.'pages/fundraiser.php?slug='.$f['slug']); }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash_set('error', t('invalid_email')); redirect(BASE_URL.'pages/fundraiser.php?slug='.$f['slug']); }
}

// Transaction / Reference ID is required — donors pay via bank/UPI (details
// + QR shown on the page) and the admin verifies this ID to confirm payment.
$transaction_id = trim($_POST['transaction_id'] ?? '');
if ($transaction_id === '') {
    flash_set('error', 'Please enter the Transaction / Reference ID (UPI UTR or bank reference) so we can verify your payment.');
    redirect(BASE_URL.'pages/fundraiser.php?slug='.$f['slug'].'#contribute');
}

$data = [
    'fundraiser_id'  => $fundraiser_id,
    'donor_name'     => $is_anon ? 'Anonymous' : $donor_name,
    'email'          => $email ?: null,
    'amount'         => $amount,
    'currency'       => $f['currency'],
    'message'        => trim($_POST['message'] ?? ''),
    'is_anonymous'   => $is_anon,
    'payment_status' => 'pending', // admin marks completed after verifying payment
    'transaction_id' => $transaction_id,
];

try {
    $cols = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    try {
        $pdo->prepare("INSERT INTO fundraiser_contributions ($cols) VALUES ($place)")->execute($data);
    } catch (Throwable $e) {
        // transaction_id column may be missing on un-migrated installs —
        // retry without it (the ID is still saved on the mirrored donation
        // below and emailed to the donor). Run sql/acts_foundation.sql to add it.
        unset($data['transaction_id']);
        $cols = implode(',', array_keys($data));
        $place = ':' . implode(',:', array_keys($data));
        $pdo->prepare("INSERT INTO fundraiser_contributions ($cols) VALUES ($place)")->execute($data);
    }

    // Also create a record in main donations table for unified tracking
    try {
        $pdo->prepare("INSERT INTO donations (donor_name, email, phone, amount, currency, donation_type, purpose, payment_method, payment_status, message, is_anonymous, receipt_required, transaction_id)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $is_anon ? 'Anonymous Donor' : $donor_name,
                $email ?: 'anonymous@noemail.local',
                $_POST['phone'] ?? '',
                $amount,
                $f['currency'],
                'one-time',
                'Fundraiser: ' . $f['title'],
                'bank_transfer',
                'pending',
                $data['message'],
                $is_anon, 0,
                $transaction_id
            ]);
    } catch (Throwable $e) {}

    // Notify admin
    $cfg = mail_config();
    $sym = $f['currency'] === 'INR' ? '₹' : ($f['currency'] === 'GBP' ? '£' : '$');
    $body = '<p>A new contribution has been pledged to fundraiser <strong>"' . htmlspecialchars($f['title']) . '"</strong>.</p>';
    $body .= '<p style="font-size:22px;color:#2563eb;text-align:center;margin:15px 0;font-weight:800">' . $sym . number_format($amount, 0) . '</p>';
    $body .= '<p>From: <strong>' . htmlspecialchars($data['donor_name']) . '</strong>' . ($email ? ' (' . htmlspecialchars($email) . ')' : '') . '</p>';
    if ($data['message']) $body .= '<blockquote style="border-left:3px solid #f4a261;padding:.6rem 1rem;background:#fffaf0">"' . htmlspecialchars($data['message']) . '"</blockquote>';
    try { send_mail($cfg['admin_notify_email'], 'New Fundraiser Contribution — ' . $f['title'],
                   email_template('💝 New Contribution', $body, 'View Fundraiser', ADMIN_URL.'fundraisers.php?view='.$fundraiser_id)); } catch (Throwable $e){}

    // Notify organizer
    if (!empty($f['organizer_email'])) {
        $orgbody = '<p>Great news! Someone just contributed to your fundraiser <strong>"' . htmlspecialchars($f['title']) . '"</strong>.</p>';
        $orgbody .= '<p style="font-size:22px;color:#2563eb;text-align:center;margin:15px 0;font-weight:800">' . $sym . number_format($amount, 0) . '</p>';
        $orgbody .= '<p>From: <strong>' . htmlspecialchars($data['donor_name']) . '</strong></p>';
        if ($data['message']) $orgbody .= '<blockquote style="border-left:3px solid #f4a261;padding:.6rem 1rem;background:#fffaf0">"' . htmlspecialchars($data['message']) . '"</blockquote>';
        $orgbody .= '<p>Keep sharing your campaign — every contribution counts! 💪</p>';
        try { send_mail($f['organizer_email'], 'New contribution to your fundraiser!',
                       email_template('🎉 Someone Just Contributed!', $orgbody, 'View Your Campaign', BASE_URL.'pages/fundraiser.php?slug='.$f['slug'])); } catch (Throwable $e){}
    }

    // Acknowledge donor
    if ($email) {
        $dbody  = '<p>Dear ' . htmlspecialchars($donor_name) . ',</p>';
        $dbody .= '<p>Thank you for supporting <strong>"' . htmlspecialchars($f['title']) . '"</strong>! 🙏</p>';
        $dbody .= '<p style="font-size:22px;color:#2563eb;text-align:center;margin:15px 0;font-weight:800">' . $sym . number_format($amount, 0) . '</p>';
        $dbody .= '<p>We have received your Transaction / Reference ID: <code>' . htmlspecialchars($transaction_id) . '</code></p>';
        $dbody .= '<p>Our team will verify the payment against this ID. Once confirmed, we will send you an official receipt.</p>';
        try { send_mail($email, 'Thank you for your contribution',
                       email_template('🙏 Thank You for Contributing!', $dbody, 'Visit Donation Page', BASE_URL.'pages/donate.php')); } catch (Throwable $e){}
    }

    flash_set('success', '🙏 Thank you for your contribution! We have your Transaction ID and will verify the payment shortly. The organizer will be notified.');
} catch (Exception $ex) {
    flash_set('error', 'Submission failed: ' . $ex->getMessage());
}

redirect(BASE_URL.'pages/fundraiser.php?slug='.$f['slug'].'#contribute');
