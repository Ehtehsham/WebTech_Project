<?php
require __DIR__.'/db.php';

$truck_id = isset($_GET['truck_id']) ? (int)$_GET['truck_id'] : 0;
if ($truck_id<=0) die('Missing truck_id');

$ts = $mysqli->prepare("SELECT reg_number, truck_type FROM trucks WHERE id=?");
$ts->bind_param('i',$truck_id);
$ts->execute();
$truck = $ts->get_result()->fetch_assoc();
if (!$truck) die('Truck not found');

$today = date('Y-m-d');
$default_from = date('Y-m-d', strtotime('-30 days'));

$range = $_GET['range'] ?? '30';
if ($range === 'all') {
  $from = '1970-01-01';
  $to   = $today;
} else {
  $from = $_GET['from'] ?? $default_from;
  $to   = $_GET['to']   ?? $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from = $default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $to   = $today;

$sql = "SELECT t.*, d.name AS driver_name, d.phone AS driver_phone
        FROM trips t
        JOIN drivers d ON d.id = t.driver_id
        WHERE t.truck_id = ? AND t.trip_date BETWEEN ? AND ?
        ORDER BY t.trip_date DESC";
$st = $mysqli->prepare($sql);
$st->bind_param('iss',$truck_id,$from,$to);
$st->execute();
$res = $st->get_result();

$totalRevenue=$totalExpense=$totalProfit=0;
$rows=[];
while($r=$res->fetch_assoc()){
  $expense = $r['driver_fee']+$r['fuel_cost']+$r['toll_cost']+$r['labor_cost']+$r['gate_cost']+$r['other_cost'];
  $profit  = $r['revenue_bdt'] - $expense;
  $r['expense']=$expense;
  $r['profit']=$profit;
  $rows[]=$r;
  $totalRevenue += $r['revenue_bdt'];
  $totalExpense += $expense;
  $totalProfit  += $profit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Trips — <?= htmlspecialchars($truck['reg_number']) ?></title>
<style>
  :root{--bg:#f6f8fb;--surface:#fff;--text:#111;--muted:#5b6675;--border:#e3e6eb;--btn:#0d6efd;--btn-text:#fff;--pos:#157347;--neg:#dc3545}
  body{font-family:system-ui,Segoe UI,Arial;background:var(--bg);margin:0;color:var(--text)}
  .shell{max-width:1100px;margin:20px auto;padding:0 12px}
  .btn{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--btn);color:var(--btn-text);text-decoration:none;cursor:pointer}
  .btn.link{background:#eef2f7;color:#333}
  .btn.secondary{background:#eef5ff;color:#0d6efd;border-color:#cfe2ff}
  .card{border:1px solid var(--border);border-radius:12px;padding:16px;background:#fff}
  .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .filters{display:flex;gap:8px;margin:10px 0;align-items:center;flex-wrap:wrap}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border:1px solid var(--border);text-align:center}
  th{background:#f3f6fb}
  .profit-positive{color:var(--pos);font-weight:700}
  .profit-negative{color:var(--neg);font-weight:700}
  .totals{background:#f1f1f1;font-weight:800}
  .ranges .btn{background:#eef5ff;color:#0d6efd;border-color:#cfe2ff}
</style>
<script>
  function setRange(days){
    const url = new URL(window.location.href);
    url.searchParams.set('range', days);
    if(days==='all'){ url.searchParams.delete('from'); url.searchParams.delete('to'); }
    window.location.href = url.toString();
  }

  function openReceipt(data){
    const w = window.open('', '_blank', 'width=720,height=900');
    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt ${data.receipt}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .brand{font-weight:800;font-size:20px}
  .meta{color:#555}
  .card{border:1px solid #e3e6eb;border-radius:10px;padding:16px;margin-top:10px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border:1px solid #e3e6eb;text-align:left}
  th{background:#f3f6fb}
  .tot{font-weight:800}
  .actions{margin-top:16px}
  .btn{padding:8px 12px;border:1px solid #ddd;border-radius:8px;background:#0d6efd;color:#fff;cursor:pointer}
</style>
</head>
<body>
  <div class="head">
    <div class="brand">HaulPro – ${data.truck}</div>
    <div class="meta">Receipt #: ${data.receipt}</div>
  </div>

  <div class="card">
    <h3>Truck</h3>
    <table>
      <tr><th>Reg No</th><td>${data.truck}</td></tr>
      <tr><th>Driver</th><td>${data.driver_name} (${data.driver_phone})</td></tr>
    </table>
  </div>

  <div class="card">
    <h3>Trip</h3>
    <table>
      <tr><th>Date</th><td>${data.date}</td></tr>
      <tr><th>Route</th><td>${data.route}</td></tr>
      <tr><th>Trip Type</th><td>${data.type}</td></tr>
      <tr><th>Distance</th><td>${data.distance} km</td></tr>
      <tr><th>Revenue</th><td>৳${data.revenue}</td></tr>
    </table>
  </div>

  <div class="card">
    <h3>Cost Breakdown</h3>
    <table>
      <tr><th>Driver Fee</th><td>৳${data.driver_fee}</td></tr>
      <tr><th>Fuel Cost</th><td>৳${data.fuel_cost}</td></tr>
      <tr><th>Toll Cost</th><td>৳${data.toll_cost}</td></tr>
      <tr><th>Labor Cost</th><td>৳${data.labor_cost}</td></tr>
      <tr><th>Gate Cost</th><td>৳${data.gate_cost}</td></tr>
      <tr><th>Other Cost</th><td>৳${data.other_cost}</td></tr>
      <tr class="tot"><th>Total Expense</th><td>৳${data.expense}</td></tr>
      <tr class="tot"><th>Profit</th><td>৳${data.profit}</td></tr>
    </table>
  </div>

  <div class="actions">
    <button class="btn" onclick="window.print()">Print</button>
  </div>
</body>
</html>`;
    w.document.open(); w.document.write(html); w.document.close();
  }
</script>
</head>
<body>
<div class="shell">
  <div class="topbar">
    <a class="btn link" href="lorrylist.php">⬅ Trucks</a>
    <div><strong><?= htmlspecialchars($truck['reg_number']) ?></strong> — <?= htmlspecialchars($truck['truck_type']) ?></div>
    <a class="btn" href="calculationInput.php?truck_id=<?= (int)$truck_id ?>">+ New Trip</a>
  </div>

  <div class="card">
    <div class="filters">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="truck_id" value="<?= (int)$truck_id ?>">
        <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
        <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
        <button class="btn" type="submit">Filter</button>
        <a class="btn secondary" href="calculationShow.php?truck_id=<?= (int)$truck_id ?>">Reset</a>
      </form>
      <div class="ranges" style="margin-left:auto;display:flex;gap:6px">
        <button class="btn" onclick="setRange(10)">10 days</button>
        <button class="btn" onclick="setRange(30)">1 month</button>
        <button class="btn" onclick="setRange(180)">6 months</button>
        <button class="btn" onclick="setRange(365)">1 year</button>
        <button class="btn" onclick="setRange('all')">All</button>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Route</th>
          <th>Type</th>
          <th>Distance</th>
          <th>Revenue</th>
          <th>Expense</th>
          <th>Profit</th>
          <th>Receipt</th>
        </tr>
      </thead>
      <tbody>
        <?php if($rows): foreach($rows as $r):
          $dateBD = date('d/m/Y', strtotime($r['trip_date']));
          $route  = $r['route_from'].' → '.$r['route_to'];
          $distance = number_format($r['distance_km']);
          $revenue  = number_format($r['revenue_bdt']);
          $expense  = number_format($r['expense']);
          $profit   = number_format($r['profit']);
          $driver_fee = number_format($r['driver_fee']);
          $fuel_cost  = number_format($r['fuel_cost']);
          $toll_cost  = number_format($r['toll_cost']);
          $labor_cost = number_format($r['labor_cost']);
          $gate_cost  = number_format($r['gate_cost']);
          $other_cost = number_format($r['other_cost']);
          $receipt = $r['receipt_no'] ?: ('#'.$r['id']);
        ?>
          <tr>
            <td><?= htmlspecialchars($dateBD) ?></td>
            <td><?= htmlspecialchars($route) ?></td>
            <td><?= htmlspecialchars($r['trip_type']) ?></td>
            <td><?= htmlspecialchars($distance) ?> km</td>
            <td>৳<?= htmlspecialchars($revenue) ?></td>
            <td>৳<?= htmlspecialchars($expense) ?></td>
            <td class="<?= $r['profit']>=0?'profit-positive':'profit-negative' ?>">৳<?= htmlspecialchars($profit) ?></td>
            <td>
              <button
                class="btn secondary"
                onclick='openReceipt({
                  receipt: <?= json_encode($receipt) ?>,
                  truck:   <?= json_encode($truck["reg_number"]) ?>,
                  date:    <?= json_encode($dateBD) ?>,
                  route:   <?= json_encode($route) ?>,
                  type:    <?= json_encode($r["trip_type"]) ?>,
                  distance: <?= json_encode($distance) ?>,
                  revenue:  <?= json_encode($revenue) ?>,
                  driver_fee: <?= json_encode($driver_fee) ?>,
                  fuel_cost:  <?= json_encode($fuel_cost) ?>,
                  toll_cost:  <?= json_encode($toll_cost) ?>,
                  labor_cost: <?= json_encode($labor_cost) ?>,
                  gate_cost:  <?= json_encode($gate_cost) ?>,
                  other_cost: <?= json_encode($other_cost) ?>,
                  expense: <?= json_encode($expense) ?>,
                  profit:  <?= json_encode($profit) ?>,
                  driver_name:  <?= json_encode($r["driver_name"]) ?>,
                  driver_phone: <?= json_encode($r["driver_phone"]) ?>
                })'>
                Receipt
              </button>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8">No trips in this range.</td></tr>
        <?php endif; ?>
        <tr class="totals">
          <td colspan="4">TOTAL (<?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>)</td>
          <td>৳<?= number_format($totalRevenue) ?></td>
          <td>৳<?= number_format($totalExpense) ?></td>
          <td>৳<?= number_format($totalProfit) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
