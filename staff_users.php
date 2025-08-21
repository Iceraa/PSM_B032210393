<?php
include 'header.php';
include 'supabase_connector.php';
include 'admin-api/auth_helpers.php';

$role = $_SESSION['role'] ?? 'staff';
$hospitalId = $_SESSION['hospital_id'] ?? null;

$SUPABASE_PROJECT_ID = 'lorvwulnebjxtipkvsvz';
$SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0';

// --- Handle update/delete POST ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_user_id = $_POST['user_id'] ?? '';
    $form_full_name = $_POST['full_name'] ?? '';
    $form_tel_no = $_POST['tel_no'] ?? '';
    $form_position = $_POST['position'] ?? '';
    $form_email = $_POST['email'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'update' && $form_user_id) {
        // Update staff table
        $result = update_supabase('staff', [
            'full_name' => $form_full_name,
            'tel_no' => $form_tel_no,
            'position' => $form_position
        ], "user_id=eq.$form_user_id");

        // Update email in Supabase Auth
        $url = "https://$SUPABASE_PROJECT_ID.supabase.co/auth/v1/admin/users/$form_user_id";
        $payload = json_encode(['email' => $form_email]);
        $headers = [
            "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
            "apikey: $SUPABASE_SERVICE_ROLE_KEY",
            "Content-Type: application/json"
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $auth_response = curl_exec($ch);
        curl_close($ch);

        $auth_result = json_decode($auth_response, true);
        $success = $result && isset($auth_result['id']);
        $message = $success ? 'Staff updated successfully.' : 'Update failed.';
    }
    elseif ($action === 'delete' && $form_user_id) {
        // Soft delete: set is_active = false
        $result = update_supabase('staff', ['is_active' => false], "user_id=eq.$form_user_id");
        $message = $result ? 'Staff marked as inactive (soft deleted).' : 'Failed to deactivate staff.';
    }

    // After update/delete, reload to refresh table
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- Filtering, search, and fetch data for display ---
$filters = [];
if (isset($_GET['filter_position']) && $_GET['filter_position'] !== '') {
    $filters[] = "position=eq." . urlencode($_GET['filter_position']);
}
if (isset($_GET['search_name']) && $_GET['search_name'] !== '') {
    $filters[] = "full_name=ilike.*" . urlencode($_GET['search_name']) . "*";
}
$filters[] = "is_active=eq.true";
if ($role !== 'superadmin') {
    $filters[] = "hospital_id=eq.$hospitalId";
}
$filterQuery = 'select=*' . ($filters ? '&' . implode('&', $filters) : '');

$staffs = fetch_from_supabase('staff', $filterQuery);

// Sorting
if (!empty($_GET['sort_by'])) {
    $sortKey = $_GET['sort_by'];
    usort($staffs, function ($a, $b) use ($sortKey) {
        return strcmp($b[$sortKey], $a[$sortKey]);
    });
}

// Build aggregate data
$totalStaff = count($staffs);
$positions = [];
foreach ($staffs as $s) {
    $pos = $s['position'] ?? 'Unknown';
    $positions[$pos] = ($positions[$pos] ?? 0) + 1;
}

// Fetch Auth users for email mapping
$authUsersRaw = get_auth_users($role, $hospitalId, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY);
$authUsers = [];
foreach (($authUsersRaw['users'] ?? []) as $u) {
    $authUsers[$u['id']] = $u['email'] ?? '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Staff</title>
    <style>
        body { background: #f8fafc; font-family: Arial, sans-serif; }
        .container { padding: 20px; }
        h2 { border-left: 5px solid crimson; padding-left: 10px; }
        .top-button { margin-bottom: 10px; }
        .top-button a { background-color: #007bff; color: white; padding: 8px 14px; border: none; border-radius: 4px; text-decoration: none; }
        .controls { margin: 15px 0; }
        .controls label { margin-right: 10px; }
        select, button { padding: 5px; margin-right: 10px; }
        .flex-box { display: flex; gap: 24px; margin-top: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.08); flex: 1; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        .selected { background: #e3f2fd; }
        .form-section { background: #fff; padding: 18px 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.08); min-width: 290px; flex: 1.2; }
        .form-label { font-weight: 500; margin-top: 10px; display: block; }
        .form-input { width: 100%; padding: 6px 8px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-update { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-view { background: #007bff; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-delete { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .btn-add { background:rgb(30, 160, 50); color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .btn:disabled { background: #ccc; }
        .msg { color: #1976d2; margin-bottom: 8px; }
    </style>
    <script>
    // For in-page population
    const staffData = <?= json_encode($staffs) ?>;
    const authUsers = <?= json_encode($authUsers) ?>;

    function fillForm(userId) {
        const staff = staffData.find(s => s.user_id === userId);
        document.getElementById('form_user_id').value = staff ? staff.user_id : '';
        document.getElementById('form_email').value = authUsers[userId] || '';
        document.getElementById('form_full_name').value = staff ? staff.full_name : '';
        document.getElementById('form_tel_no').value = staff ? staff.tel_no : '';
        document.getElementById('form_position').value = staff ? staff.position : '';
        // Highlight
        document.querySelectorAll('tr.staff-row').forEach(row => row.classList.remove('selected'));
        const row = document.getElementById('row_' + userId);
        if (row) row.classList.add('selected');
    }
    function clearForm() {
        document.getElementById('form_user_id').value = '';
        document.getElementById('form_email').value = '';
        document.getElementById('form_full_name').value = '';
        document.getElementById('form_tel_no').value = '';
        document.getElementById('form_position').value = '';
        document.querySelectorAll('tr.staff-row').forEach(row => row.classList.remove('selected'));
    }
    </script>
</head>
<body>
<div class="container">
    <h2>Manage STAFF</h2>
    <div class="top-button">
        <!-- <a href="users.php">View Donor</a> -->
        <a href="add_staff.php" style="background:#0D883B; margin-left:12px;">+ Add Staff</a>
    </div>

    <!-- Controls bar for filter/search/sort -->
    <div class="controls">
        <form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
            <label>
                Filter by Position:
                <select name="filter_position">
                    <option value="">-- All --</option>
                    <?php foreach (["Admin","Staff"] as $p): ?>
                        <option value="<?= $p ?>" <?= ($_GET['filter_position'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Search Name:
                <input type="text" name="search_name" value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>" placeholder="Enter name...">
            </label>
            <label>
                Sort by:
                <select name="sort_by">
                    <option value="">-- None --</option>
                    <option value="full_name" <?= ($_GET['sort_by'] ?? '') === 'full_name' ? 'selected' : '' ?>>Name</option>
                    <option value="position" <?= ($_GET['sort_by'] ?? '') === 'position' ? 'selected' : '' ?>>Position</option>
                </select>
            </label>
            <button type="submit">Apply</button>
        </form>
    </div>

    <!-- Aggregate Report -->
    <div class="card" style="margin-bottom: 20px; max-width: 400px;">
        <h3>Staff Aggregate Report</h3>
        <ul>
            <li>Total Active Staff: <?= $totalStaff ?></li>
            <li>By Position:
                <ul>
                    <?php foreach ($positions as $pos => $count): ?>
                        <li><?= htmlspecialchars($pos) ?>: <?= $count ?></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </div>

    <div class="flex-box">
        <div class="form-section">
            <form method="post" id="staff-form" autocomplete="off">
                <label class="form-label">Staff ID</label>
                <input class="form-input" type="text" id="form_user_id" name="user_id" readonly>
                <label class="form-label">Email</label>
                <input class="form-input" type="email" id="form_email" name="email" required>
                <label class="form-label">Full Name</label>
                <input class="form-input" type="text" id="form_full_name" name="full_name" required>
                <label class="form-label">Tel No</label>
                <input class="form-input" type="text" id="form_tel_no" name="tel_no" required>
                <label class="form-label">Position</label>
                <input class="form-input" type="text" id="form_position" name="position" required>
                <button type="submit" class="btn-update" name="action" value="update">UPDATE</button>
                <button type="submit" class="btn-delete" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this staff? This cannot be undone!');">DELETE</button>
                <button type="button" onclick="clearForm()" class="btn" style="margin-left:10px;">CLEAR</button>
            </form>
        </div>
        <div class="card">
            <h3>Staff List</h3>
            <table>
                <tr><th>No</th><th>Full Name</th><th>Tel No</th><th>Position</th><th>Email</th></tr>
                <?php foreach ($staffs as $i => $s): ?>
                    <?php $uid = $s['user_id'] ?? ''; $email = $authUsers[$uid] ?? '-'; ?>
                    <tr class="staff-row" id="row_<?= $uid ?>" onclick="fillForm('<?= $uid ?>')">
                        <td><?= $i + 1 ?></td>
                        
                        <td><?= htmlspecialchars($s['full_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['tel_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['position'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($email) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
