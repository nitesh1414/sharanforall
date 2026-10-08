<?php
require_once __DIR__ . '/auth.php';
admin_check();
$admin = current_admin();
$current = basename($_SERVER['PHP_SELF']);
error_reporting(0);

// Count badges
$counts = [
  'volunteers_new' => (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE status='new'")->fetchColumn(),
  'partners_new'   => (int)$pdo->query("SELECT COUNT(*) FROM partners WHERE status='new'")->fetchColumn(),
  'contacts_new'   => (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE status='new'")->fetchColumn(),
];
// Donations pending badge (only if table exists — gracefully skip on fresh installs without upgrade_v3)
try {
  $counts['donations_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='pending'")->fetchColumn();
} catch (Throwable $e) {
  $counts['donations_pending'] = 0;
}
// Recurring donations due today (only if table exists)
try {
  $counts['recurring_due'] = (int)$pdo->query("SELECT COUNT(*) FROM recurring_donations WHERE status='active' AND next_charge_date <= CURDATE()")->fetchColumn();
} catch (Throwable $e) {
  $counts['recurring_due'] = 0;
}

// Critical security alerts (red badge on sidebar)
$counts['sec_alerts'] = 0;
if (is_file(__DIR__ . '/../../install.php')) $counts['sec_alerts']++;
try {
    $row = $pdo->query("SELECT password FROM admins WHERE username='admin' LIMIT 1")->fetch();
    if ($row && password_verify('admin123', $row['password'])) $counts['sec_alerts']++;
} catch (Throwable $e) {}

$page_title = $page_title ?? 'Dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> | Sharan Foundation Admin</title>
<link rel="icon" href="<?= BASE_URL ?>images/logo.png">
<link rel="stylesheet" href="<?= ADMIN_URL ?>assets/css/admin.css">
</head>
<body>
<div class="admin-wrap">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="logo-mark"><img src="<?= BASE_URL ?>images/logo.png" alt=""></div>
    <div>
      <h2>Sharan Foundation</h2>
      <small>ADMIN PANEL</small>
    </div>
  </div>
  <nav class="sidebar-menu">
    <div class="menu-label">Overview</div>
    <a href="<?= ADMIN_URL ?>index.php" class="<?= $current=='index.php'?'active':'' ?>"><span class="icon">📊</span> Dashboard</a>

    <div class="menu-label">Content</div>
    <a href="<?= ADMIN_URL ?>hero.php" class="<?= $current=='hero.php'?'active':'' ?>"><span class="icon">🎞️</span> Hero Carousel</a>
    <a href="<?= ADMIN_URL ?>carousel_settings.php" class="<?= $current=='carousel_settings.php'?'active':'' ?>"><span class="icon">⚡</span> Carousel Settings</a>
    <a href="<?= ADMIN_URL ?>marquee.php" class="<?= $current=='marquee.php'?'active':'' ?>"><span class="icon">📢</span> Marquee</a>
    <a href="<?= ADMIN_URL ?>site_media.php" class="<?= $current=='site_media.php'?'active':'' ?>"><span class="icon">🎨</span> Banners &amp; Images</a>
    <a href="<?= ADMIN_URL ?>programs.php" class="<?= $current=='programs.php'?'active':'' ?>"><span class="icon">📚</span> Programs</a>
    <a href="<?= ADMIN_URL ?>program_courses.php" class="<?= $current=='program_courses.php'?'active':'' ?>"><span class="icon">🎓</span> Program Courses</a>
    <a href="<?= ADMIN_URL ?>milestones.php" class="<?= $current=='milestones.php'?'active':'' ?>"><span class="icon">🌱</span> Milestones</a>
    <a href="<?= ADMIN_URL ?>mission_phases.php" class="<?= $current=='mission_phases.php'?'active':'' ?>"><span class="icon">🚧</span> Mission Plan</a>
    <a href="<?= ADMIN_URL ?>vision_capacity.php" class="<?= $current=='vision_capacity.php'?'active':'' ?>"><span class="icon">👥</span> Vision Capacity</a>
    <a href="<?= ADMIN_URL ?>projects.php" class="<?= $current=='projects.php'?'active':'' ?>"><span class="icon">🎯</span> Projects</a>
    <a href="<?= ADMIN_URL ?>blog.php" class="<?= $current=='blog.php'?'active':'' ?>"><span class="icon">📝</span> Blog Posts</a>
    <a href="<?= ADMIN_URL ?>gallery.php" class="<?= $current=='gallery.php'?'active':'' ?>"><span class="icon">🖼️</span> Gallery</a>
    <a href="<?= ADMIN_URL ?>team.php" class="<?= $current=='team.php'?'active':'' ?>"><span class="icon">👥</span> Team Members</a>
    <a href="<?= ADMIN_URL ?>testimonials.php" class="<?= $current=='testimonials.php'?'active':'' ?>"><span class="icon">💬</span> Testimonials</a>

    <div class="menu-label">Submissions</div>
    <a href="<?= ADMIN_URL ?>donations.php" class="<?= $current=='donations.php'?'active':'' ?>"><span class="icon">💝</span> Donations<?php if($counts['donations_pending']): ?><span class="badge"><?= $counts['donations_pending'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>recurring.php" class="<?= $current=='recurring.php'?'active':'' ?>"><span class="icon">🔁</span> Recurring<?php if(!empty($counts['recurring_due'])): ?><span class="badge"><?= (int)$counts['recurring_due'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>analytics.php" class="<?= $current=='analytics.php'?'active':'' ?>"><span class="icon">📊</span> Analytics</a>
    <a href="<?= ADMIN_URL ?>fundraisers.php" class="<?= $current=='fundraisers.php'?'active':'' ?>"><span class="icon">🎗️</span> Fundraisers</a>
    <a href="<?= ADMIN_URL ?>volunteers.php" class="<?= $current=='volunteers.php'?'active':'' ?>"><span class="icon">🤝</span> Volunteers<?php if($counts['volunteers_new']): ?><span class="badge"><?= $counts['volunteers_new'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>partners.php" class="<?= $current=='partners.php'?'active':'' ?>"><span class="icon">🏢</span> Partners<?php if($counts['partners_new']): ?><span class="badge"><?= $counts['partners_new'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>contacts.php" class="<?= $current=='contacts.php'?'active':'' ?>"><span class="icon">✉️</span> Contact Msgs<?php if($counts['contacts_new']): ?><span class="badge"><?= $counts['contacts_new'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>subscribers.php" class="<?= $current=='subscribers.php'?'active':'' ?>"><span class="icon">📧</span> Subscribers</a>

    <div class="menu-label">System</div>
    <a href="<?= ADMIN_URL ?>languages.php" class="<?= $current=='languages.php'?'active':'' ?>"><span class="icon">🌐</span> Languages &amp; Translation</a>
    <a href="<?= ADMIN_URL ?>settings.php" class="<?= $current=='settings.php'?'active':'' ?>"><span class="icon">⚙️</span> Site Settings</a>
    <a href="<?= ADMIN_URL ?>payments.php" class="<?= $current=='payments.php'?'active':'' ?>"><span class="icon">💳</span> Payment Gateways</a>
    <a href="<?= ADMIN_URL ?>email_log.php" class="<?= $current=='email_log.php'?'active':'' ?>"><span class="icon">📨</span> Email Log</a>
    <a href="<?= ADMIN_URL ?>security_check.php" class="<?= $current=='security_check.php'?'active':'' ?>"><span class="icon">🛡️</span> Security Check<?php if(!empty($counts['sec_alerts'])): ?><span class="badge"><?= (int)$counts['sec_alerts'] ?></span><?php endif; ?></a>
    <a href="<?= ADMIN_URL ?>logout.php"><span class="icon">🚪</span> Logout</a>
  </nav>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:.8rem">
      <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <h1><?= e($page_title) ?></h1>
    </div>
    <div class="right">
      <a href="<?= BASE_URL ?>" target="_blank" class="view-site">🌐 View Site</a>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($admin['name'],0,1)) ?></div>
        <span><?= e($admin['name']) ?></span>
        <a href="<?= ADMIN_URL ?>logout.php">Logout</a>
      </div>
    </div>
  </header>

  <main class="content">
    <?= flash_render() ?>
