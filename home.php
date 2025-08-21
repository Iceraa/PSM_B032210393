<?php
include 'header.php';
include 'supabase_connector.php';

// Fetch donor statistics (count by blood type)
$hospitalId = $_SESSION['hospital_id'] ?? null;
$donors = fetch_from_supabase("donor", "hospital_id=eq.$hospitalId");
$bloodStats = [];
if (is_array($donors)) {
    foreach ($donors as $donor) {
        if (!is_array($donor)) continue;
        $type = strtoupper($donor['blood_type']);
        if (!isset($bloodStats[$type])) {
            $bloodStats[$type] = 0;
        }
        $bloodStats[$type]++;
    }
}
arsort($bloodStats);

$monthlySupply = [];
$supplyByType = [];

if ($hospitalId) {
    $donations = fetch_from_supabase("blood_donation", "hospital_id=eq.$hospitalId");
    $supplies = fetch_from_supabase("blood_supply", "hospital_id=eq.$hospitalId");

    $donationDates = [];
    if (is_array($donations)) {
        foreach ($donations as $d) {
            if (!is_array($d)) continue;
            $donationDates[$d['donation_id']] = $d['donation_date'];
        }
    }

    if (is_array($supplies)) {
        foreach ($supplies as $s) {
            if (!is_array($s)) continue;
            $donationId = $s['donation_id'];
            if (!isset($donationDates[$donationId])) continue;

            $month = date('Y-m', strtotime($donationDates[$donationId]));
            $type = strtoupper($s['blood_type']);
            $volume = (float) $s['volume_ml'];

            if (!isset($monthlySupply[$type])) $monthlySupply[$type] = [];
            if (!isset($monthlySupply[$type][$month])) $monthlySupply[$type][$month] = 0;
            $monthlySupply[$type][$month] += $volume;

            if (!isset($supplyByType[$type])) $supplyByType[$type] = 0;
            $supplyByType[$type] += $volume;
        }
    }
}

if ($hospitalId) {
    $donations = fetch_from_supabase("blood_donation");
    $supplies = fetch_from_supabase("blood_supply");

    $donationDates = [];
    foreach ($donations as $d) {
        $donationDates[$d['donation_id']] = $d['donation_date'];
    }

    foreach ($supplies as $s) {
        if ($s['hospital_id'] !== $hospitalId) continue;

        $donationId = $s['donation_id'];
        if (!isset($donationDates[$donationId])) continue;

        $month = date('Y-m', strtotime($donationDates[$donationId]));
        $type = strtoupper($s['blood_type']);
        $volume = (float) $s['volume_ml'];

        if (!isset($monthlySupply[$type])) $monthlySupply[$type] = [];
        if (!isset($monthlySupply[$type][$month])) $monthlySupply[$type][$month] = 0;
        $monthlySupply[$type][$month] += $volume;

        if (!isset($supplyByType[$type])) $supplyByType[$type] = 0;
        $supplyByType[$type] += $volume;
    }
}



ksort($monthlySupply);
foreach ($monthlySupply as &$months) {
    ksort($months);
}

$appointments = fetch_from_supabase("appointment", "hospital_id=eq.$hospitalId");
$sourceCounts = ['request' => 0, 'invite' => 0, 'emergency' => 0];
if (is_array($appointments)) {
    foreach ($appointments as $app) {
        if (!is_array($app)) continue;
        if (!empty($app['request_id'])) $sourceCounts['request']++;
        elseif (!empty($app['invite_id'])) $sourceCounts['invite']++;
        else $sourceCounts['emergency']++;
    }
}

$pendingRequests = fetch_from_supabase("blood_request", "hospital_id=eq.$hospitalId&status=eq.pending&select=request_id,blood_type,unit_req,deadline_date");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Blood Donation</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container { padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 15px; }
        .stat-box { background: #f8d7da; padding: 12px; text-align: center; border-radius: 5px; }
        .chart-area { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
        .chart-container { flex: 1; min-width: 300px; max-width: 48%; }
        canvas { width: 100% !important; height: auto !important; max-height: 300px; }
        .split-box { display: flex; gap: 20px; flex-wrap: wrap; }
        .half { flex: 1; min-width: 300px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Blood Type Statistics</h2>
        <div class="stat-grid">
            <?php foreach ($bloodStats as $type => $count): ?>
                <div class="stat-box">
                    <strong><?= $type ?></strong><br><?= $count ?> Donors
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card chart-area">
        <div class="chart-container">
            <h3>Total Blood Volume by Blood Type & Month</h3>
            <canvas id="barChart"></canvas>
        </div>
        <div class="chart-container">
            <h3>Total Blood Volume by Blood Type</h3>
            <canvas id="pieChart"></canvas>
        </div>
    </div>

    <div class="card split-box">
        <div class="half">
            <h3>Appointment Source Overview</h3>
            <canvas id="appointmentChart" style="max-height: 210px;"></canvas>
        </div>
       
    </div>
</div>

<script>
const monthlySupply = <?= json_encode($monthlySupply) ?>;
const supplyByType = <?= json_encode($supplyByType) ?>;
const sourceCounts = <?= json_encode($sourceCounts) ?>;

const months = [...new Set(Object.values(monthlySupply).flatMap(obj => Object.keys(obj)))].sort();
const types = Object.keys(monthlySupply);

const barData = {
    labels: months,
    datasets: types.map((type, i) => ({
        label: type,
        data: months.map(month => monthlySupply[type][month] || 0),
        backgroundColor: `hsl(${i * 30 + 10}, 70%, 55%)`
    }))
};

const pieData = {
    labels: Object.keys(supplyByType),
    datasets: [{
        data: Object.values(supplyByType),
        backgroundColor: Object.keys(supplyByType).map((_, i) => `hsl(${i * 30 + 10}, 80%, 50%)`)
    }]
};

const ctx1 = document.getElementById('barChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: barData,
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'ml' } } }
    }
});

const ctx2 = document.getElementById('pieChart').getContext('2d');
new Chart(ctx2, {
    type: 'pie',
    data: pieData,
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

const ctx3 = document.getElementById('appointmentChart').getContext('2d');
new Chart(ctx3, {
    type: 'doughnut',
    data: {
        labels: ['Request', 'Invite', 'Emergency'],
        datasets: [{
            data: Object.values(sourceCounts),
            backgroundColor: ['#c0392b', '#e74c3c', '#f5b7b1']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>
