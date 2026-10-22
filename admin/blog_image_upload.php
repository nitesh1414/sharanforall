<?php
/**
 * Blog content image upload — AJAX endpoint used by the WYSIWYG editor
 * (admin/includes/editor.php) to insert images into a blog post.
 *
 * POST multipart/form-data:  image=<file>, csrf=<token>
 * Response JSON:  {"ok":true,"url":"https://…/uploads/blog/xxx.jpg"}
 *           or:   {"ok":false,"error":"…"}
 */
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['ok' => $ok], $extra, $ok ? [] : ['error' => $msg]));
    exit;
}

// Must be a logged-in admin
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    json_out(false, 'Unauthorized. Please log in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(false, 'POST required.');
}

if (!csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(403);
    json_out(false, 'Invalid CSRF token.');
}

if (empty($_FILES['image']) || empty($_FILES['image']['name'])) {
    json_out(false, 'No file uploaded.');
}
if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(false, 'Upload error — please try again.');
}

// Verify it really is an image (extension checks alone are not enough)
$tmp = $_FILES['image']['tmp_name'];
if (!is_uploaded_file($tmp) || !@getimagesize($tmp)) {
    json_out(false, 'The uploaded file is not a valid image.');
}

// Saves under uploads/blog/ — validates extension + 5 MB limit
$path = upload_image('image', 'blog', 1600, 1600);
if ($path === false) {
    json_out(false, 'Invalid image. Use JPG/PNG/WebP/GIF under 5 MB.');
}

json_out(true, 'ok', ['url' => BASE_URL . $path]);
