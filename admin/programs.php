<?php
$page_title = 'Programs';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM programs WHERE id = ?")->execute([$id]);
    flash_set('success', 'Program deleted.');
    redirect(ADMIN_URL . 'programs.php');
}

// SAVE (create or update)
if (($action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'      => trim($_POST['title']),
        'title_hi'   => trim($_POST['title_hi'] ?? ''),
        'slug'       => trim($_POST['slug']) ?: slugify($_POST['title']),
        'subtitle'   => trim($_POST['subtitle']),
        'subtitle_hi'=> trim($_POST['subtitle_hi'] ?? ''),
        'icon'       => trim($_POST['icon']),
        'short_desc' => trim($_POST['short_desc']),
        'short_desc_hi' => trim($_POST['short_desc_hi'] ?? ''),
        'long_desc'  => trim($_POST['long_desc']),
        'long_desc_hi'  => trim($_POST['long_desc_hi'] ?? ''),
        'features'   => trim($_POST['features']),
        'stats'      => trim($_POST['stats']),
        'display_order' => (int)$_POST['display_order'],
        'status'     => $_POST['status'],
    ];

    $image_path = upload_image('image', 'programs', 1200, 800);
    if ($image_path === false) {
        flash_set('error', 'Image upload failed. Use JPG/PNG/WebP under 5 MB.');
    } else {
        if ($image_path) $data['image'] = $image_path;

        if ($action === 'add') {
            $cols = implode(',', array_keys($data));
            $place = ':' . implode(',:', array_keys($data));
            $pdo->prepare("INSERT INTO programs ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Program', 'programs', (int)$pdo->lastInsertId());
        } else {
            $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $pdo->prepare("UPDATE programs SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Program', 'programs', $id);
        }
        redirect(ADMIN_URL . 'programs.php');
    }
}

// FORM
if ($action === 'add' || $action === 'edit') {
    $row = ['title'=>'','title_hi'=>'','slug'=>'','subtitle'=>'','subtitle_hi'=>'','icon'=>'','short_desc'=>'','short_desc_hi'=>'','long_desc'=>'','long_desc_hi'=>'','features'=>'','stats'=>'','image'=>'','display_order'=>0,'status'=>'active'];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
    ?>
    <div class="page-head">
      <div><h2><?= $action==='add' ? 'Add Program' : 'Edit Program' ?></h2><p class="sub">Programs shown on the Programs page.</p></div>
      <a href="<?= ADMIN_URL ?>programs.php" class="btn btn-outline">← Back</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label>Slug (URL)</label>
            <input type="text" name="slug" value="<?= e($row['slug']) ?>" placeholder="auto-generated if blank">
          </div>
          <div class="form-group">
            <label>Icon (Emoji)</label>
            <input type="text" name="icon" value="<?= e($row['icon']) ?>" placeholder="📚">
            <?= icon_howto_help('icon') ?>
          </div>
        </div>

        <!-- LANGUAGE TABS -->
        <div class="lang-tabs">
          <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
          <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी (Hindi) <span class="lang-note">optional</span></button>
        </div>

        <!-- ENGLISH PANEL -->
        <div class="lang-panel active" data-lang-panel="en">
          <div class="form-group">
            <label>Title (English) <span class="req">*</span></label>
            <input type="text" name="title" value="<?= e($row['title']) ?>" required>
          </div>
          <div class="form-group">
            <label>Subtitle</label>
            <input type="text" name="subtitle" value="<?= e($row['subtitle']) ?>" placeholder="e.g. Education for All">
          </div>
          <div class="form-group">
            <label>Short Description <span class="req">*</span></label>
            <textarea name="short_desc" rows="2" required><?= e($row['short_desc']) ?></textarea>
            <p class="help">Shown on home/listing cards.</p>
          </div>
          <div class="form-group">
            <label>Long Description</label>
            <textarea name="long_desc" rows="5"><?= e($row['long_desc']) ?></textarea>
            <p class="help">Shown on the Programs detail section.</p>
          </div>
        </div>

        <!-- HINDI PANEL -->
        <div class="lang-panel" data-lang-panel="hi">
          <p style="background:#fef7e0;padding:.6rem 1rem;border-left:3px solid #d4a017;border-radius:6px;color:#5b4a2c;font-size:.88rem;margin-bottom:1rem">
            🇮🇳 Hindi translations are optional. If left blank, the English version will be shown to Hindi users.
          </p>
          <div class="form-group">
            <label>शीर्षक (Title in Hindi)</label>
            <input type="text" name="title_hi" value="<?= e($row['title_hi']) ?>" placeholder="उदा. बाल शिक्षा">
          </div>
          <div class="form-group">
            <label>उप-शीर्षक (Subtitle in Hindi)</label>
            <input type="text" name="subtitle_hi" value="<?= e($row['subtitle_hi']) ?>" placeholder="उदा. सबके लिए शिक्षा">
          </div>
          <div class="form-group">
            <label>संक्षिप्त विवरण (Short Description in Hindi)</label>
            <textarea name="short_desc_hi" rows="2"><?= e($row['short_desc_hi']) ?></textarea>
          </div>
          <div class="form-group">
            <label>विस्तृत विवरण (Long Description in Hindi)</label>
            <textarea name="long_desc_hi" rows="5"><?= e($row['long_desc_hi']) ?></textarea>
          </div>
        </div>
        <div class="form-group">
          <label>Features (one per line, separated by |)</label>
          <textarea name="features" rows="3" placeholder="Free tuition & books|School uniforms|Mid-day meals|Health check-ups"><?= e($row['features']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Stats (format: NUMBER:LABEL, separated by |)</label>
          <input type="text" name="stats" value="<?= e($row['stats']) ?>" placeholder="1200+:Children Enrolled|12:Centers|98%:Pass Rate">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="active" <?= $row['status']==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Image</label>
          <input type="file" name="image" accept="image/*" data-rec-w="1200" data-rec-h="800">
          <?= image_upload_help(1200, 800) ?>
          <?php if (!empty($row['image'])): ?>
            <div class="current-image"><img src="<?= BASE_URL . e($row['image']) ?>" alt=""><p class="help">Current image — upload to replace.</p></div>
          <?php endif; ?>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?> Program</button>
          <a href="<?= ADMIN_URL ?>programs.php" class="btn btn-outline">Cancel</a>
        </div>
      </div>
    </form>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// LIST
$rows = $pdo->query("SELECT * FROM programs ORDER BY display_order, id")->fetchAll();
?>

<div class="page-head">
  <div><h2>Programs</h2><p class="sub">Manage the 8 core programs displayed on your website.</p></div>
  <a href="?action=add" class="btn btn-primary">➕ Add Program</a>
</div>

<div class="card">
  <div class="card-body">
    <?php if (!$rows): ?>
      <div class="empty"><div class="ico">📚</div><h3>No programs yet</h3><a href="?action=add" class="btn btn-primary">Add your first program</a></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Image</th><th>Title</th><th>Subtitle</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <?php if ($r['image']): ?>
              <img src="<?= BASE_URL . e($r['image']) ?>" class="thumb-sm" alt="">
            <?php else: ?>
              <div class="thumb-sm" style="display:grid;place-items:center;font-size:1.4rem;background:linear-gradient(135deg,#2563eb,#f4a261);color:#fff"><?= e($r['icon']) ?></div>
            <?php endif; ?>
          </td>
          <td><strong><?= e($r['title']) ?></strong><br><small style="color:#888"><?= e($r['slug']) ?></small></td>
          <td><?= e($r['subtitle']) ?></td>
          <td><?= (int)$r['display_order'] ?></td>
          <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td>
            <div class="actions">
              <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
              <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn-sm btn-del">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
