<?php

include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

$message = "";

// --- Handle add/update/delete (soft delete = status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $data = [
            'hospital_id' => $hospitalId,
            'blood_type' => $_POST['blood_type'] ?? '',
            'unit_req' => $_POST['unit_req'] ?? '',
            'request_date' => $_POST['request_date'] ?? '',
            'deadline_date' => $_POST['deadline_date'] ?? '',
            'status' => $_POST['status'] ?? 'pending',
            'no_of_donor' => $_POST['no_of_donor'] ?? 0
        ];
        $ok = insert_into_supabase('blood_request', $data);
        $message = $ok ? "Blood request added!" : "Failed to add blood request.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    // UPDATE/DELETE
    if (isset($_POST['request_id'])) {
        $id = $_POST['request_id'];
        if ($_POST['action'] === 'update') {
            $data = [
                'blood_type' => $_POST['blood_type'] ?? '',
                'unit_req' => $_POST['unit_req'] ?? '',
                'request_date' => $_POST['request_date'] ?? '',
                'deadline_date' => $_POST['deadline_date'] ?? '',
                'status' => $_POST['status'] ?? '',
                'no_of_donor' => $_POST['no_of_donor'] ?? 0
            ];
            $ok = update_supabase('blood_request', $data, "request_id=eq.$id");
            $message = $ok ? "Blood request updated." : "Update failed.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $ok = update_supabase('blood_request', ['status' => 'canceled'], "request_id=eq.$id");
            $message = $ok ? "Request marked as canceled." : "Failed to cancel request.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// --- Fetch blood requests ---
$filter = [];
if ($role !== 'superadmin') {
    $filter[] = "hospital_id=eq.$hospitalId";
}
$filterQuery = $filter ? 'select=*' . '&' . implode('&', $filter) : 'select=*';
$blood_requests = fetch_from_supabase("blood_request", $filterQuery);

// --- Apply filters and sorting ---
if (!empty($_GET['filter_status'])) {
    $blood_requests = array_filter($blood_requests, function ($r) {
        return $r['status'] === $_GET['filter_status'];
    });
}

if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
    $blood_requests = array_filter($blood_requests, function ($r) {
        $date = $r['request_date'] ?? '';
        if (!$date) return false;
        if (!empty($_GET['start_date']) && $date < $_GET['start_date']) return false;
        if (!empty($_GET['end_date']) && $date > $_GET['end_date']) return false;
        return true;
    });
}

if (!empty($_GET['sort_by'])) {
    usort($blood_requests, function ($a, $b) {
        $aDate = $a['request_date'] ?? '';
        $bDate = $b['request_date'] ?? '';
        return ($_GET['sort_by'] === 'asc') 
            ? strcmp($aDate, $bDate) 
            : strcmp($bDate, $aDate);
    });
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Blood Requests</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container {
            display: flex;
            flex-direction: row;
            padding: 20px;
            max-width: 1400px;
            margin: auto;
            margin-left: 230px;  /* for sidebar */
            margin-top: 40px;
        }
        .side-form { flex: 0 0 350px; min-width: 320px; margin-right: 32px; }
        .main-table { flex: 1; }
        h2 { border-left: 5px solid #b00; padding-left: 10px; color: #222; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        .btn-add { background: #007bff; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-bottom: 16px; cursor: pointer; }
        .btn-update { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-cancel, .btn-delete { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-clear { background: #ccc; color: #333; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .form-section {
            background: #fff;
            padding: 16px 22px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 22px;
        }
        .form-label { font-weight: 600; display: block; margin-top: 10px; }
        .form-input { width: 100%; padding: 6px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #ccc; }
        .msg { color: #1976d2; margin-bottom: 8px; }
        tr.selected { background: #fff3cd !important; }
    </style>
    <script>
    let requests = <?= json_encode($blood_requests) ?>;
    let editingIdx = null;
    function showEditForm(idx) {
        editingIdx = idx;
        document.getElementById('addForm').style.display = 'none';
        let r = requests[idx];
        if (r) {
            document.getElementById('edit_request_id').value = r.request_id || '';
            document.getElementById('edit_blood_type').value = r.blood_type || '';
            document.getElementById('edit_unit_req').value = r.unit_req || '';
            document.getElementById('edit_request_date').value = r.request_date || '';
            document.getElementById('edit_deadline_date').value = r.deadline_date || '';
            document.getElementById('edit_status').value = r.status || '';
            document.getElementById('edit_no_of_donor').value = r.no_of_donor || 0;
            document.getElementById('editForm').style.display = '';
            highlightRow(idx);
        }
    }
    function showAddForm() {
        editingIdx = null;
        clearSelection();
        document.getElementById('editForm').style.display = 'none';
        document.getElementById('addForm').reset();
        document.getElementById('addForm').style.display = '';
    }
    function clearEditForm() {
        editingIdx = null;
        document.getElementById('editForm').style.display = 'none';
        clearSelection();
    }
    function clearAddForm() {
        document.getElementById('addForm').style.display = 'none';
    }
    function highlightRow(idx) {
        document.querySelectorAll('.req-row').forEach(r => r.classList.remove('selected'));
        let row = document.getElementById('row_' + idx);
        if (row) row.classList.add('selected');
    }
    function clearSelection() {
        document.querySelectorAll('.req-row').forEach(r => r.classList.remove('selected'));
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
    link.download = "blood_requests.csv"; // Updated filename
    link.click();
    }

    </script>
</head>
<body>
<div class="container">
    <!-- Side Form -->
    <div class="side-form">
        <?php if ($role !== 'superadmin'): ?>
            <button class="btn-add" onclick="showAddForm()">+ Add Blood Request</button>
        <?php endif; ?>
        <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <!-- Edit Form (hidden until row clicked) -->
        <form method="post" class="form-section" id="editForm" style="display:none;">
            <input type="hidden" name="request_id" id="edit_request_id">
            <label class="form-label">Blood Type</label>
            <input class="form-input" type="text" name="blood_type" id="edit_blood_type" required>
            <label class="form-label">Unit Requested</label>
            <input class="form-input" type="number" name="unit_req" id="edit_unit_req" step="0.1" min="0" required>
            <label class="form-label">Request Date</label>
            <input class="form-input" type="date" name="request_date" id="edit_request_date" required>
            <label class="form-label">Deadline Date</label>
            <input class="form-input" type="date" name="deadline_date" id="edit_deadline_date" required>
            <label class="form-label">Status</label>
            <select class="form-input" name="status" id="edit_status" required>
                <?php foreach (["pending","fulfilled","canceled"] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label">No. of Donors</label>
            <input class="form-input" type="number" name="no_of_donor" id="edit_no_of_donor" min="0" required>
            <button type="submit" class="btn-update" name="action" value="update">Update</button>
            <button type="submit" class="btn-delete" name="action" value="delete" onclick="return confirm('Cancel this blood request?');">Cancel Request</button>
            <button type="button" class="btn-clear" onclick="clearEditForm()">Cancel</button>
        </form>

        <!-- Add Form (hidden until add button pressed) -->
        <form method="post" class="form-section" id="addForm" style="display:none;">
            <input type="hidden" name="action" value="add">
         
            <label class="form-label">Blood Type</label>
            <select class="form-input" name="blood_type" required>
                <?php foreach (["A+","A-","B+","B-","O+","O-","AB+","AB-"] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label">Unit Requested</label>
            <input class="form-input" type="number" name="unit_req" step="0.1" min="0" required>
            <label class="form-label">Request Date</label>
            <input class="form-input" type="date" name="request_date" required>
            <label class="form-label">Deadline Date</label>
            <input class="form-input" type="date" name="deadline_date" required>
            <label class="form-label">Status</label>
            <select class="form-input" name="status" required>
                <?php foreach (["pending","fulfilled","canceled"] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label">No. of Donors</label>
            <input class="form-input" type="number" name="no_of_donor" min="0" required>
            <button type="submit" class="btn-update">Add Request</button>
            <button type="button" class="btn-clear" onclick="clearAddForm()">Cancel</button>
        </form>
    </div>
    <!-- Main Table -->
    <div class="main-table">
        <h2>Manage Blood Requests</h2>

        <!-- Filters -->
        <form method="get" class="controls">
            <label>Status:
                <select name="filter_status">
                <option value="">-- All --</option>
                    <?php foreach (["pending", "completed", "canceled"] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_GET['filter_status'] ?? '') === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
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
            <h3>Blood Request List</h3>
            <table>
                <tr>
                    <th>No</th>
                    
                   
                    <th>Blood Type</th>
                    <th>Unit Requested</th>
                    <th>Request Date</th>
                    <th>Deadline Date</th>
                    <th>Status</th>
                    <th>No. of Donors</th>
                </tr>
                <?php if (is_array($blood_requests)): foreach ($blood_requests as $i => $r): ?>
                    <tr class="req-row" id="row_<?= $i ?>" onclick="showEditForm(<?= $i ?>)">
                        <td><?= $i + 1 ?></td>
                      
                        
                        <td><?= htmlspecialchars($r['blood_type'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['unit_req'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['request_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['deadline_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['no_of_donor'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9">No blood requests found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
