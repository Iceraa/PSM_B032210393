<?php
include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';
$message = "";

// --- Update or delete (soft) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['shift_id']) && $role !== 'superadmin') {
    $shiftId = $_POST['shift_id'];
    if ($_POST['action'] === 'update') {
        $update = [
            'shift_time' => $_POST['shift_time'] ?? null,
        ];
        $ok = update_supabase('staff_shift', $update, "shift_id=eq.$shiftId");
        $message = $ok ? "Shift updated successfully." : "Failed to update shift.";
    } elseif ($_POST['action'] === 'delete') {
        // Soft delete: set shift_time to null
        $ok = update_supabase('staff_shift', ['shift_time' => null], "shift_id=eq.$shiftId");
        $message = $ok ? "Shift deleted (soft delete)." : "Failed to delete shift.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$shifts = [];

if ($role !== 'superadmin') {
    // 1. Get all staff in this hospital
    $staff = fetch_from_supabase("staff", "select=user_id,full_name&hospital_id=eq.$hospitalId&is_active=eq.true");
    $staff_ids = [];
    $staff_names = [];
    if (is_array($staff)) {
        foreach ($staff as $s) {
            if (!empty($s['user_id'])) {
                $staff_ids[] = $s['user_id'];
                $staff_names[$s['user_id']] = $s['full_name'] ?? $s['user_id'];
            }
        }
    }
    // 2. Fetch shifts for those staff
    if (!empty($staff_ids)) {
        $id_list = implode(',', array_map(function($id){return "\"$id\"";}, $staff_ids));
        $filter = "select=*&user_id=in.($id_list)";
        $shifts = fetch_from_supabase("staff_shift", $filter);
    }
} else {
    // Superadmin: show all
    $shifts = fetch_from_supabase("staff_shift", "select=*");
    // Optionally fetch all staff for name mapping
    $staff = fetch_from_supabase("staff", "select=user_id,full_name");
    $staff_names = [];
    if (is_array($staff)) {
        foreach ($staff as $s) {
            $staff_names[$s['user_id']] = $s['full_name'] ?? $s['user_id'];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Shifts</title>
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
        table { border-collapse: collapse; width: 100%; margin-bottom: 18px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        tr.selected { background: #fff3cd !important; }
        .form-section {
            background: #fff;
            padding: 16px 22px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 18px;
        }
        .form-label { font-weight: 600; display: block; margin-top: 10px; }
        .form-input { width: 100%; padding: 6px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-update { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-cancel { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .btn-clear { background: #ccc; color: #333; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .msg { color: #1976d2; margin-bottom: 8px; }
    </style>
    <script>
    let shifts = <?= json_encode($shifts) ?>;
    let staff_names = <?= json_encode($staff_names) ?>;
    function fillEditForm(idx) {
        let rows = document.querySelectorAll('.shift-row');
        rows.forEach(r => r.classList.remove('selected'));
        let row = document.getElementById('row_' + idx);
        if (row) row.classList.add('selected');
        let s = shifts[idx];
        if (s) {
            document.getElementById('form_shift_id').value = s['shift_id'] || '';
            document.getElementById('form_user_id').value = s['user_id'] || '';
            document.getElementById('form_staff_name').value = staff_names[s['user_id']] || s['user_id'] || '';
            document.getElementById('form_shift_time').value = s['shift_time'] || '';
            document.getElementById('editForm').style.display = '';
        }
    }
    function clearEditForm() {
        document.querySelectorAll('.shift-row').forEach(r => r.classList.remove('selected'));
        document.getElementById('editForm').reset();
        document.getElementById('editForm').style.display = 'none';
    }
    </script>
</head>
<body>
<div class="container">
    <!-- Side Form -->
    <div class="side-form">
        <?php if ($role !== 'superadmin'): ?>
        <form method="post" class="form-section" id="editForm" style="display:none;">
            <input type="hidden" id="form_shift_id" name="shift_id">
            <label class="form-label">Staff Name</label>
            <input class="form-input" type="text" id="form_staff_name" name="staff_name" readonly>
            <input type="hidden" id="form_user_id" name="user_id">
            <label class="form-label">Shift Time</label>
            <select class="form-input" name="shift_time" id="form_shift_time" required>
                <?php foreach (["morning","afternoon","evening"] as $s): ?>
                    <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-update" name="action" value="update">Update</button>
            <button type="submit" class="btn-cancel" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this shift?');">Delete</button>
            <button type="button" class="btn-clear" onclick="clearEditForm()">Clear</button>
        </form>
        <?php endif; ?>
    </div>
    <!-- Main Table -->
    <div class="main-table">
        <h2>Manage Staff Shifts</h2>
        <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <div class="card table-section">
            <h3>Staff Shift List</h3>
            <table>
                <tr>
                    <th>No</th>
                   
                    <th>Staff Name</th>
                  
                    <th>Shift Time</th>
                </tr>
                <?php if (is_array($shifts) && count($shifts) > 0): foreach ($shifts as $i => $s): ?>
                    <tr class="shift-row" id="row_<?= $i ?>" onclick="fillEditForm(<?= $i ?>)">
                        <td><?= $i + 1 ?></td>
                       
                        <td><?= htmlspecialchars($staff_names[$s['user_id']] ?? '-') ?></td>
                        
                        <td><?= htmlspecialchars($s['shift_time'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5">No staff shifts found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
