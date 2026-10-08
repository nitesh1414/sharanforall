<?php
$page_title = 'Mission Phases';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM mission_phases WHERE id=?")->execute([$id]);
    flash_set('success','Phase deleted.');
    redirect(ADMIN_URL.'mission_phases.php');
}

if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'phase_number'   => (int)$_POST['phase_number'],
        'title'          => trim($_POST['title']),
        'title_hi'       => trim($_POST['title_hi'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'description_hi' => trim($_POST['description_hi'] ?? ''),
        'capacity'       => trim($_POST['capacity'] ?? ''),
        'icon'           => trim($_POST['icon'] ?? '🎯'),
        'status'         => in_array($_POST['status']??'upcoming',['upcoming','active','completed'])?$_POST['status']:'upcoming',
        'display_order'  => (int)($_POST['display_order'] ?? 0),
    ];
    if ($action==='add') {
        $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
        $pdo->prepare("INSERT INTO mission_phases ($cols) VALUES ($place)")->execute($data);
        flash_saved_row('added', 'Mission phase', 'mission_phases', (int)$pdo->lastInsertId());
    } else {
        $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
        $data['id']=$id;
        $pdo->prepare("UPDATE mission_phases SET $set WHERE id=:id")->execute($data);
        flash_saved_row('updated', 'Mission phase', 'mission_phases', $id);
    }
    redirect(ADMIN_URL.'mission_phases.php');
}

if ($action==='add'||$action==='edit') {
    $row=['phase_number'=>0,'title'=>'','title_hi'=>'','description'=>'','description_hi'=>'','capacity'=>'','icon'=>'🎯','status'=>'upcoming','display_order'=>0];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM mission_phases WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add'?'➕ Add Phase':'✏️ Edit Phase' ?></h2><p class="sub">A phase in the Mission Development Plan.</p></div>
  <a href="<?= ADMIN_URL ?>mission_phases.php" class="btn btn-outline">← Back</a>
</div>
<form method="post" class="card"><?= csrf_field() ?><div class="card-body">
  <div class="form-row">
    <div class="form-group"><label>Phase Number <span class="req">*</span></label><input type="number" name="phase_number" value="<?= (int)$row['phase_number'] ?>" required></div>
    <div class="form-group"><label>Icon</label><input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="4">
      <?= icon_howto_help('icon') ?></div>
  </div>

  <div class="lang-tabs">
    <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
    <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी</button>
  </div>
  <div class="lang-panel active" data-lang-panel="en">
    <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required placeholder="e.g. Land Acquisition"></div>
    <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?= e($row['description']) ?></textarea></div>
  </div>
  <div class="lang-panel" data-lang-panel="hi">
    <div class="form-group"><label>Title (Hindi)</label><input type="text" name="title_hi" value="<?= e($row['title_hi']) ?>"></div>
    <div class="form-group"><label>Description (Hindi)</label><textarea name="description_hi" rows="3"><?= e($row['description_hi']) ?></textarea></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Capacity <span style="color:#888;font-weight:400">(optional)</span></label><input type="text" name="capacity" value="<?= e($row['capacity']) ?>" placeholder="e.g. 500 residents, 15-20 acres"></div>
    <div class="form-group"><label>Status</label><select name="status">
      <option value="upcoming"  <?= $row['status']==='upcoming'?'selected':'' ?>>📅 Upcoming</option>
      <option value="active"    <?= $row['status']==='active'?'selected':'' ?>>🚧 In Progress</option>
      <option value="completed" <?= $row['status']==='completed'?'selected':'' ?>>✅ Completed</option>
    </select></div>
  </div>
  <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px"></div>

  <div class="form-actions">
    <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Save' ?></button>
    <a href="<?= ADMIN_URL ?>mission_phases.php" class="btn btn-outline">Cancel</a>
  </div>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM mission_phases ORDER BY display_order, phase_number")->fetchAll();
?>
<div class="page-head">
  <div><h2>🚧 Mission Development Plan</h2><p class="sub">The phased plan for the Community Care &amp; Transformation Campus.</p></div>
  <a href="?action=add" class="btn btn-primary">➕ Add Phase</a>
</div>

<div class="card"><div class="card-body">
<?php if (!$rows): ?>
  <div class="empty"><div class="ico">🎯</div><h3>No phases yet</h3><a href="?action=add" class="btn btn-primary" style="margin-top:1rem">Add First Phase</a></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Phase</th><th>Icon</th><th>Title</th><th>Capacity</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r):
  $sc = ['upcoming'=>'#d4a017','active'=>'#2563eb','completed'=>'#0b6e4f'][$r['status']] ?? '#888';
?>
  <tr>
    <td><strong style="color:var(--primary);font-size:1.05rem">Phase <?= (int)$r['phase_number'] ?></strong></td>
    <td style="font-size:1.4rem"><?= e($r['icon']) ?></td>
    <td><strong><?= e($r['title']) ?></strong></td>
    <td><?php if($r['capacity']): ?><span class="status-badge" style="background:#eaf2ff;color:#1d4ed8"><?= e($r['capacity']) ?></span><?php endif; ?></td>
    <td style="font-size:.85rem;color:#666;max-width:350px"><?= e(mb_strimwidth($r['description'], 0, 100, '...')) ?></td>
    <td><span style="background:<?= $sc ?>;color:#fff;padding:.25rem .8rem;border-radius:50px;font-size:.75rem;font-weight:600;text-transform:uppercase"><?= e($r['status']) ?></span></td>
    <td><div class="actions">
      <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
      <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Del</button></form>
    </div></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

<?php require __DIR__.'/includes/footer.php'; ?>
