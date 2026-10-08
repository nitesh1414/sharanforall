<?php
$page_title = 'Volunteers';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

// Delete
if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM volunteers WHERE id=?")->execute([$id]);
    flash_set('success','Application deleted.'); redirect(ADMIN_URL.'volunteers.php');
}

// Update status / notes
if ($action==='update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("UPDATE volunteers SET status=?, admin_notes=? WHERE id=?")
        ->execute([$_POST['status'], trim($_POST['admin_notes']), $id]);
    flash_saved_row('updated', 'Volunteer application', 'volunteers', $id);
    redirect(ADMIN_URL.'volunteers.php');
}

// VIEW SINGLE
if (isset($_GET['view']) && $id) {
    $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE id=?"); $stmt->execute([$id]); $v = $stmt->fetch();
    if (!$v) { flash_set('error','Application not found.'); redirect(ADMIN_URL.'volunteers.php'); }
    // mark as read if new
    if ($v['status']==='new') { $pdo->prepare("UPDATE volunteers SET status='reviewed' WHERE id=?")->execute([$id]); $v['status']='reviewed'; }
?>
<div class="page-head">
  <div><h2>Volunteer Application</h2><p class="sub">Submitted <?= time_ago($v['submitted_at']) ?> (<?= $v['submitted_at'] ?>)</p></div>
  <a href="<?= ADMIN_URL ?>volunteers.php" class="btn btn-outline">← Back to List</a>
</div>

<div class="card"><div class="card-body">
  <dl class="detail-grid">
    <div><dt>Full Name</dt><dd><?= e($v['full_name']) ?></dd></div>
    <div><dt>Email</dt><dd><a href="mailto:<?= e($v['email']) ?>"><?= e($v['email']) ?></a></dd></div>
    <div><dt>Phone</dt><dd><?= e($v['phone']) ?></dd></div>
    <div><dt>Country / City</dt><dd><?= e($v['country']) ?> / <?= e($v['city']) ?></dd></div>
    <div><dt>Age</dt><dd><?= e($v['age']) ?></dd></div>
    <div><dt>Gender</dt><dd><?= e($v['gender']) ?></dd></div>
    <div><dt>Occupation</dt><dd><?= e($v['occupation']) ?></dd></div>
    <div><dt>Area of Interest</dt><dd><?= e($v['area_of_interest']) ?></dd></div>
    <div><dt>Availability</dt><dd><?= e($v['availability']) ?></dd></div>
    <div><dt>Status</dt><dd><span class="status-badge status-<?= e($v['status']) ?>"><?= e($v['status']) ?></span></dd></div>
    <div class="full"><dt>Skills</dt><dd><?= nl2br(e($v['skills'])) ?></dd></div>
    <div class="full"><dt>Experience</dt><dd><?= nl2br(e($v['experience'])) ?></dd></div>
    <div class="full"><dt>Motivation</dt><dd><?= nl2br(e($v['motivation'])) ?></dd></div>
  </dl>
</div></div>

<form method="post" action="?action=update&id=<?= $id ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>Admin Action</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="status">
        <?php foreach(['new','reviewed','approved','rejected'] as $s): ?>
          <option <?= $v['status']===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select></div>
      <div class="form-group">
        <label>&nbsp;</label>
        <div style="display:flex;gap:.5rem">
          <a href="mailto:<?= e($v['email']) ?>" class="btn btn-accent">📧 Email</a>
          <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button></form>
        </div>
      </div>
    </div>
    <div class="form-group"><label>Admin Notes (internal)</label><textarea name="admin_notes" rows="3"><?= e($v['admin_notes']) ?></textarea></div>
    <div class="form-actions"><button class="btn btn-primary">💾 Save Changes</button></div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// LIST
$status_filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM volunteers";
$params = [];
if ($status_filter) { $sql .= " WHERE status = ?"; $params[] = $status_filter; }
$sql .= " ORDER BY submitted_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>
<div class="page-head"><div><h2>Volunteer Applications</h2><p class="sub">Submitted via the volunteer form on your website.</p></div></div>

<div class="filters">
  <strong>Status:</strong>
  <a href="?" class="btn-sm <?= !$status_filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All (<?= $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?>)</a>
  <?php foreach(['new','reviewed','approved','rejected'] as $s):
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM volunteers WHERE status=?"); $cnt->execute([$s]); ?>
    <a href="?status=<?= $s ?>" class="btn-sm <?= $status_filter===$s?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($s) ?> (<?= $cnt->fetchColumn() ?>)</a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">🤝</div><h3>No volunteer applications</h3><p>Submissions from <a href="<?= BASE_URL ?>pages/volunteer.php" target="_blank">the volunteer form</a> will appear here.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Email / Phone</th><th>Interest</th><th>Country</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['full_name']) ?></strong></td>
        <td><?= e($r['email']) ?><br><small style="color:#888"><?= e($r['phone']) ?></small></td>
        <td><?= e($r['area_of_interest']) ?></td>
        <td><?= e($r['country']) ?></td>
        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><?= time_ago($r['submitted_at']) ?></td>
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
