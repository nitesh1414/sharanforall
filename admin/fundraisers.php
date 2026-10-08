<?php
$page_title = 'Fundraisers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/mailer.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM fundraisers WHERE id=?")->execute([$id]);
    flash_set('success','Fundraiser deleted.');
    redirect(ADMIN_URL.'fundraisers.php');
}

// ===== UPDATE =====
if ($action === 'update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'         => trim($_POST['title']),
        'slug'          => trim($_POST['slug']) ?: slugify($_POST['title']),
        'cause'         => trim($_POST['cause']),
        'story'         => $_POST['story'],
        'organizer_name'  => trim($_POST['organizer_name']),
        'organizer_email' => trim($_POST['organizer_email']),
        'organizer_phone' => trim($_POST['organizer_phone']),
        'organizer_bio'   => trim($_POST['organizer_bio']),
        'goal_amount'   => (float)$_POST['goal_amount'],
        'raised_amount' => (float)$_POST['raised_amount'],
        'currency'      => $_POST['currency'],
        'start_date'    => $_POST['start_date'] ?: null,
        'end_date'      => $_POST['end_date'] ?: null,
        'status'        => $_POST['status'],
        'is_featured'   => isset($_POST['is_featured']) ? 1 : 0,
        'admin_notes'   => trim($_POST['admin_notes']),
    ];
    $img = upload_image('cover_image','fundraisers');
    if ($img === false) flash_set('error','Image upload failed.');
    else {
        if ($img) $data['cover_image'] = $img;
        $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $data['id'] = $id;
        $pdo->prepare("UPDATE fundraisers SET $set WHERE id=:id")->execute($data);

        // Send approval/rejection email if status changed to active or rejected
        if (in_array($_POST['status'], ['active','rejected'])) {
            $f = $pdo->query("SELECT * FROM fundraisers WHERE id=$id")->fetch();
            if ($f) {
                if ($f['status'] === 'active') {
                    $body  = '<p>Dear ' . htmlspecialchars($f['organizer_name']) . ',</p>';
                    $body .= '<p>Great news! Your fundraiser <strong>"' . htmlspecialchars($f['title']) . '"</strong> has been approved and is now live on Sharan Foundation. 🎉</p>';
                    $body .= '<p>Share your campaign link with friends and family to start receiving contributions:</p>';
                    $body .= '<p style="text-align:center;background:#f9fafb;padding:15px;border-radius:8px;font-family:monospace"><a href="' . BASE_URL . 'pages/fundraiser.php?slug=' . htmlspecialchars($f['slug']) . '">' . BASE_URL . 'pages/fundraiser.php?slug=' . htmlspecialchars($f['slug']) . '</a></p>';
                    $body .= '<p>Thank you for choosing to fundraise for Sharan Foundation. Every contribution will be tracked and acknowledged.</p>';
                    send_mail($f['organizer_email'], '🎉 Your fundraiser is live — Sharan Foundation',
                              email_template('🎉 Your Fundraiser is Approved!', $body, 'View Your Campaign', BASE_URL.'pages/fundraiser.php?slug='.$f['slug']));
                } else {
                    $body  = '<p>Dear ' . htmlspecialchars($f['organizer_name']) . ',</p>';
                    $body .= '<p>Thank you for your interest in starting a fundraiser with Sharan Foundation. Unfortunately, we are unable to approve your campaign at this time.</p>';
                    if (!empty($data['admin_notes'])) {
                        $body .= '<p><strong>Reason:</strong> ' . nl2br(htmlspecialchars($data['admin_notes'])) . '</p>';
                    }
                    $body .= '<p>Please reach out to us if you have questions.</p>';
                    send_mail($f['organizer_email'], 'Regarding your fundraiser — Sharan Foundation',
                              email_template('Fundraiser Update', $body));
                }
            }
        }

        flash_saved_row('updated', 'Fundraiser', 'fundraisers', $id);
    }
    redirect(ADMIN_URL.'fundraisers.php');
}

