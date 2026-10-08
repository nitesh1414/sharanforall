<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error','Invalid request.');
    redirect(BASE_URL.'pages/partner.php');
}

$required = ['org_name','contact_person','email','phone'];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) {
        flash_set('error', t('fill_required'));
        redirect(BASE_URL.'pages/partner.php');
    }
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    flash_set('error', t('invalid_email'));
    redirect(BASE_URL.'pages/partner.php');
}

$data = [
    'org_name'             => trim($_POST['org_name']),
    'org_type'             => $_POST['org_type'] ?? 'Other',
    'contact_person'       => trim($_POST['contact_person']),
    'designation'          => trim($_POST['designation'] ?? ''),
    'email'                => trim($_POST['email']),
    'phone'                => trim($_POST['phone']),
    'country'              => trim($_POST['country'] ?? ''),
    'website'              => trim($_POST['website'] ?? ''),
    'partnership_type'     => trim($_POST['partnership_type'] ?? ''),
    'budget_range'         => trim($_POST['budget_range'] ?? ''),
    'programs_of_interest' => trim($_POST['programs_of_interest'] ?? ''),
    'proposal'             => trim($_POST['proposal'] ?? ''),
];

try {
    $cols = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO partners ($cols) VALUES ($place)")->execute($data);
    try { notify_new_partner($data); } catch (Throwable $e) { /* silent */ }
    flash_set('success', t('thank_partner'));
} catch (Exception $ex) {
    flash_set('error','Submission failed: '.$ex->getMessage());
}

redirect(BASE_URL.'pages/partner.php?submitted=1');
