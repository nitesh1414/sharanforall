<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Pagination
$per_page = 8;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// Optional category / search filter
$cat_filter = trim($_GET['cat'] ?? '');
$q          = trim($_GET['q'] ?? '');
$where = ["status='published'"];
$params = [];
if ($cat_filter) { $where[] = "category = ?"; $params[] = $cat_filter; }
if ($q)          { $where[] = "(title LIKE ? OR excerpt LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

// Featured (only on page 1 with no filters)
$featured = null;
if ($page === 1 && !$cat_filter && !$q) {
    $featured = $pdo->query("SELECT * FROM blog_posts WHERE is_featured=1 AND status='published' ORDER BY published_at DESC LIMIT 1")->fetch();
}

// Total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts $where_sql" . ($featured ? " AND id != ?" : ''));
$count_params = $params;
if ($featured) $count_params[] = $featured['id'];
$count_stmt->execute($count_params);
$total = (int)$count_stmt->fetchColumn();
$pages = max(1, ceil($total / $per_page));

// Posts (excluding featured if any)
$sql = "SELECT * FROM blog_posts $where_sql" . ($featured ? " AND id != ?" : '') . " ORDER BY published_at DESC, id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($count_params);
$posts = $stmt->fetchAll();

// Sidebar data
$categories  = $pdo->query("SELECT category, COUNT(*) as cnt FROM blog_posts WHERE status='published' AND category!='' GROUP BY category ORDER BY cnt DESC")->fetchAll();
$recent      = $pdo->query("SELECT id,title,slug,image,published_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC, id DESC LIMIT 4")->fetchAll();
$all_tags    = [];
foreach ($pdo->query("SELECT tags FROM blog_posts WHERE status='published' AND tags!=''")->fetchAll(PDO::FETCH_COLUMN) as $t)
    foreach (explode(',', $t) as $tag) { $tag = trim($tag); if ($tag) $all_tags[$tag] = ($all_tags[$tag] ?? 0) + 1; }
arsort($all_tags); $all_tags = array_slice($all_tags, 0, 15, true);

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_blog');
$page_desc = 'Stories, updates and reflections from the Sharan Foundation team and the lives we serve.';
$current_page = 'blog';
$extra_head = '<style>
  .blog-wrap{display:grid;grid-template-columns:2.5fr 1fr;gap:3rem}
  .blog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.8rem}
  .post{background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden;transition:.3s;display:flex;flex-direction:column}
  .post:hover{transform:translateY(-6px)}
  .post-img{height:200px;background-size:cover;background-position:center;position:relative;display:grid;place-items:center;color:#fff;font-size:3rem}
  .post-cat{position:absolute;top:1rem;left:1rem;background:var(--accent);color:#fff;padding:.3rem .9rem;border-radius:50px;font-size:.75rem;font-weight:600}
  .post-body{padding:1.5rem;flex:1;display:flex;flex-direction:column}
  .post-meta{display:flex;gap:1rem;font-size:.82rem;color:var(--gray);margin-bottom:.7rem}
  .post-meta span::before{content:"•";margin-right:.6rem;color:var(--accent)}
  .post-meta span:first-child::before{display:none}
  .post h3{color:var(--primary-dark);font-size:1.15rem;margin-bottom:.6rem;line-height:1.4}
  .post p{color:var(--gray);font-size:.92rem;margin-bottom:1rem;flex:1}
  .post a.read{color:var(--primary);font-weight:600;font-size:.9rem;display:inline-flex;align-items:center;gap:.3rem;transition:.2s}
  .post a.read:hover{color:var(--accent);gap:.6rem}
  .featured{background:#fff;border-radius:16px;overflow:hidden;box-shadow:var(--shadow);margin-bottom:2.5rem;display:grid;grid-template-columns:1fr 1fr}
  .featured-img{min-height:340px;background-size:cover;background-position:center;display:grid;place-items:center;color:#fff;font-size:4rem}
  .featured-body{padding:2.5rem}
  .featured-body .tag{margin-bottom:.6rem}
  .featured-body h2{color:var(--dark);font-size:1.8rem;margin-bottom:.8rem;line-height:1.3}
  .featured-body .meta{display:flex;gap:1rem;font-size:.85rem;color:var(--gray);margin-bottom:1rem}
  .featured-body p{color:#555;margin-bottom:1.4rem}
  .sidebar{position:sticky;top:90px;align-self:start}
  .widget{background:#fff;padding:1.6rem;border-radius:12px;box-shadow:var(--shadow);margin-bottom:1.5rem}
  .widget h4{color:var(--primary-dark);font-size:1.1rem;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .widget input{width:100%;padding:.7rem;border:1px solid #ddd;border-radius:8px;margin-bottom:.6rem}
  .cat-list li{padding:.5rem 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;cursor:pointer;color:#555;transition:.2s}
  .cat-list li:hover{color:var(--primary);padding-left:.4rem}
  .cat-list li span{background:rgba(37,99,235,.1);color:var(--primary);padding:.1rem .6rem;border-radius:50px;font-size:.78rem;font-weight:600}
  .recent-post{display:flex;gap:.8rem;padding:.7rem 0;border-bottom:1px solid #f0f0f0}
  .recent-post:last-child{border-bottom:none}
  .recent-post .thumb{width:60px;height:60px;border-radius:8px;background-size:cover;background-position:center;flex-shrink:0;display:grid;place-items:center;color:#fff;font-size:1.5rem}
  .recent-post h5{font-size:.9rem;color:var(--dark);margin-bottom:.2rem;line-height:1.3}
  .recent-post small{color:var(--gray);font-size:.78rem}
  .tags-cloud{display:flex;flex-wrap:wrap;gap:.4rem}
  .tags-cloud a{padding:.3rem .8rem;background:#f5f5f0;border-radius:50px;font-size:.78rem;color:#555;transition:.2s}
  .tags-cloud a:hover{background:var(--primary);color:#fff}
  .pagination{text-align:center;margin-top:3rem}
  .pagination a{padding:.6rem 1rem;margin:0 .2rem;border:1px solid var(--primary);color:var(--primary);border-radius:6px;text-decoration:none;display:inline-block}
  .pagination a.active{background:var(--primary);color:#fff}
  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500;max-width:900px;margin:0 auto 1.5rem}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}
  @media(max-width:880px){.blog-wrap{grid-template-columns:1fr}.featured{grid-template-columns:1fr}.sidebar{position:static}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_blog', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_blog')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_blog')) ?><?= $cat_filter ? ' &nbsp;›&nbsp; ' . e($cat_filter) : '' ?></div>
  </div>
</section>

<section class="sec-cream">
  <div class="container">
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

    <div class="blog-wrap">
      <div>
        <?php if ($featured):
          $has_img = !empty($featured['image']) && file_exists(__DIR__ . '/../' . $featured['image']); ?>
          <article class="featured">
            <div class="featured-img" style="<?= $has_img ? "background-image:url('".BASE_URL.e($featured['image'])."')" : "background:linear-gradient(135deg,#2563eb,#f4a261)" ?>">
              <?php if (!$has_img): ?>📰<?php endif; ?>
            </div>
            <div class="featured-body">
              <span class="tag"><?= e(t('featured_story')) ?></span>
              <h2><?= e(tr_field($featured, 'title')) ?></h2>
              <div class="meta">
                <span>📅 <?= date('M j, Y', strtotime($featured['published_at'])) ?></span>
                <span>✍️ <?= e($featured['author']) ?></span>
                <?php if ($featured['category']): ?><span>📂 <?= e($featured['category']) ?></span><?php endif; ?>
              </div>
              <p><?= e(tr_field($featured, 'excerpt')) ?></p>
              <a href="<?= BASE_URL ?>pages/blog-post.php?slug=<?= e($featured['slug']) ?>" class="btn btn-primary"><?= e(t('read_full_story')) ?> →</a>
            </div>
          </article>
        <?php endif; ?>

        <?php if (!$posts && !$featured): ?>
          <p style="text-align:center;color:var(--gray);padding:3rem 0">No blog posts found<?= $q ? ' for "' . e($q) . '"' : '' ?>.</p>
        <?php else: ?>
        <div class="blog-grid">
          <?php $emojis = ['📰','📖','💝','🌟','🙏','✨','📚','💌'];
          foreach ($posts as $i => $p):
            $has_img = !empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']);
            $colors = ['linear-gradient(135deg,#5b3a1f,#d4a017)','linear-gradient(135deg,#1a4d6e,#5fa8c9)','linear-gradient(135deg,#2563eb,#f4a261)','linear-gradient(135deg,#e76f51,#f4a261)'];
          ?>
          <article class="post">
            <div class="post-img" style="<?= $has_img ? "background-image:url('".BASE_URL.e($p['image'])."')" : 'background:'.$colors[$i % count($colors)] ?>">
              <?php if (!$has_img): echo $emojis[$i % count($emojis)]; endif; ?>
              <?php if ($p['category']): ?><span class="post-cat"><?= e($p['category']) ?></span><?php endif; ?>
            </div>
            <div class="post-body">
              <div class="post-meta"><span>📅 <?= date('M j, Y', strtotime($p['published_at'])) ?></span><span><?= e($p['read_time']) ?></span></div>
              <h3><?= e(tr_field($p, 'title')) ?></h3>
              <p><?= e(tr_field($p, 'excerpt') ?: mb_strimwidth(strip_tags(tr_field($p, 'content')), 0, 130, '...')) ?></p>
              <a href="<?= BASE_URL ?>pages/blog-post.php?slug=<?= e($p['slug']) ?>" class="read"><?= e(t('btn_read_more')) ?> →</a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?><?= $cat_filter?'&cat='.urlencode($cat_filter):'' ?><?= $q?'&q='.urlencode($q):'' ?>">‹ Prev</a><?php endif; ?>
          <?php for ($i=1; $i<=$pages; $i++): ?>
            <a href="?page=<?= $i ?><?= $cat_filter?'&cat='.urlencode($cat_filter):'' ?><?= $q?'&q='.urlencode($q):'' ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?><?= $cat_filter?'&cat='.urlencode($cat_filter):'' ?><?= $q?'&q='.urlencode($q):'' ?>">Next ›</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- SIDEBAR -->
      <aside class="sidebar">
        <form method="get" class="widget">
          <h4>🔍 <?= e(t('search')) ?></h4>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search')) ?>...">
          <button class="btn btn-primary" style="width:100%"><?= e(t('search')) ?></button>
        </form>

        <?php if ($categories): ?>
        <div class="widget">
          <h4>📂 <?= e(t('categories')) ?></h4>
          <ul class="cat-list">
            <?php foreach ($categories as $c): ?>
              <li onclick="location.href='?cat=<?= urlencode($c['category']) ?>'"><?= e($c['category']) ?> <span><?= (int)$c['cnt'] ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($recent): ?>
        <div class="widget">
          <h4>🆕 <?= e(t('recent_posts')) ?></h4>
          <?php foreach ($recent as $r):
            $has_img = !empty($r['image']) && file_exists(__DIR__ . '/../' . $r['image']); ?>
          <a href="<?= BASE_URL ?>pages/blog-post.php?slug=<?= e($r['slug']) ?>" class="recent-post" style="text-decoration:none;color:inherit">
            <div class="thumb" style="<?= $has_img ? "background-image:url('".BASE_URL.e($r['image'])."')" : 'background:linear-gradient(135deg,#2563eb,#f4a261)' ?>"><?= $has_img?'':'📰' ?></div>
            <div><h5><?= e(mb_strimwidth(tr_field($r, 'title'), 0, 60, '...')) ?></h5><small><?= date('M j, Y', strtotime($r['published_at'])) ?></small></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($all_tags): ?>
        <div class="widget">
          <h4>🏷️ <?= e(t('tags')) ?></h4>
          <div class="tags-cloud">
            <?php foreach ($all_tags as $tag => $cnt): ?>
              <a href="?q=<?= urlencode($tag) ?>"><?= e($tag) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>api/submit_newsletter.php" method="post" class="widget" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;text-align:center">
          <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
          <h4 style="color:#fff;border-color:#fff">💌 <?= e(t('newsletter')) ?></h4>
          <p style="font-size:.9rem;margin-bottom:1rem;opacity:.95"><?= e(t('newsletter_text')) ?></p>
          <input type="email" name="email" placeholder="<?= e(t('email_address')) ?>" required style="color:#333">
          <button class="btn" style="background:#fff;color:var(--primary-dark);width:100%"><?= e(t('btn_subscribe')) ?></button>
        </form>
      </aside>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
