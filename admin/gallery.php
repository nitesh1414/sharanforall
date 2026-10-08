<?php
$page_title = 'Gallery';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM gallery WHERE id=?")->execute([$id]);
    flash_set('success','Image deleted.');
    redirect(ADMIN_URL.'gallery.php');
}

if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'=>trim($_POST['title']),
        'caption'=>trim($_POST['caption']),
        'category'=>$_POST['category'],
        'display_order'=>(int)$_POST['display_order'],
    ];
    $image_path = upload_image('image','gallery');
    if ($image_path===false) flash_set('error','Image upload failed.');
    else {
        if ($action==='add' && !$image_path) flash_set('error','Image is required for new entries.');
        else {
            if ($image_path) $data['image']=$image_path;
            if ($action==='add') {
                $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
                $pdo->prepare("INSERT INTO gallery ($cols) VALUES ($place)")->execute($data);
                flash_saved_row('added', 'Gallery image', 'gallery', (int)$pdo->lastInsertId());
            } else {
                $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
                $data['id']=$id;
                $pdo->prepare("UPDATE gallery SET $set WHERE id=:id")->execute($data);
                flash_saved_row('updated', 'Gallery image', 'gallery', $id);
            }
            redirect(ADMIN_URL.'gallery.php');
        }
    }
}

if ($action==='add' || $action==='edit') {
    $row=['title'=>'','caption'=>'','category'=>'events','image'=>'','display_order'=>0];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM gallery WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head"><div><h2><?= $action==='add'?'Add Image':'Edit Image' ?></h2></div><a href="<?= ADMIN_URL ?>gallery.php" class="btn btn-outline">← Back</a></div>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><div class="card-body">
  <div class="form-row">
    <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required></div>
    <div class="form-group"><label>Category</label><select name="category">
      <?php foreach(['education','women','oldage','hostel','bible','events'] as $c): ?><option <?= $row['category']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
    </select></div>
  </div>
  <div class="form-group"><label>Caption</label><input type="text" name="caption" value="<?= e($row['caption']) ?>"></div>
  <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="width:120px"></div>
  <div class="form-group"><label>Image <?php if($action==='add') echo '<span class="req">*</span>'; ?></label><input type="file" name="image" accept="image/*">
    <?php if($row['image']): ?><div class="current-image"><img src="<?= BASE_URL.e($row['image']) ?>"></div><?php endif; ?>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?> Image</button>
    <a href="<?= ADMIN_URL ?>gallery.php" class="btn btn-outline">Cancel</a>
  </div>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$filter = $_GET['cat'] ?? '';
$sql = "SELECT * FROM gallery";
$params = [];
if ($filter) { $sql .= " WHERE category = ?"; $params[] = $filter; }
$sql .= " ORDER BY display_order, id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>
<div class="page-head"><div><h2>Photo Gallery</h2><p class="sub">Images displayed on the Gallery page.</p></div><a href="?action=add" class="btn btn-primary">➕ Add Image</a></div>

<div class="filters">
  <strong>Filter:</strong>
  <a href="?" class="btn-sm <?= !$filter?'btn-view':'btn-edit' ?>" style="text-decoration:none">All</a>
  <?php foreach(['education','women','oldage','hostel','bible','events'] as $c): ?>
    <a href="?cat=<?= $c ?>" class="btn-sm <?= $filter===$c?'btn-view':'btn-edit' ?>" style="text-decoration:none"><?= ucfirst($c) ?></a>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">🖼️</div><h3>No images yet</h3><a href="?action=add" class="btn btn-primary">Add your first image</a></div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem">
    <?php foreach($rows as $r): ?>
      <div class="card" style="margin:0">
        <div style="height:160px;background-image:url('<?= BASE_URL.e($r['image']) ?>');background-size:cover;background-position:center"></div>
        <div style="padding:.9rem">
          <h4 style="font-size:.95rem;margin-bottom:.3rem;color:#0d2940"><?= e($r['title']) ?></h4>
          <p style="font-size:.78rem;color:#888;margin-bottom:.5rem"><?= e($r['caption']) ?></p>
          <span class="status-badge" style="background:#f1f4f6;color:#666"><?= e($r['category']) ?></span>
          <div class="actions" style="margin-top:.7rem">
            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
            <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Delete</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
