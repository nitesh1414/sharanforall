<?php
$page_title = 'Donation Analytics';
require_once __DIR__ . '/includes/header.php';

// ============ DATA COLLECTION ============
// Convert all amounts to GBP-equivalent for trend comparison
// (~1 GBP = 100 INR approximation — adjust as needed for live FX)
$GBP_PER_INR = 0.01;

// ---- Top-line totals ----
$tot_completed     = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='completed'")->fetchColumn();
$tot_pending       = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE payment_status='pending'")->fetchColumn();
$tot_donors_unique = (int)$pdo->query("SELECT COUNT(DISTINCT email) FROM donations WHERE payment_status='completed'")->fetchColumn();
$tot_inr           = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='INR'")->fetchColumn();
$tot_gbp           = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='GBP'")->fetchColumn();
$tot_usd           = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='USD'")->fetchColumn();
$tot_in_gbp        = $tot_gbp + ($tot_inr * $GBP_PER_INR) + ($tot_usd * 0.78);
$avg_donation_gbp  = $tot_completed ? $tot_in_gbp / $tot_completed : 0;

// ---- Monthly trend (last 12 months) ----
$months = $pdo->query("
  SELECT DATE_FORMAT(submitted_at, '%Y-%m') as ym,
         COUNT(*) as cnt,
         SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR
                  WHEN currency='USD' THEN amount * 0.78
                  ELSE amount END) as gbp_amount
  FROM donations
  WHERE payment_status='completed'
    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
  GROUP BY ym
  ORDER BY ym
")->fetchAll();

// Fill in missing months with zeros
$labels = []; $count_data = []; $amount_data = [];
$start = new DateTime('first day of -11 months');
for ($i = 0; $i < 12; $i++) {
    $key = $start->format('Y-m');
    $labels[]      = $start->format('M Y');
    $row           = current(array_filter($months, fn($m) => $m['ym'] === $key));
    $count_data[]  = $row ? (int)$row['cnt'] : 0;
    $amount_data[] = $row ? round((float)$row['gbp_amount'], 2) : 0;
    $start->modify('+1 month');
}

