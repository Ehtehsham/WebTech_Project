<?php
// Set timezone
date_default_timezone_set('Asia/Dhaka');

// ----- DATA -----
$locations = ["Dhaka", "Chattogram", "Cumilla"];

$routeFrom = $routeTo = $driverName = $driverNumber = $driverFee = $fuelCost = $otherCosts = $revenue = '';
$tollCost = $laborCost = $gateCost = $miscCost = 0;
$distance = 0;
$totalCost = 0;
$profit = 0;
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $routeFrom = $_POST['routeFrom'] ?? '';
    $routeTo = $_POST['routeTo'] ?? '';
    $driverName = $_POST['driverName'] ?? '';
    $driverNumber = $_POST['driverNumber'] ?? '';
    $driverFee = $_POST['driverFee'] ?? 0;
    $fuelCost = $_POST['fuelCost'] ?? 0;
    $otherCosts = $_POST['otherCosts'] ?? 0;
    $tollCost = $_POST['tollCost'] ?? 0;
    $laborCost = $_POST['laborCost'] ?? 0;
    $gateCost = $_POST['gateCost'] ?? 0;
    $miscCost = $_POST['miscCost'] ?? 0;
    $revenue = $_POST['revenue'] ?? 0;

    // Prevent negative values
    if ($driverFee < 0 || $fuelCost < 0 || $otherCosts < 0 || $tollCost < 0 || $laborCost < 0 || $gateCost < 0 || $miscCost < 0 || $revenue < 0) {
        $errorMessage = "Values cannot be negative.";
    }

    // Distance
    if (!empty($routeFrom) && !empty($routeTo)) {
        $distance = calculateDistance($routeFrom, $routeTo);
    }

    // Cast to float
    $driverFee = (float)$driverFee;
    $fuelCost = (float)$fuelCost;
    $otherCosts = (float)$otherCosts;
    $tollCost = (float)$tollCost;
    $laborCost = (float)$laborCost;
    $gateCost = (float)$gateCost;
    $miscCost = (float)$miscCost;
    $revenue = (float)$revenue;

    // Total cost + profit
    $totalCost = $driverFee + $fuelCost + $otherCosts + $tollCost + $laborCost + $gateCost + $miscCost;
    $profit = $revenue - $totalCost;
}

