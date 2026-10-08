<?php
$page_title = 'Recurring Donations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/mailer.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

// ===== RUN CRON NOW =====
if ($action === 'run_cron' && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    // Run the cron in-process. Capture its output.
    ob_start();
    $_GET['admin_run'] = 1;
    $_SESSION['admin'] = $_SESSION['admin'] ?? ['id'=>1]; // ensure session var stays set
    include __DIR__ . '/../cron/recurring_donations.php';
    $output = ob_get_clean();
    flash_set('success', '✓ Cron run completed! See latest run details below.');
    redirect(ADMIN_URL . 'recurring.php');
}

// ===== UPDATE =====
if ($action === 'update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $old_status = $pdo->prepare("SELECT status FROM recurring_donations WHERE id=?");
    $old_status->execute([$id]);
    $old_status = $old_status->fetchColumn();

    $update = [
        'donor_name'       => trim($_POST['donor_name']),
        'email'            => trim($_POST['email']),
        'phone'            => trim($_POST['phone']),
        'amount'           => (float)$_POST['amount'],
        'currency'         => $_POST['currency'],
        'frequency'        => $_POST['frequency'],
        'purpose'          => trim($_POST['purpose']),
        'payment_method'   => $_POST['payment_method'],
        'status'           => $_POST['status'],
        'next_charge_date' => $_POST['next_charge_date'] ?: date('Y-m-d'),
        'max_cycles'       => $_POST['max_cycles'] ? (int)$_POST['max_cycles'] : null,
    ];

    // Track cancellation
    if ($update['status'] === 'cancelled' && $old_status !== 'cancelled') {
        $update['cancelled_at'] = date('Y-m-d H:i:s');
        $update['cancellation_reason'] = $_POST['cancellation_reason'] ?? 'Cancelled by admin';
    }

    $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($update)));
    $update['id'] = $id;
    $pdo->prepare("UPDATE recurring_donations SET $set WHERE id=:id")->execute($update);

    // Send cancellation/pause email if status changed
    if (in_array($update['status'], ['cancelled','paused']) && $old_status === 'active') {
        $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE id=?");
        $stmt->execute([$id]);
        $fresh = $stmt->fetch();
        if ($fresh) { try { notify_recurring_cancelled($fresh); } catch (Throwable $e){} }
    }

    flash_saved_row('updated', 'Recurring donation', 'recurring_donations', $id);
    redirect(ADMIN_URL.'recurring.php');
}

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM recurring_donations WHERE id=?")->execute([$id]);
    flash_set('success','Recurring donation deleted.');
    redirect(ADMIN_URL.'recurring.php');
}

// ===== VIEW SINGLE =====
if (isset($_GET['view']) && $id) {
    $stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE id=?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) { flash_set('error','Not found.'); redirect(ADMIN_URL.'recurring.php'); }

    $charges = $pdo->prepare("SELECT * FROM recurring_charges WHERE recurring_id=? ORDER BY charged_at DESC LIMIT 50");
    $charges->execute([$id]);
    $charges = $charges->fetchAll();

    $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
?>
<div class="page-head">
  <div><h2>Recurring Donation #<?= $id ?></h2><p class="sub">Created <?= time_ago($r['created_at']) ?></p></div>
  <a href="<?= ADMIN_URL ?>recurring.php" class="btn btn-outline">← Back</a>
</div>

<!-- SUMMARY -->
<div class="card" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff">
  <div class="card-body" style="text-align:center;padding:2rem">
    <p style="margin:0;opacity:.85;font-size:.85rem;letter-spacing:2px;text-transform:uppercase"><?= e($r['donor_name']) ?></p>
    <p style="margin:6px 0;font-size:2.4rem;font-weight:800;color:#f4a261">
      <?= $sym . number_format($r['amount'], 0) ?>
      <span style="font-size:1rem;opacity:.7">/ <?= e($r['frequency']) ?></span>
    </p>
    <p style="margin:0;opacity:.9">Supporting: <strong><?= e($r['purpose']) ?></strong></p>
    <div style="margin-top:1rem;display:flex;justify-content:center;gap:1.5rem;flex-wrap:wrap">
      <span style="background:rgba(255,255,255,.15);padding:.5rem 1rem;border-radius:50px;font-size:.85rem">
        ✓ <strong><?= (int)$r['total_cycles'] ?></strong> cycles
      </span>
      <span style="background:rgba(255,255,255,.15);padding:.5rem 1rem;border-radius:50px;font-size:.85rem">
        💰 Total: <strong><?= $sym . number_format($r['total_raised'], 0) ?></strong>
      </span>
      <span style="background:rgba(255,255,255,.15);padding:.5rem 1rem;border-radius:50px;font-size:.85rem">
        📅 Next: <strong><?= e($r['next_charge_date']) ?></strong>
      </span>
      <span class="status-badge status-<?= $r['status']==='active'?'active':($r['status']==='paused'?'new':'inactive') ?>" style="font-size:.85rem;padding:.5rem 1rem">
        <?= strtoupper($r['status']) ?>
      </span>
    </div>
  </div>
