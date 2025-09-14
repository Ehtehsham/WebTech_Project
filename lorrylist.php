<?php
require __DIR__.'/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'add') {
      $reg  = trim($_POST['reg_number'] ?? '');
      $type = trim($_POST['truck_type'] ?? '');
      $status = trim($_POST['status'] ?? 'Available');
      if ($reg === '' || $type === '') throw new Exception('Reg number and truck type are required.');

      $stm = $mysqli->prepare("INSERT INTO trucks (reg_number, truck_type, status) VALUES (?,?,?)");
      $stm->bind_param('sss', $reg, $type, $status);
      $stm->execute();
      $msg = 'Truck added.';
    } elseif ($action === 'update') {
      $id   = (int)($_POST['id'] ?? 0);
      $reg  = trim($_POST['reg_number'] ?? '');
      $type = trim($_POST['truck_type'] ?? '');
      $status = trim($_POST['status'] ?? 'Available');
      if ($id<=0) throw new Exception('Invalid truck id.');
      if ($reg === '' || $type === '') throw new Exception('Reg number and truck type are required.');

      $stm = $mysqli->prepare("UPDATE trucks SET reg_number=?, truck_type=?, status=? WHERE id=?");
      $stm->bind_param('sssi', $reg, $type, $status, $id);
      $stm->execute();
      $msg = 'Truck updated.';
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new Exception('Invalid truck id.');
      $stm = $mysqli->prepare("DELETE FROM trucks WHERE id=?");
      $stm->bind_param('i', $id);
      $stm->execute();
      $msg = 'Truck deleted.';
    }
  } catch(Throwable $e) {
    $msg = 'Error: '.$e->getMessage();
  }
}

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$edit_truck = null;
if ($edit_id) {
  $st = $mysqli->prepare("SELECT id, reg_number, truck_type, status FROM trucks WHERE id=?");
  $st->bind_param('i', $edit_id);
  $st->execute();
  $edit_truck = $st->get_result()->fetch_assoc();
}

$res = $mysqli->query("SELECT id, reg_number, truck_type FROM trucks ORDER BY reg_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HaulPro — Lorry List</title>
<style>
  :root{--bg:#f6f8fb;--surface:#fff;--text:#111;--muted:#5b6675;--border:#e3e6eb;--btn:#0d6efd;--btn-text:#fff;--link:#0d6efd}
  body{font-family:system-ui,Segoe UI,Arial;background:var(--bg);margin:0;color:var(--text)}
  .shell{max-width:1000px;margin:20px auto;padding:0 12px}
  .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .btn{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--btn);color:var(--btn-text);text-decoration:none;cursor:pointer}
  .btn.link{background:transparent;color:#333}
  .btn.secondary{background:#eef5ff;color:#0d6efd;border-color:#cfe2ff}
  .btn.danger{background:#dc3545}
  .card{border:1px solid var(--border);border-radius:12px;padding:16px;background:#fff}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  label{font-size:12px;color:#555}
  input,select{width:100%;padding:8px;border:1px solid var(--border);border-radius:10px}
  .form-actions{margin-top:10px;display:flex;gap:10px}
  .msg{margin:10px 0;color:#0a7d2e}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px;border-bottom:1px solid var(--border)}
  th{background:#f3f6fb;text-align:left}
  tr.row-link{cursor:pointer}
  tr.row-link:hover{background:#f8fbff}
  .actions a,.actions button{margin-right:6px}
</style>
<script>
  function confirmDel(id){
    if(confirm('Delete this truck? Associated trips will also be removed.')){
      document.getElementById('del-'+id).submit();
    }
  }
  function goRow(url){ window.location.href = url; }
  function stopEvt(e){ e.stopPropagation(); }
</script>
</head>
<body>
<div class="shell">
  <div class="topbar">
    <a class="btn link" href="Dashboard.html">⬅ Back to Dashboard</a>
    <h2>🚛 Lorry List</h2>
    <span></span>
  </div>

  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:16px">
    <h3><?= $edit_truck ? '✏️ Edit Truck' : '➕ Add Truck' ?></h3>
    <form method="post">
      <div class="grid">
        <div>
          <label>Reg Number</label>
          <input type="text" name="reg_number" required value="<?= htmlspecialchars($edit_truck['reg_number'] ?? '') ?>">
        </div>
        <div>
          <label>Truck Type</label>
          <select name="truck_type" required>
            <?php
              $opts=['Small Truck','Medium Truck','Large Truck','Covered Van','Open Truck'];
              $cur=$edit_truck['truck_type'] ?? '';
              foreach($opts as $o){ $sel=($o===$cur)?'selected':''; echo "<option $sel>".htmlspecialchars($o)."</option>"; }
            ?>
          </select>
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <?php
              $sopts=['Available','Active','In Transit','Delivered','Waiting for Load','Out of Service','Maintenance'];
              $scur=$edit_truck['status'] ?? 'Available';
              foreach($sopts as $s){ $sel=($s===$scur)?'selected':''; echo "<option $sel>".htmlspecialchars($s)."</option>"; }
            ?>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <?php if($edit_truck): ?>
          <input type="hidden" name="id" value="<?= (int)$edit_truck['id'] ?>">
          <input type="hidden" name="action" value="update">
          <button class="btn" type="submit">💾 Save</button>
          <a class="btn secondary" href="lorrylist.php">Cancel</a>
        <?php else: ?>
          <input type="hidden" name="action" value="add">
          <button class="btn" type="submit">✅ Add Truck</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Reg Number</th>
          <th>Truck Type</th>
          <th style="width:240px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if($res && $res->num_rows): while($row=$res->fetch_assoc()): $id=(int)$row['id']; ?>
        <tr class="row-link" onclick="goRow('calculationInput.php?truck_id=<?= $id ?>')">
          <td><?= htmlspecialchars($row['reg_number']) ?></td>
          <td><?= htmlspecialchars($row['truck_type']) ?></td>
          <td class="actions" onclick="stopEvt(event)">
            <a class="btn secondary" href="lorrylist.php?edit_id=<?= $id ?>">Edit</a>
            <form id="del-<?= $id ?>" method="post" style="display:inline">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="action" value="delete">
              <button type="button" class="btn danger" onclick="confirmDel(<?= $id ?>)">Delete</button>
            </form>
            <a class="btn" href="calculationShow.php?truck_id=<?= $id ?>">Trips</a>
          </td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="3">No trucks yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
