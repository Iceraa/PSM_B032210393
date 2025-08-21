<?php
// blood_given.php  — Admin view for blood_given stats
include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

// --- Guard: must have a hospital (admins of each hospital) ---
if (!$hospitalId && $role !== 'superadmin') {
    echo "<p style='margin:40px'>Unauthorized: hospital is required.</p>";
    exit;
}

// ---------- Reference Data ----------
$BLOOD_TYPES = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];

// Same list you showed in Flutter (kept wording identical)
$PURPOSE_OPTIONS = [
    'Emergency/Trauma',
    'Surgery',
    'Childbirth',
    'Anemia (iron deficiency)',
    'Cancer treatment',
    'Blood disorders',
    'Platelet transfusion',
    'Plasma transfusion',
    'Other',
];

// Staff map (limit to this hospital unless superadmin viewing all)
$staffFilter = ($role === 'superadmin')
    ? 'select=user_id,full_name,hospital_id'
    : 'select=user_id,full_name&hospital_id=eq.'.$hospitalId;

$staffRows = fetch_from_supabase('staff', $staffFilter);
$staffMap = [];
if (is_array($staffRows)) {
    foreach ($staffRows as $r) {
        $staffMap[$r['user_id']] = $r['full_name'];
    }
}

// ---------- Filters (GET) ----------
$filterBlood   = $_GET['blood_type'] ?? '';   // '' means all
$filterPurpose = $_GET['purpose'] ?? '';      // '' means all
$filterStaff   = $_GET['given_by'] ?? '';     // '' means all

// (Optional for superadmin) allow selecting a hospital to view
if ($role === 'superadmin') {
    $viewHospital = $_GET['hospital_id'] ?? '';
} else {
    $viewHospital = $hospitalId;
}

// Build Supabase filter query
$clauses = [];
$clauses[] = 'select=*';
if ($viewHospital) $clauses[] = 'hospital_id=eq.'.$viewHospital;
if ($filterBlood)  $clauses[] = 'blood_type=eq.'.urlencode($filterBlood);
if ($filterPurpose)$clauses[] = 'purpose=eq.'.urlencode($filterPurpose);
if ($filterStaff)  $clauses[] = 'given_by=eq.'.urlencode($filterStaff);

$query = implode('&', $clauses);

// Fetch blood_given rows
$rows = fetch_from_supabase('blood_given', $query);
if (!is_array($rows)) $rows = [];

// ---------- Aggregations ----------
// Purpose pie
$purposeCounts = [];
foreach ($rows as $row) {
    $p = trim($row['purpose'] ?? 'Unknown');
    if ($p === '') $p = 'Unknown';
    if (!isset($purposeCounts[$p])) $purposeCounts[$p] = 0;
    $purposeCounts[$p]++;
}
// ensure stable order with known purposes first
foreach ($PURPOSE_OPTIONS as $p) {
    if (!isset($purposeCounts[$p])) $purposeCounts[$p] = 0;
}

// Monthly trend (YYYY-MM)
$monthlyCounts = [];
foreach ($rows as $row) {
    $d = $row['date_given'] ?? null;
    if (!$d) continue;
    // normalize to YYYY-MM
    $ym = substr($d, 0, 7);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) continue;
    if (!isset($monthlyCounts[$ym])) $monthlyCounts[$ym] = 0;
    $monthlyCounts[$ym]++;
}
// sort months ascending
ksort($monthlyCounts);

