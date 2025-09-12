<?php
require __DIR__.'/db.php';

$locations = ["Dhaka","Chattogram","Cumilla"];

function calculateDistance($from,$to){
  $dist=[
    "Dhaka"=>["Chattogram"=>253,"Cumilla"=>109],
    "Chattogram"=>["Dhaka"=>253,"Cumilla"=>152],
    "Cumilla"=>["Dhaka"=>109,"Chattogram"=>152]
  ];
  return $dist[$from][$to] ?? 0;
}

$truck_id = isset($_GET['truck_id']) ? (int)$_GET['truck_id'] : 0;
if ($truck_id<=0) die('Missing truck_id');

$st = $mysqli->prepare("SELECT t.id, t.reg_number, t.truck_type, t.current_location, t.driver_id,
                               d.name AS driver_name, d.phone AS driver_phone
                        FROM trucks t LEFT JOIN drivers d ON d.id=t.driver_id WHERE t.id=?");
$st->bind_param('i',$truck_id);
$st->execute();
$truck = $st->get_result()->fetch_assoc();
if (!$truck) die('Truck not found');

$driverName = $truck['driver_name'] ?? '';
$driverNumber = $truck['driver_phone'] ?? '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $tripDate = $_POST['tripDate'] ?? date('Y-m-d');
  $tripType = $_POST['tripType'] ?? 'Single';
  $routeFrom = $_POST['routeFrom'] ?? '';
  $routeTo   = $_POST['routeTo']   ?? '';
  $revenue   = (float)($_POST['revenue'] ?? 0);

  $driverName   = trim($_POST['driverName'] ?? '');
  $driverNumber = trim($_POST['driverNumber'] ?? '');

  $driverFee = (float)($_POST['driverFee'] ?? 0);
  $fuelCost  = (float)($_POST['fuelCost'] ?? 0);
  $tollCost  = (float)($_POST['tollCost'] ?? 0);
  $laborCost = (float)($_POST['laborCost'] ?? 0);
  $gateCost  = (float)($_POST['gateCost'] ?? 0);
  $miscCost  = (float)($_POST['miscCost'] ?? 0);

  if ($revenue<0 || $driverFee<0 || $fuelCost<0 || $tollCost<0 || $laborCost<0 || $gateCost<0 || $miscCost<0) {
    $errorMessage = 'Values cannot be negative.';
  } elseif ($routeFrom==='' || $routeTo==='' || $routeFrom===$routeTo) {
    $errorMessage = 'Choose valid From and To.';
  } elseif ($driverNumber==='') {
    $errorMessage = 'Driver number is required.';
  } else {
    $distance = calculateDistance($routeFrom,$routeTo);

    $mysqli->begin_transaction();
    try {
      // upsert driver by phone
      $q = $mysqli->prepare("SELECT id FROM drivers WHERE phone=?");
      $q->bind_param('s',$driverNumber);
      $q->execute();
      $driver_id = ($q->get_result()->fetch_assoc()['id'] ?? null);

      if ($driver_id) {
        $upd = $mysqli->prepare("UPDATE drivers SET name=? WHERE id=?");
        $upd->bind_param('si',$driverName,$driver_id);
        $upd->execute();
      } else {
        $ins = $mysqli->prepare("INSERT INTO drivers(name,phone) VALUES(?,?)");
        $ins->bind_param('ss',$driverName,$driverNumber);
        $ins->execute();
        $driver_id = $ins->insert_id;
      }

      // attach driver to truck (optional)
      $att = $mysqli->prepare("UPDATE trucks SET driver_id=? WHERE id=?");
      $att->bind_param('ii',$driver_id,$truck_id);
      $att->execute();

      // generate receipt no yy-###-m (sequence per month per truck)
      $seqS = $mysqli->prepare("SELECT COUNT(*)+1 FROM trips WHERE truck_id=? AND YEAR(trip_date)=YEAR(?) AND MONTH(trip_date)=MONTH(?)");
      $seqS->bind_param('iss',$truck_id,$tripDate,$tripDate);
      $seqS->execute();
      $seq = (int)$seqS->get_result()->fetch_row()[0];
      $receipt_no = sprintf('%02d-%03d-%d', (int)date('y',strtotime($tripDate)), $seq, (int)date('n',strtotime($tripDate)));

      $insT = $mysqli->prepare("INSERT INTO trips
        (truck_id, trip_date, route_from, route_to, trip_type, distance_km, revenue_bdt,
         driver_id, driver_fee, fuel_cost, toll_cost, labor_cost, gate_cost, other_cost, receipt_no)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $insT->bind_param('isssssdidddddds',
        $truck_id, $tripDate, $routeFrom, $routeTo, $tripType, $distance, $revenue,
        $driver_id, $driverFee, $fuelCost, $tollCost, $laborCost, $gateCost, $miscCost, $receipt_no
      );
      $insT->execute();

      $mysqli->commit();
      header('Location: calculationShow.php?truck_id='.$truck_id);
      exit;
    } catch(Throwable $e) {
      $mysqli->rollback();
      $errorMessage = 'Save failed: '.$e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Trip Input — <?= htmlspecialchars($truck['reg_number']) ?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;background:#f6f8fb;margin:0}
    .shell{max-width:900px;margin:20px auto;padding:0 12px}
    .topbar{display:flex;gap:10px;align-items:center;margin-bottom:12px}
    .btn{padding:8px 12px;border:1px solid #ddd;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;cursor:pointer}
    .btn.link{background:#eef2f7;color:#333}
    .card{border:1px solid #e3e6eb;border-radius:12px;padding:16px;background:#fff}
    label{display:block;margin-top:8px;font-size:14px}
    input,select{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .error{background:#ffe8ea;color:#a10016;padding:8px;border-radius:8px;margin-bottom:8px}
  </style>
</head>
<body>
<div class="shell">
  <div class="topbar">
    <a class="btn link" href="lorrylist.php">⬅ Back</a>
    <strong><?= htmlspecialchars($truck['reg_number']) ?></strong> — <?= htmlspecialchars($truck['truck_type']) ?>
  </div>

  <div class="card">
    <h2>🚚 Trip Details</h2>
    <?php if ($errorMessage): ?><div class="error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

    <form method="POST">
      <div class="row">
        <div>
          <label>Trip Date</label>
          <input type="date" name="tripDate" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label>Trip Type</label>
          <select name="tripType">
            <option value="Single">Single</option>
            <option value="Round">Round</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label>From</label>
          <select id="routeFrom" name="routeFrom" required onchange="updateDistance()">
            <option value="">Select From</option>
            <?php foreach($locations as $l): ?>
              <option><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>To</label>
          <select id="routeTo" name="routeTo" required onchange="updateDistance()">
            <option value="">Select To</option>
            <?php foreach($locations as $l): ?>
              <option><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label>Distance (km)</label>
      <div id="distanceDisplay" style="padding:8px;border:1px dashed #ccd;border-radius:8px;background:#fafbff">Select both locations to see distance</div>

      <label>Revenue (BDT)</label>
      <input type="number" id="revenue" name="revenue" min="0" step="0.01" required>

      <div class="row">
        <div>
          <label>Driver's Name</label>
          <input type="text" id="driverName" name="driverName" value="<?= htmlspecialchars($driverName) ?>" required>
        </div>
        <div>
          <label>Driver's Number</label>
          <input type="text" id="driverNumber" name="driverNumber" value="<?= htmlspecialchars($driverNumber) ?>" required>
        </div>
      </div>

      <div class="row">
        <div><label>Driver Fee (BDT)</label><input type="number" id="driverFee" name="driverFee" min="0" step="0.01" required></div>
        <div><label>Fuel Cost (BDT)</label><input type="number" id="fuelCost" name="fuelCost" min="0" step="0.01" required></div>
      </div>
      <div class="row">
        <div><label>Toll Cost (BDT)</label><input type="number" id="tollCost" name="tollCost" min="0" step="0.01" required></div>
        <div><label>Labor Cost (BDT)</label><input type="number" id="laborCost" name="laborCost" min="0" step="0.01" required></div>
      </div>
      <div class="row">
        <div><label>Gate Cost (BDT)</label><input type="number" id="gateCost" name="gateCost" min="0" step="0.01" required></div>
        <div><label>Others (BDT)</label><input type="number" id="miscCost" name="miscCost" min="0" step="0.01" required></div>
      </div>

      <div class="row">
        <div>
          <label>Total Cost (BDT)</label>
          <div id="totalCost" style="padding:8px;border:1px solid #e3e6eb;border-radius:8px;background:#f9fbff">0.00</div>
        </div>
        <div>
          <label>Profit (BDT)</label>
          <div id="profit" style="padding:8px;border:1px solid #e3e6eb;border-radius:8px;background:#f9fbff">0.00</div>
        </div>
      </div>

      <div style="margin-top:12px"><button class="btn" type="submit">💾 Save Trip</button></div>
    </form>
  </div>
</div>

<script>
function updateDistance(){
  var f=document.getElementById('routeFrom').value;
  var t=document.getElementById('routeTo').value;
  var el=document.getElementById('distanceDisplay');
  const map={"Dhaka":{"Chattogram":253,"Cumilla":109},"Chattogram":{"Dhaka":253,"Cumilla":152},"Cumilla":{"Dhaka":109,"Chattogram":152}};
  if(f && t && f!==t && map[f] && map[f][t]) el.textContent = map[f][t];
  else el.textContent='Select both locations to see distance';
}

['driverFee','fuelCost','tollCost','laborCost','gateCost','miscCost','revenue'].forEach(id=>{
  document.getElementById(id).addEventListener('input',calc);
});
function calc(){
  let df=+document.getElementById('driverFee').value||0;
  let fu=+document.getElementById('fuelCost').value||0;
  let to=+document.getElementById('tollCost').value||0;
  let la=+document.getElementById('laborCost').value||0;
  let ga=+document.getElementById('gateCost').value||0;
  let ot=+document.getElementById('miscCost').value||0;
  let rv=+document.getElementById('revenue').value||0;
  let tc=df+fu+to+la+ga+ot;
  document.getElementById('totalCost').textContent=tc.toFixed(2);
  document.getElementById('profit').textContent=(rv-tc).toFixed(2);
}
</script>
</body>
</html>
