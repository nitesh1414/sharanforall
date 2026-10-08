<?php
$page_title = 'Team Members';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$id]);
    flash_set('success','Team member deleted.');
    redirect(ADMIN_URL.'team.php');
}

if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data=['name'=>trim($_POST['name']),'role'=>trim($_POST['role']),'bio'=>trim($_POST['bio']),'display_order'=>(int)$_POST['display_order']];
    $img = upload_image('image','team');
    if ($img===false) flash_set('error','Image upload failed.');
    else {
        if ($img) $data['image']=$img;
        if ($action==='add') {
            $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
            $pdo->prepare("INSERT INTO team_members ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Team member', 'team_members', (int)$pdo->lastInsertId());
        } else {
            $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
            $data['id']=$id;
            $pdo->prepare("UPDATE team_members SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Team member', 'team_members', $id);
        }
        redirect(ADMIN_URL.'team.php');
    }
}

if ($action==='add'||$action==='edit') {
    $row=['name'=>'','role'=>'','bio'=>'','image'=>'','display_order'=>0];
    if ($action==='edit' && $id) { $stmt=$pdo->prepare("SELECT * FROM team_members WHERE id=?"); $stmt->execute([$id]); $row=$stmt->fetch() ?: $row; }
?>
<div class="page-head"><div><h2><?= $action==='add'?'Add Team Member':'Edit Team Member' ?></h2></div><a href="<?= ADMIN_URL ?>team.php" class="btn btn-outline">← Back</a></div>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><div class="card-body">
  <div class="form-row">
    <div class="form-group"><label>Name <span class="req">*</span></label><input type="text" name="name" value="<?= e($row['name']) ?>" required></div>
    <div class="form-group"><label>Role / Designation</label><input type="text" name="role" value="<?= e($row['role']) ?>" placeholder="Founder & President"></div>
  </div>
  <div class="form-group"><label>Bio</label><textarea name="bio" rows="3"><?= e($row['bio']) ?></textarea></div>
  <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="width:120px"></div>
  <div class="form-group"><label>Photo</label><input type="file" name="image" accept="image/*">
    <?php if($row['image']): ?><div class="current-image"><img src="<?= BASE_URL.e($row['image']) ?>"></div><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?></button><a href="<?= ADMIN_URL ?>team.php" class="btn btn-outline">Cancel</a></div>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM team_members ORDER BY display_order, id")->fetchAll();
?>
<div class="page-head"><div><h2>Team Members</h2><p class="sub">Leadership team shown on the About page.</p></div><a href="?action=add" class="btn btn-primary">➕ Add Member</a></div>
<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">👥</div><h3>No team members yet</h3><a href="?action=add" class="btn btn-primary">Add first member</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Photo</th><th>Name</th><th>Role</th><th>Order</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?php if($r['image']): ?><img src="<?= BASE_URL.e($r['image']) ?>" class="thumb-sm" style="border-radius:50%"><?php else: ?><div class="thumb-sm" style="border-radius:50%;background:linear-gradient(135deg,#2563eb,#f4a261);color:#fff;display:grid;place-items:center;font-weight:700"><?= strtoupper(substr($r['name'],0,1)) ?></div><?php endif; ?></td>
        <td><strong><?= e($r['name']) ?></strong></td>
        <td><?= e($r['role']) ?></td>
        <td><?= (int)$r['display_order'] ?></td>
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