</div>

<!-- EDIT FORM -->
<form method="post" action="?action=update&id=<?= $id ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>📝 Edit Subscription</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Donor Name</label><input type="text" name="donor_name" value="<?= e($r['donor_name']) ?>" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($r['email']) ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($r['phone']) ?>"></div>
      <div class="form-group"><label>Purpose</label><input type="text" name="purpose" value="<?= e($r['purpose']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" value="<?= e($r['amount']) ?>" required></div>
      <div class="form-group"><label>Currency</label><select name="currency">
        <?php foreach (['INR','GBP','USD'] as $c): ?><option <?= $r['currency']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Frequency</label><select name="frequency">
        <?php foreach (['weekly','monthly','quarterly','yearly'] as $f): ?><option <?= $r['frequency']===$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label>Payment Method</label><select name="payment_method">
        <?php foreach (['upi','razorpay','stripe','paypal','bank_transfer','standing_order','other'] as $m): ?><option <?= $r['payment_method']===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="status">
        <?php foreach (['active','paused','cancelled','expired'] as $s): ?><option <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select><p class="help">Setting <strong>cancelled/paused</strong> notifies the donor by email.</p></div>
      <div class="form-group"><label>Next Charge Date</label><input type="date" name="next_charge_date" value="<?= e($r['next_charge_date']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Max Cycles</label><input type="number" name="max_cycles" value="<?= e($r['max_cycles']) ?>" placeholder="leave blank = unlimited"></div>
      <div class="form-group"><label>Failed Attempts</label><input type="text" value="<?= (int)$r['failed_attempts'] ?>" disabled style="background:#f1f1f1"><p class="help">After 3 failures, status auto-pauses.</p></div>
    </div>
    <div class="form-group"><label>Cancellation Reason (if cancelling)</label><textarea name="cancellation_reason" rows="2"><?= e($r['cancellation_reason']) ?></textarea></div>

    <div style="background:#fef7e0;padding:.8rem 1rem;border-left:3px solid #d4a017;border-radius:6px;font-size:.85rem;color:#5b4a2c;margin-bottom:1rem">
      <strong>🔗 Donor manage link:</strong><br>
      <code style="font-size:.78rem"><?= e(BASE_URL) ?>pages/manage-donation.php?token=<?= e($r['manage_token']) ?></code>
      <br><small>The donor can use this link to pause / cancel / update their subscription themselves.</small>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary">💾 Save Changes</button>
      <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline;margin-left:auto"><?= csrf_field() ?><button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button></form>
    </div>
  </div>
</form>

<!-- CHARGE HISTORY -->
<div class="card">
  <div class="card-head"><h3>📊 Charge History (<?= count($charges) ?> entries)</h3></div>
  <div class="card-body">
    <?php if (!$charges): ?>
      <div class="empty"><div class="ico">📊</div><h3>No charges yet</h3></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Status</th><th>Amount</th><th>Donation</th><th>Notes</th></tr></thead>
      <tbody>
      <?php foreach ($charges as $c):
        $color = ['success'=>'#2563eb','failed'=>'#c0392b','retry'=>'#d4a017','manual'=>'#3498db','reminder_sent'=>'#9b59b6'][$c['status']] ?? '#666'; ?>
        <tr>
          <td style="white-space:nowrap;font-size:.85rem"><?= e($c['charged_at']) ?></td>
          <td><span style="color:#fff;background:<?= $color ?>;padding:.25rem .7rem;border-radius:50px;font-size:.72rem;text-transform:uppercase;font-weight:600"><?= e($c['status']) ?></span></td>
          <td><strong><?= ($c['currency']==='INR'?'₹':($c['currency']==='GBP'?'£':'$')) . number_format($c['amount'], 0) ?></strong></td>
          <td><?php if ($c['donation_id']): ?><a href="<?= ADMIN_URL ?>donations.php?view=<?= (int)$c['donation_id'] ?>">#<?= (int)$c['donation_id'] ?></a><?php else: ?>—<?php endif; ?></td>
          <td style="font-size:.85rem;color:#555"><?= e($c['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== LIST + CRON CONTROL =====
$status_filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM recurring_donations";
$params = [];
if ($status_filter) { $sql .= " WHERE status=?"; $params[] = $status_filter; }
$sql .= " ORDER BY status='active' DESC, next_charge_date ASC LIMIT 500";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$stats = [
  'total'     => (int)$pdo->query("SELECT COUNT(*) FROM recurring_donations")->fetchColumn(),
  'active'    => (int)$pdo->query("SELECT COUNT(*) FROM recurring_donations WHERE status='active'")->fetchColumn(),
  'paused'    => (int)$pdo->query("SELECT COUNT(*) FROM recurring_donations WHERE status='paused'")->fetchColumn(),
  'mrr_inr'   => (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN frequency='monthly' THEN amount WHEN frequency='weekly' THEN amount*4.33 WHEN frequency='quarterly' THEN amount/3 WHEN frequency='yearly' THEN amount/12 END),0) FROM recurring_donations WHERE status='active' AND currency='INR'")->fetchColumn(),
  'mrr_gbp'   => (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN frequency='monthly' THEN amount WHEN frequency='weekly' THEN amount*4.33 WHEN frequency='quarterly' THEN amount/3 WHEN frequency='yearly' THEN amount/12 END),0) FROM recurring_donations WHERE status='active' AND currency='GBP'")->fetchColumn(),
  'due_today' => (int)$pdo->query("SELECT COUNT(*) FROM recurring_donations WHERE status='active' AND next_charge_date <= CURDATE()")->fetchColumn(),
];

