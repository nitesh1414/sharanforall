<?php
$page_title = 'Blog Posts';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM blog_posts WHERE id=?")->execute([$id]);
    flash_set('success','Blog post deleted.');
    redirect(ADMIN_URL.'blog.php');
}

if (($action==='add'||$action==='edit') && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $data = [
        'title'=>trim($_POST['title']),
        'slug'=>trim($_POST['slug']) ?: slugify($_POST['title']),
        'category'=>trim($_POST['category']),
        'excerpt'=>trim($_POST['excerpt']),
        'content'=>$_POST['content'],
        'author'=>trim($_POST['author']),
        'read_time'=>trim($_POST['read_time']),
        'tags'=>trim($_POST['tags']),
        'is_featured'=>isset($_POST['is_featured'])?1:0,
        'status'=>$_POST['status'],
        'published_at'=>$_POST['published_at'] ?: date('Y-m-d'),
    ];
    $image_path = upload_image('image','blog');
    if ($image_path===false) { flash_set('error','Image upload failed.'); }
    else {
        if ($image_path) $data['image']=$image_path;
        if ($action==='add') {
            $cols=implode(',',array_keys($data)); $place=':'.implode(',:',array_keys($data));
            $pdo->prepare("INSERT INTO blog_posts ($cols) VALUES ($place)")->execute($data);
            flash_saved_row('added', 'Blog post', 'blog_posts', (int)$pdo->lastInsertId());
        } else {
            $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data)));
            $data['id']=$id;
            $pdo->prepare("UPDATE blog_posts SET $set WHERE id=:id")->execute($data);
            flash_saved_row('updated', 'Blog post', 'blog_posts', $id);
        }
        redirect(ADMIN_URL.'blog.php');
    }
}

if ($action==='add'||$action==='edit') {
    $row=['title'=>'','slug'=>'','category'=>'','excerpt'=>'','content'=>'','image'=>'','author'=>'Admin','read_time'=>'5 min read','tags'=>'','is_featured'=>0,'status'=>'published','published_at'=>date('Y-m-d')];
    if ($action==='edit' && $id) {
        $stmt=$pdo->prepare("SELECT * FROM blog_posts WHERE id=?"); $stmt->execute([$id]);
        $row=$stmt->fetch() ?: $row;
    }
?>
<div class="page-head"><div><h2><?= $action==='add'?'Add Blog Post':'Edit Blog Post' ?></h2></div><a href="<?= ADMIN_URL ?>blog.php" class="btn btn-outline">← Back</a></div>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="form-group"><label>Title <span class="req">*</span></label><input type="text" name="title" value="<?= e($row['title']) ?>" required></div>
    <div class="form-row">
      <div class="form-group"><label>Slug (URL)</label><input type="text" name="slug" value="<?= e($row['slug']) ?>" placeholder="auto-generated if blank"></div>
      <div class="form-group"><label>Category</label><input type="text" name="category" value="<?= e($row['category']) ?>" placeholder="Success Stories"></div>
    </div>
    <div class="form-group"><label>Excerpt (short preview)</label><textarea name="excerpt" rows="2"><?= e($row['excerpt']) ?></textarea></div>
    <div class="form-group"><label>Content <span class="req">*</span></label><textarea name="content" rows="14" required><?= e($row['content']) ?></textarea><p class="help">HTML allowed. Use &lt;p&gt;, &lt;h2&gt;, &lt;blockquote&gt;, &lt;ul&gt;, &lt;strong&gt;, etc.</p></div>
    <div class="form-row">
      <div class="form-group"><label>Author</label><input type="text" name="author" value="<?= e($row['author']) ?>"></div>
      <div class="form-group"><label>Read Time</label><input type="text" name="read_time" value="<?= e($row['read_time']) ?>" placeholder="5 min read"></div>
    </div>
    <div class="form-group"><label>Tags (comma separated)</label><input type="text" name="tags" value="<?= e($row['tags']) ?>" placeholder="Success Story, Education, Hope"></div>
    <div class="form-row">
      <div class="form-group"><label>Published Date</label><input type="date" name="published_at" value="<?= e($row['published_at']) ?>"></div>
      <div class="form-group"><label>Status</label><select name="status">
        <option value="published" <?= $row['status']==='published'?'selected':'' ?>>Published</option>
        <option value="draft" <?= $row['status']==='draft'?'selected':'' ?>>Draft</option>
      </select></div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="is_featured" value="1" <?= $row['is_featured']?'checked':'' ?>> Mark as Featured (shows at top of blog)</label></div>
    <div class="form-group"><label>Cover Image</label><input type="file" name="image" accept="image/*">
      <?php if($row['image']): ?><div class="current-image"><img src="<?= BASE_URL.e($row['image']) ?>"></div><?php endif; ?>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary"><?= $action==='add'?'➕ Add':'💾 Update' ?> Post</button>
      <a href="<?= ADMIN_URL ?>blog.php" class="btn btn-outline">Cancel</a>
    </div>
  </div>
</form>
<?php require __DIR__.'/includes/footer.php'; exit; }

$rows = $pdo->query("SELECT * FROM blog_posts ORDER BY is_featured DESC, published_at DESC, id DESC")->fetchAll();
?>
<div class="page-head"><div><h2>Blog Posts</h2><p class="sub">Stories, news and updates published on the Blog page.</p></div><a href="?action=add" class="btn btn-primary">➕ Add Post</a></div>
<div class="card"><div class="card-body">
  <?php if (!$rows): ?>
    <div class="empty"><div class="ico">📝</div><h3>No blog posts yet</h3><a href="?action=add" class="btn btn-primary">Write your first post</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Author</th><th>Published</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?php if($r['image']): ?><img src="<?= BASE_URL.e($r['image']) ?>" class="thumb-sm"><?php else: ?><div class="thumb-sm" style="background:#eee;display:grid;place-items:center">📝</div><?php endif; ?></td>
        <td><strong><?= e($r['title']) ?></strong><?php if($r['is_featured']): ?> <span class="status-badge" style="background:#fef7e0;color:#d4a017">★ Featured</span><?php endif; ?><br><small style="color:#888"><?= e($r['slug']) ?></small></td>
        <td><?= e($r['category']) ?></td>
        <td><?= e($r['author']) ?></td>
        <td><?= e($r['published_at']) ?></td>
        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><div class="actions">
          <a href="?action=edit&id=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
          <form method="post" action="?action=delete&id=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Delete</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
