<?php
include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';
$donations = [];
$donorMap = [];

// Fetch donor names
$donors = fetch_from_supabase("donor", "select=user_id,full_name");
foreach ($donors as $d) {
    $donorMap[$d['user_id']] = $d['full_name'] ?? '-';
}

if ($role !== 'superadmin') {
    $supplies = fetch_from_supabase("blood_supply", "select=donation_id&hospital_id=eq.$hospitalId");
    $donation_ids = [];
    if (is_array($supplies)) {
        foreach ($supplies as $s) {
            if (!empty($s['donation_id'])) {
                $donation_ids[] = $s['donation_id'];
            }
        }
    }
    if (!empty($donation_ids)) {
        $id_list = implode(',', array_map(fn($id) => '"' . $id . '"', $donation_ids));
        $filter = "select=*&donation_id=in.($id_list)";
        $donations = fetch_from_supabase("blood_donation", $filter);
    }
} else {
    $donations = fetch_from_supabase("blood_donation", "select=*");
}

// Apply filters and sort
if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
    $donations = array_filter($donations, function ($d) {
        $date = $d['donation_date'] ?? '';
        if (!$date) return false;
        if (!empty($_GET['start_date']) && $date < $_GET['start_date']) return false;
        if (!empty($_GET['end_date']) && $date > $_GET['end_date']) return false;
        return true;
    });
}
if (!empty($_GET['sort_by'])) {
    usort($donations, function ($a, $b) {
        $aDate = $a['donation_date'] ?? '';
        $bDate = $b['donation_date'] ?? '';
        return ($_GET['sort_by'] === 'asc') ? strcmp($aDate, $bDate) : strcmp($bDate, $aDate);
    });
}

// Prepare trend data (volume by month)
$trendData = [];
foreach ($donations as $d) {
    $date = $d['donation_date'] ?? null;
    if ($date) {
        $month = date('Y-m', strtotime($date));
        $trendData[$month] = ($trendData[$month] ?? 0) + floatval($d['unit_donated'] ?? 0);
    }
}
ksort($trendData);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blood Donations</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container { padding: 20px; max-width: 1400px; margin: auto; margin-left: 230px; margin-top: 40px; }
        h2 { border-left: 5px solid #b00; padding-left: 10px; color: #222; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        .controls { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; }
        .btn-print { background: #333; color: white; }
        .btn-export { background: #007bff; color: white; }
        .chart-container { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    </style>
    <script>
    function exportTableToCSV() {
        const rows = Array.from(document.querySelectorAll("table tr"));
        const csv = rows.map(row => {
            const cells = Array.from(row.querySelectorAll("th, td")).map(cell =>
                '"' + cell.innerText.replace(/"/g, '""') + '"'
            );
            return cells.join(",");
        }).join("\n");

        const blob = new Blob([csv], { type: "text/csv" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "blood_donations.csv";
        link.click();
    }
    </script>
</head>
<body>
<div class="container">
    <h2>Blood Donation History</h2>

    <!-- Chart -->
    <div class="chart-container">
        <canvas id="donationTrend" height="100"></canvas>
    </div>
    <script>
    const ctx = document.getElementById('donationTrend').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($trendData)) ?>,
            datasets: [{
                label: 'Total Units Donated (Monthly)',
                data: <?= json_encode(array_values($trendData)) ?>,
                backgroundColor: 'rgba(220,53,69,0.5)',
                borderColor: 'rgba(220,53,69,1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
    </script>

    <!-- Filters -->
    <form method="get" class="controls">
        <label>Start Date:
            <input type="date" name="start_date" value="<?= $_GET['start_date'] ?? '' ?>">
        </label>
        <label>End Date:
            <input type="date" name="end_date" value="<?= $_GET['end_date'] ?? '' ?>">
        </label>
        <label>Sort:
            <select name="sort_by">
                <option value="">-- None --</option>
                <option value="asc" <?= ($_GET['sort_by'] ?? '') === 'asc' ? 'selected' : '' ?>>Date ↑</option>
                <option value="desc" <?= ($_GET['sort_by'] ?? '') === 'desc' ? 'selected' : '' ?>>Date ↓</option>
            </select>
        </label>
        <button type="submit">Apply</button>
        <button type="button" onclick="window.print()" class="btn btn-print">Print</button>
        <button type="button" onclick="exportTableToCSV()" class="btn btn-export">Export CSV</button>
    </form>

    <div class="card table-section">
        <h3>Donation List</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Donation Date</th>
                <th>Unit Donated</th>
                <th>Donor Name</th>
            </tr>
            <?php if (is_array($donations) && count($donations) > 0): foreach ($donations as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($d['donation_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['unit_donated'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($donorMap[$d['user_id']] ?? '-') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No donations found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>