$last_cron = $pdo->query("SELECT * FROM cron_log WHERE job_name='recurring_donations' ORDER BY ran_at DESC LIMIT 1")->fetch();
$recent_cron = $pdo->query("SELECT * FROM cron_log WHERE job_name='recurring_donations' ORDER BY ran_at DESC LIMIT 10")->fetchAll();
?>

<div class="page-head">
  <div><h2>🔁 Recurring Donations</h2><p class="sub">Donors who have set up recurring giving (weekly / monthly / quarterly / yearly).</p></div>
</div>

<!-- KPIs -->
<div class="stats">
  <div class="stat-card green"><div><div class="label">Total Subscriptions</div><div class="value"><?= $stats['total'] ?></div></div><div class="ico">🔁</div></div>
  <div class="stat-card blue"><div><div class="label">Active Now</div><div class="value"><?= $stats['active'] ?></div></div><div class="ico">✓</div></div>
  <div class="stat-card orange"><div><div class="label">Due Today</div><div class="value"><?= $stats['due_today'] ?></div></div><div class="ico">⏰</div></div>
  <div class="stat-card gold"><div><div class="label">Est. Monthly (INR)</div><div class="value" style="font-size:1.4rem">₹<?= number_format($stats['mrr_inr'], 0) ?></div></div><div class="ico">🇮🇳</div></div>
  <div class="stat-card purple"><div><div class="label">Est. Monthly (GBP)</div><div class="value" style="font-size:1.4rem">£<?= number_format($stats['mrr_gbp'], 0) ?></div></div><div class="ico">🇬🇧</div></div>
</div>

<!-- CRON CONTROL -->
<div class="card">
  <div class="card-head">
    <h3>⚙️ Cron Job Status</h3>
    <form method="post" action="?action=run_cron" style="display:inline">
      <?= csrf_field() ?>
      <button class="btn btn-accent">▶ Run Cron Now</button>
    </form>
  </div>
  <div class="card-body">
    <?php if ($last_cron): ?>
      <p style="margin-bottom:1rem;color:#555">Last run: <strong><?= time_ago($last_cron['ran_at']) ?></strong>
        &nbsp;|&nbsp; Processed: <strong><?= (int)$last_cron['processed'] ?></strong>
        &nbsp;|&nbsp; ✓ <strong><?= (int)$last_cron['succeeded'] ?></strong> succeeded
        &nbsp;|&nbsp; ✗ <strong><?= (int)$last_cron['failed'] ?></strong> failed
        &nbsp;|&nbsp; 📧 <strong><?= (int)$last_cron['reminders_sent'] ?></strong> reminders
        &nbsp;|&nbsp; ⏱ <?= (int)$last_cron['duration_ms'] ?>ms
      </p>
      <details>
        <summary style="cursor:pointer;color:var(--primary);font-weight:600">View latest run log</summary>
        <pre style="background:#0d2940;color:#bfc8cb;padding:1rem;border-radius:6px;font-size:.78rem;overflow:auto;max-height:300px;margin-top:.7rem"><?= e($last_cron['details']) ?></pre>
      </details>
    <?php else: ?>
      <p style="color:#888">Cron has not run yet. Click "Run Cron Now" or set up a scheduler.</p>
    <?php endif; ?>

    <div style="background:#fef7e0;border-left:3px solid #d4a017;padding:1rem;border-radius:6px;margin-top:1.2rem;font-size:.88rem;color:#5b4a2c">
      <strong>⚙️ Cron Setup Instructions:</strong>
      <p style="margin:.5rem 0">For production, schedule this command to run <strong>once daily at 9 AM</strong>:</p>
      <code style="display:block;background:#fff;padding:.6rem;border-radius:4px;font-size:.78rem;margin:.5rem 0">0 9 * * * /usr/bin/php <?= e(realpath(__DIR__ . '/../cron/recurring_donations.php')) ?: __DIR__ . '/../cron/recurring_donations.php' ?></code>
      <p style="margin:.5rem 0"><strong>OR</strong> if your host has no real cron, use a free pinger like <a href="https://cron-job.org" target="_blank">cron-job.org</a> to hit:</p>
      <code style="display:block;background:#fff;padding:.6rem;border-radius:4px;font-size:.78rem;margin:.5rem 0;word-break:break-all"><?= e(BASE_URL) ?>cron/recurring_donations.php?key=YOUR_SECRET</code>
      <p style="margin:.5rem 0;font-size:.82rem">⚠️ Edit <code>CRON_SECRET</code> in <code>cron/recurring_donations.php</code> first!</p>
    </div>
  </div>
