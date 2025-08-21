<?php
include 'header.php';
include 'supabase_connector.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

$filter = [];
if (isset($_GET['filter_blood']) && $_GET['filter_blood'] !== '') {
    $filter[] = "blood_type=eq." . urlencode($_GET['filter_blood']);
}
if (isset($_GET['filter_gender']) && $_GET['filter_gender'] !== '') {
    $filter[] = "gender=eq.{$_GET['filter_gender']}";
}
$filterQuery = $filter ? 'select=*&' . implode('&', $filter) : 'select=*';

$donors = fetch_from_supabase("donor", $filterQuery);
$donations = fetch_from_supabase("blood_donation", "select=donation_date");

if (!empty($_GET['sort_by'])) {
    $sortKey = $_GET['sort_by'];
    usort($donors, function ($a, $b) use ($sortKey) {
        return strcmp($b[$sortKey], $a[$sortKey]);
    });
}

$bloodCounts = [];
$genderCounts = ['M' => 0, 'F' => 0];
$recentDate = '';
if (is_array($donors)) {
    foreach ($donors as $d) {
        $bt = $d['blood_type'] ?? '-';
        $gender = $d['gender'] ?? '-';
        $bloodCounts[$bt] = ($bloodCounts[$bt] ?? 0) + 1;
        $genderCounts[$gender]++;
        if (!empty($d['last_donate_date']) && $d['last_donate_date'] > $recentDate) {
            $recentDate = $d['last_donate_date'];
        }
    }
}

$donationTrend = [];
$yearSelected = $_GET['filter_year'] ?? date('Y');
if (is_array($donations)) {
    foreach ($donations as $d) {
        $date = $d['donation_date'] ?? null;
        if ($date && strpos($date, $yearSelected) === 0) {
            $month = date('F', strtotime($date));
            $donationTrend[$month] = ($donationTrend[$month] ?? 0) + 1;
        }
    }
}
krsort($donationTrend);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Donors</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container { padding: 20px; max-width: 1400px; margin: auto; }
        h2 { border-left: 5px solid #c00; padding-left: 10px; color: #333; }
        .top-button { margin-bottom: 10px; }
        .top-button a, .top-button button { background-color: #007bff; color: white; padding: 8px 14px; border: none; border-radius: 4px; text-decoration: none; }
        .controls { margin: 15px 0; }
        .controls label { margin-right: 10px; }
        select, button { padding: 5px; margin-right: 10px; }
        .main-section { display: flex; flex-wrap: wrap; gap: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 0 5px rgba(0,0,0,0.1); flex: 1; min-width: 400px; }
        .table-section { flex: 2; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        canvas { width: 100%; height: auto; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage DONOR</h2>
    <div class="top-button">
        <a href="staff_users.php">View staff</a>
    </div>
    <?php if ($role !== 'superadmin'): ?>
        <p>You have read-only access to donor data. To modify donor info, please contact a superadmin.</p>
    <?php endif; ?>

    <div class="controls">
        <form method="get">
            <label>Filter by Blood Type:
                <select name="filter_blood">
                    <option value="">-- All --</option>
                    <?php foreach (["A+","A-","B+","B-","AB+","AB-","O+","O-"] as $b): ?>
                        <option value="<?= $b ?>" <?= ($_GET['filter_blood'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Gender:
                <select name="filter_gender">
                    <option value="">-- All --</option>
                    <option value="F" <?= ($_GET['filter_gender'] ?? '') === 'F' ? 'selected' : '' ?>>F</option>
                    <option value="M" <?= ($_GET['filter_gender'] ?? '') === 'M' ? 'selected' : '' ?>>M</option>
                </select>
            </label>
            <label>Sort by:
                <select name="sort_by">
                    <option value="">-- None --</option>
                    <option value="last_donate_date" <?= ($_GET['sort_by'] ?? '') === 'last_donate_date' ? 'selected' : '' ?>>Last Donate Date</option>
                    <option value="full_name" <?= ($_GET['sort_by'] ?? '') === 'full_name' ? 'selected' : '' ?>>Name</option>
                </select>
            </label>
            <label>Year:
                <select name="filter_year">
                    <?php foreach (range(date('Y'), 2020) as $y): ?>
                        <option value="<?= $y ?>" <?= $yearSelected == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="main-section">
        <div class="card">
            <h3>Monthly Donation Trend (<?= $yearSelected ?>)</h3>
            <canvas id="trendChart"></canvas>

            <h3>Aggregate Report</h3>
            <ul>
                <li>Total Donors: <?= count($donors) ?></li>
                <li>Last Recorded Donation: <?= $recentDate ?: 'N/A' ?></li>
                <li>Gender: Male = <?= $genderCounts['M'] ?>, Female = <?= $genderCounts['F'] ?></li>
                <li>Blood Types:
                    <ul>
                        <?php foreach ($bloodCounts as $type => $count): ?>
                            <li><?= $type ?> : <?= $count ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="card table-section">
            <h3>Donor List</h3>
            <table>
                <tr><th>No</th><th>Donor ID</th><th>Full Name</th><th>Gender</th><th>Blood Type</th><th>Last Donated</th></tr>
                <?php if (is_array($donors)): foreach ($donors as $i => $d): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= $d['user_id'] ?></td>
                        <td><?= $d['full_name'] ?></td>
                        <td><?= $d['gender'] ?></td>
                        <td><?= $d['blood_type'] ?></td>
                        <td><?= $d['last_donate_date'] ?: '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_reverse(array_keys($donationTrend))) ?>,
        datasets: [{
            label: 'Donations per Month',
            data: <?= json_encode(array_reverse(array_values($donationTrend))) ?>,
            fill: false,
            borderColor: 'red',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Donation Trend by Month' }
        }
    }
});
</script>
</body>
</html>
