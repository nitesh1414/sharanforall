<?php
$page_title = 'Testimonials';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM testimonials WHERE id=?")->execute([$id]);
    flash_set('success','Testimonial deleted.'); redirect(ADMIN_URL.'testimonials.php');
}
if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data=['name'=>trim($_POST['name']),'role'=>trim($_POST['role']),'message'=>trim($_POST['message']),'status'=>$_POST['status'],'display_order'=>(int)$_POST['display_order']];
    if ($action==='add') {
        $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
        $pdo->prepare("INSERT INTO testimonials ($cols) VALUES ($place)")->execute($data);
        flash_saved_row('added', 'Testimonial', 'testimonials', (int)$pdo->lastInsertId());
    } else {
        $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
        $data['id']=$id;
        $pdo->prepare("UPDATE testimonials SET $set WHERE id=:id")->execute($data);
        flash_saved_row('updated', 'Testimonial', 'testimonials', $id);
    }
    redirect(ADMIN_URL.'testimonials.php');
}

if ($action==='add'||$action==='edit') {
    $row=['name'=>'','role'=>'','message'=>'','status'=>'active','display_order'=>0];
    if ($action==='edit' && $id) { $stmt=$pdo->prepare("SELECT * FROM testimonials WHERE id=?"); $stmt->execute([$id]); $row=$stmt->fetch() ?: $row; }
?>
<div class="page-head"><div><h2><?= $action==='add'?'Add Testimonial':'Edit Testimonial' ?></h2></div><a href="<?= ADMIN_URL ?>testimonials.php" class="btn btn-outline">← Back</a></div>
<form method="post" class="card"><?= csrf_field() ?><div class="card-body">
  <div class="form-row">
    <div class="form-group"><label>Name <span class="req">*</span></label><input type="text" name="name" value="<?= e($row['name']) ?>" required></div>
    <div class="form-group"><label>Role / Designation</label><input type="text" name="role" value="<?= e($row['role']) ?>" placeholder="Beneficiary, India"></div>
  </div>
  <div class="form-group"><label>Message <span class="req">*</span></label><textarea name="message" rows="4" required><?= e($row['message']) ?></textarea></div>
  <div class="form-row">
    <div class="form-group"><label>Status</label><select name="status">
      <option value="active" <?= $row['status']==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>Inactive</option>
    </select></div>
    <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?></button><a href="<?= ADMIN_URL ?>testimonials.php" class="btn btn-outline">Cancel</a></div>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM testimonials ORDER BY display_order, id DESC")->fetchAll();
?>
<div class="page-head"><div><h2>Testimonials</h2><p class="sub">Stories of hope shown on the homepage.</p></div><a href="?action=add" class="btn btn-primary">➕ Add Testimonial</a></div>
<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">💬</div><h3>No testimonials yet</h3><a href="?action=add" class="btn btn-primary">Add first testimonial</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Role</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['name']) ?></strong></td>
        <td><?= e($r['role']) ?></td>
        <td style="max-width:400px"><em>"<?= e(mb_strimwidth($r['message'],0,150,'...')) ?>"</em></td>
        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><div class="actions">
          <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
          <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Delete</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
