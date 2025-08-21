<?php

include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

$message = "";

// --- Handle update/soft delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $appId = $_POST['appointment_id'];
    if ($_POST['action'] === 'update') {
        $update = [
            'appointment_date' => $_POST['appointment_date'] ?? null,
            'status' => $_POST['status'] ?? null,
        ];
        $ok = update_supabase('appointment', $update, "appointment_id=eq.$appId");
        $message = $ok ? "Appointment updated successfully." : "Failed to update appointment.";
    } elseif ($_POST['action'] === 'cancel') {
        $ok = update_supabase('appointment', ['status' => 'canceled'], "appointment_id=eq.$appId");
        $message = $ok ? "Appointment canceled." : "Failed to cancel appointment.";
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- Fetch appointments ---
$filter = [];
if ($role !== 'superadmin') {
    $filter[] = "hospital_id=eq.$hospitalId";
}
$filterQuery = $filter ? 'select=*' . '&' . implode('&', $filter) : 'select=*';
$appointments = fetch_from_supabase("appointment", $filterQuery);

// --- Fetch hospital and donor maps ---
$hospitals = fetch_from_supabase("hospital", "select=hospital_id,name");
$donors = fetch_from_supabase("donor", "select=user_id,full_name");

$hospitalMap = [];
foreach ($hospitals as $h) {
    $hospitalMap[$h['hospital_id']] = $h['name'] ?? '-';
}
$donorMap = [];
foreach ($donors as $d) {
    $donorMap[$d['user_id']] = $d['full_name'] ?? '-';
}

// --- Apply filters ---
if (!empty($_GET['filter_status'])) {
    $appointments = array_filter($appointments, function($a) {
        return $a['status'] === $_GET['filter_status'];
    });
}

if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
    $appointments = array_filter($appointments, function($a) {
        $date = $a['appointment_date'] ?? '';
        return (!$date || 
               (!empty($_GET['start_date']) && $date < $_GET['start_date']) ||
               (!empty($_GET['end_date']) && $date > $_GET['end_date'])) ? false : true;
    });
}

if (!empty($_GET['sort_by'])) {
    usort($appointments, function($a, $b) {
        return ($_GET['sort_by'] === 'asc')
            ? strcmp($a['appointment_date'], $b['appointment_date'])
            : strcmp($b['appointment_date'], $a['appointment_date']);
    });
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Appointments</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container {
            display: flex;
            flex-direction: row;
            padding: 20px;
            max-width: 1400px;
            margin: auto;
            margin-left: 230px;
            margin-top: 40px;
        }
        .side-form { flex: 0 0 350px; min-width: 320px; margin-right: 32px; }
        .main-table { flex: 1; }
        h2 { border-left: 5px solid #b00; padding-left: 10px; color: #222; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        tr.selected { background: #fff3cd !important; }
        .form-section {
            background: #fff;
            padding: 16px 22px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-top: 10px;
        }
        .form-label { font-weight: 600; display: block; margin-top: 10px; }
        .form-input { width: 100%; padding: 6px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-update { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-cancel { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .btn-clear { background: #ccc; color: #333; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .msg { color: #1976d2; margin-bottom: 8px; }
        .controls { margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    </style>
    <script>
    let appointments = <?= json_encode($appointments) ?>;

    function selectRow(idx) {
        let rows = document.querySelectorAll('.app-row');
        rows.forEach(r => r.classList.remove('selected'));
        let row = document.getElementById('row_' + idx);
        if (row) row.classList.add('selected');

        let appt = appointments[idx];
        if (appt) {
            document.getElementById('form_appointment_id').value = appt['appointment_id'] || '';
            document.getElementById('form_appointment_date').value = appt['appointment_date'] || '';
            document.getElementById('form_status').value = appt['status'] || '';
            document.getElementById('editForm').style.display = '';
        }
    }

    function clearForm() {
        document.querySelectorAll('.app-row').forEach(r => r.classList.remove('selected'));
        document.getElementById('form_appointment_id').value = '';
        document.getElementById('form_appointment_date').value = '';
        document.getElementById('form_status').value = '';
        document.getElementById('editForm').style.display = 'none';
    }

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
        link.download = "appointments.csv";
        link.click();
    }
    </script>
</head>
<body>
<div class="container">
    <!-- Side Form -->
    <div class="side-form">
        <?php if ($role !== 'superadmin'): ?>
        <form method="post" class="form-section" id="editForm" style="display:none;">
            <input type="hidden" id="form_appointment_id" name="appointment_id">
            <label class="form-label">Appointment Date</label>
            <input class="form-input" type="date" id="form_appointment_date" name="appointment_date" required>
            <label class="form-label">Status</label>
            <select class="form-input" id="form_status" name="status" required>
                <?php foreach (["scheduled","confirmed","completed","canceled","no_show"] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-update" name="action" value="update">Update</button>
            <button type="submit" class="btn-cancel" name="action" value="cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</button>
            <button type="button" class="btn-clear" onclick="clearForm()">Clear</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Main Table -->
    <div class="main-table">
        <h2>Manage Appointments</h2>
        <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <!-- Filters -->
        <form method="get" class="controls">
            <label>Status:
                <select name="filter_status">
                    <option value="">-- All --</option>
                    <?php foreach (["scheduled","confirmed","completed","canceled","no_show"] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_GET['filter_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
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
                    <option value="asc" <?= ($_GET['sort_by'] ?? '') === 'asc' ? 'selected' : '' ?>>Date ↑</option>
                    <option value="desc" <?= ($_GET['sort_by'] ?? '') === 'desc' ? 'selected' : '' ?>>Date ↓</option>
                </select>
            </label>
            <button type="submit">Apply</button>
            <button type="button" onclick="window.print()">🖨️ Print</button>
            <button type="button" onclick="exportTableToCSV()">⬇ Export CSV</button>
        </form>

        <div class="card table-section">
            <h3>Appointment List</h3>
            <table>
                <tr>
                    <th>No</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Hospital</th>
                    <th>Donor</th>
                    <th>Request</th>
                    <th>Invite</th>
                </tr>
                <?php if (is_array($appointments)): foreach ($appointments as $i => $a): ?>
                    <tr class="app-row" id="row_<?= $i ?>" onclick="selectRow(<?= $i ?>)">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($a['appointment_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($hospitalMap[$a['hospital_id']] ?? '-') ?></td>
                        <td><?= htmlspecialchars($donorMap[$a['user_id']] ?? '-') ?></td>
                        <td><?= !empty($a['request_id']) ? '✔️' : '❌' ?></td>
                        <td><?= !empty($a['invite_id']) ? '✔️' : '❌' ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">No appointments found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
