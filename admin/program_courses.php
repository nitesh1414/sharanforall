<?php
$page_title = 'Program Courses';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action==='delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM program_courses WHERE id=?")->execute([$id]);
    flash_set('success','Course deleted.');
    redirect(ADMIN_URL.'program_courses.php');
}

if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'category'        => trim($_POST['category']),
        'category_hi'     => trim($_POST['category_hi'] ?? ''),
        'course_name'     => trim($_POST['course_name']),
        'course_name_hi'  => trim($_POST['course_name_hi'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'description_hi'  => trim($_POST['description_hi'] ?? ''),
        'icon'            => trim($_POST['icon'] ?? '📚'),
        'display_order'   => (int)($_POST['display_order'] ?? 0),
        'status'          => $_POST['status'] ?? 'active',
    ];
    if ($action==='add') {
        $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
        $pdo->prepare("INSERT INTO program_courses ($cols) VALUES ($place)")->execute($data);
        flash_saved_row('added', 'Course', 'program_courses', (int)$pdo->lastInsertId());
    } else {
        $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
        $data['id']=$id;
        $pdo->prepare("UPDATE program_courses SET $set WHERE id=:id")->execute($data);
        flash_saved_row('updated', 'Course', 'program_courses', $id);
    }
    redirect(ADMIN_URL.'program_courses.php');
}

// Get unique categories for autocomplete dropdown
$existing_cats = $pdo->query("SELECT DISTINCT category FROM program_courses ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

if ($action==='add' || $action==='edit') {
    $row=['category'=>'','category_hi'=>'','course_name'=>'','course_name_hi'=>'','description'=>'','description_hi'=>'','icon'=>'📚','display_order'=>0,'status'=>'active'];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM program_courses WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head">
  <div><h2><?= $action==='add'?'➕ Add Course':'✏️ Edit Course' ?></h2><p class="sub">A sub-course or skill module shown under Programs.</p></div>
  <a href="<?= ADMIN_URL ?>program_courses.php" class="btn btn-outline">← Back</a>
</div>
<form method="post" class="card"><?= csrf_field() ?><div class="card-body">
  <div class="form-row">
    <div class="form-group">
      <label>Category <span class="req">*</span></label>
      <input type="text" name="category" value="<?= e($row['category']) ?>" required list="catlist" placeholder="e.g. Media & Communication">
      <datalist id="catlist">
        <?php foreach ($existing_cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
      </datalist>
      <p class="help">Type a new category or pick an existing one.</p>
    </div>
    <div class="form-group"><label>Icon</label><input type="text" name="icon" value="<?= e($row['icon']) ?>" maxlength="4" placeholder="📷">
      <?= icon_howto_help('icon') ?></div>
  </div>

  <div class="lang-tabs">
    <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
    <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी</button>
  </div>
  <div class="lang-panel active" data-lang-panel="en">
    <div class="form-group"><label>Course Name <span class="req">*</span></label><input type="text" name="course_name" value="<?= e($row['course_name']) ?>" required placeholder="e.g. Photography"></div>
    <div class="form-group"><label>Description <span style="color:#888;font-weight:400">(optional)</span></label><textarea name="description" rows="2"><?= e($row['description']) ?></textarea></div>
  </div>
  <div class="lang-panel" data-lang-panel="hi">
    <div class="form-group"><label>Category (Hindi)</label><input type="text" name="category_hi" value="<?= e($row['category_hi']) ?>"></div>
    <div class="form-group"><label>Course Name (Hindi)</label><input type="text" name="course_name_hi" value="<?= e($row['course_name_hi']) ?>"></div>
    <div class="form-group"><label>Description (Hindi)</label><textarea name="description_hi" rows="2"><?= e($row['description_hi']) ?></textarea></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px"></div>
    <div class="form-group"><label>Status</label><select name="status">
      <option value="active" <?= $row['status']==='active'?'selected':'' ?>>✓ Active</option>
      <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>✗ Hidden</option>
    </select></div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Save' ?></button>
    <a href="<?= ADMIN_URL ?>program_courses.php" class="btn btn-outline">Cancel</a>
  </div>
</div></form>
<?php require __DIR__.'/includes/footer.php'; exit; }

// LIST grouped by category
$rows = $pdo->query("SELECT * FROM program_courses ORDER BY category, display_order, id")->fetchAll();
$grouped = [];
foreach ($rows as $r) { $grouped[$r['category']][] = $r; }
$cat_count = count($grouped);
$total_courses = count($rows);
?>
<div class="page-head">
  <div><h2>🎓 Program Courses</h2><p class="sub"><strong><?= $total_courses ?></strong> course<?= $total_courses==1?'':'s' ?> across <strong><?= $cat_count ?></strong> categor<?= $cat_count==1?'y':'ies' ?>.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>pages/programs.php" target="_blank" class="btn btn-outline">🌐 Preview</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Course</a>
  </div>
</div>

<?php if (!$rows): ?>
<div class="card"><div class="card-body"><div class="empty"><div class="ico">🎓</div><h3>No courses yet</h3><a href="?action=add" class="btn btn-primary" style="margin-top:1rem">Add First Course</a></div></div></div>
<?php else: foreach ($grouped as $cat => $courses): ?>
  <div class="card">
    <div class="card-head" style="background:#eaf2ff">
      <h3 style="color:var(--primary-dark)">📂 <?= e($cat) ?> <small style="font-weight:400;color:#666">(<?= count($courses) ?> course<?= count($courses)==1?'':'s' ?>)</small></h3>
    </div>
    <div class="card-body">
      <div class="table-wrap"><table>
        <thead><tr><th style="width:60px">Icon</th><th>Course</th><th>Description</th><th style="width:80px">Order</th><th style="width:90px">Status</th><th style="width:140px">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td style="font-size:1.4rem;text-align:center"><?= e($c['icon']) ?></td>
            <td><strong><?= e($c['course_name']) ?></strong></td>
            <td style="font-size:.85rem;color:#666"><?= e(mb_strimwidth($c['description'] ?? '', 0, 100, '...')) ?: '—' ?></td>
            <td><?= (int)$c['display_order'] ?></td>
            <td><span class="status-badge status-<?= $c['status']==='active'?'active':'inactive' ?>"><?= e($c['status']) ?></span></td>
            <td><div class="actions">
              <a href="?action=edit&id=<?= $c['id'] ?>" class="btn-sm btn-edit">Edit</a>
              <form method="post" action="?action=delete&id=<?= $c['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Del</button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php require __DIR__.'/includes/footer.php'; ?>
