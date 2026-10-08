<?php
$page_title = 'Carousel Settings';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'carousel_autoplay'     => isset($_POST['carousel_autoplay']) ? 1 : 0,
        'carousel_interval'     => max(2000, min(30000, (int)($_POST['carousel_interval'] ?? 6000))),
        'carousel_pause_hover'  => isset($_POST['carousel_pause_hover']) ? 1 : 0,
        'carousel_show_arrows'  => isset($_POST['carousel_show_arrows']) ? 1 : 0,
        'carousel_show_dots'    => isset($_POST['carousel_show_dots']) ? 1 : 0,
        'carousel_show_counter' => isset($_POST['carousel_show_counter']) ? 1 : 0,
        'carousel_transition'   => in_array($_POST['carousel_transition']??'fade', ['fade','slide','zoom']) ? $_POST['carousel_transition'] : 'fade',
        'carousel_video_audio'  => isset($_POST['carousel_video_audio']) ? 1 : 0,
        'carousel_show_text'    => isset($_POST['carousel_show_text']) ? 1 : 0,
    ];
    $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
    try {
        $pdo->prepare("UPDATE settings SET $set WHERE id=1")->execute($data);
        flash_saved('updated', 'Carousel settings', $data);
    } catch (Throwable $ex) {
        $msg = $ex->getMessage();
        if (stripos($msg, 'carousel_show_text') !== false) {
            $msg .= ' → The database is missing the new `carousel_show_text` column. Run: ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `carousel_show_text` TINYINT(1) DEFAULT 1; (see sql/acts_foundation.sql)';
        }
        flash_set('error', 'Carousel settings could not be saved: ' . $msg);
    }
    // Always return to the settings page — never a blank page.
    redirect(ADMIN_URL.'carousel_settings.php');
}

$s = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>

<div class="page-head">
  <div><h2>🎞️ Carousel Settings</h2><p class="sub">Configure how the homepage hero slider behaves.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= ADMIN_URL ?>hero.php" class="btn btn-outline">← Manage Slides</a>
    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-primary">🌐 Preview Homepage</a>
  </div>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>⏯ Autoplay</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;font-weight:600">
      <input type="checkbox" name="carousel_autoplay" value="1" <?= !empty($s['carousel_autoplay'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Auto-advance slides
    </label>

    <div class="form-row">
      <div class="form-group">
        <label>Interval between slides</label>
        <div style="display:flex;align-items:center;gap:1rem">
          <input type="range" name="carousel_interval" id="intervalRange" value="<?= (int)($s['carousel_interval'] ?? 6000) ?>" min="2000" max="15000" step="500" style="flex:1">
          <span id="intervalDisplay" style="font-weight:700;color:var(--primary);min-width:60px;text-align:right"><?= (int)($s['carousel_interval'] ?? 6000)/1000 ?>s</span>
        </div>
        <p class="help">Range: 2s – 15s (recommended: 5–8s for image slides, 10–15s for video).</p>
      </div>
      <div class="form-group">
        <label>Transition Effect</label>
        <select name="carousel_transition">
          <option value="fade"  <?= ($s['carousel_transition']??'fade')==='fade'?'selected':'' ?>>✨ Fade (smoothest, recommended)</option>
          <option value="slide" <?= ($s['carousel_transition']??'')==='slide'?'selected':'' ?>>➡ Slide (horizontal)</option>
          <option value="zoom"  <?= ($s['carousel_transition']??'')==='zoom'?'selected':'' ?>>🔍 Zoom (Ken Burns)</option>
        </select>
      </div>
    </div>

    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem">
      <input type="checkbox" name="carousel_pause_hover" value="1" <?= !empty($s['carousel_pause_hover'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Pause autoplay when hovered (desktop)
    </label>
  </div>

  <div class="card-head"><h3>📝 Slide Text</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem">
      <input type="checkbox" name="carousel_show_text" value="1" <?= !empty($s['carousel_show_text']) || !array_key_exists('carousel_show_text', $s) ? 'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Show text over hero images <span style="font-weight:400;color:#888">(headline + buttons on the photo)</span>
    </label>
    <p class="help">
      When OFF, every hero slide displays its image/video full-bleed with no text on top.
      You can also override this per slide from <strong>🎞️ Hero Carousel → Edit</strong>.
    </p>
  </div>

  <div class="card-head"><h3>🎛 UI Controls</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem">
      <input type="checkbox" name="carousel_show_arrows" value="1" <?= !empty($s['carousel_show_arrows'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Show prev / next arrows
    </label>
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem">
      <input type="checkbox" name="carousel_show_dots" value="1" <?= !empty($s['carousel_show_dots'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Show dot indicators at the bottom
    </label>
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem">
      <input type="checkbox" name="carousel_show_counter" value="1" <?= !empty($s['carousel_show_counter'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Show slide counter (1 / N) in top-right
    </label>
  </div>

  <div class="card-head"><h3>🎬 Video Slides</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem">
      <input type="checkbox" name="carousel_video_audio" value="1" <?= !empty($s['carousel_video_audio'])?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
      Unmute video audio
      <span style="background:#fdecea;color:#c0392b;padding:.1rem .5rem;border-radius:50px;font-size:.72rem;font-weight:600;margin-left:.5rem">⚠️ NOT recommended</span>
    </label>
    <p class="help">
      Most browsers <strong>block autoplay with sound</strong>. Leaving this OFF (muted) ensures video backgrounds always play automatically.
      Turning it ON means videos will need a user click to play (defeating the auto-loop hero effect).
    </p>
  </div>

  <div class="card-body">
    <div class="form-actions">
      <button class="btn btn-primary">💾 Save Settings</button>
      <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-outline">🌐 Preview Homepage</a>
    </div>
  </div>
</form>

<script>
  const range = document.getElementById('intervalRange');
  const disp = document.getElementById('intervalDisplay');
  range?.addEventListener('input', () => disp.textContent = (range.value/1000).toFixed(1) + 's');
</script>

<?php require __DIR__.'/includes/footer.php'; ?>