// Table data: replace IDs with names; hide all IDs
function h($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blood Given — Hospital Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background:#f8fafc; }
        .container { padding:20px; max-width:1400px; margin:auto; margin-left:230px; margin-top:40px; }
        h2 { margin-bottom:6px; }
        .muted { color:#6b7280; margin:0 0 16px 0; }
        form.filters { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin:12px 0 20px; }
        .filters label { display:block; font-weight:600; margin-bottom:4px; }
        .filters .field { min-width:180px; }
        .filters select, .filters input { width:100%; padding:6px; border:1px solid #d1d5db; border-radius:6px; }
        .btn { padding:8px 14px; border:none; border-radius:6px; cursor:pointer; }
        .btn-apply { background:#2563eb; color:#fff; }
        .btn-print { background:#f3f4f6; }
        .btn-export { background:#f3f4f6; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:18px; }
        .card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08); padding:18px; }
        .card h3 { margin:0 0 10px; font-size:18px; }
        #purposeChart {
            max-height: 260px !important;  /* pie chart */
        }
        #monthChart {
            max-height: 320px !important;  /* trend chart */
        }

        .table-wrap { overflow:auto; }
        table { border-collapse:collapse; width:100%; }
        th, td { border:1px solid #e5e7eb; padding:8px; text-align:center; }
        th { background:#f9fafb; }
    </style>
</head>
<body>
<div class="container">
    <h2>Blood Given — Statistics</h2>
    <p class="muted">View totals for your hospital, filter by blood type, purpose, or staff, and see distribution & trends.</p>

    <form class="filters" method="get">
        <?php if ($role === 'superadmin'): ?>
        <div class="field">
            <label>Hospital ID</label>
            <input type="text" name="hospital_id" value="<?= h($viewHospital) ?>" placeholder="Leave empty for all">
        </div>
        <?php endif; ?>

        <div class="field">
            <label>Blood Type</label>
            <select name="blood_type">
                <option value="">All</option>
                <?php foreach ($BLOOD_TYPES as $bt): ?>
                    <option value="<?= $bt ?>" <?= ($filterBlood === $bt ? 'selected':'') ?>><?= $bt ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="min-width:220px">
            <label>Purpose</label>
            <select name="purpose">
                <option value="">All</option>
                <?php foreach ($PURPOSE_OPTIONS as $p): ?>
                    <option value="<?= h($p) ?>" <?= ($filterPurpose === $p ? 'selected':'') ?>><?= h($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field" style="min-width:220px">
            <label>Given By (Staff)</label>
            <select name="given_by">
                <option value="">All</option>
                <?php foreach ($staffMap as $uid => $name): ?>
                    <option value="<?= h($uid) ?>" <?= ($filterStaff === $uid ? 'selected':'') ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; gap:8px;">
            <button class="btn btn-apply" type="submit">Apply</button>
            <button class="btn btn-print" type="button" onclick="window.print()">🖨️ Print</button>
            <button class="btn btn-export" type="button" onclick="exportTableToCSV('blood_given.csv')">⬇ Export</button>
        </div>
    </form>

    <div class="grid">
        <div class="card">
            <h3>Purpose Distribution</h3>
            <canvas id="purposeChart"></canvas>
        </div>
        <div class="card">
            <h3>Monthly Blood Given Trend</h3>
            <canvas id="monthChart"></canvas>
        </div>
    </div>

    <div class="card" style="margin-top:18px;">
        <h3>Records (IDs hidden)</h3>
        <div class="table-wrap">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Date Given</th>
                        <th>Blood Type</th>
                        <th>Purpose</th>
                        <th>Given By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($rows) > 0): ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= h($r['date_given'] ?? '-') ?></td>
                            <td><?= h($r['blood_type'] ?? '-') ?></td>
                            <td><?= h($r['purpose'] ?? '-') ?></td>
                            <td><?= h($staffMap[$r['given_by'] ?? ''] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                        <tr><td colspan="5">No records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ---------- Charts Data from PHP ----------
const purposeLabels = <?= json_encode(array_keys($purposeCounts)) ?>;
const purposeData   = <?= json_encode(array_values($purposeCounts)) ?>;

const monthLabels   = <?= json_encode(array_keys($monthlyCounts)) ?>; // YYYY-MM
const monthData     = <?= json_encode(array_values($monthlyCounts)) ?>;

// ---------- Purpose Pie (Doughnut) ----------
new Chart(
    document.getElementById('purposeChart'),
    {
        type: 'doughnut',
        data: {
            labels: purposeLabels,
            datasets: [{
                data: purposeData,
                // colors left default; Chart.js will auto-pick
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            cutout: '50%',
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    }
);

// ---------- Monthly Line Chart ----------
new Chart(
    document.getElementById('monthChart'),
    {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Blood Given (count)',
                data: monthData,
                tension: 0.25, // slight smoothing
                pointRadius: 3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: { title: { display: true, text: 'Month (YYYY-MM)' } },
                y: { beginAtZero: true, title: { display: true, text: 'Count' }, ticks: { precision:0 } }
            },
            plugins: {
                legend: { display: false },
                title: { display: false },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    }
);

// ---------- CSV Export (IDs are not present in the table anyway) ----------
function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#dataTable tr");
    for (let row of rows) {
        let cols = Array.from(row.querySelectorAll("td, th"))
            .map(col => `"${col.innerText.replace(/"/g, '""')}"`);
        csv.push(cols.join(","));
    }
    let blob = new Blob([csv.join("\n")], { type: 'text/csv' });
    let a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
}
</script>
</body>
</html>
