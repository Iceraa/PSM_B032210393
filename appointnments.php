<?php
include 'navigation.php'; // navigation includes header
include 'supabase_connector.php';

$role = $_SESSION['role'] ?? 'staff';
$hospitalId = $_SESSION['hospital_id'] ?? null;

// Handle update/soft-delete (cancel)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $aid = $_POST['appointment_id'];
    if ($_POST['action'] === 'update' && isset($_POST['status'])) {
        // Update status
        $ok = update_supabase('appointment', ['status' => $_POST['status']], "appointment_id=eq.$aid");
        $msg = $ok ? "Appointment updated." : "Failed to update appointment.";
    } elseif ($_POST['action'] === 'cancel') {
        // Soft delete = set status to canceled
        $ok = update_supabase('appointment', ['status' => 'canceled'], "appointment_id=eq.$aid");
        $msg = $ok ? "Appointment canceled." : "Failed to cancel.";
    }
}

// Fetch hospitals for dropdown (superadmin)
$hospitals = [];
if ($role === 'superadmin') {
    $hospitals = fetch_from_supabase('hospital', 'select=hospital_id,name');
}

// Filters
$where = [];
if ($role !== 'superadmin') {
    $where[] = "hospital_id=eq.$hospitalId";
} else {
    $selectedHospital = $_GET['hospital'] ?? '';
    if ($selectedHospital) $where[] = "hospital_id=eq.$selectedHospital";
}

// Filtering options (status/date/search)
if (!empty($_GET['status'])) {
    $where[] = "status=eq." . urlencode($_GET['status']);
}
if (!empty($_GET['search'])) {
    $where[] = "appointment_id=ilike.*" . urlencode($_GET['search']) . "*";
}
if (!empty($_GET['date'])) {
    $where[] = "appointment_date=eq." . urlencode($_GET['date']);
}

$query = 'select=*' . ($where ? '&' . implode('&', $where) : '');
$appointments = fetch_from_supabase('appointment', $query);

// Sort (simple)
if (!empty($_GET['sort_by'])) {
    $key = $_GET['sort_by'];
    usort($appointments, fn($a, $b) => strcmp($a[$key], $b[$key]));
}

