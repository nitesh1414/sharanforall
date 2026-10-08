<?php
$page_title = 'Contact Messages';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? $_GET['view'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM contacts WHERE id=?")->execute([$id]);
    flash_set('success','Message deleted.'); redirect(ADMIN_URL.'contacts.php');
}
if ($action==='update' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("UPDATE contacts SET status=? WHERE id=?")->execute([$_POST['status'], $id]);
    flash_saved_row('updated', 'Contact message', 'contacts', $id);
    redirect(ADMIN_URL.'contacts.php');
}

if (isset($_GET['view']) && $id) {
    $stmt=$pdo->prepare("SELECT * FROM contacts WHERE id=?"); $stmt->execute([$id]); $c=$stmt->fetch();
    if (!$c) { flash_set('error','Not found.'); redirect(ADMIN_URL.'contacts.php'); }
    if ($c['status']==='new') { $pdo->prepare("UPDATE contacts SET status='read' WHERE id=?")->execute([$id]); $c['status']='read'; }
?>
<div class="page-head"><div><h2>Contact Message</h2><p class="sub">Received <?= time_ago($c['submitted_at']) ?></p></div><a href="<?= ADMIN_URL ?>contacts.php" class="btn btn-outline">← Back</a></div>
<div class="card"><div class="card-body">
  <dl class="detail-grid">
    <div><dt>From</dt><dd><strong><?= e($c['name']) ?></strong></dd></div>
    <div><dt>Email</dt><dd><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd></div>
    <div><dt>Phone</dt><dd><?= e($c['phone']) ?></dd></div>
    <div><dt>Interest</dt><dd><?= e($c['interest']) ?></dd></div>
    <div><dt>Preferred Office</dt><dd><?= e($c['office']) ?></dd></div>
    <div><dt>Status</dt><dd><span class="status-badge status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></dd></div>
    <div class="full"><dt>Message</dt><dd style="background:#f9fafb;padding:1rem;border-radius:6px;border-left:3px solid #f4a261"><?= nl2br(e($c['message'])) ?></dd></div>
  </dl>
</div></div>
<form method="post" action="?action=update&id=<?= $id ?>" class="card"><?= csrf_field() ?><div class="card-body" style="display:flex;gap:.7rem;align-items:flex-end">
  <div class="form-group" style="margin:0;flex:1"><label>Status</label><select name="status">
    <?php foreach(['new','read','replied'] as $s): ?><option <?= $c['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn-primary">💾 Update</button>
  <a href="mailto:<?= e($c['email']) ?>" class="btn btn-accent">📧 Reply</a>
  <form method="post" action="?action=delete&id=<?= $id ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn" style="background:#e74c3c;color:#fff">🗑 Delete</button></form>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM contacts ORDER BY submitted_at DESC")->fetchAll();
?>
<div class="page-head"><div><h2>Contact Messages</h2><p class="sub">All messages submitted via the Contact form.</p></div></div>
<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">✉️</div><h3>No messages yet</h3></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Interest</th><th>Office</th><th>Status</th><th>Received</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr style="<?= $r['status']==='new'?'background:#fff8f0':'' ?>">
        <td><strong><?= e($r['name']) ?></strong></td>
        <td><?= e($r['email']) ?></td>
        <td><?= e($r['interest']) ?></td>
        <td><?= e($r['office']) ?></td>
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
