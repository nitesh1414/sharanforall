<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

$email = trim($_POST['email'] ?? '');
$redirect_back = $_POST['redirect'] ?? BASE_URL.'pages/blog.php';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect($redirect_back);
}

try {
    $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE status='active'");
    $stmt->execute([$email]);
    try { notify_new_subscriber($email); } catch (Throwable $e) { /* silent */ }
    flash_set('success', t('thank_subscribe'));
} catch (Exception $ex) {
    flash_set('error','Subscription failed: '.$ex->getMessage());
}
redirect($redirect_back);
