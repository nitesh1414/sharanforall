<?php
$page_title = 'Donations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/receipt_pdf.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

// ===== DOWNLOAD PDF =====
if ($action === 'pdf' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    if (!$d) { flash_set('error','Donation not found.'); redirect(ADMIN_URL.'donations.php'); }
    // Auto-generate receipt number if missing
    if (empty($d['receipt_number'])) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE receipt_number IS NOT NULL")->fetchColumn();
        $d['receipt_number'] = sprintf('AF-%s-%04d', date('Y'), $count + 1);
        $pdo->prepare("UPDATE donations SET receipt_number=? WHERE id=?")->execute([$d['receipt_number'], $id]);
    }
    $file = generate_receipt_pdf($d);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM donations WHERE id=?")->execute([$id]);
    flash_set('success','Donation record deleted.');
    redirect(ADMIN_URL.'donations.php');
}

// ===== UPDATE STATUS + NOTES + SEND RECEIPT =====
if ($action === 'update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    if (!$d) { flash_set('error','Donation not found.'); redirect(ADMIN_URL.'donations.php'); }

    $new_status = $_POST['payment_status'];
    $update = [
        'payment_status' => $new_status,
        'transaction_id' => trim($_POST['transaction_id'] ?? '') ?: null,
        'payment_date'   => $_POST['payment_date'] ?: null,
        'admin_notes'    => trim($_POST['admin_notes'] ?? ''),
    ];

    // Auto-generate receipt number when marking as completed
    if ($new_status === 'completed' && empty($d['receipt_number'])) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE receipt_number IS NOT NULL")->fetchColumn();
        $update['receipt_number'] = sprintf('AF-%s-%04d', date('Y'), $count + 1);
    }

    $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($update)));
    $update['id'] = $id;
    $pdo->prepare("UPDATE donations SET $set WHERE id=:id")->execute($update);

    // Send receipt email if requested
    if (isset($_POST['send_receipt']) && $new_status === 'completed') {
        $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
        $stmt->execute([$id]);
        $fresh = $stmt->fetch();
        [$ok, $err] = send_donation_receipt($fresh);
        if ($ok) {
            $pdo->prepare("UPDATE donations SET receipt_sent_at=NOW() WHERE id=?")->execute([$id]);
            flash_saved_row('updated', 'Donation (receipt emailed to ' . $fresh['email'] . ')', 'donations', $id);
        } else {
            flash_set('error','Donation updated, but receipt email failed: ' . e($err));
        }
    } else {
        flash_saved_row('updated', 'Donation', 'donations', $id);
    }
    redirect(ADMIN_URL . 'donations.php');
}

// ===== EXPORT CSV =====
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donations-'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Receipt No','Date','Donor','Email','Phone','Country','Amount','Currency','Type','Purpose','Payment Method','Status','TXN ID']);
    foreach ($pdo->query("SELECT * FROM donations ORDER BY submitted_at DESC") as $r) {
        fputcsv($out, [
            $r['id'], $r['receipt_number'], $r['submitted_at'], $r['donor_name'], $r['email'], $r['phone'],
            $r['country'], $r['amount'], $r['currency'], $r['donation_type'], $r['purpose'],
            $r['payment_method'], $r['payment_status'], $r['transaction_id'],
        ]);
    }
    fclose($out); exit;
}