// ===== ADD NEW =====
if ($action === 'add' && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'         => trim($_POST['title']),
        'slug'          => trim($_POST['slug']) ?: slugify($_POST['title']),
        'cause'         => trim($_POST['cause']),
        'story'         => $_POST['story'],
        'organizer_name'  => trim($_POST['organizer_name']),
        'organizer_email' => trim($_POST['organizer_email']),
        'organizer_phone' => trim($_POST['organizer_phone']),
        'organizer_bio'   => trim($_POST['organizer_bio']),
        'goal_amount'   => (float)$_POST['goal_amount'],
        'raised_amount' => (float)$_POST['raised_amount'],
        'currency'      => $_POST['currency'],
        'start_date'    => $_POST['start_date'] ?: null,
        'end_date'      => $_POST['end_date'] ?: null,
        'status'        => $_POST['status'],
        'is_featured'   => isset($_POST['is_featured']) ? 1 : 0,
    ];
    $img = upload_image('cover_image','fundraisers');
    if ($img && $img !== false) $data['cover_image'] = $img;
    $cols = implode(',', array_keys($data));
    $place = ':' . implode(',:', array_keys($data));
    $pdo->prepare("INSERT INTO fundraisers ($cols) VALUES ($place)")->execute($data);
    flash_saved_row('added', 'Fundraiser', 'fundraisers', (int)$pdo->lastInsertId());
    redirect(ADMIN_URL.'fundraisers.php');
}

