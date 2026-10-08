<?php
$page_title = 'Partners';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM partners WHERE id=?")->execute([$id]);
    flash_set('success','Inquiry deleted.'); redirect(ADMIN_URL.'partners.php');
}

if ($action==='update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("UPDATE partners SET status=?, admin_notes=? WHERE id=?")
        ->execute([$_POST['status'], trim($_POST['admin_notes']), $id]);
    flash_saved_row('updated', 'Partnership inquiry', 'partners', $id);
    redirect(ADMIN_URL.'partners.php');
}

if (isset($_GET['view']) && $id) {
    $stmt=$pdo->prepare("SELECT * FROM partners WHERE id=?"); $stmt->execute([$id]); $p=$stmt->fetch();
    if (!$p) { flash_set('error','Not found.'); redirect(ADMIN_URL.'partners.php'); }
    if ($p['status']==='new') { $pdo->prepare("UPDATE partners SET status='reviewed' WHERE id=?")->execute([$id]); $p['status']='reviewed'; }
?>
<div class="page-head"><div><h2>Partnership Inquiry</h2><p class="sub">Submitted <?= time_ago($p['submitted_at']) ?> (<?= $p['submitted_at'] ?>)</p></div><a href="<?= ADMIN_URL ?>partners.php" class="btn btn-outline">← Back</a></div>

<div class="card"><div class="card-body">
  <dl class="detail-grid">
    <div><dt>Organization</dt><dd><strong style="font-size:1.05rem"><?= e($p['org_name']) ?></strong></dd></div>
    <div><dt>Type</dt><dd><?= e($p['org_type']) ?></dd></div>
    <div><dt>Contact Person</dt><dd><?= e($p['contact_person']) ?></dd></div>
    <div><dt>Designation</dt><dd><?= e($p['designation']) ?></dd></div>
    <div><dt>Email</dt><dd><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></dd></div>
    <div><dt>Phone</dt><dd><?= e($p['phone']) ?></dd></div>
    <div><dt>Country</dt><dd><?= e($p['country']) ?></dd></div>
    <div><dt>Website</dt><dd><?php if($p['website']): ?><a href="<?= e($p['website']) ?>" target="_blank"><?= e($p['website']) ?></a><?php endif; ?></dd></div>
    <div><dt>Partnership Type</dt><dd><?= e($p['partnership_type']) ?></dd></div>
    <div><dt>Budget Range</dt><dd><?= e($p['budget_range']) ?></dd></div>
    <div><dt>Status</dt><dd><span class="status-badge status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></dd></div>
    <div class="full"><dt>Programs of Interest</dt><dd><?= nl2br(e($p['programs_of_interest'])) ?></dd></div>
    <div class="full"><dt>Proposal / Message</dt><dd><?= nl2br(e($p['proposal'])) ?></dd></div>
  </dl>
</div></div>

<form method="post" action="?action=update&id=<?= $id ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>Admin Action</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="status">
        <?php foreach(['new','reviewed','approved','rejected'] as $s): ?><option <?= $p['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label>&nbsp;</label><div style="display:flex;gap:.5rem">
        <a href="mailto:<?= e($p['email']) ?>" class="btn btn-accent">📧 Email</a>
        <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button></form>
      </div></div>
    </div>
    <div class="form-group"><label>Admin Notes (internal)</label><textarea name="admin_notes" rows="3"><?= e($p['admin_notes']) ?></textarea></div>
    <div class="form-actions"><button class="btn btn-primary">💾 Save Changes</button></div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM partners";
$params = [];
if ($status_filter) { $sql .= " WHERE status=?"; $params[]=$status_filter; }
$sql .= " ORDER BY submitted_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?>
<div class="page-head"><div><h2>Partnership Inquiries</h2><p class="sub">Submitted via the Partner With Us form.</p></div></div>
<div class="filters">
  <strong>Status:</strong>
  <a href="?" class="btn-sm <?= !$status_filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All</a>
  <?php foreach(['new','reviewed','approved','rejected'] as $s): ?>
    <a href="?status=<?= $s ?>" class="btn-sm <?= $status_filter===$s?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">🏢</div><h3>No partnership inquiries yet</h3><p>Submissions from <a href="<?= BASE_URL ?>pages/partner.php" target="_blank">the partner form</a> will appear here.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Organization</th><th>Contact</th><th>Type</th><th>Country</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['org_name']) ?></strong></td>
        <td><?= e($r['contact_person']) ?><br><small style="color:#888"><?= e($r['email']) ?></small></td>
        <td><?= e($r['org_type']) ?></td>
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
