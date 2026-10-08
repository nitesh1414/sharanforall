<?php
$page_title = 'Milestones';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// DELETE
if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM milestones WHERE id=?")->execute([$id]);
    flash_set('success','Milestone deleted.');
    redirect(ADMIN_URL.'milestones.php');
}

// REORDER
if ($action==='reorder' && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    foreach (($_POST['order'] ?? []) as $i => $mid) {
        $pdo->prepare("UPDATE milestones SET display_order=? WHERE id=?")->execute([(int)$i+1, (int)$mid]);
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// SAVE
if (($action==='add' || $action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'year'           => trim($_POST['year']),
        'title'          => trim($_POST['title']),
        'title_hi'       => trim($_POST['title_hi'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'description_hi' => trim($_POST['description_hi'] ?? ''),
        'icon'           => trim($_POST['icon'] ?? '✦'),
        'is_highlight'   => isset($_POST['is_highlight']) ? 1 : 0,
        'display_order'  => (int)($_POST['display_order'] ?? 0),
        'status'         => $_POST['status'] ?? 'active',
    ];
    if ($action==='add') {
        $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
        $pdo->prepare("INSERT INTO milestones ($cols) VALUES ($place)")->execute($data);
        flash_saved_row('added', 'Milestone', 'milestones', (int)$pdo->lastInsertId());
    } else {
        $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
        $data['id']=$id;
        $pdo->prepare("UPDATE milestones SET $set WHERE id=:id")->execute($data);
        flash_saved_row('updated', 'Milestone', 'milestones', $id);
    }
    redirect(ADMIN_URL.'milestones.php');
}

// FORM
if ($action==='add' || $action==='edit') {
    $row = ['year'=>'','title'=>'','title_hi'=>'','description'=>'','description_hi'=>'','icon'=>'✦','is_highlight'=>0,'display_order'=>0,'status'=>'active'];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM milestones WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add'?'➕ Add Milestone':'✏️ Edit Milestone' ?></h2><p class="sub">Shown on the About page timeline.</p></div>
  <a href="<?= ADMIN_URL ?>milestones.php" class="btn btn-outline">← Back</a>
</div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Year <span class="req">*</span></label><input type="text" name="year" value="<?= e($row['year']) ?>" required placeholder="2024 or Today"></div>
      <div class="form-group"><label>Icon (Emoji)</label><input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="4" placeholder="🌱">
      <?= icon_howto_help('icon') ?></div>
    </div>

    <div class="lang-tabs">
      <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
      <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी <span class="lang-note">optional</span></button>
    </div>
    <div class="lang-panel active" data-lang-panel="en">
      <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required></div>
      <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?= e($row['description']) ?></textarea></div>
    </div>
    <div class="lang-panel" data-lang-panel="hi">
      <div class="form-group"><label>Title (Hindi)</label><input type="text" name="title_hi" value="<?= e($row['title_hi']) ?>"></div>
      <div class="form-group"><label>Description (Hindi)</label><textarea name="description_hi" rows="3"><?= e($row['description_hi']) ?></textarea></div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px"></div>
      <div class="form-group"><label>Status</label><select name="status">
        <option value="active" <?= $row['status']==='active'?'selected':'' ?>>✓ Active</option>
        <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>✗ Hidden</option>
      </select></div>
    </div>
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem"><input type="checkbox" name="is_highlight" value="1" <?= $row['is_highlight']?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)"> ⭐ Highlight this milestone (larger card style)</label>

    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Save' ?></button>
      <a href="<?= ADMIN_URL ?>milestones.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// LIST
$rows = $pdo->query("SELECT * FROM milestones ORDER BY display_order, id")->fetchAll();
?>
<div class="page-head">
  <div><h2>🌱 Milestones Timeline</h2><p class="sub">Year-by-year journey shown on the About page.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/about.php" target="_blank" class="btn btn-outline">🌐 Preview</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Milestone</a>
  </div>
</div>

<div class="card"><div class="card-head"><h3>All Milestones</h3><small style="color:#888">⠿ drag to reorder</small></div><div class="card-body">
<?php if (!$rows): ?>
  <div class="empty"><div class="ico">🌱</div><h3>No milestones yet</h3><a href="?action=add" class="btn btn-primary" style="margin-top:1rem">Add First Milestone</a></div>
<?php else: ?>
<div class="table-wrap"><table id="msTable"><thead><tr><th style="width:30px"></th><th>Year</th><th>Icon</th><th>Title</th><th>Description</th><th>Highlight</th><th>Status</th><th>Actions</th></tr></thead>
<tbody id="msRows">
<?php foreach ($rows as $r): ?>
  <tr data-id="<?= $r['id'] ?>">
    <td style="cursor:grab;text-align:center;color:#bbb;font-size:1.2rem" class="drag-handle">⠿</td>
    <td><strong style="color:var(--primary)"><?= e($r['year']) ?></strong></td>
    <td style="font-size:1.4rem"><?= e($r['icon']) ?></td>
    <td><strong><?= e($r['title']) ?></strong></td>
    <td style="font-size:.85rem;color:#666;max-width:380px"><?= e(mb_strimwidth($r['description'], 0, 120, '...')) ?></td>
    <td><?= $r['is_highlight'] ? '⭐' : '' ?></td>
    <td><span class="status-badge status-<?= $r['status']==='active'?'active':'inactive' ?>"><?= e($r['status']) ?></span></td>
    <td><div class="actions">
      <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
      <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Del</button></form>
    </div></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

<script>
(function(){
  const tbody = document.getElementById('msRows'); if(!tbody) return;
  let dragRow = null;
  tbody.querySelectorAll('tr').forEach(tr => {
    const h = tr.querySelector('.drag-handle');
    h?.addEventListener('mousedown', () => tr.setAttribute('draggable','true'));
    tr.addEventListener('dragstart', () => { dragRow = tr; tr.style.opacity = .4; });
    tr.addEventListener('dragend', () => { if(dragRow) dragRow.style.opacity=1; dragRow=null; tr.removeAttribute('draggable'); saveOrder(); });
    tr.addEventListener('dragover', e => {
      e.preventDefault(); if(!dragRow || dragRow===tr) return;
      const r = tr.getBoundingClientRect();
      tbody.insertBefore(dragRow, (e.clientY-r.top) > r.height/2 ? tr.nextSibling : tr);
    });
  });
  function saveOrder(){
    const fd = new FormData();
    fd.append('csrf','<?= e(csrf_token()) ?>');
    [...tbody.querySelectorAll('tr')].forEach(tr => fd.append('order[]', tr.dataset.id));
    fetch('?action=reorder',{method:'POST',body:fd}).then(()=>location.reload());
  }
})();
</script>

<?php require __DIR__.'/includes/footer.php'; ?>
