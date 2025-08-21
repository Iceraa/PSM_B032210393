<?php
include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

// Build filter for admin/staff (not for superadmin)
$filter = [];
if ($role !== 'superadmin') {
    $filter[] = "hospital_id=eq.$hospitalId";
}
$filterQuery = $filter ? 'select=*' . '&' . implode('&', $filter) : 'select=*';

// Fetch blood supply data
$blood_supplies = fetch_from_supabase("blood_supply", $filterQuery);

// Prepare chart data (grouped: Available vs Expired)
$today = date('Y-m-d');
$chartAvailable = []; // unexpired volume per type
$chartExpired   = []; // expired volume per type

if (is_array($blood_supplies)) {
    foreach ($blood_supplies as $row) {
        $type = $row['blood_type'] ?? '-';
        $vol  = (float)($row['volume_ml'] ?? 0);
        $exp  = $row['exp_date'] ?? null;
        $status = strtolower($row['status'] ?? '');

        // consider it expired if status says 'expired' OR exp_date < today
        $isExpired = ($status === 'expired') || ($exp && $exp < $today);

        if ($isExpired) {
            $chartExpired[$type] = ($chartExpired[$type] ?? 0) + $vol;
        } else {
            $chartAvailable[$type] = ($chartAvailable[$type] ?? 0) + $vol;
        }
    }
}

// Apply filtering and sorting for the TABLE (unchanged logic, just reused)
if (!empty($_GET['filter_type'])) {
    $blood_supplies = array_filter($blood_supplies ?? [], fn($s) => ($s['blood_type'] ?? '') === $_GET['filter_type']);
}
if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
    $blood_supplies = array_filter($blood_supplies ?? [], function ($s) {
        $date = $s['exp_date'] ?? '';
        if (!$date) return false;
        if (!empty($_GET['start_date']) && $date < $_GET['start_date']) return false;
        if (!empty($_GET['end_date']) && $date > $_GET['end_date']) return false;
        return true;
    });
}
if (!empty($_GET['sort_by'])) {
    usort($blood_supplies, function ($a, $b) {
        $aDate = $a['exp_date'] ?? '';
        $bDate = $b['exp_date'] ?? '';
        return ($_GET['sort_by'] === 'asc') ? strcmp($aDate, $bDate) : strcmp($bDate, $aDate);
    });
}

// Build labels in a stable order (only types that appear)
$ALL_TYPES = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
$presentTypes = array_unique(array_merge(array_keys($chartAvailable), array_keys($chartExpired)));
$labels = array_values(array_filter($ALL_TYPES, fn($t) => in_array($t, $presentTypes, true)));

// Ensure data arrays aligned with labels
$availData = [];
$expData   = [];
foreach ($labels as $t) {
    $availData[] = (float)($chartAvailable[$t] ?? 0);
    $expData[]   = (float)($chartExpired[$t] ?? 0);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Blood Supply</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container { padding: 20px; max-width: 1400px; margin: auto; margin-left: 230px; margin-top: 40px; }
        h2 { border-left: 5px solid #b00; padding-left: 10px; color: #222; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        .controls { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .controls label { font-weight: 500; }
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; }
        .btn-print { background: #333; color: white; }
        .btn-export { background: #007bff; color: white; }

        /* Chart size control (smaller & adjustable) */
        .chart-wrap {
            height: 280px;         /* tweak this height to your liking */
            max-width: 760px;      /* cap width so it doesn’t stretch too wide */
            position: relative;
            margin-bottom: 20px;
        }
        .chart-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }
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
        link.download = "blood_supply.csv";
        link.click();
    }
    </script>
</head>
<body>
<div class="container">
    <h2>Manage Blood Supply</h2>

    <!-- Grouped Bar Chart: Available vs Expired -->
    <div class="chart-wrap">
        <canvas id="bloodChart"></canvas>
    </div>
    <script>
    const ctx = document.getElementById('bloodChart').getContext('2d');

    // Red family colors (lighter for Available, darker for Expired)
    const redLight = '#ef4444';
    const redDark  = '#991b1b';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Available (Unexpired)',
                    data: <?= json_encode($availData) ?>,
                    backgroundColor: redLight,
                    borderColor: '#7f1d1d',
                    borderWidth: 1
                },
                {
                    label: 'Expired',
                    data: <?= json_encode($expData) ?>,
                    backgroundColor: redDark,
                    borderColor: '#7f1d1d',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // the .chart-wrap height dictates size
            plugins: {
                legend: { display: true, position: 'top' },
                tooltip: {
                    callbacks: {
                        label: (item) => ` ${item.dataset.label}: ${item.formattedValue}`
                    }
                }
            },
            scales: {
                x: {
                    stacked: false, // grouped (side-by-side) like your example
                    ticks: { autoSkip: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
    </script>

    <!-- Filters (unchanged) -->
    <form method="get" class="controls">
        <label>Blood Type:
            <select name="filter_type">
                <option value="">-- All --</option>
                <?php foreach (["A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"] as $type): ?>
                    <option value="<?= $type ?>" <?= ($_GET['filter_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Start Date:
            <input type="date" name="start_date" value="<?= $_GET['start_date'] ?? '' ?>">
        </label>
        <label>End Date:
            <input type="date" name="end_date" value="<?= $_GET['end_date'] ?? '' ?>">
        </label>
        <label>Sort:
            <select name="sort_by">
                <option value="">-- None --</option>
                <option value="asc" <?= ($_GET['sort_by'] ?? '') === 'asc' ? 'selected' : '' ?>>Expiry ↑</option>
                <option value="desc" <?= ($_GET['sort_by'] ?? '') === 'desc' ? 'selected' : '' ?>>Expiry ↓</option>
            </select>
        </label>
        <button type="submit">Apply</button>
        <button type="button" onclick="window.print()" class="btn btn-print">Print</button>
        <button type="button" onclick="exportTableToCSV()" class="btn btn-export">Export CSV</button>
    </form>

    <div class="card table-section">
        <h3>Blood Supply List</h3>
        <table>
            <tr>
                <th>No</th>
                <th>Blood Type</th>
                <th>Volume (ml)</th>
                <th>Expiry Date</th>
                <th>Status</th> <!-- NEW -->
            </tr>
            <?php if (is_array($blood_supplies) && count($blood_supplies)): foreach ($blood_supplies as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['blood_type'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['volume_ml'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['exp_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['status'] ?? '-') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">No blood supply found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>
