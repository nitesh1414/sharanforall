<?php
$page_title = 'Subscribers';
require_once __DIR__ . '/includes/header.php';

if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $pdo->prepare("DELETE FROM subscribers WHERE id=?")->execute([(int)$_GET['delete']]);
    flash_set('success','Subscriber removed.'); redirect(ADMIN_URL.'subscribers.php');
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email','Status','Subscribed At']);
    foreach ($pdo->query("SELECT email,status,subscribed_at FROM subscribers ORDER BY subscribed_at DESC") as $r) {
        fputcsv($out, [$r['email'],$r['status'],$r['subscribed_at']]);
    }
    fclose($out); exit;
}

$rows = $pdo->query("SELECT * FROM subscribers ORDER BY subscribed_at DESC")->fetchAll();
?>
<div class="page-head"><div><h2>Newsletter Subscribers</h2><p class="sub">Email addresses collected via newsletter forms.</p></div>
  <a href="?export=1" class="btn btn-primary">📥 Export CSV</a>
</div>
<div class="card"><div class="card-body">
  <?php if(!$rows): ?>
    <div class="empty"><div class="ico">📧</div><h3>No subscribers yet</h3></div>
  <?php else: ?>
  <p style="margin-bottom:1rem;color:#666"><strong><?= count($rows) ?></strong> total subscribers</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Email</th><th>Status</th><th>Subscribed</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['email']) ?></strong></td>
        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><?= time_ago($r['subscribed_at']) ?></td>
        <td><form method="post" action="?delete=<?= $r['id'] ?>" class="del-form" style="display:inline"><?= csrf_field() ?><button class="btn-sm btn-del">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
