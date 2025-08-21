<?php
include 'superadmin_header.php';
include 'supabase_connector.php';
include 'admin-api/auth_helpers.php';

// Quick metrics
$totalHospitals = count(fetch_from_supabase("hospital", "select=hospital_id"));
$totalDonors    = count(fetch_from_supabase("donor", "select=user_id"));
$totalStaff     = count(fetch_from_supabase("staff", "select=user_id"));
$totalSupply    = count(fetch_from_supabase("blood_supply", "select=supply_id"));

// --- Hospital Leaderboard by Supply Volume ---
$supply = fetch_from_supabase("blood_supply", "select=hospital_id,volume_ml");
$hospitals = fetch_from_supabase("hospital", "select=hospital_id,name");
$hospitalNames = [];
foreach ($hospitals as $h) {
    if (!is_array($h)) continue;
    $hospitalNames[$h['hospital_id']] = $h['name'] ?? $h['hospital_id'];
}
$hospitalSupply = [];
foreach ($supply as $row) {
    if (!is_array($row)) continue;
    $hid = $row['hospital_id'];
    $vol = (float)($row['volume_ml'] ?? 0);
    if (!isset($hospitalSupply[$hid])) $hospitalSupply[$hid] = 0;
    $hospitalSupply[$hid] += $vol;
}
arsort($hospitalSupply);
$topHospitals = array_slice($hospitalSupply, 0, 10, true);

// --- Top 10 Donor Contributors ---
$donations = fetch_from_supabase("blood_donation", "select=user_id,unit_donated");
$donorTotals = [];
foreach ($donations as $d) {
    if (!is_array($d)) continue;
    $uid = $d['user_id'];
    $unit = (float)($d['unit_donated'] ?? 0);
    if (!isset($donorTotals[$uid])) $donorTotals[$uid] = 0;
    $donorTotals[$uid] += $unit;
}
arsort($donorTotals);
$topDonors = array_slice($donorTotals, 0, 10, true);

// --- Get donor full names (optimized join style) ---
$topDonorIds = array_keys($topDonors);
$inList = implode(',', array_map(fn($id) => "\"$id\"", $topDonorIds));
$donorInfo = fetch_from_supabase("donor", "user_id=in.($inList)&select=user_id,full_name");
$donorNames = [];
foreach ($donorInfo as $d) {
    if (!is_array($d)) continue;
    $donorNames[$d['user_id']] = $d['full_name'];
}

// --- Charts: Monthly Trends and Volume by Blood Type ---
$donationData = fetch_from_supabase("blood_donation", "select=donation_date,unit_donated,user_id");
$donorBloodTypes = fetch_from_supabase("donor", "select=user_id,blood_type");

$monthlyTrends = [];
$volumeByType = [];
$typeMap = [];

foreach ($donorBloodTypes as $d) {
    if (!is_array($d)) continue;
    $typeMap[$d['user_id']] = strtoupper($d['blood_type']);
}

foreach ($donationData as $d) {
    if (!is_array($d)) continue;
    $date = $d['donation_date'];
    $uid = $d['user_id'];
    $volume = (float)($d['unit_donated'] ?? 0);
    $month = date('Y-m', strtotime($date));

    if (!isset($monthlyTrends[$month])) $monthlyTrends[$month] = 0;
    $monthlyTrends[$month]++;

    $type = $typeMap[$uid] ?? 'UNKNOWN';
    if (!isset($volumeByType[$type])) $volumeByType[$type] = 0;
    $volumeByType[$type] += $volume;
}

ksort($monthlyTrends);
ksort($volumeByType);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Superadmin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container { padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.09); margin-bottom: 20px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom:24px; }
        .stat-box { background:rgb(248, 212, 212); padding: 18px; text-align: center; border-radius: 8px; font-size:18px; } 
        .chart-area { display: flex; flex-wrap: wrap; gap: 28px; }
        .chart-container { flex: 1; min-width: 320px; max-width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
<div class="container">
    <div class="stat-grid">
        <div class="stat-box"><b><?= $totalHospitals ?></b><br>Hospitals</div>
        <div class="stat-box"><b><?= $totalDonors ?></b><br>Donors</div>
        <div class="stat-box"><b><?= $totalStaff ?></b><br>Staff</div>
        <div class="stat-box"><b><?= $totalSupply ?></b><br>Blood Supply Records</div>
    </div>

    <div class="card">
        <h2>🏥 Hospital Leaderboard (by Blood Supply Volume)</h2>
        <table>
            <tr><th>Rank</th><th>Hospital</th><th>Total Supply (ml)</th></tr>
            <?php $rank = 1; foreach ($topHospitals as $hid => $vol): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($hospitalNames[$hid] ?? $hid) ?></td>
                <td><?= number_format($vol, 1) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2>👤 Top 10 Blood Donor Contributors</h2>
        <table>
            <tr><th>Rank</th><th>Full Name</th><th>Total Donated Units</th></tr>
            <?php $rank = 1; foreach ($topDonors as $uid => $tot): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($donorNames[$uid] ?? 'Unknown') ?></td>
                <td><?= number_format($tot, 1) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card chart-area">
        <div class="chart-container">
            <h3>🌍 Monthly Donation Trends (Count)</h3>
            <canvas id="donationChart"></canvas>
        </div>
        <div class="chart-container">
            <h3>🩸 Total Blood Volume by Type</h3>
            <canvas id="volumeChart"></canvas>
        </div>
    </div>
</div>

<script>
const monthlyLabels = <?= json_encode(array_keys($monthlyTrends)) ?>;
const monthlyValues = <?= json_encode(array_values($monthlyTrends)) ?>;
const typeLabels = <?= json_encode(array_keys($volumeByType)) ?>;
const typeVolumes = <?= json_encode(array_values($volumeByType)) ?>;

// Line Chart: Monthly Donation Trends
new Chart(document.getElementById('donationChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'No. of Donations',
            data: monthlyValues,
            borderColor: 'blue',
            backgroundColor: 'lightblue',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
    }
});

// Bar Chart: Blood Volume by Type
new Chart(document.getElementById('volumeChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: typeLabels,
        datasets: [{
            label: 'Total Volume Donated (ml)',
            data: typeVolumes,
            backgroundColor: 'salmon'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
</body>
</html>