</div>

<!-- FILTERS -->
<div class="filters">
  <strong>Status:</strong>
  <a href="?" class="btn-sm <?= !$status_filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All (<?= $stats['total'] ?>)</a>
  <?php foreach (['active','paused','cancelled','expired'] as $s):
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM recurring_donations WHERE status=?"); $cnt->execute([$s]); ?>
    <a href="?status=<?= $s ?>" class="btn-sm <?= $status_filter===$s?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($s) ?> (<?= $cnt->fetchColumn() ?>)</a>
  <?php endforeach; ?>
</div>

<!-- LIST -->
<div class="card"><div class="card-body">
  <?php if (!$rows): ?>
    <div class="empty"><div class="ico">🔁</div><h3>No recurring donations yet</h3><p>Donors who select "Monthly" or "Yearly" on the donation form will appear here.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Donor</th><th>Amount / Cycle</th><th>Purpose</th><th>Method</th><th>Next Charge</th><th>Cycles</th><th>Total Raised</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $sym = $r['currency']==='INR'?'₹':($r['currency']==='GBP'?'£':'$');
      $overdue = $r['status']==='active' && $r['next_charge_date'] < date('Y-m-d');
    ?>
      <tr style="<?= $overdue ? 'background:#fff8f0' : '' ?>">
        <td><strong><?= e($r['donor_name']) ?></strong><br><small style="color:#888"><?= e($r['email']) ?></small></td>
        <td><strong style="color:var(--primary-dark);font-size:1.05rem"><?= $sym . number_format($r['amount'], 0) ?></strong><br><small style="color:#888">/ <?= e($r['frequency']) ?></small></td>
        <td style="font-size:.85rem"><?= e($r['purpose']) ?></td>
        <td style="font-size:.82rem"><?= e(strtoupper(str_replace('_',' ',$r['payment_method']))) ?></td>
        <td style="font-size:.85rem">
          <?= e($r['next_charge_date']) ?>
          <?php if ($overdue): ?><br><small style="color:#c0392b;font-weight:600">⚠ OVERDUE</small><?php endif; ?>
        </td>
        <td><strong><?= (int)$r['total_cycles'] ?></strong></td>
        <td><strong style="color:var(--primary-dark)"><?= $sym . number_format($r['total_raised'], 0) ?></strong></td>
        <td><span class="status-badge status-<?= $r['status']==='active'?'active':($r['status']==='paused'?'new':'inactive') ?>"><?= e($r['status']) ?></span></td>
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

<!-- CRON HISTORY -->
<?php if ($recent_cron): ?>
<div class="card">
  <div class="card-head"><h3>📜 Recent Cron Runs</h3></div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Ran</th><th>Processed</th><th>✓ Succeeded</th><th>✗ Failed</th><th>📧 Reminders</th><th>Duration</th></tr></thead>
      <tbody>
        <?php foreach ($recent_cron as $cr): ?>
        <tr>
          <td style="font-size:.85rem"><?= e($cr['ran_at']) ?><br><small style="color:#888"><?= time_ago($cr['ran_at']) ?></small></td>
          <td><?= (int)$cr['processed'] ?></td>
          <td style="color:#2563eb"><strong><?= (int)$cr['succeeded'] ?></strong></td>
          <td style="color:#c0392b"><strong><?= (int)$cr['failed'] ?></strong></td>
          <td><?= (int)$cr['reminders_sent'] ?></td>
          <td><?= (int)$cr['duration_ms'] ?>ms</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__.'/includes/footer.php'; ?>