// ---- Currency distribution ----
$currency_data = $pdo->query("
  SELECT currency, COUNT(*) as cnt, SUM(amount) as total
  FROM donations WHERE payment_status='completed'
  GROUP BY currency
")->fetchAll();

// ---- Donations by purpose ----
$purpose_data = $pdo->query("
  SELECT purpose, COUNT(*) as cnt,
         SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR
                  WHEN currency='USD' THEN amount * 0.78
                  ELSE amount END) as gbp_amount
  FROM donations WHERE payment_status='completed' AND purpose != ''
  GROUP BY purpose ORDER BY gbp_amount DESC LIMIT 10
")->fetchAll();

// ---- Payment methods ----
$method_data = $pdo->query("
  SELECT payment_method, COUNT(*) as cnt
  FROM donations WHERE payment_status='completed'
  GROUP BY payment_method ORDER BY cnt DESC
")->fetchAll();

// ---- Donation types ----
$type_data = $pdo->query("
  SELECT donation_type, COUNT(*) as cnt
  FROM donations WHERE payment_status='completed'
  GROUP BY donation_type
")->fetchAll();

// ---- Country distribution ----
$country_data = $pdo->query("
  SELECT country, COUNT(*) as cnt,
         SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR
                  WHEN currency='USD' THEN amount * 0.78
                  ELSE amount END) as gbp_amount
  FROM donations WHERE payment_status='completed' AND country != ''
  GROUP BY country ORDER BY gbp_amount DESC LIMIT 8
")->fetchAll();

// ---- Top donors (non-anonymous) ----
$top_donors = $pdo->query("
  SELECT donor_name, email, COUNT(*) as donation_count,
         SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR
                  WHEN currency='USD' THEN amount * 0.78
                  ELSE amount END) as total_gbp,
         MAX(submitted_at) as last_donation
  FROM donations
  WHERE payment_status='completed' AND is_anonymous=0
  GROUP BY email
  ORDER BY total_gbp DESC LIMIT 10
")->fetchAll();

// ---- Status breakdown ----
$status_data = $pdo->query("
  SELECT payment_status, COUNT(*) as cnt FROM donations GROUP BY payment_status
")->fetchAll();

// ---- This-month vs last-month comparison ----
$this_month = (float)$pdo->query("
  SELECT COALESCE(SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR WHEN currency='USD' THEN amount * 0.78 ELSE amount END),0)
  FROM donations WHERE payment_status='completed' AND DATE_FORMAT(submitted_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
")->fetchColumn();
$last_month = (float)$pdo->query("
  SELECT COALESCE(SUM(CASE WHEN currency='INR' THEN amount * $GBP_PER_INR WHEN currency='USD' THEN amount * 0.78 ELSE amount END),0)
  FROM donations WHERE payment_status='completed' AND DATE_FORMAT(submitted_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
")->fetchColumn();
$mom_change = $last_month > 0 ? round((($this_month - $last_month) / $last_month) * 100, 1) : 0;

?>

<div class="page-head">
  <div><h2>📊 Donation Analytics</h2><p class="sub">Insights and trends from all completed donations.</p></div>
  <div style="display:flex;gap:.5rem">
    <a href="<?= ADMIN_URL ?>donations.php" class="btn btn-outline">← Back to Donations</a>
    <a href="<?= ADMIN_URL ?>donations.php?export=1" class="btn btn-primary">📥 Export Data</a>
  </div>
</div>

<!-- KPI CARDS -->
<div class="stats">
  <div class="stat-card green">
    <div>
      <div class="label">Total Raised (GBP eq.)</div>
      <div class="value" style="font-size:1.8rem">£<?= number_format($tot_in_gbp, 0) ?></div>
      <div style="font-size:.78rem;color:<?= $mom_change >= 0 ? '#2563eb' : '#c0392b' ?>;margin-top:.3rem">
        <?= $mom_change >= 0 ? '▲' : '▼' ?> <?= abs($mom_change) ?>% vs last month
      </div>
    </div>
    <div class="ico">💰</div>
  </div>
  <div class="stat-card orange"><div><div class="label">Completed</div><div class="value"><?= number_format($tot_completed) ?></div></div><div class="ico">✓</div></div>
  <div class="stat-card blue"><div><div class="label">Unique Donors</div><div class="value"><?= number_format($tot_donors_unique) ?></div></div><div class="ico">👥</div></div>
  <div class="stat-card purple"><div><div class="label">Avg Donation</div><div class="value" style="font-size:1.6rem">£<?= number_format($avg_donation_gbp, 0) ?></div></div><div class="ico">📊</div></div>
  <div class="stat-card gold"><div><div class="label">India Total</div><div class="value" style="font-size:1.4rem">₹<?= number_format($tot_inr, 0) ?></div></div><div class="ico">🇮🇳</div></div>
  <div class="stat-card red"><div><div class="label">UK Total</div><div class="value" style="font-size:1.4rem">£<?= number_format($tot_gbp, 0) ?></div></div><div class="ico">🇬🇧</div></div>
</div>

<!-- LINE CHART: Monthly Trend -->
<div class="card">
  <div class="card-head"><h3>📈 Monthly Donation Trend (Last 12 Months)</h3></div>
  <div class="card-body"><canvas id="trendChart" height="100"></canvas></div>
</div>

<!-- TWO COLUMN CHARTS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

  <!-- Donations by Purpose (Horizontal Bar) -->
  <div class="card">
    <div class="card-head"><h3>🎯 Top Causes by Amount Raised</h3></div>
    <div class="card-body"><canvas id="purposeChart" height="220"></canvas></div>
  </div>

  <!-- Currency Distribution (Doughnut) -->
  <div class="card">
    <div class="card-head"><h3>💱 By Currency</h3></div>
    <div class="card-body"><canvas id="currencyChart" height="220"></canvas></div>
  </div>

  <!-- Payment Methods (Bar) -->
  <div class="card">
    <div class="card-head"><h3>💳 Payment Methods</h3></div>
    <div class="card-body"><canvas id="methodChart" height="220"></canvas></div>
  </div>

  <!-- Donation Type (Pie) -->
  <div class="card">
    <div class="card-head"><h3>🔁 Donation Type</h3></div>
    <div class="card-body"><canvas id="typeChart" height="220"></canvas></div>
  </div>

  <!-- Country (Bar) -->
  <div class="card">
    <div class="card-head"><h3>🌍 Top Countries</h3></div>
    <div class="card-body"><canvas id="countryChart" height="220"></canvas></div>
  </div>

  <!-- Status (Doughnut) -->
  <div class="card">
    <div class="card-head"><h3>📋 Status Breakdown</h3></div>
    <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
  </div>
</div>

<!-- TOP DONORS TABLE -->
<div class="card">
  <div class="card-head"><h3>🏆 Top 10 Donors (All-time)</h3></div>
  <div class="card-body">
    <?php if (!$top_donors): ?>
      <div class="empty"><div class="ico">🤝</div><h3>No donor data yet</h3></div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Donor</th><th>Email</th><th># Donations</th><th>Total (GBP eq.)</th><th>Last Donation</th></tr></thead>
      <tbody>
      <?php foreach ($top_donors as $i => $td): ?>
        <tr>
          <td><strong style="color:#d4a017;font-size:1.1rem"><?= $i + 1 ?></strong></td>
          <td><strong><?= e($td['donor_name']) ?></strong></td>
          <td><small><?= e($td['email']) ?></small></td>
          <td><span class="status-badge status-active"><?= (int)$td['donation_count'] ?></span></td>
          <td><strong style="color:var(--primary-dark);font-size:1.05rem">£<?= number_format($td['total_gbp'], 0) ?></strong></td>
          <td><small><?= time_ago($td['last_donation']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== CHART.JS ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Common chart options
const brandColors = ['#2563eb','#f4a261','#e76f51','#d4a017','#1a4d6e','#9b59b6','#3498db','#16a085','#c0392b','#34495e'];
Chart.defaults.font.family = "'Segoe UI', Tahoma, sans-serif";
Chart.defaults.color = '#555';

// 1. Monthly Trend (Line + Bar combo)
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      {
        type:'line', label:'Amount Raised (£)', data: <?= json_encode($amount_data) ?>,
        borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.35, yAxisID:'y', fill:true, pointRadius:4, pointBackgroundColor:'#2563eb'
      },
      {
        type:'bar', label:'# of Donations', data: <?= json_encode($count_data) ?>,
        backgroundColor:'rgba(244,162,97,.7)', yAxisID:'y1', borderRadius:6
      }
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:true,
    scales: {
      y: { type:'linear', position:'left', title:{display:true, text:'Amount (£ equivalent)'} },
      y1:{ type:'linear', position:'right', title:{display:true, text:'Number of donations'}, grid:{drawOnChartArea:false} }
    },
    plugins:{ legend:{position:'top'} }
  }
});

// 2. Donations by Purpose (horizontal bar)
new Chart(document.getElementById('purposeChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_map(fn($p) => $p['purpose'], $purpose_data)) ?>,
    datasets:[{
      label:'Amount Raised (£)',
      data: <?= json_encode(array_map(fn($p) => round((float)$p['gbp_amount'], 2), $purpose_data)) ?>,
      backgroundColor: brandColors.slice(0, <?= count($purpose_data) ?>),
      borderRadius:6
    }]
  },
  options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{title:{display:true,text:'£ equivalent'}}} }
});

// 3. Currency Distribution
new Chart(document.getElementById('currencyChart'), {
  type:'doughnut',
  data:{
    labels: <?= json_encode(array_map(fn($c) => $c['currency'] . ' (' . (int)$c['cnt'] . ')', $currency_data)) ?>,
    datasets:[{
      data: <?= json_encode(array_map(fn($c) => (int)$c['cnt'], $currency_data)) ?>,
      backgroundColor:['#2563eb','#f4a261','#3498db']
    }]
  },
  options:{ plugins:{legend:{position:'bottom'}} }
});

// 4. Payment Methods
new Chart(document.getElementById('methodChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_map(fn($m) => strtoupper(str_replace('_',' ',$m['payment_method'])), $method_data)) ?>,
    datasets:[{
      label:'Donations',
      data: <?= json_encode(array_map(fn($m) => (int)$m['cnt'], $method_data)) ?>,
      backgroundColor:'#f4a261', borderRadius:6
    }]
  },
  options:{ plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
});

// 5. Donation Type
new Chart(document.getElementById('typeChart'), {
  type:'pie',
  data:{
    labels: <?= json_encode(array_map(fn($t) => ucfirst($t['donation_type']), $type_data)) ?>,
    datasets:[{
      data: <?= json_encode(array_map(fn($t) => (int)$t['cnt'], $type_data)) ?>,
      backgroundColor:['#2563eb','#f4a261','#9b59b6']
    }]
  },
  options:{ plugins:{legend:{position:'bottom'}} }
});

// 6. Country
new Chart(document.getElementById('countryChart'), {
  type:'bar',
  data:{
    labels: <?= json_encode(array_map(fn($c) => $c['country'], $country_data)) ?>,
    datasets:[{
      label:'Amount Raised (£)',
      data: <?= json_encode(array_map(fn($c) => round((float)$c['gbp_amount'], 2), $country_data)) ?>,
      backgroundColor: brandColors.slice(0, <?= count($country_data) ?>),
      borderRadius:6
    }]
  },
  options:{ plugins:{legend:{display:false}} }
});

// 7. Status Breakdown
new Chart(document.getElementById('statusChart'), {
  type:'doughnut',
  data:{
    labels: <?= json_encode(array_map(fn($s) => ucfirst($s['payment_status']), $status_data)) ?>,
    datasets:[{
      data: <?= json_encode(array_map(fn($s) => (int)$s['cnt'], $status_data)) ?>,
      backgroundColor: ['#2563eb','#f4a261','#e74c3c','#3498db']
    }]
  },
  options:{ plugins:{legend:{position:'bottom'}} }
});
</script>

<style>
@media(max-width:880px) { #trendChart{height:280px !important} div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important} }
</style>

<?php require __DIR__.'/includes/footer.php'; ?>
