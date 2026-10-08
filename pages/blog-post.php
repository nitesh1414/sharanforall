<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . BASE_URL . 'pages/blog.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status='published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

require_once __DIR__ . '/../includes/i18n.php';

if (!$post) {
    http_response_code(404);
    $page_title = t('post_not_found');
    $current_page = 'blog';
    require __DIR__ . '/../includes/public_header.php';
    echo '<section style="padding:5rem 1rem;text-align:center"><h2>📭 ' . e(t('post_not_found')) . '</h2><a href="' . BASE_URL . 'pages/blog.php" class="btn btn-primary">← ' . e(t('nav_blog')) . '</a></section>';
    require __DIR__ . '/../includes/public_footer.php';
    exit;
}

// Related posts
$related = $pdo->prepare("SELECT id,title,slug,image,published_at FROM blog_posts WHERE status='published' AND id != ? ORDER BY published_at DESC LIMIT 4");
$related->execute([$post['id']]);
$related = $related->fetchAll();

$tags = $post['tags'] ? array_filter(array_map('trim', explode(',', $post['tags']))) : [];

$page_title = tr_field($post, 'title');
$page_desc  = tr_field($post, 'excerpt') ?: mb_strimwidth(strip_tags(tr_field($post, 'content')), 0, 160, '...');
$current_page = 'blog';
$has_hero = !empty($post['image']) && file_exists(__DIR__ . '/../' . $post['image']);
$extra_head = '<style>
  .post-hero{position:relative;height:55vh;min-height:380px;color:#fff;display:flex;align-items:flex-end;
    background:linear-gradient(rgba(29,78,216,.4),rgba(26,46,53,.9))' . ($has_hero ? ',url(\''.BASE_URL.e($post['image']).'\') center/cover' : ',linear-gradient(135deg,#2563eb,#1d4ed8)') . '}
  .post-hero .container{padding-bottom:3rem}
  .post-hero .tag{background:var(--accent);color:#fff}
  .post-hero h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:800;line-height:1.2;margin:.6rem 0;max-width:900px}
  .post-meta-hero{display:flex;gap:1.5rem;font-size:.9rem;opacity:.95;flex-wrap:wrap}
  .article-wrap{display:grid;grid-template-columns:2.5fr 1fr;gap:3rem;max-width:1100px;margin:0 auto}
  .article{background:#fff;padding:3rem;border-radius:14px;box-shadow:var(--shadow);margin-top:-5rem;position:relative;z-index:2}
  .article p{color:#444;margin-bottom:1.2rem;font-size:1.02rem;line-height:1.8}
  .article h2{color:var(--primary-dark);margin:2rem 0 1rem;font-size:1.5rem}
  .article h3{color:var(--dark);margin:1.5rem 0 .8rem;font-size:1.2rem}
  .article blockquote{border-left:4px solid var(--accent);padding:1rem 1.5rem;background:#fffaf0;font-style:italic;color:#5b4a2c;margin:1.5rem 0;border-radius:6px;font-size:1.05rem}
  .article img{border-radius:10px;margin:1.5rem 0;max-width:100%}
  .article ul{margin:1rem 0 1.5rem 1.5rem;color:#444}
  .article ul li{list-style:disc;margin-bottom:.5rem;line-height:1.7}
  .article strong{color:var(--dark)}
  .share{padding:1.5rem;background:#f5f5f0;border-radius:10px;margin-top:2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
  .share-btns{display:flex;gap:.5rem}
  .share-btns a{width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;transition:.2s;font-weight:bold;text-decoration:none}
  .share-btns a:hover{background:var(--accent);transform:translateY(-2px)}
  .author-box{background:linear-gradient(135deg,#fafafa,#fff);padding:1.8rem;border-radius:12px;margin-top:2rem;display:flex;gap:1.2rem;align-items:center;border:1px solid #eee}
  .author-avatar{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.4rem;flex-shrink:0}
  .author-box h4{color:var(--dark);margin-bottom:.2rem}
  .author-box span{color:var(--primary);font-size:.85rem;font-weight:600}
  .sidebar{align-self:start;margin-top:0}
  .widget{background:#fff;padding:1.6rem;border-radius:12px;box-shadow:var(--shadow);margin-bottom:1.5rem}
  .widget h4{color:var(--primary-dark);font-size:1.1rem;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .recent-post{display:flex;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #f0f0f0;text-decoration:none;color:inherit}
  .recent-post:last-child{border-bottom:none}
  .recent-post .thumb{width:60px;height:60px;border-radius:8px;background-size:cover;background-position:center;flex-shrink:0;display:grid;place-items:center;color:#fff;font-size:1.4rem}
  .recent-post h5{font-size:.9rem;color:var(--dark);margin-bottom:.2rem;line-height:1.3}
  .recent-post small{color:var(--gray);font-size:.78rem}
  @media(max-width:880px){.article-wrap{grid-template-columns:1fr;padding:0 1rem}.article{padding:2rem 1.5rem;margin-top:-3rem}}
</style>';

require __DIR__ . '/../includes/public_header.php';

$url_full = (isset($_SERVER['HTTPS'])?'https':'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>

<section class="post-hero">
  <div class="container">
    <?php if ($post['is_featured']): ?><span class="tag"><?= e(t('featured_story')) ?></span><?php elseif ($post['category']): ?><span class="tag"><?= e($post['category']) ?></span><?php endif; ?>
    <h1><?= e(tr_field($post, 'title')) ?></h1>
    <div class="post-meta-hero">
      <span>📅 <?= date('M j, Y', strtotime($post['published_at'])) ?></span>
      <span>✍️ <?= e($post['author']) ?></span>
      <?php if ($post['category']): ?><span>📂 <?= e($post['category']) ?></span><?php endif; ?>
      <span>⏱ <?= e($post['read_time']) ?></span>
    </div>
  </div>
</section>

<section class="sec-cream" style="padding:0 1rem 5rem">
  <div class="article-wrap">
    <article class="article">
      <?= tr_field($post, 'content')  /* admin-provided HTML, language-aware */ ?>

      <div class="share">
        <strong><?= e(t('share_story')) ?></strong>
        <div class="share-btns">
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($url_full) ?>" target="_blank" title="Facebook">f</a>
          <a href="https://twitter.com/intent/tweet?url=<?= urlencode($url_full) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" title="Twitter">✕</a>
          <a href="https://wa.me/?text=<?= urlencode($post['title'].' '.$url_full) ?>" target="_blank" title="WhatsApp">w</a>
          <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($url_full) ?>" target="_blank" title="LinkedIn">in</a>
          <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($url_full) ?>" title="Email">✉</a>
        </div>
      </div>

      <?php if ($tags): ?>
        <div style="margin-top:1.5rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
          <strong>Tags:</strong>
          <?php foreach ($tags as $t): ?>
            <a href="<?= BASE_URL ?>pages/blog.php?q=<?= urlencode($t) ?>" style="padding:.3rem .8rem;background:#f5f5f0;border-radius:50px;font-size:.78rem;color:#555;text-decoration:none"><?= e($t) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="author-box">
        <div class="author-avatar"><?= strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$post['author']),0,2)) ?></div>
        <div>
          <h4><?= e($post['author']) ?></h4>
          <span>Author at Sharan Foundation</span>
        </div>
      </div>
    </article>

    <aside class="sidebar">
      <?php if ($related): ?>
      <div class="widget">
        <h4>📖 <?= e(t('read_more_posts')) ?></h4>
        <?php foreach ($related as $r):
          $has_img = !empty($r['image']) && file_exists(__DIR__ . '/../' . $r['image']); ?>
        <a href="<?= BASE_URL ?>pages/blog-post.php?slug=<?= e($r['slug']) ?>" class="recent-post">
          <div class="thumb" style="<?= $has_img ? "background-image:url('".BASE_URL.e($r['image'])."')" : 'background:linear-gradient(135deg,#2563eb,#f4a261)' ?>"><?= $has_img?'':'📰' ?></div>
          <div><h5><?= e(mb_strimwidth(tr_field($r, 'title'),0,55,'...')) ?></h5><small><?= date('M j, Y', strtotime($r['published_at'])) ?></small></div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form action="<?= BASE_URL ?>api/submit_newsletter.php" method="post" class="widget" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;text-align:center">
        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
        <h4 style="color:#fff;border-color:#fff">💌 Newsletter</h4>
        <p style="font-size:.9rem;margin-bottom:1rem;opacity:.95">Get more stories in your inbox.</p>
        <input type="email" name="email" placeholder="Your email" required style="width:100%;padding:.6rem;border-radius:6px;border:none;margin-bottom:.6rem;color:#333">
        <button class="btn" style="background:#fff;color:var(--primary-dark);width:100%">Subscribe</button>
      </form>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