// Distance function
function calculateDistance($routeFrom, $routeTo) {
    $distances = [
        "Dhaka" => ["Chattogram" => 253, "Cumilla" => 109],
        "Chattogram" => ["Dhaka" => 253, "Cumilla" => 152],
        "Cumilla" => ["Dhaka" => 109, "Chattogram" => 152]
    ];
    return $distances[$routeFrom][$routeTo] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>🚚 HaulPro – Truck 1 Trip Calculation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="calculation.css">
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="left">
            <button class="btn link" onclick="goHome()">🏠 Home</button>
            <span class="brand">HaulPro</span>
            <span class="truck-badge">Truck 1</span>
        </div>
        <div class="right">
            <button class="btn" onclick="toggleTheme()">🌙 Dark Mode</button>
            <a href="logout.php" class="btn">🚪 Logout</a>
        </div>
    </div>

    <div class="card">
        <h2>🚚 Trip Details Form</h2>

        <?php if ($errorMessage): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form action="calculationInput.php" method="POST">
            <!-- From -->
            <label for="routeFrom">From:</label>
            <select id="routeFrom" name="routeFrom" required onchange="updateDistance()">
                <option value="">Select From</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= htmlspecialchars($location) ?>" <?= ($routeFrom == $location) ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- To -->
            <label for="routeTo">To:</label>
            <select id="routeTo" name="routeTo" required onchange="updateDistance()">
                <option value="">Select To</option>
                <?php foreach ($locations as $location): ?>
                    <?php if ($routeFrom != $location || $routeTo == $location): ?>
                        <option value="<?= htmlspecialchars($location) ?>" <?= ($routeTo == $location) ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <!-- Distance -->
            <label>Distance (km):</label>
            <div id="distanceDisplay"><?= htmlspecialchars($distance) ?: 'Select both locations to see distance' ?></div>

            <!-- Revenue -->
            <label for="revenue">Revenue (BDT):</label>
            <input type="number" id="revenue" name="revenue" value="<?= htmlspecialchars($revenue) ?>" required>

            <!-- Driver Info -->
            <label for="driverName">Driver's Name:</label>
            <input type="text" id="driverName" name="driverName" value="<?= htmlspecialchars($driverName) ?>" required>

            <label for="driverNumber">Driver's Number:</label>
            <input type="text" id="driverNumber" name="driverNumber" value="<?= htmlspecialchars($driverNumber) ?>" required>

            <!-- Cost Fields -->
            <label for="driverFee">Driver Fee (BDT):</label>
            <input type="number" id="driverFee" name="driverFee" value="<?= htmlspecialchars($driverFee) ?>" required>

            <label for="fuelCost">Fuel Cost (BDT):</label>
            <input type="number" id="fuelCost" name="fuelCost" value="<?= htmlspecialchars($fuelCost) ?>" required>

            <label for="tollCost">Toll Cost (BDT):</label>
            <input type="number" id="tollCost" name="tollCost" value="<?= htmlspecialchars($tollCost) ?>" required>

            <label for="laborCost">Labor Cost (BDT):</label>
            <input type="number" id="laborCost" name="laborCost" value="<?= htmlspecialchars($laborCost) ?>" required>

            <label for="gateCost">Gate Cost (BDT):</label>
            <input type="number" id="gateCost" name="gateCost" value="<?= htmlspecialchars($gateCost) ?>" required>

            <label for="miscCost">Other Costs (BDT):</label>
            <input type="number" id="miscCost" name="miscCost" value="<?= htmlspecialchars($miscCost) ?>" required>

            <!-- Old Other -->
            <label for="otherCosts">Miscellaneous (BDT):</label>
            <input type="number" id="otherCosts" name="otherCosts" value="<?= htmlspecialchars($otherCosts) ?>" required>

            <!-- Results -->
            <label>Total Cost (BDT):</label>
            <div id="totalCost"><?= number_format($totalCost, 2) ?></div>

            <label>Profit (BDT):</label>
            <div id="profit"><?= number_format($profit, 2) ?></div>

            <button type="submit" class="btn">Submit</button>
        </form>
    </div>
</div>

<script>
    function updateDistance() {
        var routeFrom = document.getElementById("routeFrom").value;
        var routeTo = document.getElementById("routeTo").value;
        var distanceDisplay = document.getElementById("distanceDisplay");

        if (routeFrom && routeTo && routeFrom !== routeTo) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'calculate_distance.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    distanceDisplay.textContent = xhr.responseText;
                }
            };
            xhr.send('routeFrom=' + routeFrom + '&routeTo=' + routeTo);
        } else {
            distanceDisplay.textContent = 'Select both locations to see distance';
        }
    }

    function toggleTheme() {
        document.body.classList.toggle("dark-mode");
    }

    // Cost calculation
    let fields = ['driverFee','fuelCost','otherCosts','tollCost','laborCost','gateCost','miscCost','revenue'];
    fields.forEach(id => {
        document.getElementById(id).addEventListener('input', updateCalculations);
    });

    function updateCalculations() {
        let driverFee = parseFloat(document.getElementById('driverFee').value) || 0;
        let fuelCost = parseFloat(document.getElementById('fuelCost').value) || 0;
        let otherCosts = parseFloat(document.getElementById('otherCosts').value) || 0;
        let tollCost = parseFloat(document.getElementById('tollCost').value) || 0;
        let laborCost = parseFloat(document.getElementById('laborCost').value) || 0;
        let gateCost = parseFloat(document.getElementById('gateCost').value) || 0;
        let miscCost = parseFloat(document.getElementById('miscCost').value) || 0;
        let revenue = parseFloat(document.getElementById('revenue').value) || 0;

        let totalCost = driverFee + fuelCost + otherCosts + tollCost + laborCost + gateCost + miscCost;
        document.getElementById('totalCost').textContent = totalCost.toFixed(2);

        let profit = revenue - totalCost;
        document.getElementById('profit').textContent = profit.toFixed(2);
    }
</script>
</body>
</html>
