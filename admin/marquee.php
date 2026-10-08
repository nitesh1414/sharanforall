<?php
$page_title = 'Marquee';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

/* Small helper: the error shown when the marquees table is missing */
function marquee_table_hint() {
    return ' → The <code>marquees</code> table does not exist yet. Run the <strong>CREATE TABLE IF NOT EXISTS marquees …</strong> block from <code>sql/acts_foundation.sql</code> (or the full file) in phpMyAdmin.';
}

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    try {
        $pdo->prepare("DELETE FROM marquees WHERE id=?")->execute([$id]);
        flash_set('success', 'Marquee deleted.');
    } catch (Throwable $ex) {
        flash_set('error', 'Could not delete marquee: ' . $ex->getMessage() . marquee_table_hint());
    }
    redirect(ADMIN_URL.'marquee.php');
}

// ===== TOGGLE STATUS (quick action) =====
if ($action === 'toggle' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    try {
        $pdo->prepare("UPDATE marquees SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        flash_set('success', 'Marquee status toggled.');
    } catch (Throwable $ex) {
        flash_set('error', 'Could not toggle marquee: ' . $ex->getMessage() . marquee_table_hint());
    }
    redirect(ADMIN_URL.'marquee.php');
}

// ===== SAVE (add / edit) =====
if (($action==='add' || $action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'text'          => trim($_POST['text'] ?? ''),
        'text_hi'       => trim($_POST['text_hi'] ?? ''),
        'icon'          => trim($_POST['icon'] ?? ''),
        'display_order' => (int)($_POST['display_order'] ?? 0),
        'status'        => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
    ];
    if ($data['text'] === '') {
        flash_set('error', 'Please enter marquee text.');
        redirect(ADMIN_URL.'marquee.php?action='.$action.($action==='edit'?'&id='.$id:''));
    }
    try {
        if ($action === 'add') {
            $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
            $pdo->prepare("INSERT INTO marquees ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Marquee', 'marquees', (int)$pdo->lastInsertId());
        } else {
            $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
            $data['id']=$id;
            $pdo->prepare("UPDATE marquees SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Marquee', 'marquees', $id);
        }
    } catch (Throwable $ex) {
        flash_set('error', 'Marquee could not be saved: ' . $ex->getMessage() . marquee_table_hint());
    }
    redirect(ADMIN_URL.'marquee.php');
}

// ===== FORM (add / edit) =====
if ($action === 'add' || $action === 'edit') {
    $row = ['id'=>0,'text'=>'','text_hi'=>'','icon'=>'','display_order'=>0,'status'=>'active'];
    if ($action === 'edit' && $id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM marquees WHERE id=?"); $stmt->execute([$id]);
            $row = $stmt->fetch() ?: $row;
        } catch (Throwable $ex) {
            flash_set('error', 'Could not load marquee: ' . $ex->getMessage() . marquee_table_hint());
            redirect(ADMIN_URL.'marquee.php');
        }
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add' ? '➕ Add Marquee' : '✏️ Edit Marquee' ?></h2><p class="sub">Scrolling announcement shown between the hero and stats on the homepage.</p></div>
  <a href="<?= ADMIN_URL ?>marquee.php" class="btn btn-outline">← Back to List</a>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-group">
      <label>Marquee Text (English) <span class="req">*</span></label>
      <textarea name="text" rows="2" required placeholder="e.g. Supporting 2,500+ children with education, shelter and love"><?= e($row['text']) ?></textarea>
      <p class="help">This is the message that scrolls across the site. Keep it short (one sentence reads best).</p>
    </div>
    <div class="form-group">
      <label>Marquee Text (हिन्दी — optional)</label>
      <input type="text" name="text_hi" value="<?= e($row['text_hi']) ?>" placeholder="Optional Hindi translation">
      <p class="help">Shown instead of the English text when a visitor switches the site to Hindi. Leave blank to reuse the English text.</p>
    </div>
    <div class="form-group">
      <label>Icon (Emoji — optional)</label>
      <input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="8" placeholder="🙏">
            <?= icon_howto_help('icon — optional') ?>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="active" <?= $row['status']==='active'?'selected':'' ?>>✓ Active (show on homepage)</option>
          <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>✗ Inactive (hide)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Display Order</label>
        <input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px">
        <p class="help">Lower numbers appear first in the ticker.</p>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add' ? '➕ Add Marquee' : '💾 Save Changes' ?></button>
      <a href="<?= ADMIN_URL ?>marquee.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// ===== LIST =====
try {
    $rows = $pdo->query("SELECT * FROM marquees ORDER BY display_order, id")->fetchAll();
    $table_ok = true;
} catch (Throwable $ex) {
    $rows = [];
    $table_ok = false;
}
$active_count = 0;
foreach ($rows as $r) if ($r['status']==='active') $active_count++;
?>
<div class="page-head">
  <div><h2>📢 Marquee</h2><p class="sub">Scrolling announcements between the hero and the stats strip. <strong><?= $active_count ?></strong> active of <?= count($rows) ?> total. The ticker hides itself automatically when no active marquee exists.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-outline">🌐 Preview Homepage</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Marquee</a>
  </div>
</div>

<?php if (!$table_ok): ?>
<div class="box" style="background:#fdecea;border-left:4px solid #c0392b;padding:1rem 1.2rem;border-radius:6px;margin-bottom:1.5rem">
  <strong style="color:#c0392b">Marquee table missing.</strong>
  <p style="margin:.4rem 0 0;color:#5a3b37">This is expected if the new SQL migration hasn't been applied yet. Open <code>sql/acts_foundation.sql</code> and run the <strong>MARQUEE ANNOUNCEMENTS</strong> block (or the whole file) in phpMyAdmin, then reload this page.</p>
</div>
<?php elseif (!$rows): ?>
<div class="card"><div class="card-body">
  <div class="empty">
    <div class="ico">📢</div>
    <h3>No marquee messages yet</h3>
    <p>The homepage ticker is hidden until you add one.</p>
    <a href="?action=add" class="btn btn-primary" style="margin-top:1rem">➕ Add First Marquee</a>
  </div>
</div></div>
<?php else: ?>
<div class="card">
  <div class="card-head"><h3>Marquee messages</h3><small style="color:#888">💡 Use Display Order to arrange the order they scroll in.</small></div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th style="width:50px">#</th><th>Icon</th><th>Text (EN)</th><th>Text (हिन्दी)</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong style="color:#666"><?= (int)$r['display_order'] ?></strong></td>
          <td style="font-size:1.4rem"><?= $r['icon'] ? e($r['icon']) : '<span style="color:#bbb">—</span>' ?></td>
          <td><strong><?= e($r['text']) ?></strong></td>
          <td style="color:#888"><?= $r['text_hi'] ? e($r['text_hi']) : '<span style="color:#bbb">—</span>' ?></td>
          <td>
            <form method="post" action="?action=toggle&id=<?= $r['id'] ?>" style="display:inline">
              <?= csrf_field() ?>
              <button style="border:none;background:none;cursor:pointer" title="Click to toggle">
                <span class="status-badge status-<?= $r['status']==='active'?'active':'inactive' ?>"><?= $r['status']==='active'?'✓ Active':'✗ Hidden' ?></span>
              </button>
            </form>
          </td>
          <td>
            <div class="actions">
              <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
              <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline">
                <?= csrf_field() ?><button class="btn-sm btn-del">Del</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
