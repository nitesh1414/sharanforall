<?php
$page_title = 'Projects';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    flash_set('success', 'Project deleted.');
    redirect(ADMIN_URL . 'projects.php');
}

if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'         => trim($_POST['title']),
        'category'      => $_POST['category'],
        'description'   => trim($_POST['description']),
        'location'      => trim($_POST['location']),
        'goal_amount'   => (float)$_POST['goal_amount'],
        'raised_amount' => (float)$_POST['raised_amount'],
        'currency'      => $_POST['currency'],
        'status'        => $_POST['status'],
        'days_left'     => (int)$_POST['days_left'],
        'display_order' => (int)$_POST['display_order'],
    ];
    $image_path = upload_image('image', 'projects', 800, 500);
    if ($image_path === false) {
        flash_set('error', 'Image upload failed.');
    } else {
        if ($image_path) $data['image'] = $image_path;
        if ($action === 'add') {
            $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
            $pdo->prepare("INSERT INTO projects ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Project', 'projects', (int)$pdo->lastInsertId());
        } else {
            $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
            $data['id']=$id;
            $pdo->prepare("UPDATE projects SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Project', 'projects', $id);
        }
        redirect(ADMIN_URL.'projects.php');
    }
}

if ($action === 'add' || $action === 'edit') {
    $row=['title'=>'','category'=>'education','description'=>'','location'=>'','goal_amount'=>0,'raised_amount'=>0,'currency'=>'INR','status'=>'active','days_left'=>0,'display_order'=>0,'image'=>''];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?"); $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
?>
    <div class="page-head">
      <div><h2><?= $action==='add'?'Add Project':'Edit Project' ?></h2></div>
      <a href="<?= ADMIN_URL ?>projects.php" class="btn btn-outline">← Back</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required></div>
        <div class="form-row">
          <div class="form-group"><label>Category</label><select name="category">
            <?php foreach(['education','shelter','infrastructure','outreach'] as $c): ?>
              <option <?= $row['category']===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="form-group"><label>Location</label><input type="text" name="location" value="<?= e($row['location']) ?>" placeholder="Hyderabad, India"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?= e($row['description']) ?></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Goal Amount</label><input type="number" step="0.01" name="goal_amount" value="<?= e($row['goal_amount']) ?>"></div>
          <div class="form-group"><label>Raised Amount</label><input type="number" step="0.01" name="raised_amount" value="<?= e($row['raised_amount']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Currency</label><select name="currency">
            <?php foreach(['INR','GBP','USD'] as $c): ?><option <?= $row['currency']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
          </select></div>
          <div class="form-group"><label>Status</label><select name="status">
            <?php foreach(['active','completed','urgent','seasonal'] as $s): ?><option <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Days Left</label><input type="number" name="days_left" value="<?= (int)$row['days_left'] ?>"></div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>"></div>
        </div>
        <div class="form-group">
          <label>Image</label>
          <input type="file" name="image" accept="image/*" data-rec-w="800" data-rec-h="500">
          <?= image_upload_help(800, 500) ?>
          <?php if ($row['image']): ?><div class="current-image"><img src="<?= BASE_URL.e($row['image']) ?>" alt=""></div><?php endif; ?>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?> Project</button>
          <a href="<?= ADMIN_URL ?>projects.php" class="btn btn-outline">Cancel</a>
        </div>
      </div>
    </form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM projects ORDER BY display_order, id DESC")->fetchAll();
?>
<div class="page-head">
  <div><h2>Projects</h2><p class="sub">Active fundraising campaigns shown on the Projects page.</p></div>
  <a href="?action=add" class="btn btn-primary">➕ Add Project</a>
</div>
<div class="card"><div class="card-body">
  <?php if (!$rows): ?>
    <div class="empty"><div class="ico">🎯</div><h3>No projects yet</h3><a href="?action=add" class="btn btn-primary">Add your first project</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r):
      $pct = $r['goal_amount'] > 0 ? min(100, round(($r['raised_amount']/$r['goal_amount'])*100)) : 0; ?>
      <tr>
        <td>
          <?php if($r['image']): ?>
            <img src="<?= BASE_URL.e($r['image']) ?>" class="thumb-sm" alt="">
          <?php else: ?>
            <div class="thumb-sm" style="background:#eee"></div>
          <?php endif; ?>
        </td>
        <td><strong><?= e($r['title']) ?></strong><br><small style="color:#888"><?= e($r['location']) ?></small></td>
        <td><?= e($r['category']) ?></td>
        <td>
          <div style="font-size:.78rem;color:#666;margin-bottom:.2rem"><?= format_money($r['raised_amount'],$r['currency']) ?> / <?= format_money($r['goal_amount'],$r['currency']) ?></div>
          <div style="height:6px;background:#eee;border-radius:50px;width:140px"><div style="height:100%;background:linear-gradient(90deg,#2563eb,#f4a261);width:<?= $pct ?>%;border-radius:50px"></div></div>
          <small><?= $pct ?>%</small>
        </td>
        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td>
          <div class="actions">
            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
            <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline">
              <?= csrf_field() ?><button class="btn-sm btn-del">Delete</button>
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
