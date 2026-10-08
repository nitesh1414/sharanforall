<?php
$page_title = 'Banners & Images';
require_once __DIR__ . '/includes/header.php';

/* Graceful message shown when the site_media table does not exist yet */
function sm_table_hint() {
    return ' → The <code>site_media</code> table is missing. Run the <strong>SITE MEDIA</strong> block from <code>sql/acts_foundation.sql</code> (or the whole file) in phpMyAdmin.';
}

try {
    $rows = $pdo->query("SELECT * FROM site_media ORDER BY FIELD(group_name,'banner','content'), label")->fetchAll();
    $table_ok = true;
} catch (Throwable $ex) {
    $rows = []; $table_ok = false;
}

/* ===== Handle actions: upload a replacement image / reset to default ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $slug = trim($_POST['slug'] ?? '');
    if ($slug !== '') {
        if (isset($_POST['reset']) && $_POST['reset'] === '1') {
            // Reset this key to its built-in default image
            try {
                $pdo->prepare("UPDATE site_media SET path = default_path WHERE slug = ?")->execute([$slug]);
                $row = $pdo->prepare("SELECT * FROM site_media WHERE slug = ?");
                $row->execute([$slug]);
                $row = $row->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>$slug];
                flash_saved('updated', ($row['label'] ?? $slug) . ' (restored default)', $row, $row['path'] ?? '');
            } catch (Throwable $ex) {
                flash_set('error', 'Could not reset image: ' . $ex->getMessage() . sm_table_hint());
            }
        } else {
            // Upload + replace
            $file_key = 'img_' . preg_replace('/[^A-Za-z0-9_]/', '_', $slug);
            $up = upload_image($file_key, 'media');
            if ($up === null) {
                flash_set('error', 'No file chosen for “' . e($slug) . '” — pick a JPG/PNG/WebP first.');
            } elseif ($up === false) {
                flash_set('error', 'Upload failed for “' . e($slug) . '”. Use JPG/PNG/WebP under 5 MB.');
            } else {
                try {
                    $pdo->prepare("UPDATE site_media SET path = ? WHERE slug = ?")->execute([$up, $slug]);
                    $stmt = $pdo->prepare("SELECT * FROM site_media WHERE slug = ?");
                    $stmt->execute([$slug]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['slug'=>$slug, 'path'=>$up];
                    flash_saved('updated', $row['label'] ?? $slug, $row, $up);
                } catch (Throwable $ex) {
                    flash_set('error', 'Image saved but DB update failed: ' . $ex->getMessage() . sm_table_hint());
                }
            }
        }
    }
    redirect(ADMIN_URL.'site_media.php');
}

$groups = [];
foreach ($rows as $r) {
    $g = $r['group_name'] === 'banner' ? 'banner' : 'content';
    $groups[$g][] = $r;
}
$group_titles = ['banner' => '🖼️ Page Hero Banners', 'content' => '📷 Homepage & Content Images'];
$group_hints  = ['banner' => 'The large background photo at the top of each page.', 'content' => 'Fixed images used inside homepage/about/donation sections.'];
?>
<div class="page-head">
  <div><h2>🎨 Banners &amp; Images</h2><p class="sub">Replace the site’s page-hero and section background images from one place. Click an image row to upload a new one, or restore its built-in default.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-outline">🌐 View Homepage</a>
  </div>
</div>

<?php if (!$table_ok): ?>
<div class="box" style="background:#fdecea;border-left:4px solid #c0392b;padding:1rem 1.2rem;border-radius:6px;margin-bottom:1.5rem">
  <strong style="color:#c0392b">Site media table missing.</strong>
  <p style="margin:.4rem 0 0;color:#5a3b37">Apply the <strong>SITE MEDIA</strong> block from <code>sql/acts_foundation.sql</code> in phpMyAdmin (or run the whole file), then reload this page. Until then the site keeps using its default images.</p>
</div>
<?php endif; ?>

<?php foreach (['banner','content'] as $g): ?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-head">
    <h3><?= $group_titles[$g] ?></h3>
    <small style="color:#888"><?= $group_hints[$g] ?></small>
  </div>
  <div class="card-body">
    <?php if (empty($groups[$g])): ?><p class="help">No images in this group.</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th style="width:220px">Image</th><th>Used for</th><th>Current file</th><th style="width:330px">Replace / Reset</th></tr></thead>
      <tbody>
      <?php foreach ($groups[$g] as $r):
          $cur = $r['path'];
          $preview = $cur !== '' ? BASE_URL . ltrim($cur, '/') : BASE_URL . 'images/hero.jpg';
          $is_default = ($cur === $r['default_path']);
          $file_key = 'img_' . preg_replace('/[^A-Za-z0-9_]/', '_', $r['slug']);
      ?>
        <tr>
          <td>
            <div style="width:200px;height:80px;border-radius:6px;overflow:hidden;border:1px solid #e6eaee;background:#f3f5f7">
              <img src="<?= e($preview) ?>" alt="<?= e($r['label']) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
            </div>
          </td>
          <td>
            <strong><?= e($r['label']) ?></strong><br>
            <code style="font-size:.75rem;color:#888"><?= e($r['slug']) ?></code>
            <?php if ($is_default): ?><br><span class="status-badge" style="background:#eef2f7;color:#5a6a80;margin-top:.3rem;display:inline-block">Default</span><?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:#666;word-break:break-all"><?= e($cur) ?></td>
          <td>
            <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:.4rem">
              <?= csrf_field() ?>
              <input type="hidden" name="slug" value="<?= e($r['slug']) ?>">
              <input type="file" name="<?= e($file_key) ?>" accept="image/*" data-rec-w="1920" data-rec-h="1080" style="font-size:.8rem;width:100%">
              <?= image_upload_help(1920, 1080) ?>
              <div style="display:flex;gap:.4rem">
                <button class="btn-sm btn-edit" style="border:none">📤 Replace</button>
                <?php if (!$is_default): ?>
                <button type="submit" name="reset" value="1" class="btn-sm" style="background:#eef2f7;color:#40506c;border:none">↩ Reset default</button>
                <?php endif; ?>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<p class="help">💡 Recommended upload size: <strong>1920 × 800 px</strong> or wider for banners (JPG/PNG/WebP, max 5 MB). Images are stored under <code>uploads/media/</code>.</p>

<?php require __DIR__.'/includes/footer.php'; ?>
