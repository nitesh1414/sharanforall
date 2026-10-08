<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(BASE_URL.'pages/contact.php');

if (empty(trim($_POST['name'] ?? '')) || empty(trim($_POST['email'] ?? '')) || empty(trim($_POST['message'] ?? ''))) {
    flash_set('error', t('fill_required'));
    redirect(BASE_URL.'pages/contact.php#contact-form');
}
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect(BASE_URL.'pages/contact.php#contact-form');
}

$data = [
    'name'     => trim($_POST['name']),
    'email'    => trim($_POST['email']),
    'phone'    => trim($_POST['phone'] ?? ''),
    'interest' => trim($_POST['interest'] ?? ''),
    'office'   => trim($_POST['office'] ?? ''),
    'message'  => trim($_POST['message']),
];

try {
    $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
    $pdo->prepare("INSERT INTO contacts ($cols) VALUES ($place)")->execute($data);
    try { notify_new_contact($data); } catch (Throwable $e) { /* silent */ }
    flash_set('success', t('thank_contact'));
} catch (Exception $ex) {
    flash_set('error','Failed to send: '.$ex->getMessage());
}
redirect(BASE_URL.'pages/contact.php?submitted=1#contact-form');