// ===== VIEW SINGLE =====
if (isset($_GET['view']) && $id) {
    $stmt = $pdo->prepare("SELECT * FROM fundraisers WHERE id=?");
    $stmt->execute([$id]);
    $f = $stmt->fetch();
    if (!$f) { flash_set('error','Fundraiser not found.'); redirect(ADMIN_URL.'fundraisers.php'); }

    $contribs = $pdo->prepare("SELECT * FROM fundraiser_contributions WHERE fundraiser_id=? ORDER BY contributed_at DESC");
    $contribs->execute([$id]);
    $contribs = $contribs->fetchAll();

    $sym = $f['currency'] === 'INR' ? '₹' : ($f['currency'] === 'GBP' ? '£' : '$');
    $pct = $f['goal_amount'] > 0 ? min(100, round(($f['raised_amount']/$f['goal_amount'])*100)) : 0;
?>

<div class="page-head">
  <div><h2>Fundraiser Details</h2><p class="sub">Submitted <?= time_ago($f['submitted_at']) ?></p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/fundraiser.php?slug=<?= e($f['slug']) ?>" target="_blank" class="btn btn-outline">🌐 View Public Page</a>
    <a href="<?= ADMIN_URL ?>fundraisers.php" class="btn btn-outline">← Back</a>
  </div>
</div>

<!-- PROGRESS CARD -->
<div class="card" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff">
  <div class="card-body" style="text-align:center;padding:2rem">
    <h2 style="color:#fff;margin-bottom:.3rem"><?= e($f['title']) ?></h2>
    <p style="opacity:.85;margin-bottom:1.5rem">Organized by <strong><?= e($f['organizer_name']) ?></strong></p>
    <p style="margin:0;font-size:2.4rem;font-weight:800;color:#f4a261">
      <?= $sym . number_format($f['raised_amount'], 0) ?>
      <span style="font-size:1rem;opacity:.7">/ <?= $sym . number_format($f['goal_amount'], 0) ?></span>
    </p>
    <div style="background:rgba(255,255,255,.15);height:10px;border-radius:50px;max-width:500px;margin:1rem auto;overflow:hidden">
      <div style="background:#f4a261;height:100%;width:<?= $pct ?>%;border-radius:50px;transition:width 1s ease"></div>
    </div>
    <p style="margin:0;opacity:.95"><strong><?= $pct ?>%</strong> of goal reached &nbsp;•&nbsp; <strong><?= count($contribs) ?></strong> contributions</p>
    <div style="margin-top:1rem">
      <span class="status-badge status-<?= $f['status']==='active'?'active':($f['status']==='pending'?'new':'inactive') ?>" style="font-size:.85rem;padding:.4rem 1rem"><?= strtoupper($f['status']) ?></span>
      <?php if ($f['is_featured']): ?><span style="margin-left:.5rem;background:rgba(244,162,97,.3);color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.78rem">★ FEATURED</span><?php endif; ?>
    </div>
  </div>
</div>

<!-- EDIT FORM -->
<form method="post" enctype="multipart/form-data" action="?action=update&id=<?= $id ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>📝 Edit Fundraiser</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($f['title']) ?>" required></div>
      <div class="form-group"><label>Slug (URL)</label><input type="text" name="slug" value="<?= e($f['slug']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Cause / Program</label><input type="text" name="cause" value="<?= e($f['cause']) ?>"></div>
      <div class="form-group"><label>Cover Image</label>
        <input type="file" name="cover_image" accept="image/*">
        <?php if ($f['cover_image']): ?><div class="current-image"><img src="<?= BASE_URL.e($f['cover_image']) ?>"></div><?php endif; ?>
      </div>
    </div>
    <div class="form-group"><label>Story / Description</label><textarea name="story" rows="6"><?= e($f['story']) ?></textarea><p class="help">HTML allowed.</p></div>

    <div class="card-head" style="margin:1rem -1.3rem;padding-left:1.3rem"><h3>👤 Organizer</h3></div>
    <div class="form-row">
      <div class="form-group"><label>Name <span class="req">*</span></label><input type="text" name="organizer_name" value="<?= e($f['organizer_name']) ?>" required></div>
      <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="organizer_email" value="<?= e($f['organizer_email']) ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Phone</label><input type="tel" name="organizer_phone" value="<?= e($f['organizer_phone']) ?>"></div>
      <div class="form-group"><label>Organizer Bio</label><textarea name="organizer_bio" rows="2"><?= e($f['organizer_bio']) ?></textarea></div>
    </div>

    <div class="card-head" style="margin:1rem -1.3rem;padding-left:1.3rem"><h3>🎯 Goal &amp; Timeline</h3></div>
    <div class="form-row">
      <div class="form-group"><label>Goal Amount</label><input type="number" step="0.01" name="goal_amount" value="<?= e($f['goal_amount']) ?>"></div>
      <div class="form-group"><label>Raised Amount</label><input type="number" step="0.01" name="raised_amount" value="<?= e($f['raised_amount']) ?>"><p class="help">Manually update as contributions come in.</p></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Currency</label><select name="currency">
        <?php foreach (['INR','GBP','USD'] as $c): ?><option <?= $f['currency']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label>Status</label><select name="status">
        <?php foreach (['pending','active','completed','rejected','closed'] as $s): ?><option <?= $f['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select><p class="help">Changing to <strong>active</strong> or <strong>rejected</strong> sends an email to the organizer.</p></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= e($f['start_date']) ?>"></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date" value="<?= e($f['end_date']) ?>"></div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="is_featured" value="1" <?= $f['is_featured']?'checked':'' ?>> Feature this fundraiser on the homepage / fundraisers listing</label></div>

    <div class="form-group"><label>Admin Notes (internal)</label><textarea name="admin_notes" rows="2"><?= e($f['admin_notes']) ?></textarea></div>

    <div class="form-actions">
      <button class="btn btn-primary">💾 Save Changes</button>
      <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline;margin-left:auto"><?= csrf_field() ?><button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button></form>
    </div>
  </div>
</form>

<!-- CONTRIBUTIONS -->
<div class="card">
  <div class="card-head"><h3>💰 Contributions (<?= count($contribs) ?>)</h3></div>
  <div class="card-body">
    <?php if (!$contribs): ?>
      <div class="empty"><div class="ico">💝</div><h3>No contributions yet</h3><p>Contributions to this fundraiser will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Donor</th><th>Amount</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($contribs as $c):
          $csym = $c['currency']==='INR'?'₹':($c['currency']==='GBP'?'£':'$'); ?>
        <tr>
          <td><strong><?= $c['is_anonymous'] || empty($c['donor_name']) ? '🔒 Anonymous' : e($c['donor_name']) ?></strong>
              <?php if ($c['email']): ?><br><small style="color:#888"><?= e($c['email']) ?></small><?php endif; ?></td>
          <td><strong style="color:var(--primary-dark)"><?= $csym . number_format($c['amount'],0) ?></strong></td>
          <td style="font-size:.88rem;color:#666;font-style:italic"><?= $c['message'] ? '"'.e(mb_strimwidth($c['message'],0,120,'...')).'"' : '—' ?></td>
          <td><span class="status-badge status-<?= $c['payment_status']==='completed'?'active':'new' ?>"><?= e($c['payment_status']) ?></span></td>
          <td><small><?= time_ago($c['contributed_at']) ?></small></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== ADD NEW FORM =====
if ($action === 'add') {
?>
<div class="page-head">
  <div><h2>Add New Fundraiser</h2><p class="sub">Manually create a fundraising campaign.</p></div>
  <a href="<?= ADMIN_URL ?>fundraisers.php" class="btn btn-outline">← Back</a>
</div>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" required></div>
      <div class="form-group"><label>Cause / Program</label><input type="text" name="cause" placeholder="e.g. Girl Child Education"></div>
    </div>
    <div class="form-group"><label>Story / Description</label><textarea name="story" rows="6"></textarea></div>
    <div class="form-group"><label>Cover Image</label><input type="file" name="cover_image" accept="image/*"></div>

    <div class="form-row">
      <div class="form-group"><label>Organizer Name <span class="req">*</span></label><input type="text" name="organizer_name" required></div>
      <div class="form-group"><label>Organizer Email <span class="req">*</span></label><input type="email" name="organizer_email" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Organizer Phone</label><input type="tel" name="organizer_phone"></div>
      <div class="form-group"><label>Organizer Bio</label><textarea name="organizer_bio" rows="2"></textarea></div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Goal Amount</label><input type="number" step="0.01" name="goal_amount" value="10000"></div>
      <div class="form-group"><label>Currency</label><select name="currency"><option>INR</option><option>GBP</option><option>USD</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="status">
        <option value="active" selected>Active</option><option>pending</option><option>completed</option>
      </select></div>
      <div class="form-group"><label>Initial Raised Amount</label><input type="number" step="0.01" name="raised_amount" value="0"></div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="is_featured" value="1"> Featured</label></div>

    <div class="form-actions"><button class="btn btn-primary">➕ Create Fundraiser</button><a href="<?= ADMIN_URL ?>fundraisers.php" class="btn btn-outline">Cancel</a></div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== LIST =====
$status_filter = $_GET['status'] ?? '';
$sql = "SELECT f.*, COALESCE((SELECT COUNT(*) FROM fundraiser_contributions WHERE fundraiser_id=f.id),0) AS contribs_count FROM fundraisers f";
$params = [];
if ($status_filter) { $sql .= " WHERE status=?"; $params[] = $status_filter; }
$sql .= " ORDER BY is_featured DESC, submitted_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$stats = [
  'total'     => (int)$pdo->query("SELECT COUNT(*) FROM fundraisers")->fetchColumn(),
  'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM fundraisers WHERE status='pending'")->fetchColumn(),
  'active'    => (int)$pdo->query("SELECT COUNT(*) FROM fundraisers WHERE status='active'")->fetchColumn(),
  'raised'    => (float)$pdo->query("SELECT COALESCE(SUM(raised_amount),0) FROM fundraisers WHERE currency='INR'")->fetchColumn(),
  'raised_gbp'=> (float)$pdo->query("SELECT COALESCE(SUM(raised_amount),0) FROM fundraisers WHERE currency='GBP'")->fetchColumn(),
];
?>
<div class="page-head">
  <div><h2>🎗️ Peer-to-Peer Fundraisers</h2><p class="sub">Campaigns started by supporters to raise funds for Sharan Foundation.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/fundraisers.php" target="_blank" class="btn btn-outline">🌐 View Public Listing</a>
    <a href="<?= BASE_URL ?>pages/start-fundraiser.php" target="_blank" class="btn btn-outline">📝 Start Fundraiser Page</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Manually</a>
  </div>
</div>

<div class="stats" style="margin-bottom:1.5rem">
  <div class="stat-card green"><div><div class="label">Total Campaigns</div><div class="value"><?= $stats['total'] ?></div></div><div class="ico">🎗️</div></div>
  <div class="stat-card orange"><div><div class="label">Awaiting Approval</div><div class="value"><?= $stats['pending'] ?></div></div><div class="ico">⏳</div></div>
  <div class="stat-card blue"><div><div class="label">Active</div><div class="value"><?= $stats['active'] ?></div></div><div class="ico">✓</div></div>
  <div class="stat-card gold"><div><div class="label">Raised (INR)</div><div class="value" style="font-size:1.4rem">₹<?= number_format($stats['raised'],0) ?></div></div><div class="ico">🇮🇳</div></div>
  <div class="stat-card purple"><div><div class="label">Raised (GBP)</div><div class="value" style="font-size:1.4rem">£<?= number_format($stats['raised_gbp'],0) ?></div></div><div class="ico">🇬🇧</div></div>
</div>

<div class="filters">
  <strong>Status:</strong>
  <a href="?" class="btn-sm <?= !$status_filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All (<?= $stats['total'] ?>)</a>
  <?php foreach (['pending','active','completed','rejected','closed'] as $s):
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM fundraisers WHERE status=?"); $cnt->execute([$s]); ?>
    <a href="?status=<?= $s ?>" class="btn-sm <?= $status_filter===$s?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($s) ?> (<?= $cnt->fetchColumn() ?>)</a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
  <?php if (!$rows): ?>
    <div class="empty"><div class="ico">🎗️</div><h3>No fundraisers yet</h3><p>People can start their own at <a href="<?= BASE_URL ?>pages/start-fundraiser.php" target="_blank">the public form</a>.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Image</th><th>Title / Organizer</th><th>Cause</th><th>Progress</th><th>Contribs</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $sym = $r['currency']==='INR'?'₹':($r['currency']==='GBP'?'£':'$');
      $pct = $r['goal_amount'] > 0 ? min(100, round(($r['raised_amount']/$r['goal_amount'])*100)) : 0; ?>
      <tr style="<?= $r['status']==='pending'?'background:#fff8f0':'' ?>">
        <td><?php if($r['cover_image']): ?><img src="<?= BASE_URL.e($r['cover_image']) ?>" class="thumb-sm"><?php else: ?><div class="thumb-sm" style="display:grid;place-items:center;background:linear-gradient(135deg,#2563eb,#f4a261);color:#fff;font-size:1.4rem">🎗️</div><?php endif; ?></td>
        <td>
          <strong><?= e($r['title']) ?></strong><?php if ($r['is_featured']): ?> ⭐<?php endif; ?>
          <br><small style="color:#888">by <?= e($r['organizer_name']) ?> &middot; <?= e($r['organizer_email']) ?></small>
        </td>
        <td><span class="status-badge" style="background:#e8eef5;color:#555"><?= e($r['cause']) ?></span></td>
        <td>
          <div style="font-size:.82rem;color:#666;margin-bottom:.2rem"><?= $sym . number_format($r['raised_amount'],0) ?> / <?= $sym . number_format($r['goal_amount'],0) ?></div>
          <div style="height:6px;background:#eee;border-radius:50px;width:140px;overflow:hidden"><div style="height:100%;background:linear-gradient(90deg,#2563eb,#f4a261);width:<?= $pct ?>%"></div></div>
          <small><?= $pct ?>%</small>
        </td>
        <td><?= (int)$r['contribs_count'] ?></td>
        <td><span class="status-badge status-<?= $r['status']==='active'?'active':($r['status']==='pending'?'new':'inactive') ?>"><?= e($r['status']) ?></span></td>
        <td><div class="actions">
          <a href="?view=<?= $r['id'] ?>" class="btn-sm btn-view">View</a>
          <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Del</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>

<?php require __DIR__.'/includes/footer.php'; ?>
