<?php
$page_title = 'Languages & Translation';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/i18n.php';   // gt_language_catalog(), site_language_codes()

/* Column-missing hint (settings.site_languages added by migration) */
function lang_col_hint() {
    return ' → The <code>site_languages</code> column is missing from the <code>settings</code> table. Run: <code>ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `site_languages` VARCHAR(255) DEFAULT \'en,hi,fi\';</code> (or re-run <code>sql/acts_foundation.sql</code>).';
}

$catalog = gt_language_catalog();

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $picked = $_POST['codes'] ?? [];
    $codes  = [];
    foreach ((array)$picked as $c) {
        if (isset($catalog[$c]) && !in_array($c, $codes, true)) $codes[] = $c;
    }
    // English is the page language — always keep it available
    if (!in_array('en', $codes, true)) array_unshift($codes, 'en');
    $csv = implode(',', $codes);
    try {
        $pdo->prepare("UPDATE settings SET site_languages = ? WHERE id=1")->execute([$csv]);
        $names = array_map(fn($c) => $catalog[$c][0], $codes);
        flash_saved('updated', 'Languages & Translation', [
            'enabled_languages' => implode(', ', $names),
            'language_codes'    => $csv,
            'count'             => count($codes),
        ]);
    } catch (Throwable $ex) {
        flash_set('error', 'Could not save languages: ' . $ex->getMessage() . lang_col_hint());
    }
    redirect(ADMIN_URL.'languages.php');
}

$enabled = site_language_codes();
?>
<div class="page-head">
  <div><h2>🌐 Languages &amp; Translation</h2><p class="sub">The website is translated live with <strong>Google Translate</strong> — visitors pick a language from the dropdown in the header.</p></div>
  <div style="display:flex;gap:.5rem"><a href="<?= BASE_URL ?>" target="_blank" class="btn btn-outline">🌐 View Website</a></div>
</div>

<div style="background:#eaf2ff;border-left:4px solid #2563eb;padding:1rem 1.2rem;border-radius:6px;margin-bottom:1.5rem">
  <strong>✅ No manual translations needed anymore.</strong>
  <p style="margin:.4rem 0 0;color:#40506c">Content is entered <strong>once, in English</strong>. Google Translate converts every page automatically for visitors in the languages you enable below — you no longer need to type Hindi or other translations in content forms.</p>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>Languages offered in the translator dropdown</h3><small style="color:#888">English (the original page language) is always available.</small></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.6rem">
      <?php foreach ($catalog as $code => $info): ?>
        <label style="display:flex;align-items:center;gap:.6rem;border:1px solid #e6eaee;border-radius:8px;padding:.6rem .8rem;cursor:pointer;background:#fff;<?= $code==='en' ? 'opacity:.75' : '' ?>">
          <input type="checkbox" name="codes[]" value="<?= e($code) ?>" <?= in_array($code,$enabled,true)?'checked':'' ?> <?= $code==='en' ? 'disabled' : '' ?> style="width:17px;height:17px;accent-color:var(--primary)">
          <span style="font-size:1.1rem"><?= $info[2] ?></span>
          <span style="font-weight:600;font-size:.92rem"><?= e($info[1]) ?></span>
          <small style="color:#888;margin-left:auto"><?= e($code) ?></small>
        </label>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="codes[]" value="en">
    <p class="help" style="margin-top:.8rem">Tick the languages you want visitors to be able to switch to. Currently enabled: <strong><?= implode(', ', array_map(fn($c)=>$catalog[$c][0], $enabled)) ?></strong>.</p>
  </div>
  <div class="card-body">
    <div class="form-actions">
      <button class="btn btn-primary">💾 Save Languages</button>
    </div>
  </div>
</form>

<?php require __DIR__.'/includes/footer.php'; ?>