// ===== VIEW SINGLE =====
if (isset($_GET['view']) && $id) {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    if (!$d) { flash_set('error','Donation not found.'); redirect(ADMIN_URL.'donations.php'); }

    $sym = $d['currency'] === 'INR' ? '₹' : ($d['currency'] === 'GBP' ? '£' : '$');
    $amount_display = $sym . number_format($d['amount'], 2);
?>
<div class="page-head">
  <div><h2>Donation Details</h2><p class="sub">Received <?= time_ago($d['submitted_at']) ?> • <?= e($d['submitted_at']) ?></p></div>
  <a href="<?= ADMIN_URL ?>donations.php" class="btn btn-outline">← Back to List</a>
</div>

<!-- AMOUNT HIGHLIGHT -->
<div class="card" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff">
  <div class="card-body" style="text-align:center;padding:2rem">
    <p style="margin:0;opacity:.9;font-size:.85rem;letter-spacing:2px;text-transform:uppercase">Donation Amount</p>
    <p style="margin:6px 0;font-size:3rem;font-weight:800;color:#f4a261"><?= $amount_display ?></p>
    <p style="margin:0;opacity:.9"><?= e(ucfirst($d['donation_type'])) ?> • <?= e($d['purpose']) ?></p>
    <div style="margin-top:1rem">
      <span class="status-badge status-<?= e($d['payment_status']==='completed'?'active':($d['payment_status']==='pending'?'new':'inactive')) ?>" style="font-size:.85rem;padding:.4rem 1rem">
        <?= strtoupper(e($d['payment_status'])) ?>
      </span>
      <?php if ($d['receipt_number']): ?>
        <span style="margin-left:.5rem;background:rgba(255,255,255,.15);padding:.3rem .8rem;border-radius:50px;font-size:.78rem">📄 Receipt: <?= e($d['receipt_number']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>👤 Donor Information</h3></div>
  <div class="card-body">
    <dl class="detail-grid">
      <div><dt>Name</dt><dd><strong><?= e($d['donor_name']) ?></strong><?php if ($d['is_anonymous']): ?> <span class="status-badge" style="background:#f0f0f0;color:#666">🔒 Anonymous (hide on public)</span><?php endif; ?></dd></div>
      <div><dt>Email</dt><dd><a href="mailto:<?= e($d['email']) ?>"><?= e($d['email']) ?></a></dd></div>
      <div><dt>Phone</dt><dd><?= e($d['phone']) ?></dd></div>
      <div><dt>Country</dt><dd><?= e($d['country']) ?></dd></div>
      <div class="full"><dt>Address</dt><dd><?= nl2br(e(trim(($d['address'] ?? '') . ', ' . ($d['city'] ?? '') . ', ' . ($d['state'] ?? '') . ' - ' . ($d['pincode'] ?? ''), ', -'))) ?></dd></div>
      <?php if ($d['pan_number']): ?><div><dt>PAN Number</dt><dd><strong><?= e($d['pan_number']) ?></strong></dd></div><?php endif; ?>
    </dl>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>💝 Donation &amp; Payment</h3></div>
  <div class="card-body">
    <dl class="detail-grid">
      <div><dt>Donation Type</dt><dd><?= e(ucfirst($d['donation_type'])) ?></dd></div>
      <div><dt>Purpose / Cause</dt><dd><?= e($d['purpose']) ?></dd></div>
      <div><dt>Payment Method</dt><dd><?= e(strtoupper(str_replace('_',' ',$d['payment_method']))) ?></dd></div>
      <div><dt>Transaction ID</dt><dd><?= $d['transaction_id'] ? '<code>'.e($d['transaction_id']).'</code>' : '<em style="color:#888">none</em>' ?></dd></div>
      <div><dt>Payment Date</dt><dd><?= e($d['payment_date'] ?? '—') ?></dd></div>
      <div><dt>Receipt Sent</dt><dd><?= $d['receipt_sent_at'] ? '✓ ' . time_ago($d['receipt_sent_at']) : '<em style="color:#888">not sent</em>' ?></dd></div>
      <?php if ($d['message']): ?>
        <div class="full"><dt>Donor Message</dt><dd style="background:#fffaf0;padding:1rem;border-left:3px solid #f4a261;border-radius:6px;font-style:italic">"<?= nl2br(e($d['message'])) ?>"</dd></div>
      <?php endif; ?>
      <div class="full"><dt>Preferences</dt><dd>
        <?= $d['receipt_required'] ? '✓ Tax receipt requested ' : '' ?>
        <?= $d['newsletter_optin']  ? ' &nbsp;✓ Newsletter opt-in' : '' ?>
      </dd></div>
    </dl>
  </div>
</div>

<!-- ADMIN ACTION FORM -->
<form method="post" action="?action=update&id=<?= $id ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>⚙️ Update &amp; Manage</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Payment Status</label>
        <select name="payment_status">
          <?php foreach (['pending','completed','failed','refunded'] as $s): ?>
            <option <?= $d['payment_status']===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <p class="help">Marking as <strong>completed</strong> auto-generates a receipt number.</p>
      </div>
      <div class="form-group"><label>Payment Date</label>
        <input type="date" name="payment_date" value="<?= e($d['payment_date']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Transaction / Reference ID</label>
      <input type="text" name="transaction_id" value="<?= e($d['transaction_id']) ?>" placeholder="UPI ref / Bank txn / Razorpay ID...">
    </div>
    <div class="form-group">
      <label>Admin Notes (internal)</label>
      <textarea name="admin_notes" rows="3"><?= e($d['admin_notes']) ?></textarea>
    </div>

    <div class="form-actions" style="flex-wrap:wrap">
      <button class="btn btn-primary">💾 Save Changes</button>
      <button name="send_receipt" value="1" class="btn btn-accent">📧 Save &amp; Email PDF Receipt</button>
      <a href="?action=pdf&id=<?= $id ?>" target="_blank" class="btn" style="background:#9b59b6;color:#fff">📄 Download PDF</a>
      <a href="mailto:<?= e($d['email']) ?>" class="btn btn-outline">✉️ Email Donor</a>
      <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline;margin-left:auto">
        <?= csrf_field() ?>
        <button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button>
      </form>
    </div>
  </div>
</form>

<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== LIST VIEW =====
// Filters
$status_filter = $_GET['status'] ?? '';
$where = []; $params = [];
if ($status_filter) { $where[] = "payment_status=?"; $params[] = $status_filter; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = $pdo->prepare("SELECT * FROM donations $where_sql ORDER BY submitted_at DESC LIMIT 500");
$rows->execute($params);
$rows = $rows->fetchAll();

// Aggregates
$stats = [
    'total'      => (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn(),
    'completed'  => (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='completed'")->fetchColumn(),
    'pending'    => (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='pending'")->fetchColumn(),
    'inr_raised' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='INR'")->fetchColumn(),
    'gbp_raised' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='GBP'")->fetchColumn(),
];
?>

<div class="page-head">
  <div><h2>Donations</h2><p class="sub">All donations submitted via the website donation form.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="?export=1" class="btn btn-outline">📥 Export CSV</a>
    <a href="<?= BASE_URL ?>pages/donate.php" target="_blank" class="btn btn-primary">🌐 View Donation Page</a>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stats" style="margin-bottom:1.5rem">
  <div class="stat-card green"><div><div class="label">Total Donations</div><div class="value"><?= number_format($stats['total']) ?></div></div><div class="ico">💝</div></div>
  <div class="stat-card orange"><div><div class="label">Pending</div><div class="value"><?= number_format($stats['pending']) ?></div></div><div class="ico">⏳</div></div>
  <div class="stat-card blue"><div><div class="label">Completed</div><div class="value"><?= number_format($stats['completed']) ?></div></div><div class="ico">✓</div></div>
  <div class="stat-card gold"><div><div class="label">Raised (INR)</div><div class="value" style="font-size:1.4rem">₹<?= number_format($stats['inr_raised'], 0) ?></div></div><div class="ico">🇮🇳</div></div>
  <div class="stat-card purple"><div><div class="label">Raised (GBP)</div><div class="value" style="font-size:1.4rem">£<?= number_format($stats['gbp_raised'], 0) ?></div></div><div class="ico">🇬🇧</div></div>
</div>

<div class="filters">
  <strong>Status:</strong>
  <a href="?" class="btn-sm <?= !$status_filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All (<?= $stats['total'] ?>)</a>
  <?php foreach (['pending','completed','failed','refunded'] as $s):
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE payment_status=?"); $cnt->execute([$s]); ?>
    <a href="?status=<?= $s ?>" class="btn-sm <?= $status_filter===$s?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($s) ?> (<?= $cnt->fetchColumn() ?>)</a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
  <?php if (!$rows): ?>
    <div class="empty">
      <div class="ico">💝</div>
      <h3>No donations yet</h3>
      <p>Donations submitted via <a href="<?= BASE_URL ?>pages/donate.php" target="_blank">the donation page</a> will appear here.</p>
    </div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr>
      <th>Date</th><th>Donor</th><th>Amount</th><th>Type</th><th>Purpose</th><th>Method</th><th>Status</th><th>Receipt</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $sym = $r['currency']==='INR' ? '₹' : ($r['currency']==='GBP'?'£':'$'); ?>
      <tr style="<?= $r['payment_status']==='pending'?'background:#fff8f0':'' ?>">
        <td style="white-space:nowrap;font-size:.85rem;color:#666"><?= time_ago($r['submitted_at']) ?><br><small><?= date('M j, Y', strtotime($r['submitted_at'])) ?></small></td>
        <td>
          <strong><?= e($r['donor_name']) ?></strong><?php if($r['is_anonymous']): ?> 🔒<?php endif; ?><br>
          <small style="color:#888"><?= e($r['email']) ?></small>
        </td>
        <td><strong style="color:var(--primary-dark);font-size:1.05rem"><?= $sym ?><?= number_format($r['amount'], $r['currency']==='INR'?0:2) ?></strong></td>
        <td><span class="status-badge" style="background:#e8eef5;color:#555"><?= e($r['donation_type']) ?></span></td>
        <td style="font-size:.85rem"><?= e($r['purpose']) ?></td>
        <td style="font-size:.82rem"><?= e(strtoupper(str_replace('_',' ',$r['payment_method']))) ?></td>
        <td>
          <span class="status-badge status-<?= $r['payment_status']==='completed'?'active':($r['payment_status']==='pending'?'new':'inactive') ?>"><?= e($r['payment_status']) ?></span>
        </td>
        <td><?= $r['receipt_number'] ? '<code style="font-size:.78rem">'.e($r['receipt_number']).'</code>' : '—' ?></td>
        <td>
          <div class="actions">
            <a href="?view=<?= $r['id'] ?>" class="btn-sm btn-view">View</a>
            <a href="?action=pdf&id=<?= $r['id'] ?>" target="_blank" class="btn-sm" style="background:#9b59b6;color:#fff" title="Download PDF">📄</a>
            <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline">
              <?= csrf_field() ?><button class="btn-sm btn-del">Del</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>

<?php require __DIR__.'/includes/footer.php'; ?>
