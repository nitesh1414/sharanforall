<?php
$page_title = 'Where We Serve';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// DELETE
if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
    flash_set('success','Office deleted.');
    redirect(ADMIN_URL.'offices.php');
}

// REORDER
if ($action==='reorder' && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    foreach (($_POST['order'] ?? []) as $i => $oid) {
        $pdo->prepare("UPDATE offices SET display_order=? WHERE id=?")->execute([(int)$i+1, (int)$oid]);
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// SAVE
if (($action==='add' || $action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'         => trim($_POST['title']),
        'country'       => trim($_POST['country'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'hours'         => trim($_POST['hours'] ?? ''),
        'map_link'      => trim($_POST['map_link'] ?? ''),
        'icon'          => trim($_POST['icon'] ?? '📍'),
        'display_order' => (int)($_POST['display_order'] ?? 0),
        'status'        => $_POST['status'] ?? 'active',
    ];
    if ($data['title'] === '') {
        flash_set('error', 'Office title is required.');
        redirect(ADMIN_URL.'offices.php?action='.$action.($id?'&id='.$id:''));
    }
    if ($action==='add') {
        $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
        $pdo->prepare("INSERT INTO offices ($cols) VALUES ($place)")->execute($data);
        flash_saved_row('added', 'Office', 'offices', (int)$pdo->lastInsertId());
    } else {
        $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
        $data['id']=$id;
        $pdo->prepare("UPDATE offices SET $set WHERE id=:id")->execute($data);
        flash_saved_row('updated', 'Office', 'offices', $id);
    }
    redirect(ADMIN_URL.'offices.php');
}

// FORM
if ($action==='add' || $action==='edit') {
    $row = ['title'=>'','country'=>'','address'=>'','phone'=>'','email'=>'','hours'=>'','map_link'=>'','icon'=>'📍','display_order'=>0,'status'=>'active'];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM offices WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add'?'➕ Add Office':'✏️ Edit Office' ?></h2><p class="sub">Where We Serve locations shown on the Contact page.</p></div>
  <a href="<?= ADMIN_URL ?>offices.php" class="btn btn-outline">← Back</a>
</div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Office Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required placeholder="🇮🇳 India Office"></div>
      <div class="form-group"><label>Icon (Emoji)</label><input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="4" placeholder="📍">
      <?= icon_howto_help('icon') ?></div>
    </div>

    <div class="form-group"><label>Address</label><textarea name="address" rows="2" placeholder="Street, City, State, Country"><?= e($row['address']) ?></textarea></div>

    <div class="form-row">
      <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($row['phone']) ?>" placeholder="+91 98765 43210"></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($row['email']) ?>" placeholder="india@sharanforall.org"></div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Office Hours</label><input type="text" name="hours" value="<?= e($row['hours']) ?>" placeholder="Mon - Sat: 9:00 AM - 6:00 PM IST"></div>
      <div class="form-group"><label>Country</label><input type="text" name="country" value="<?= e($row['country']) ?>" placeholder="India"></div>
    </div>

    <div class="form-group"><label>Google Maps Link (optional)</label><input type="url" name="map_link" value="<?= e($row['map_link']) ?>" placeholder="https://maps.google.com/..."></div>

    <div class="form-row">
      <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px"></div>
      <div class="form-group"><label>Status</label><select name="status">
        <option value="active" <?= $row['status']==='active'?'selected':'' ?>>✓ Active (shown on Contact page)</option>
        <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>✗ Hidden</option>
      </select></div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Save' ?></button>
      <a href="<?= ADMIN_URL ?>offices.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// LIST
$rows = $pdo->query("SELECT * FROM offices ORDER BY display_order, id")->fetchAll();
?>
<div class="page-head">
  <div><h2>📍 Where We Serve</h2><p class="sub">Office locations shown on the Contact page — add, update or delete offices here.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/contact.php" target="_blank" class="btn btn-outline">🌐 Preview</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Office</a>
  </div>
</div>

<div class="card"><div class="card-head"><h3>All Offices</h3><small style="color:#888">⠿ drag to reorder</small></div><div class="card-body">
<?php if (!$rows): ?>
  <div class="empty"><div class="ico">📍</div><h3>No offices yet</h3><a href="?action=add" class="btn btn-primary" style="margin-top:1rem">Add First Office</a></div>
<?php else: ?>
<div class="table-wrap"><table id="offTable"><thead><tr><th style="width:30px"></th><th>Office</th><th>Address</th><th>Phone</th><th>Email</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead>
<tbody id="offRows">
<?php foreach ($rows as $r): ?>
  <tr data-id="<?= $r['id'] ?>">
    <td style="cursor:grab;text-align:center;color:#bbb;font-size:1.2rem" class="drag-handle">⠿</td>
    <td><span style="font-size:1.3rem"><?= e(icon_or($r['icon'], '📍')) ?></span> <strong><?= e($r['title']) ?></strong><?php if ($r['country']): ?><br><small style="color:#888"><?= e($r['country']) ?></small><?php endif; ?></td>
    <td style="font-size:.85rem;color:#666;max-width:300px"><?= $r['address'] ? nl2br(e($r['address'])) : '—' ?></td>
    <td style="font-size:.85rem;white-space:nowrap"><?= e($r['phone'] ?: '—') ?></td>
    <td style="font-size:.85rem"><?= e($r['email'] ?: '—') ?></td>
    <td style="font-size:.85rem;color:#666"><?= e($r['hours'] ?: '—') ?></td>
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
  const tbody = document.getElementById('offRows'); if(!tbody) return;
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
