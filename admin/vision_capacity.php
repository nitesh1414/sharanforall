<?php
$page_title = 'Vision Capacity';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function vc_table_hint() {
    return ' → The <code>vision_capacity</code> table is missing. Run the <strong>VISION CAPACITY</strong> block from <code>sql/acts_foundation.sql</code> (or the whole file) in phpMyAdmin.';
}

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    try {
        $pdo->prepare("DELETE FROM vision_capacity WHERE id=?")->execute([$id]);
        flash_set('success', 'Capacity entry deleted.');
    } catch (Throwable $ex) {
        flash_set('error', 'Could not delete: ' . $ex->getMessage() . vc_table_hint());
    }
    redirect(ADMIN_URL.'vision_capacity.php');
}

// ===== SAVE (add / edit) =====
if (($action==='add' || $action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'category'      => trim($_POST['category'] ?? ''),
        'category_hi'   => trim($_POST['category_hi'] ?? ''),
        'capacity'      => max(0, (int)($_POST['capacity'] ?? 0)),
        'icon'          => trim($_POST['icon'] ?? '👥'),
        'display_order' => (int)($_POST['display_order'] ?? 0),
    ];
    if ($data['category'] === '') {
        flash_set('error', 'Please enter the category name.');
        redirect(ADMIN_URL.'vision_capacity.php?action='.$action.($action==='edit'?'&id='.$id:''));
    }
    try {
        if ($action === 'add') {
            $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
            $pdo->prepare("INSERT INTO vision_capacity ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Capacity entry', 'vision_capacity', (int)$pdo->lastInsertId());
        } else {
            $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
            $data['id']=$id;
            $pdo->prepare("UPDATE vision_capacity SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Capacity entry', 'vision_capacity', $id);
        }
    } catch (Throwable $ex) {
        flash_set('error', 'Could not save: ' . $ex->getMessage() . vc_table_hint());
    }
    redirect(ADMIN_URL.'vision_capacity.php');
}

// ===== FORM (add / edit) =====
if ($action === 'add' || $action === 'edit') {
    $row = ['id'=>0,'category'=>'','category_hi'=>'','capacity'=>0,'icon'=>'👥','display_order'=>0];
    if ($action === 'edit' && $id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vision_capacity WHERE id=?"); $stmt->execute([$id]);
            $row = $stmt->fetch() ?: $row;
        } catch (Throwable $ex) {
            flash_set('error', 'Could not load entry: ' . $ex->getMessage() . vc_table_hint());
            redirect(ADMIN_URL.'vision_capacity.php');
        }
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add' ? '➕ Add Capacity Entry' : '✏️ Edit Capacity Entry' ?></h2><p class="sub">Shown in the “Our Vision” capacity counters on the About page.</p></div>
  <a href="<?= ADMIN_URL ?>vision_capacity.php" class="btn btn-outline">← Back to List</a>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="lang-tabs">
      <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
      <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी (optional)</button>
    </div>
    <div class="lang-panel active" data-lang-panel="en">
      <div class="form-group">
        <label>Category <span class="req">*</span></label>
        <input type="text" name="category" value="<?= e($row['category']) ?>" required placeholder="e.g. Old Age Home">
        <p class="help">The label under the number — e.g. “Old Age Home”, “Women Care Centre”.</p>
      </div>
    </div>
    <div class="lang-panel" data-lang-panel="hi">
      <div class="form-group">
        <label>Category (हिन्दी — optional)</label>
        <input type="text" name="category_hi" value="<?= e($row['category_hi']) ?>" placeholder="Optional Hindi translation">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Capacity (number of people) <span class="req">*</span></label>
        <input type="number" name="capacity" value="<?= (int)$row['capacity'] ?>" min="0" required style="max-width:180px">
      </div>
      <div class="form-group">
        <label>Display Order</label>
        <input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px">
        <p class="help">Lower numbers appear first.</p>
      </div>
    </div>
    <div class="form-group">
      <label>Icon (Emoji)</label>
      <input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="4" placeholder="👥">
      <?= icon_howto_help('icon') ?>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add' ? '➕ Add Entry' : '💾 Save Changes' ?></button>
      <a href="<?= ADMIN_URL ?>vision_capacity.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== LIST =====
try {
    $rows = $pdo->query("SELECT * FROM vision_capacity ORDER BY display_order, id")->fetchAll();
    $table_ok = true;
} catch (Throwable $ex) {
    $rows = []; $table_ok = false;
}
$total = array_sum(array_map(fn($r) => (int)$r['capacity'], $rows));
?>
<div class="page-head">
  <div><h2>👥 Vision Capacity</h2><p class="sub">Capacity counters for the “Community Care Campus” shown on the About page — currently serving <strong><?= number_format($total) ?>+</strong> people in total.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/about.php#vision" class="btn btn-outline">🌐 View on Site</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Entry</a>
  </div>
</div>

<?php if (!$table_ok): ?>
<div class="box" style="background:#fdecea;border-left:4px solid #c0392b;padding:1rem 1.2rem;border-radius:6px;margin-bottom:1.5rem">
  <strong style="color:#c0392b">Vision Capacity table missing.</strong>
  <p style="margin:.4rem 0 0;color:#5a3b37">Apply the <strong>VISION CAPACITY</strong> block from <code>sql/acts_foundation.sql</code> in phpMyAdmin, then reload.</p>
</div>
<?php elseif (!$rows): ?>
<div class="card"><div class="card-body">
  <div class="empty">
    <div class="ico">👥</div>
    <h3>No capacity entries yet</h3>
    <p>Add counters that appear under “Our Vision” on the About page.</p>
    <a href="?action=add" class="btn btn-primary" style="margin-top:1rem">➕ Add First Entry</a>
  </div>
</div></div>
<?php else: ?>
<div class="card">
  <div class="card-head"><h3>Capacity entries</h3><small style="color:#888">💡 Reorder with Display Order — lower numbers first.</small></div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th style="width:50px">#</th><th>Icon</th><th>Category</th><th>Category (हिन्दी)</th><th>Capacity</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong style="color:#666"><?= (int)$r['display_order'] ?></strong></td>
          <td style="font-size:1.4rem"><?= e($r['icon']) ?></td>
          <td><strong><?= e($r['category']) ?></strong></td>
          <td style="color:#888"><?= $r['category_hi'] ? e($r['category_hi']) : '<span style="color:#bbb">—</span>' ?></td>
          <td><span class="status-badge" style="background:#eaf2ff;color:#1d4ed8"><?= number_format((int)$r['capacity']) ?>+</span></td>
          <td><div class="actions">
            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
            <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Del</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
