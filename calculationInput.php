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

$driverName   = $truck['driver_name']  ?? '';
$driverNumber = $truck['driver_phone'] ?? '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $tripDate  = $_POST['tripDate']  ?? date('Y-m-d');
  $tripType  = $_POST['tripType']  ?? 'Single';
  $routeFrom = $_POST['routeFrom'] ?? '';
  $routeTo   = $_POST['routeTo']   ?? '';
  $revenue   = (float)($_POST['revenue'] ?? 0);

  $driverName   = trim($_POST['driverName']   ?? '');
  $driverNumber = trim($_POST['driverNumber'] ?? '');

  $driverFee = (float)($_POST['driverFee'] ?? 0);
  $fuelCost  = (float)($_POST['fuelCost']  ?? 0);
  $tollCost  = (float)($_POST['tollCost']  ?? 0);
  $laborCost = (float)($_POST['laborCost'] ?? 0);
  $gateCost  = (float)($_POST['gateCost']  ?? 0);
  $miscCost  = (float)($_POST['miscCost']  ?? 0);

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
      $row = $q->get_result()->fetch_assoc();
      $driver_id = $row['id'] ?? null;

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

      // attach driver to truck (optional but handy)
      $att = $mysqli->prepare("UPDATE trucks SET driver_id=? WHERE id=?");
      $att->bind_param('ii',$driver_id,$truck_id);
      $att->execute();

      // make a monthly sequence per truck → receipt like yy-###-m
      $seqS = $mysqli->prepare("SELECT COUNT(*)+1 FROM trips
                                WHERE truck_id=? AND YEAR(trip_date)=YEAR(?) AND MONTH(trip_date)=MONTH(?)");
      $seqS->bind_param('iss',$truck_id,$tripDate,$tripDate);
      $seqS->execute();
      $seq = (int)$seqS->get_result()->fetch_row()[0];
      $receipt_no = sprintf('%02d-%03d-%d', (int)date('y',strtotime($tripDate)), $seq, (int)date('n',strtotime($tripDate)));

      $insT = $mysqli->prepare("INSERT INTO trips
        (truck_id, trip_date, route_from, route_to, trip_type, distance_km, revenue_bdt,
         driver_id, driver_fee, fuel_cost, toll_cost, labor_cost, gate_cost, other_cost, receipt_no)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

      // types: i s s s s d d i d d d d d d s  (must match the placeholders above)
      $insT->bind_param(
        "issssddidddddds",
        $truck_id,
        $tripDate,
        $routeFrom,
        $routeTo,
        $tripType,
        $distance,
        $revenue,
        $driver_id,
        $driverFee,
        $fuelCost,
        $tollCost,
        $laborCost,
        $gateCost,
        $miscCost,
        $receipt_no
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
    :root{--bg:#f6f8fb;--surface:#fff;--text:#111;--border:#e3e6eb;--btn:#0d6efd;--btn-text:#fff;--pill:#eef5ff;--pill-text:#0d6efd}
    *{box-sizing:border-box}
    body{font-family:system-ui,Segoe UI,Arial;background:var(--bg);margin:0;color:var(--text)}
    .shell{max-width:1000px;margin:20px auto;padding:0 12px}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .left{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--btn);color:var(--btn-text);text-decoration:none;cursor:pointer}
    .btn.link{background:transparent;color:#333}
    .btn.secondary{background:var(--pill);color:var(--pill-text);border-color:#cfe2ff}
    .card{border:1px solid var(--border);border-radius:14px;padding:16px;background:var(--surface)}
    h2{display:flex;align-items:center;gap:8px;margin:0 0 10px}

    label{display:block;margin:6px 0 4px;font-size:12px}
    input,select{width:100%;padding:8px;font-size:14px;border:1px solid var(--border);border-radius:10px;min-width:0;background:#fff}
    .row2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .distance{padding:8px;font-size:14px;border:1px dashed #ccd;border-radius:10px;background:#fafbff}
    .error{background:#ffe8ea;color:#a10016;padding:10px;border-radius:10px;margin-bottom:10px;border:1px solid #ffd1d8}

    .two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:28px;row-gap:16px;position:relative;margin-top:12px}
    .two-col::after{content:"";position:absolute;left:50%;top:0;bottom:0;width:1px;background:var(--border);pointer-events:none}
    .col{display:grid;grid-template-columns:1fr;gap:12px}
    .actions{display:flex;gap:10px;align-items:center;margin-top:14px}
    @media (max-width:800px){.two-col{grid-template-columns:1fr}.two-col::after{display:none}.row2{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="shell">
  <div class="topbar">
    <div class="left">
      <a class="btn link" href="lorrylist.php">⬅ Back</a>
      <strong><?= htmlspecialchars($truck['reg_number']) ?></strong> — <?= htmlspecialchars($truck['truck_type']) ?>
    </div>
    <a class="btn secondary" href="calculationShow.php?truck_id=<?= (int)$truck_id ?>">📄 View Trips</a>
  </div>

  <div class="card">
    <h2>🛻 Trip Details</h2>
    <?php if ($errorMessage): ?><div class="error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

    <!-- IMPORTANT: keep truck_id in the action so POST never loses it -->
    <form method="POST" action="calculationInput.php?truck_id=<?= (int)$truck_id ?>" autocomplete="off">
      <div class="row2">
        <div>
          <label>Trip Date</label>
          <input type="date" name="tripDate" value="<?= htmlspecialchars($_POST['tripDate'] ?? date('Y-m-d')) ?>">
        </div>
        <div>
          <label>Trip Type</label>
          <select name="tripType">
            <option value="Single">Single</option>
            <option value="Round">Round</option>
          </select>
        </div>
      </div>

      <div class="row2">
        <div>
          <label>From</label>
          <select id="routeFrom" name="routeFrom" required onchange="updateDistance()">
            <option value="">Select From</option>
            <?php foreach($locations as $l): ?>
              <option<?= isset($_POST['routeFrom']) && $_POST['routeFrom']===$l?' selected':''; ?>><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>To</label>
          <select id="routeTo" name="routeTo" required onchange="updateDistance()">
            <option value="">Select To</option>
            <?php foreach($locations as $l): ?>
              <option<?= isset($_POST['routeTo']) && $_POST['routeTo']===$l?' selected':''; ?>><?= htmlspecialchars($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label>Distance (km)</label>
      <div id="distanceDisplay" class="distance">Select both locations to see distance</div>

      <div class="two-col">
        <div class="col">
          <div>
            <label>Revenue (BDT)</label>
            <input type="number" id="revenue" name="revenue" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['revenue'] ?? '') ?>">
          </div>

          <div class="row2">
            <div>
              <label>Driver's Name</label>
              <input type="text" id="driverName" name="driverName" value="<?= htmlspecialchars($driverName) ?>" required>
            </div>
            <div>
              <label>Driver's Number</label>
              <input type="text" id="driverNumber" name="driverNumber" value="<?= htmlspecialchars($driverNumber) ?>" required>
            </div>
          </div>

          <div class="row2">
            <div>
              <label>Driver Fee (BDT)</label>
              <input type="number" id="driverFee" name="driverFee" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['driverFee'] ?? '') ?>">
            </div>
            <div>
              <label>Fuel Cost (BDT)</label>
              <input type="number" id="fuelCost" name="fuelCost" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['fuelCost'] ?? '') ?>">
            </div>
          </div>

          <div>
            <label>Total Cost (BDT)</label>
            <div id="totalCost" class="distance" style="border-style:solid">0.00</div>
          </div>
        </div>

        <div class="col">
          <div class="row2">
            <div>
              <label>Toll Cost (BDT)</label>
              <input type="number" id="tollCost" name="tollCost" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['tollCost'] ?? '') ?>">
            </div>
            <div>
              <label>Labor Cost (BDT)</label>
              <input type="number" id="laborCost" name="laborCost" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['laborCost'] ?? '') ?>">
            </div>
          </div>

          <div class="row2">
            <div>
              <label>Gate Cost (BDT)</label>
              <input type="number" id="gateCost" name="gateCost" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['gateCost'] ?? '') ?>">
            </div>
            <div>
              <label>Others (BDT)</label>
              <input type="number" id="miscCost" name="miscCost" min="0" step="0.01" required value="<?= htmlspecialchars($_POST['miscCost'] ?? '') ?>">
            </div>
          </div>

          <div>
            <label>Profit (BDT)</label>
            <div id="profit" class="distance" style="border-style:solid">0.00</div>
          </div>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">💾 Save Trip</button>
      </div>
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
