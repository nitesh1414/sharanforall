<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Stats
$s = [
  'programs'    => (int)$pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn(),
  'projects'    => (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
  'blog'        => (int)$pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn(),
  'gallery'     => (int)$pdo->query("SELECT COUNT(*) FROM gallery")->fetchColumn(),
  'volunteers'  => (int)$pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn(),
  'partners'    => (int)$pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn(),
  'contacts'    => (int)$pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn(),
  'subscribers' => (int)$pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn(),
];

// Donation stats (gracefully degrade if table not yet created)
$donation_stats = ['total'=>0, 'pending'=>0, 'inr'=>0, 'gbp'=>0];
$recent_donations = [];
try {
    $donation_stats = [
      'total'   => (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn(),
      'pending' => (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='pending'")->fetchColumn(),
      'inr'     => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='INR'")->fetchColumn(),
      'gbp'     => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='GBP'")->fetchColumn(),
    ];
    $recent_donations = $pdo->query("SELECT * FROM donations ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Recent submissions
$recent_volunteers = $pdo->query("SELECT * FROM volunteers ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
$recent_partners   = $pdo->query("SELECT * FROM partners ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
$recent_contacts   = $pdo->query("SELECT * FROM contacts ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
?>

<div class="page-head">
  <div>
    <h2>Welcome back, <?= e($admin['name']) ?>! 👋</h2>
    <p class="sub">Here's what's happening at Sharan Foundation today.</p>
  </div>
</div>

<?php if (!empty($counts['sec_alerts'])): ?>
<!-- SECURITY ALERT BANNER -->
<div style="background:linear-gradient(135deg,#fdecea,#fff4ee);border-left:5px solid #c0392b;padding:1.2rem 1.5rem;border-radius:10px;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:.8rem">
    <div style="width:46px;height:46px;border-radius:50%;background:#c0392b;color:#fff;display:grid;place-items:center;font-size:1.5rem">🚨</div>
    <div>
      <strong style="color:#c0392b;font-size:1rem">Critical security issues detected!</strong>
      <div style="color:#666;font-size:.88rem;margin-top:.2rem"><?= (int)$counts['sec_alerts'] ?> issue<?= $counts['sec_alerts']==1?'':'s' ?> need immediate attention — install.php still present or default password active.</div>
    </div>
  </div>
  <a href="<?= ADMIN_URL ?>security_check.php" style="background:#c0392b;color:#fff;padding:.7rem 1.4rem;border-radius:8px;text-decoration:none;font-weight:600;white-space:nowrap">🛡️ Run Security Check</a>
</div>
<?php endif; ?>

<!-- DONATIONS BIG STATS -->
<div class="stats">
  <div class="stat-card green"><div><div class="label">Total Donations</div><div class="value"><?= number_format($donation_stats['total']) ?></div></div><div class="ico">💝</div></div>
  <div class="stat-card orange"><div><div class="label">Pending Verification</div><div class="value"><?= number_format($donation_stats['pending']) ?></div></div><div class="ico">⏳</div></div>
  <div class="stat-card gold"><div><div class="label">Raised in India</div><div class="value" style="font-size:1.6rem">₹<?= number_format($donation_stats['inr'], 0) ?></div></div><div class="ico">🇮🇳</div></div>
  <div class="stat-card purple"><div><div class="label">Raised in UK</div><div class="value" style="font-size:1.6rem">£<?= number_format($donation_stats['gbp'], 0) ?></div></div><div class="ico">🇬🇧</div></div>
</div>

<!-- CONTENT STATS -->
<div class="stats">
  <div class="stat-card green"><div><div class="label">Programs</div><div class="value"><?= $s['programs'] ?></div></div><div class="ico">📚</div></div>
  <div class="stat-card orange"><div><div class="label">Active Projects</div><div class="value"><?= $s['projects'] ?></div></div><div class="ico">🎯</div></div>
  <div class="stat-card blue"><div><div class="label">Blog Posts</div><div class="value"><?= $s['blog'] ?></div></div><div class="ico">📝</div></div>
  <div class="stat-card purple"><div><div class="label">Gallery Images</div><div class="value"><?= $s['gallery'] ?></div></div><div class="ico">🖼️</div></div>
  <div class="stat-card gold"><div><div class="label">Volunteers</div><div class="value"><?= $s['volunteers'] ?></div></div><div class="ico">🤝</div></div>
  <div class="stat-card red"><div><div class="label">Partner Apps</div><div class="value"><?= $s['partners'] ?></div></div><div class="ico">🏢</div></div>
  <div class="stat-card green"><div><div class="label">Contact Messages</div><div class="value"><?= $s['contacts'] ?></div></div><div class="ico">✉️</div></div>
  <div class="stat-card blue"><div><div class="label">Subscribers</div><div class="value"><?= $s['subscribers'] ?></div></div><div class="ico">📧</div></div>
</div>

<!-- RECENT DONATIONS -->
<div class="card">
  <div class="card-head"><h3>💝 Recent Donations</h3><a href="<?= ADMIN_URL ?>donations.php" class="btn-sm btn-view">View All →</a></div>
  <div class="card-body">
    <?php if (!$recent_donations): ?>
      <div class="empty"><div class="ico">💝</div><h3>No donations yet</h3><p>Donations from the website will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Donor</th><th>Amount</th><th>Purpose</th><th>Method</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent_donations as $d):
          $sym = $d['currency']==='INR'?'₹':($d['currency']==='GBP'?'£':'$'); ?>
        <tr>
          <td><strong><?= e($d['donor_name']) ?></strong><br><small style="color:#888"><?= e($d['email']) ?></small></td>
          <td><strong style="color:var(--primary-dark);font-size:1.05rem"><?= $sym ?><?= number_format($d['amount'], $d['currency']==='INR'?0:2) ?></strong></td>
          <td style="font-size:.85rem"><?= e($d['purpose']) ?></td>
          <td style="font-size:.82rem"><?= e(strtoupper(str_replace('_',' ',$d['payment_method']))) ?></td>
          <td><span class="status-badge status-<?= $d['payment_status']==='completed'?'active':($d['payment_status']==='pending'?'new':'inactive') ?>"><?= e($d['payment_status']) ?></span></td>
          <td><?= time_ago($d['submitted_at']) ?></td>
          <td><a href="<?= ADMIN_URL ?>donations.php?view=<?= $d['id'] ?>" class="btn-sm btn-view">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- RECENT VOLUNTEERS -->
<div class="card">
  <div class="card-head"><h3>🤝 Recent Volunteer Applications</h3><a href="<?= ADMIN_URL ?>volunteers.php" class="btn-sm btn-view">View All →</a></div>
  <div class="card-body">
    <?php if (!$recent_volunteers): ?>
      <div class="empty"><div class="ico">📭</div><h3>No volunteer applications yet</h3><p>Applications submitted from the website will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Interest</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent_volunteers as $v): ?>
        <tr>
          <td><strong><?= e($v['full_name']) ?></strong></td>
          <td><?= e($v['email']) ?></td>
          <td><?= e($v['area_of_interest']) ?></td>
          <td><span class="status-badge status-<?= e($v['status']) ?>"><?= e($v['status']) ?></span></td>
          <td><?= time_ago($v['submitted_at']) ?></td>
          <td><a href="<?= ADMIN_URL ?>volunteers.php?view=<?= $v['id'] ?>" class="btn-sm btn-view">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- RECENT PARTNERS -->
<div class="card">
  <div class="card-head"><h3>🏢 Recent Partnership Inquiries</h3><a href="<?= ADMIN_URL ?>partners.php" class="btn-sm btn-view">View All →</a></div>
  <div class="card-body">
    <?php if (!$recent_partners): ?>
      <div class="empty"><div class="ico">📭</div><h3>No partnership inquiries yet</h3></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Organization</th><th>Contact Person</th><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent_partners as $p): ?>
        <tr>
          <td><strong><?= e($p['org_name']) ?></strong></td>
          <td><?= e($p['contact_person']) ?></td>
          <td><?= e($p['org_type']) ?></td>
          <td><span class="status-badge status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
          <td><?= time_ago($p['submitted_at']) ?></td>
          <td><a href="<?= ADMIN_URL ?>partners.php?view=<?= $p['id'] ?>" class="btn-sm btn-view">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- RECENT CONTACTS -->
<div class="card">
  <div class="card-head"><h3>✉️ Recent Contact Messages</h3><a href="<?= ADMIN_URL ?>contacts.php" class="btn-sm btn-view">View All →</a></div>
  <div class="card-body">
    <?php if (!$recent_contacts): ?>
      <div class="empty"><div class="ico">📭</div><h3>No contact messages yet</h3></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Email</th><th>Interest</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent_contacts as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td><?= e($c['email']) ?></td>
          <td><?= e($c['interest']) ?></td>
          <td><span class="status-badge status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
          <td><?= time_ago($c['submitted_at']) ?></td>
          <td><a href="<?= ADMIN_URL ?>contacts.php?view=<?= $c['id'] ?>" class="btn-sm btn-view">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
