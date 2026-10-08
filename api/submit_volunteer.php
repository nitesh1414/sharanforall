<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error','Invalid request.');
    redirect(BASE_URL.'pages/volunteer.php');
}

$required = ['full_name','email','phone','area_of_interest'];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) {
        flash_set('error', t('fill_required'));
        redirect(BASE_URL.'pages/volunteer.php');
    }
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect(BASE_URL.'pages/volunteer.php');
}

$data = [
    'full_name'        => trim($_POST['full_name']),
    'email'            => trim($_POST['email']),
    'phone'            => trim($_POST['phone']),
    'country'          => trim($_POST['country'] ?? ''),
    'city'             => trim($_POST['city'] ?? ''),
    'age'              => (int)($_POST['age'] ?? 0) ?: null,
    'gender'           => $_POST['gender'] ?? null,
    'occupation'       => trim($_POST['occupation'] ?? ''),
    'area_of_interest' => trim($_POST['area_of_interest']),
    'availability'     => trim($_POST['availability'] ?? ''),
    'skills'           => trim($_POST['skills'] ?? ''),
    'experience'       => trim($_POST['experience'] ?? ''),
    'motivation'       => trim($_POST['motivation'] ?? ''),
];

try {
    $cols = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO volunteers ($cols) VALUES ($place)")->execute($data);
    // Send email notifications (admin + auto-reply to volunteer)
    try { notify_new_volunteer($data); } catch (Throwable $e) { /* email failure shouldn't block submission */ }
    flash_set('success', t('thank_volunteer'));
} catch (Exception $ex) {
    flash_set('error','Submission failed: '.$ex->getMessage());
}

redirect(BASE_URL.'pages/volunteer.php?submitted=1');
