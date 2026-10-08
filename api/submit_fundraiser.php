<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'pages/start-fundraiser.php');
}

$required = ['organizer_name','organizer_email','organizer_phone','title','story','goal_amount'];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) {
        flash_set('error', t('fill_required'));
        redirect(BASE_URL . 'pages/start-fundraiser.php');
    }
}
if (!filter_var($_POST['organizer_email'], FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect(BASE_URL . 'pages/start-fundraiser.php');
}

// Generate unique slug
$base_slug = slugify($_POST['title']);
$slug = $base_slug;
$i = 1;
while (true) {
    $stmt = $pdo->prepare("SELECT id FROM fundraisers WHERE slug=?");
    $stmt->execute([$slug]);
    if (!$stmt->fetch()) break;
    $slug = $base_slug . '-' . (++$i);
    if ($i > 50) { $slug = $base_slug . '-' . time(); break; }
}

$data = [
    'organizer_name'  => trim($_POST['organizer_name']),
    'organizer_email' => trim($_POST['organizer_email']),
    'organizer_phone' => trim($_POST['organizer_phone']),
    'organizer_bio'   => trim($_POST['organizer_bio'] ?? ''),
    'title'           => trim($_POST['title']),
    'slug'            => $slug,
    'cause'           => trim($_POST['cause'] ?? 'Where Most Needed'),
    'story'           => trim($_POST['story']),
    'goal_amount'     => (float)preg_replace('/[^0-9.]/', '', $_POST['goal_amount']),
    'currency'        => in_array($_POST['currency'] ?? '', ['INR','GBP','USD']) ? $_POST['currency'] : 'INR',
    'start_date'      => $_POST['start_date'] ?: date('Y-m-d'),
    'end_date'        => $_POST['end_date'] ?: null,
    'status'          => 'pending',
];

// Handle cover image upload (optional)
$img = upload_image('cover_image','fundraisers');
if ($img && $img !== false) $data['cover_image'] = $img;

try {
    $cols  = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO fundraisers ($cols) VALUES ($place)")->execute($data);

    // Notify admin + acknowledge organizer
    $cfg = mail_config();
    $body = '<p>A new fundraiser has been submitted and is awaiting your approval.</p>';
    $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:15px;font-size:14px">';
    foreach (['Title'=>$data['title'],'Organizer'=>$data['organizer_name'],'Email'=>$data['organizer_email'],'Phone'=>$data['organizer_phone'],'Cause'=>$data['cause'],'Goal'=>$data['currency'].' '.number_format($data['goal_amount'],0)] as $k => $v) {
        $body .= '<tr><td style="padding:8px 12px;background:#f9fafb;font-weight:600;width:140px;border-bottom:1px solid #eee">'.htmlspecialchars($k).'</td><td style="padding:8px 12px;border-bottom:1px solid #eee">'.htmlspecialchars($v).'</td></tr>';
    }
    $body .= '</table>';
    try { send_mail($cfg['admin_notify_email'], 'New Fundraiser Pending Approval — '.$data['title'],
                   email_template('🎗️ New Fundraiser to Review', $body, 'Review in Admin', ADMIN_URL.'fundraisers.php')); } catch (Throwable $e){}

    $reply  = '<p>Dear ' . htmlspecialchars($data['organizer_name']) . ',</p>';
    $reply .= '<p>Thank you for starting a fundraiser for <strong>Sharan Foundation</strong>! 🎉</p>';
    $reply .= '<p>Your campaign <strong>"' . htmlspecialchars($data['title']) . '"</strong> has been received and is being reviewed by our team. You will receive an email once it goes live (usually within 1-2 business days).</p>';
    $reply .= '<p>Thank you for using your platform to make a difference!</p>';
    try { send_mail($data['organizer_email'], 'Your fundraiser has been received — Sharan Foundation',
                   email_template('🙏 Fundraiser Received', $reply, 'Visit Our Website', BASE_URL)); } catch (Throwable $e) {}

    flash_set('success','🎉 Your fundraiser has been submitted! Our team will review it within 1-2 business days. Check your email for confirmation.');
} catch (Exception $ex) {
    flash_set('error','Submission failed: ' . $ex->getMessage());
}

redirect(BASE_URL . 'pages/start-fundraiser.php?submitted=1');
