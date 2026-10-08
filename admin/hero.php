<?php
$page_title = 'Hero Carousel';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// ===== DELETE =====
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM hero_slides WHERE id=?")->execute([$id]);
    flash_set('success','Hero slide deleted.');
    redirect(ADMIN_URL.'hero.php');
}

// ===== TOGGLE STATUS (quick action) =====
if ($action === 'toggle' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("UPDATE hero_slides SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
    flash_set('success','Slide status toggled.');
    redirect(ADMIN_URL.'hero.php');
}

// ===== REORDER (drag-drop ajax) =====
if ($action === 'reorder' && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $ids = $_POST['order'] ?? [];
    if (is_array($ids)) {
        $stmt = $pdo->prepare("UPDATE hero_slides SET display_order=? WHERE id=?");
        foreach ($ids as $order => $sid) {
            $stmt->execute([(int)$order + 1, (int)$sid]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]);
    exit;
}

// ===== SAVE (add / edit) =====
if (($action==='add' || $action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $media_type = in_array($_POST['media_type']??'image', ['image','video','youtube','vimeo']) ? $_POST['media_type'] : 'image';
    $data = [
        'title'          => trim($_POST['title']),
        'title_hi'       => trim($_POST['title_hi'] ?? ''),
        'subtitle'       => trim($_POST['subtitle'] ?? ''),
        'subtitle_hi'    => trim($_POST['subtitle_hi'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'description_hi' => trim($_POST['description_hi'] ?? ''),
        'cta_text'       => trim($_POST['cta_text'] ?? 'Learn More'),
        'cta_text_hi'    => trim($_POST['cta_text_hi'] ?? ''),
        'cta_link'       => trim($_POST['cta_link'] ?? '#'),
        'cta_text_2'     => trim($_POST['cta_text_2'] ?? ''),
        'cta_text_2_hi'  => trim($_POST['cta_text_2_hi'] ?? ''),
        'cta_link_2'     => trim($_POST['cta_link_2'] ?? ''),
        'overlay_color'  => in_array($_POST['overlay_color']??'blue', ['blue','dark','amber','minimal']) ? $_POST['overlay_color'] : 'blue',
        'text_position'  => in_array($_POST['text_position']??'left', ['left','center','right']) ? $_POST['text_position'] : 'left',
        'show_text'      => isset($_POST['show_text']) ? 1 : 0,
        'badge_text'     => trim($_POST['badge_text'] ?? ''),
        'display_order'  => (int)($_POST['display_order'] ?? 0),
        'status'         => $_POST['status'] ?? 'active',
        'media_type'     => $media_type,
        'video_url'      => trim($_POST['video_url'] ?? ''),
    ];

    // Image upload (used as: still bg OR video poster fallback)
    $img = upload_image('image', 'hero', 1920, 1080);
    // Video upload (only if media_type=video)
    $vid = upload_video('video_file', 'hero/videos', 50);

    if ($img === false) { flash_set('error','Image upload failed. Use JPG/PNG/WebP under 5MB.'); }
    elseif ($vid === false) { flash_set('error','Video upload failed. Use MP4/WebM under 50MB.'); }
    else {
        if ($img) $data['image'] = $img;
        if ($vid) { $data['video_file'] = $vid; $data['poster_image'] = $img ?: ($data['image'] ?? null); }

        // Validation per media type
        $existing_img = $action==='edit' ? ($pdo->query("SELECT image FROM hero_slides WHERE id=$id")->fetchColumn() ?: '') : '';
        $existing_vid = $action==='edit' ? ($pdo->query("SELECT video_file FROM hero_slides WHERE id=$id")->fetchColumn() ?: '') : '';

        $error = null;
        if ($media_type === 'image' && !$img && !$existing_img) $error = 'Please upload a background image.';
        if ($media_type === 'video' && !$vid && !$existing_vid)  $error = 'Please upload a video file (MP4/WebM).';
        if (in_array($media_type, ['youtube','vimeo']) && !$data['video_url']) $error = 'Please paste the YouTube/Vimeo URL or video ID.';

        if ($error) {
            flash_set('error', $error);
        } else {
            try {
                if ($action === 'add') {
                    $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
                    $pdo->prepare("INSERT INTO hero_slides ($cols) VALUES ($place)")->execute($data);
                    flash_saved_row('added', 'Hero slide', 'hero_slides', (int)$pdo->lastInsertId());
                } else {
                    $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
                    $data['id']=$id;
                    $pdo->prepare("UPDATE hero_slides SET $set WHERE id=:id")->execute($data);
                    flash_saved_row('updated', 'Hero slide', 'hero_slides', $id);
                }
            } catch (Throwable $ex) {
                $msg = $ex->getMessage();
                if (stripos($msg, 'show_text') !== false) {
                    $msg .= ' → The database is missing the new `show_text` column. Run: ALTER TABLE `hero_slides` ADD COLUMN IF NOT EXISTS `show_text` TINYINT(1) DEFAULT 1; (see sql/acts_foundation.sql)';
                }
                flash_set('error', 'Hero slide could not be saved: ' . $msg);
            }
            // Always return to the list — never a blank page.
            redirect(ADMIN_URL.'hero.php');
        }
    }
}

// ===== FORM (add / edit) =====
if ($action === 'add' || $action === 'edit') {
    $row = ['id'=>0,'title'=>'','title_hi'=>'','subtitle'=>'','subtitle_hi'=>'','description'=>'','description_hi'=>'',
            'image'=>'','cta_text'=>'Donate Now','cta_text_hi'=>'','cta_link'=>'pages/donate.php',
            'cta_text_2'=>'Learn More','cta_text_2_hi'=>'','cta_link_2'=>'pages/about.php',
            'overlay_color'=>'blue','text_position'=>'left','show_text'=>1,'badge_text'=>'','display_order'=>0,'status'=>'active',
            'media_type'=>'image','video_file'=>'','video_url'=>'','poster_image'=>''];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE id=?"); $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    }
?>

<div class="page-head">
  <div><h2><?= $action==='add' ? '➕ Add Hero Slide' : '✏️ Edit Hero Slide' ?></h2><p class="sub">Hero slides shown on the homepage carousel.</p></div>
  <a href="<?= ADMIN_URL ?>hero.php" class="btn btn-outline">← Back to List</a>
</div>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="card-body">

    <!-- LANGUAGE TABS -->
    <div class="lang-tabs">
      <button type="button" class="lang-tab active" data-lang="en">🇬🇧 English</button>
      <button type="button" class="lang-tab" data-lang="hi">🇮🇳 हिन्दी (Hindi) <span class="lang-note">optional</span></button>
    </div>

    <!-- ENGLISH PANEL -->
    <div class="lang-panel active" data-lang-panel="en">
      <div class="form-group">
        <label>Badge / Eyebrow Text <span style="color:#888;font-weight:400">(e.g. "✦ CHILD EDUCATION")</span></label>
        <input type="text" name="badge_text" value="<?= e($row['badge_text']) ?>" placeholder="✦ FEATURED">
      </div>
      <div class="form-group">
        <label>Title <span class="req">*</span></label>
        <input type="text" name="title" value="<?= e($row['title']) ?>" required placeholder="Bringing Hope Through Child Education">
      </div>
      <div class="form-group">
        <label>Subtitle</label>
        <input type="text" name="subtitle" value="<?= e($row['subtitle']) ?>" placeholder="Every Child Deserves a Future">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3"><?= e($row['description']) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Primary Button Text</label>
          <input type="text" name="cta_text" value="<?= e($row['cta_text']) ?>" placeholder="Donate Now">
        </div>
        <div class="form-group">
          <label>Primary Button Link</label>
          <input type="text" name="cta_link" value="<?= e($row['cta_link']) ?>" placeholder="pages/donate.php">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Secondary Button Text <span style="color:#888;font-weight:400">(optional)</span></label>
          <input type="text" name="cta_text_2" value="<?= e($row['cta_text_2']) ?>" placeholder="Learn More">
        </div>
        <div class="form-group">
          <label>Secondary Button Link</label>
          <input type="text" name="cta_link_2" value="<?= e($row['cta_link_2']) ?>" placeholder="pages/programs.php">
        </div>
      </div>
    </div>

    <!-- HINDI PANEL -->
    <div class="lang-panel" data-lang-panel="hi">
      <p style="background:#fef7e0;padding:.6rem 1rem;border-left:3px solid #d4a017;border-radius:6px;color:#5b4a2c;font-size:.88rem;margin-bottom:1rem">
        🇮🇳 Hindi translations are optional. If left blank, English will be shown to Hindi visitors.
      </p>
      <div class="form-group"><label>Title (Hindi)</label><input type="text" name="title_hi" value="<?= e($row['title_hi']) ?>"></div>
      <div class="form-group"><label>Subtitle (Hindi)</label><input type="text" name="subtitle_hi" value="<?= e($row['subtitle_hi']) ?>"></div>
      <div class="form-group"><label>Description (Hindi)</label><textarea name="description_hi" rows="3"><?= e($row['description_hi']) ?></textarea></div>
      <div class="form-row">
        <div class="form-group"><label>Primary Button Text (Hindi)</label><input type="text" name="cta_text_hi" value="<?= e($row['cta_text_hi']) ?>"></div>
        <div class="form-group"><label>Secondary Button Text (Hindi)</label><input type="text" name="cta_text_2_hi" value="<?= e($row['cta_text_2_hi']) ?>"></div>
      </div>
    </div>

    <hr style="margin:2rem 0;border:none;border-top:1px solid #eee">

    <h3 style="color:var(--primary-dark);font-size:1rem;margin-bottom:1rem">🎨 Appearance</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Overlay Style</label>
        <select name="overlay_color">
          <option value="blue"    <?= $row['overlay_color']==='blue'?'selected':'' ?>>🔵 Blue gradient (recommended)</option>
          <option value="dark"    <?= $row['overlay_color']==='dark'?'selected':'' ?>>⚫ Dark navy</option>
          <option value="amber"   <?= $row['overlay_color']==='amber'?'selected':'' ?>>🟠 Blue-to-amber gradient</option>
          <option value="minimal" <?= $row['overlay_color']==='minimal'?'selected':'' ?>>⚪ Minimal (light overlay)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Text Position</label>
        <select name="text_position">
          <option value="left"   <?= $row['text_position']==='left'?'selected':'' ?>>⬅ Left</option>
          <option value="center" <?= $row['text_position']==='center'?'selected':'' ?>>⬛ Centered</option>
          <option value="right"  <?= $row['text_position']==='right'?'selected':'' ?>>➡ Right</option>
        </select>
      </div>
    </div>
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;font-weight:600;background:#f9fafb;border:1px solid #eef0f3;border-radius:8px;padding:.75rem .9rem">
      <input type="checkbox" name="show_text" value="1" <?= (int)($row['show_text'] ?? 1)===1?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Show text over this image
      <span style="font-weight:400;color:#888;font-size:.82rem">(headline + buttons on the photo — uncheck to show only the image)</span>
    </label>
    <div class="form-row">
      <div class="form-group">
        <label>Display Order</label>
        <input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>" style="max-width:120px">
        <p class="help">Lower numbers appear first.</p>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="active"   <?= $row['status']==='active'?'selected':'' ?>>✓ Active (show on homepage)</option>
          <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>✗ Inactive (hide)</option>
        </select>
      </div>
    </div>

    <h3 style="color:var(--primary-dark);font-size:1rem;margin-bottom:1rem;margin-top:1rem">🎬 Background Media</h3>

    <!-- Media-type picker -->
    <div class="form-group">
      <label>Media Type</label>
      <div class="media-type-grid">
        <?php foreach (['image'=>['🖼️','Image','Static photo'],'video'=>['🎬','Uploaded Video','MP4/WebM'],'youtube'=>['▶️','YouTube','Paste URL'],'vimeo'=>['🅥','Vimeo','Paste URL']] as $mt => $info): ?>
          <label class="media-type-opt">
            <input type="radio" name="media_type" value="<?= $mt ?>" <?= $row['media_type']===$mt?'checked':'' ?>>
            <span class="media-card">
              <span class="ico"><?= $info[0] ?></span>
              <strong><?= $info[1] ?></strong>
              <small><?= $info[2] ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- IMAGE PANEL -->
    <div class="media-panel" data-media="image">
      <div class="form-group">
        <label>Image <span style="color:#888;font-weight:400">(also used as fallback poster for videos)</span></label>
        <input type="file" name="image" accept="image/*" data-rec-w="1920" data-rec-h="1080">
        <?= image_upload_help(1920, 1080) ?>
        <?php if ($row['image']): ?>
          <div class="current-image" style="margin-top:.5rem">
            <img src="<?= BASE_URL.e($row['image']) ?>" style="max-width:400px;border-radius:8px;box-shadow:var(--shadow)">
            <p class="help">Current — upload a new one to replace.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- VIDEO UPLOAD PANEL -->
    <div class="media-panel" data-media="video">
      <div style="background:#fef7e0;padding:.7rem 1rem;border-left:3px solid #d4a017;border-radius:6px;color:#5b4a2c;font-size:.85rem;margin-bottom:1rem">
        💡 <strong>Video tips:</strong> Use MP4 (H.264) for max compatibility. Compress to under <strong>15 MB</strong> for fast loading. Aim for 1920×1080, 10–20 seconds, no audio (videos auto-play muted).
      </div>
      <div class="form-group">
        <label>Upload Video <small style="color:#888;font-weight:400">(MP4 / WebM / Ogg / MOV — max 50 MB)</small></label>
        <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime">
        <?php if ($row['video_file']): ?>
          <div class="current-image" style="margin-top:.5rem">
            <video src="<?= BASE_URL.e($row['video_file']) ?>" style="max-width:400px;border-radius:8px;box-shadow:var(--shadow)" controls muted playsinline></video>
            <p class="help">Current video — upload a new one to replace.</p>
          </div>
        <?php endif; ?>
      </div>
      <p class="help">↑ Also upload an <strong>Image</strong> above as the poster shown before the video loads.</p>
    </div>

    <!-- YOUTUBE PANEL -->
    <div class="media-panel" data-media="youtube">
      <div class="form-group">
        <label>YouTube URL or Video ID</label>
        <input type="text" name="video_url" id="ytField" value="<?= $row['media_type']==='youtube' ? e($row['video_url']) : '' ?>" placeholder="https://www.youtube.com/watch?v=XXXXXXX or just XXXXXXX">
        <p class="help">Paste any YouTube URL — we'll auto-extract the video ID and embed it muted with autoplay loop.</p>
      </div>
      <p class="help">↑ Optionally upload an <strong>Image</strong> above as a fast-loading poster.</p>
    </div>

    <!-- VIMEO PANEL -->
    <div class="media-panel" data-media="vimeo">
      <div class="form-group">
        <label>Vimeo URL or Video ID</label>
        <input type="text" name="video_url" id="vmField" value="<?= $row['media_type']==='vimeo' ? e($row['video_url']) : '' ?>" placeholder="https://vimeo.com/12345678 or just 12345678">
        <p class="help">Paste any Vimeo URL — we'll auto-extract the video ID.</p>
      </div>
    </div>

    <style>
      .media-type-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.7rem}
      .media-type-opt{cursor:pointer;display:block}
      .media-type-opt input{position:absolute;opacity:0;pointer-events:none}
      .media-card{display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:1.2rem .8rem;background:#fff;border:2px solid #eee;border-radius:10px;text-align:center;transition:.2s;color:#555;font-weight:600}
      .media-card .ico{font-size:1.8rem}
      .media-card small{font-weight:400;color:#888;font-size:.75rem}
      .media-type-opt input:checked + .media-card{border-color:var(--primary);background:#eaf2ff;color:var(--primary-dark)}
      .media-panel{display:none;padding:1rem;background:#f9fafb;border-radius:10px;margin-top:1rem}
      .media-panel.active{display:block;animation:fadeIn .2s ease}
      @keyframes fadeIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
    </style>
    <script>
      (function(){
        function syncPanels(){
          const selected = document.querySelector('input[name="media_type"]:checked')?.value || 'image';
          document.querySelectorAll('.media-panel').forEach(p => {
            p.classList.toggle('active', p.dataset.media === selected);
          });
          // YouTube panel keeps its own field; Vimeo too. Reset name conflict:
          const ytIn = document.getElementById('ytField');
          const vmIn = document.getElementById('vmField');
          if (ytIn) ytIn.name = selected === 'youtube' ? 'video_url' : 'video_url_yt_ignore';
          if (vmIn) vmIn.name = selected === 'vimeo'   ? 'video_url' : 'video_url_vm_ignore';
        }
        document.querySelectorAll('input[name="media_type"]').forEach(r => r.addEventListener('change', syncPanels));
        syncPanels();
      })();
    </script>

    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add' ? '➕ Add Slide' : '💾 Save Changes' ?></button>
      <a href="<?= ADMIN_URL ?>hero.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; exit; }

// ===== LIST =====
$rows = $pdo->query("SELECT * FROM hero_slides ORDER BY display_order, id")->fetchAll();
$active_count = (int)$pdo->query("SELECT COUNT(*) FROM hero_slides WHERE status='active'")->fetchColumn();
?>

<div class="page-head">
  <div><h2>🎞️ Hero Carousel</h2><p class="sub">Manage the rotating slides on your homepage hero. <strong><?= $active_count ?></strong> active slide<?= $active_count===1?'':'s' ?>.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-outline">🌐 Preview Homepage</a>
    <a href="?action=add" class="btn btn-primary">➕ Add Slide</a>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3>Slides</h3>
    <small style="color:#888">💡 Drag rows by the ⠿ handle to reorder.</small>
  </div>
  <div class="card-body">
    <?php if (!$rows): ?>
      <div class="empty">
        <div class="ico">🎞️</div>
        <h3>No hero slides yet</h3>
        <p>Add your first slide to populate the homepage carousel.</p>
        <a href="?action=add" class="btn btn-primary" style="margin-top:1rem">➕ Add First Slide</a>
      </div>
    <?php else: ?>
    <div class="table-wrap"><table id="heroTable">
      <thead><tr>
        <th style="width:30px"></th>
        <th>#</th>
        <th>Image</th>
        <th>Title</th>
        <th>Badge</th>
        <th>Overlay</th>
        <th>Status</th>
        <th>Actions</th>
      </tr></thead>
      <tbody id="heroRows">
      <?php foreach ($rows as $r): ?>
        <tr data-id="<?= $r['id'] ?>">
          <td style="cursor:grab;text-align:center;color:#bbb;font-size:1.2rem;user-select:none" class="drag-handle">⠿</td>
          <td><strong style="color:#666"><?= (int)$r['display_order'] ?></strong></td>
          <td>
            <div style="position:relative;width:120px;height:60px">
              <?php if ($r['image']): ?>
                <img src="<?= BASE_URL.e($r['image']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,.1)">
              <?php else: ?>
                <div style="width:100%;height:100%;background:linear-gradient(135deg,#2563eb,#f4a261);border-radius:6px;display:grid;place-items:center;color:#fff">?</div>
              <?php endif; ?>
              <?php if (($r['media_type'] ?? 'image') !== 'image'): ?>
                <?php $mtIco=['video'=>'🎬','youtube'=>'▶️','vimeo'=>'🅥'][$r['media_type']] ?? '🖼️'; ?>
                <span style="position:absolute;bottom:3px;right:3px;background:rgba(0,0,0,.75);color:#fff;font-size:.7rem;padding:.1rem .4rem;border-radius:50px;line-height:1.4"><?= $mtIco ?> <?= ucfirst($r['media_type']) ?></span>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <strong><?= e($r['title']) ?></strong>
            <?php if ($r['subtitle']): ?><br><small style="color:#888"><?= e($r['subtitle']) ?></small><?php endif; ?>
            <?php if ((int)($r['show_text'] ?? 1) !== 1): ?>
              <br><span class="status-badge" style="background:#eef2f7;color:#5a6a80;font-size:.72rem">🚫 No text on slide</span>
            <?php endif; ?>
          </td>
          <td><?php if ($r['badge_text']): ?><span class="status-badge" style="background:#fef7e0;color:#5b4a2c"><?= e($r['badge_text']) ?></span><?php endif; ?></td>
          <td>
            <?php
              $colors = ['blue'=>['#2563eb','🔵 Blue'],'dark'=>['#0d2940','⚫ Dark'],'amber'=>['#f4a261','🟠 Amber'],'minimal'=>['#ccc','⚪ Min']];
              $oc = $colors[$r['overlay_color']] ?? ['#999', $r['overlay_color']];
            ?>
            <span style="display:inline-block;width:14px;height:14px;background:<?= $oc[0] ?>;border-radius:50%;vertical-align:middle;margin-right:5px"></span><?= $oc[1] ?>
          </td>
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
    <?php endif; ?>
  </div>
</div>

<script>
// Drag-to-reorder using HTML5 drag-and-drop
(function(){
  const tbody = document.getElementById('heroRows');
  if (!tbody) return;
  let dragRow = null;
  tbody.querySelectorAll('tr').forEach(tr => {
    const handle = tr.querySelector('.drag-handle');
    if (!handle) return;
    handle.addEventListener('mousedown', () => tr.setAttribute('draggable', 'true'));
    tr.addEventListener('dragstart', e => {
      dragRow = tr;
      tr.style.opacity = '0.4';
    });
    tr.addEventListener('dragend', () => {
      if (dragRow) dragRow.style.opacity = '1';
      dragRow = null;
      tr.removeAttribute('draggable');
      saveOrder();
    });
    tr.addEventListener('dragover', e => {
      e.preventDefault();
      if (!dragRow || dragRow === tr) return;
      const rect = tr.getBoundingClientRect();
      const after = (e.clientY - rect.top) > rect.height/2;
      tbody.insertBefore(dragRow, after ? tr.nextSibling : tr);
    });
  });
  function saveOrder() {
    const ids = [...tbody.querySelectorAll('tr')].map(tr => tr.dataset.id);
    const fd = new FormData();
    fd.append('csrf', '<?= e(csrf_token()) ?>');
    ids.forEach(id => fd.append('order[]', id));
    fetch('?action=reorder', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(() => location.reload());
  }
})();
</script>

<?php require __DIR__.'/includes/footer.php'; ?>