// Aggregate/Report Example
$total = count($appointments);
$statusCounts = [];
foreach ($appointments as $a) {
    $status = $a['status'] ?? 'unknown';
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Appointments</title>
    <style>
        body { background: #f8fafc; }
        .container { padding: 22px 18px 0 220px; }
        .oracle-table { border-collapse: collapse; width: 100%; margin-top: 16px;}
        .oracle-table th, .oracle-table td { border: 1px solid #bbb; padding: 7px 9px; text-align: center; }
        .oracle-table th { background: #e4e4e4; }
        .oracle-table tr.selected { background: #ffe5e5; }
        .action-bar, .filter-bar { display: flex; gap: 20px; align-items: center; margin-bottom: 16px;}
        .dropdown { padding: 5px 8px; }
        .btn { background: #b30000; color: #fff; border: none; border-radius: 4px; padding: 6px 16px; cursor: pointer; }
        .btn-cancel { background: #d32f2f; }
        .msg { color: #1976d2; margin-bottom: 8px; }
        .agg-card { display:inline-block; margin-right:18px; padding:8px 22px; background:#fff8e1; border-radius:8px;}
    </style>
    <script>
    // (Optional) row select, edit/cancel handlers, etc.
    </script>
</head>
<body>
<div class="container">
    <h2>Appointments</h2>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="action-bar">
        <?php if ($role === 'superadmin'): ?>
        <form method="get" style="display:inline;">
            <label for="hospital">Hospital: </label>
            <select name="hospital" id="hospital" class="dropdown" onchange="this.form.submit()">
                <option value="">All Hospitals</option>
                <?php foreach ($hospitals as $h): ?>
                    <option value="<?= $h['hospital_id'] ?>" <?= ($_GET['hospital'] ?? '') === $h['hospital_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($h['name'] ?? $h['hospital_id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <form method="get" class="filter-bar" style="display:inline; margin-left:10px;">
            <input type="hidden" name="hospital" value="<?= htmlspecialchars($_GET['hospital'] ?? '') ?>">
            <input type="text" name="search" placeholder="Search Appointment ID" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <select name="status" class="dropdown">
                <option value="">All Status</option>
                <?php foreach (["pending", "confirmed", "completed", "canceled"] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
            <select name="sort_by" class="dropdown">
                <option value="">Sort By</option>
                <option value="appointment_date" <?= ($_GET['sort_by'] ?? '') === 'appointment_date' ? 'selected' : '' ?>>Date</option>
                <option value="status" <?= ($_GET['sort_by'] ?? '') === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
            <button type="submit" class="btn">Apply</button>
        </form>
        <!-- Dropdown for report/agg/etc -->
        <div style="margin-left:auto;">
            <div class="dropdown" style="border:1px solid #bbb;cursor:pointer;" onclick="document.getElementById('actionMenu').style.display='block';">
                ACTION &#x25BC;
            </div>
            <div id="actionMenu" style="display:none;position:absolute; background:#fff; border:1px solid #ccc; z-index:10;">
                <div onclick="alert('Filter: Use the controls above!')">Filter</div>
                <div onclick="alert('Sort: Use the sort dropdown!')">Sort</div>
                <div onclick="document.getElementById('aggBox').style.display='block';">Aggregate</div>
                <div onclick="window.print()">Report</div>
                <div onclick="alert('Download coming soon!')">Download</div>
                <div onclick="alert('Chart coming soon!')">Chart</div>
            </div>
        </div>
    </div>
    <!-- AGGREGATE BOX -->
    <div id="aggBox" style="display:none;">
        <h4>Aggregate Report</h4>
        <div class="agg-card">Total: <?= $total ?></div>
        <?php foreach ($statusCounts as $stat => $cnt): ?>
            <div class="agg-card"><?= ucfirst($stat) ?>: <?= $cnt ?></div>
        <?php endforeach; ?>
        <button class="btn" onclick="document.getElementById('aggBox').style.display='none';">Close</button>
    </div>

    <!-- MAIN TABLE -->
    <form method="post">
        <table class="oracle-table">
            <tr>
                <th>No</th>
                <th>Appointment ID</th>
                <th>Date</th>
                <th>Status</th>
                <th>Hospital</th>
                <th>Donor</th>
                <th>Action</th>
            </tr>
            <?php foreach ($appointments as $i => $a): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= $a['appointment_id'] ?></td>
                <td><?= $a['appointment_date'] ?></td>
                <td>
                    <?php if ($role === 'superadmin'): ?>
                        <?= htmlspecialchars($a['status']) ?>
                    <?php else: ?>
                        <select name="status" <?= $a['status'] === 'canceled' ? 'disabled' : '' ?>>
                            <?php foreach (["pending","confirmed","completed","canceled"] as $s): ?>
                                <option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
                <td><?= $a['hospital_id'] ?></td>
                <td><?= $a['donor_id'] ?></td>
                <td>
                    <?php if ($role !== 'superadmin'): ?>
                        <button type="submit" class="btn" name="action" value="update" onclick="this.form.appointment_id.value='<?= $a['appointment_id'] ?>';">Save</button>
                        <button type="submit" class="btn btn-cancel" name="action" value="cancel" onclick="this.form.appointment_id.value='<?= $a['appointment_id'] ?>';return confirm('Cancel this appointment?');">Cancel</button>
                        <input type="hidden" name="appointment_id" value="">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </form>
</div>
<script>
// Simple dropdown for actions
document.addEventListener('click', function(e){
    var m = document.getElementById('actionMenu');
    if (m && !m.contains(e.target) && e.target.innerText !== "ACTION ▼") m.style.display='none';
});
</script>
</body>
</html>
