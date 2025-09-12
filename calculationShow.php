<?php
require __DIR__.'/db.php';

$truck_id = isset($_GET['truck_id']) ? (int)$_GET['truck_id'] : 0;
if ($truck_id<=0) die('Missing truck_id');

// truck header
$ts = $mysqli->prepare("SELECT id, reg_number, truck_type, current_location FROM trucks WHERE id=?");
$ts->bind_param('i',$truck_id);
$ts->execute();
$truck = $ts->get_result()->fetch_assoc();
if (!$truck) die('Truck not found');

// compute date range
$today = date('Y-m-d');
$range = $_GET['range'] ?? null;       // "10" | "30" | "180" | "365" | "all" | null
$from  = $_GET['from'] ?? null;
$to    = $_GET['to']   ?? null;

// if a quick range is chosen, it overrides manual from/to
if ($range) {
  if ($range === 'all') {
    $minS = $mysqli->prepare("SELECT MIN(trip_date) AS mn FROM trips WHERE truck_id=?");
    $minS->bind_param('i',$truck_id);
    $minS->execute();
    $mn = $minS->get_result()->fetch_assoc()['mn'] ?? null;
    $from = $mn ?: '2000-01-01';
    $to   = $today;
  } else {
    $days = (int)$range;
    if ($days <= 0) $days = 30;
    $from = date('Y-m-d', strtotime("-$days days"));
    $to   = $today;
  }
}

// default window = last 30 days
if (!$from) $from = date('Y-m-d', strtotime('-30 days'));
if (!$to)   $to   = $today;

// sanitize safety
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $to   = $today;

// fetch rows
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
    :root{
      --bg:#f6f8fb; --surface:#fff; --text:#111; --muted:#5b6675; --border:#e3e6eb;
      --btn:#0d6efd; --btn-text:#fff; --thead:#f3f6fb;
      --profit:#157347; --loss:#dc3545; --row-alt:#fafcff;
    }
    body.dark{
      --bg:#0f141a; --surface:#101821; --text:#e8eef6; --muted:#aab7c4; --border:#1f2a36;
      --btn:#2b6fdd; --btn-text:#e8eef6; --thead:#0f2034;
      --profit:#57d89d; --loss:#ff7b91; --row-alt:#0d1620;
    }
    *{box-sizing:border-box}
    body{font-family:system-ui,Segoe UI,Arial;background:var(--bg);color:var(--text);margin:0}
    .shell{max-width:1100px;margin:20px auto;padding:0 12px}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .left{display:flex;gap:8px;align-items:center}
    .brand{font-weight:700}
    .btn{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--btn);color:var(--btn-text);text-decoration:none;cursor:pointer}
    .btn.link{background:transparent;color:var(--text)}
    .btn.ghost{background:transparent;color:var(--muted)}
    .btn.pill{border-radius:999px}
    .group{display:flex;gap:8px;flex-wrap:wrap}
    .btn.secondary{background:#e9f1ff;color:#0d6efd;border-color:#cfe2ff}
    body.dark .btn.secondary{background:#0b1a2a;color:#86b9ff;border-color:#163255}
    .rangebar{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
    .rangebar .btn{background:#eef5ff;color:#0d6efd;border-color:#cfe2ff}
    .rangebar .btn.active{background:var(--btn);color:var(--btn-text)}
    body.dark .rangebar .btn{background:#0b1a2a;color:#86b9ff;border-color:#163255}
    .card{border:1px solid var(--border);border-radius:14px;padding:16px;background:var(--surface)}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border:1px solid var(--border);text-align:center}
    th{background:var(--thead)}
    tbody tr:nth-child(even){background:var(--row-alt)}
    .profit-positive{color:var(--profit);font-weight:700}
    .profit-negative{color:var(--loss);font-weight:700}
    .totals{background:rgba(0,0,0,.04);font-weight:800}
    body.dark .totals{background:rgba(255,255,255,.05)}
    .filters{display:flex;gap:8px;margin:10px 0;align-items:center;flex-wrap:wrap}
    input[type="date"]{padding:8px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text)}
  </style>
</head>
<body>
<div class="shell">

  <div class="topbar">
    <div class="left">
      <a class="btn link" href="lorrylist.php">⬅ Trucks</a>
      <span class="brand"><?= htmlspecialchars($truck['reg_number']) ?></span> — <?= htmlspecialchars($truck['truck_type']) ?>
    </div>
    <button id="themeToggle" class="btn pill ghost" type="button">🌙 Night mode</button>
  </div>

  <div class="card">
    <!-- Quick ranges -->
    <div class="rangebar">
      <?php
        $r = $range ?: '';
        function rangeBtn($label,$val,$r,$truck_id){
          $active = ($r === $val) ? 'active' : '';
          $q = http_build_query(['truck_id'=>$truck_id,'range'=>$val]);
          echo "<a class='btn $active' href='calculationShow.php?$q'>$label</a>";
        }
        rangeBtn('Last 10 Days','10',$r,$truck_id);
        rangeBtn('1 Month','30',$r,$truck_id);
        rangeBtn('6 Months','180',$r,$truck_id);
        rangeBtn('1 Year','365',$r,$truck_id);
        rangeBtn('All Records','all',$r,$truck_id);
      ?>
    </div>

    <!-- Manual filters still available -->
    <form class="filters" method="get">
      <input type="hidden" name="truck_id" value="<?= (int)$truck_id ?>">
      <label>From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
      <button class="btn" type="submit">Filter</button>
      <a class="btn secondary" href="calculationShow.php?truck_id=<?= (int)$truck_id ?>">Reset</a>
      <a class="btn" href="calculationInput.php?truck_id=<?= (int)$truck_id ?>">+ New Trip</a>
    </form>

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
          // build data for receipt
          $receiptData = [
            'receipt'   => $r['receipt_no'] ?: '-',
            'date'      => date('d/m/Y', strtotime($r['trip_date'])),
            'route'     => $r['route_from'].' → '.$r['route_to'],
            'type'      => $r['trip_type'],
            'distance'  => (float)$r['distance_km'],
            'revenue'   => (float)$r['revenue_bdt'],
            'expense'   => (float)$r['expense'],
            'profit'    => (float)$r['profit'],
            'driver'    => $r['driver_name'],
            'driver_phone' => $r['driver_phone'],
            'driver_fee'=> (float)$r['driver_fee'],
            'fuel_cost' => (float)$r['fuel_cost'],
            'toll_cost' => (float)$r['toll_cost'],
            'labor_cost'=> (float)$r['labor_cost'],
            'gate_cost' => (float)$r['gate_cost'],
            'other_cost'=> (float)$r['other_cost'],
            // truck
            'truck_reg' => $truck['reg_number'],
            'truck_type'=> $truck['truck_type'],
            'truck_loc' => $truck['current_location'],
          ];
        ?>
          <tr>
            <td><?= htmlspecialchars(date('d/m/Y',strtotime($r['trip_date']))) ?></td>
            <td><?= htmlspecialchars($r['route_from'].' → '.$r['route_to']) ?></td>
            <td><?= htmlspecialchars($r['trip_type']) ?></td>
            <td><?= number_format($r['distance_km']) ?> km</td>
            <td>৳<?= number_format($r['revenue_bdt']) ?></td>
            <td>৳<?= number_format($r['expense']) ?></td>
            <td class="<?= $r['profit']>=0?'profit-positive':'profit-negative' ?>">৳<?= number_format($r['profit']) ?></td>
            <td>
              <button class="btn secondary" type="button"
                onclick='openReceipt(<?= json_encode($receiptData, JSON_UNESCAPED_UNICODE) ?>)'>
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

<script>
// --- theme toggle with persistence ---
const btn = document.getElementById('themeToggle');
function applyTheme(){
  const saved = localStorage.getItem('hp-theme') || 'light';
  if(saved==='dark'){ document.body.classList.add('dark'); btn.textContent='☀️ Light mode'; }
  else { document.body.classList.remove('dark'); btn.textContent='🌙 Night mode'; }
}
btn.addEventListener('click', ()=>{
  const now = document.body.classList.contains('dark') ? 'light' : 'dark';
  localStorage.setItem('hp-theme', now);
  applyTheme();
});
applyTheme();

// --- printable receipt window ---
function money(x){
  try{ return '৳'+Number(x).toLocaleString('en-BD'); }catch(e){ return '৳'+x; }
}
function openReceipt(d){
  const html = `
<!doctype html>
<html><head><meta charset="utf-8">
<title>Receipt ${d.receipt}</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial;margin:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .brand{font-weight:800;font-size:20px}
  .meta{color:#555}
  .card{border:1px solid #e3e6eb;border-radius:10px;padding:16px;margin-top:10px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border:1px solid #e3e6eb;text-align:left}
  th{background:#f3f6fb}
  .right{text-align:right}
  .tot{font-weight:800}
  .actions{margin-top:16px}
  .btn{padding:8px 12px;border:1px solid #ddd;border-radius:8px;background:#0d6efd;color:#fff;cursor:pointer}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .muted{color:#555}
</style></head><body>
<div class="head">
  <div class="brand">HaulPro – Truck</div>
  <div class="meta">Receipt #: ${d.receipt}</div>
</div>

<div class="card">
  <div class="grid">
    <div>
      <strong>Truck</strong><br>
      Reg: ${d.truck_reg}<br>
      Type: ${d.truck_type}<br>
      Location: ${d.truck_loc??'-'}
    </div>
    <div>
      <strong>Driver</strong><br>
      ${d.driver} (${d.driver_phone})
    </div>
  </div>
</div>

<div class="card">
  <table>
    <tr><th>Date</th><td>${d.date}</td></tr>
    <tr><th>Route</th><td>${d.route}</td></tr>
    <tr><th>Trip Type</th><td>${d.type}</td></tr>
    <tr><th>Distance</th><td>${d.distance} km</td></tr>
  </table>
</div>

<div class="card">
  <table>
    <thead><tr><th>Cost item</th><th class="right">Amount</th></tr></thead>
    <tbody>
      <tr><td>Driver Fee</td><td class="right">${money(d.driver_fee)}</td></tr>
      <tr><td>Fuel Cost</td><td class="right">${money(d.fuel_cost)}</td></tr>
      <tr><td>Toll Cost</td><td class="right">${money(d.toll_cost)}</td></tr>
      <tr><td>Labor Cost</td><td class="right">${money(d.labor_cost)}</td></tr>
      <tr><td>Gate Cost</td><td class="right">${money(d.gate_cost)}</td></tr>
      <tr><td>Others</td><td class="right">${money(d.other_cost)}</td></tr>
      <tr class="tot"><td>Total Expense</td><td class="right">${money(d.expense)}</td></tr>
      <tr><td><strong>Revenue</strong></td><td class="right"><strong>${money(d.revenue)}</strong></td></tr>
      <tr class="tot"><td>Profit</td><td class="right">${money(d.profit)}</td></tr>
    </tbody>
  </table>
</div>

<div class="actions">
  <button class="btn" onclick="window.print()">Print</button>
</div>
</body></html>`;
  const w = window.open('', '_blank', 'width=760,height=920');
  w.document.open(); w.document.write(html); w.document.close();
}
</script>
</div>
</body>
</html>
