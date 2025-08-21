<?php
include 'superadmin_header.php';
include 'supabase_connector.php';
include 'admin-api/auth_helpers.php';

$role = $_SESSION['role'] ?? 'staff';
if ($role !== 'superadmin') {
    header("Location: staff.php");
    exit;
}

$SUPABASE_PROJECT_ID = 'lorvwulnebjxtipkvsvz';
$SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0';

$form_mode = 'add'; // add | add2 | edit
$form_msg = '';
$form_data = [
    'user_id' => '', 'email' => '', 'full_name' => '', 'tel_no' => '', 'position' => '', 'hospital_id' => '', 'is_active' => true
];
$new_user_id = '';
$email_for_next = '';

// Get all hospitals for dropdown
$hospitals = fetch_from_supabase('hospital', "select=hospital_id,name");
$hospital_options = [];
foreach ($hospitals as $h) {
    $hospital_options[$h['hospital_id']] = $h['name'];
}

// --- FORM handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Auth
    if (isset($_POST['add_step']) && $_POST['add_step'] === 'account') {
        $add_email = $_POST['add_email'] ?? '';
        $add_password = $_POST['add_password'] ?? '';
        $authUsersRaw = get_auth_users('superadmin', null, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY);
        $targetUser = null;
        foreach (($authUsersRaw['users'] ?? []) as $u) {
            if (strtolower($u['email']) === strtolower($add_email)) {
                $targetUser = $u; break;
            }
        }
        if ($targetUser) {
            $exists = fetch_from_supabase('staff', "user_id=eq.{$targetUser['id']}&is_active=eq.true");
            if ($exists && count($exists) > 0) {
                $form_msg = "A staff with this email already exists.";
            } else {
                $new_user_id = $targetUser['id'];
                $email_for_next = $add_email;
                $form_mode = 'add2';
            }
        } else {
            $url = "https://$SUPABASE_PROJECT_ID.supabase.co/auth/v1/admin/users";
            $payload = json_encode(['email' => $add_email, 'password' => $add_password, 'email_confirm' => true]);
            $headers = [
                "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
                "apikey: $SUPABASE_SERVICE_ROLE_KEY",
                "Content-Type: application/json"
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $auth_response = curl_exec($ch);
            curl_close($ch);
            $auth_data = json_decode($auth_response, true);
            if (!empty($auth_data['id'])) {
                $new_user_id = $auth_data['id'];
                $email_for_next = $add_email;
                $form_mode = 'add2';
            } else {
                $form_msg = "Failed to create user. " . ($auth_data['msg'] ?? $auth_data['error_description'] ?? "Check email/password validity.");
            }
        }
    }
    // Step 2: Info
    elseif (isset($_POST['add_step']) && $_POST['add_step'] === 'info') {
        $new_user_id = $_POST['add_user_id'] ?? '';
        $add_full_name = $_POST['add_full_name'] ?? '';
        $add_tel_no = $_POST['add_tel_no'] ?? '';
        $add_position = $_POST['add_position'] ?? '';
        $add_hospital_id = $_POST['add_hospital_id'] ?? '';
        $result = insert_into_supabase('staff', [
            'user_id' => $new_user_id,
            'full_name' => $add_full_name,
            'tel_no' => $add_tel_no,
            'position' => $add_position,
            'hospital_id' => $add_hospital_id,
            'is_active' => true
        ]);
        if ($result) {
            $form_msg = "Staff account created!";
            $form_mode = 'add';
        } else {
            $form_msg = "Failed to add staff info. Please check details and try again.";
            $form_mode = 'add2';
        }
    }
    // Update
    elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $form_mode = 'edit';
        $form_data = [
            'user_id' => $_POST['user_id'],
            'email' => $_POST['email'],
            'full_name' => $_POST['full_name'],
            'tel_no' => $_POST['tel_no'],
            'position' => $_POST['position'],
            'hospital_id' => $_POST['hospital_id'],
            'is_active' => true
        ];
        $payload = $form_data;
        unset($payload['user_id'], $payload['email']);
        $result = fetch_from_supabase("staff?user_id=eq.{$form_data['user_id']}", "", "PATCH", $payload);
        if ($result) $form_msg = "Staff info updated.";
        else $form_msg = "Update failed!";
    }
    // Soft Delete
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $form_mode = 'edit';
        $user_id = $_POST['user_id'];
        $result = fetch_from_supabase("staff?user_id=eq.$user_id", "", "PATCH", ['is_active' => false]);
        $form_msg = $result ? "Staff deleted (soft)." : "Delete failed!";
    }
    // Row select for edit
    elseif (isset($_POST['edit_row_id'])) {
        $edit_id = $_POST['edit_row_id'];
        $staff_list = fetch_from_supabase('staff', "user_id=eq.$edit_id");
        $users = fetch_from_supabase('users', "user_id=eq.$edit_id");
        if (is_array($staff_list) && count($staff_list)) {
            $form_mode = 'edit';
            $s = $staff_list[0];
            $form_data = [
                'user_id' => $s['user_id'],
                'email' => (is_array($users) && isset($users[0]['email'])) ? $users[0]['email'] : '',
                'full_name' => $s['full_name'] ?? '',
                'tel_no' => $s['tel_no'] ?? '',
                'position' => $s['position'] ?? '',
                'hospital_id' => $s['hospital_id'] ?? '',
                'is_active' => $s['is_active']
            ];
        }
    }
    // Cancel/clear
    elseif (isset($_POST['clear_form']) || isset($_POST['cancel_edit'])) {
        $form_mode = 'add';
        $form_data = [
            'user_id' => '', 'email' => '', 'full_name' => '', 'tel_no' => '', 'position' => '', 'hospital_id' => '', 'is_active' => true
        ];
    }
}

// --- Fetch all staff (only active) and map emails ---
$staffs = fetch_from_supabase('staff', "select=*&is_active=eq.true");
$authUsersRaw = get_auth_users('superadmin', null, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY);
$userEmails = [];
foreach (($authUsersRaw['users'] ?? []) as $u) {
    $userEmails[$u['id']] = $u['email'] ?? '';
}

// --- Filter/Sort ---
$filter_name = $_GET['filter_name'] ?? '';
$filter_position = $_GET['filter_position'] ?? '';
$filter_hospital = $_GET['filter_hospital'] ?? '';
$sort_by = $_GET['sort_by'] ?? '';
$filtered = [];
foreach ($staffs as $s) {
    if (!is_array($s)) continue;
    if ($filter_name && stripos($s['full_name'] ?? '', $filter_name) === false) continue;
    if ($filter_position && stripos($s['position'] ?? '', $filter_position) === false) continue;
    if ($filter_hospital && ($s['hospital_id'] ?? '') !== $filter_hospital) continue;
    $filtered[] = $s;
}
if ($sort_by) {
    usort($filtered, function ($a, $b) use ($sort_by) {
        return strcmp($a[$sort_by], $b[$sort_by]);
    });
}

// --- Aggregate ---
$total_staff = count($staffs);
$positions = [];
foreach ($staffs as $s) {
    $pos = $s['position'] ?? 'Unknown';
    $positions[$pos] = ($positions[$pos] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Master (Superadmin)</title>
    <style>
        body { background: #f8fafc; font-family: Arial, sans-serif; }
        .container { padding: 20px; }
        .top-row { display: flex; gap: 32px; margin-bottom: 24px; }
        .agg-card { background: #fff; padding: 20px 34px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.07); max-width: 430px; min-width: 330px;}
        .flex-box { display: flex; gap: 28px; margin-top: 12px;}
        .form-section { background: #fff; padding: 22px 24px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.09); min-width: 340px; flex: 1.1; }
        .form-label { font-weight: 500; margin-top: 10px; display: block; }
        .form-input { width: 100%; padding: 7px 9px; margin-bottom: 13px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-update, .btn-add { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; margin-right: 10px; cursor: pointer;}
        .btn-delete { background: #e53935; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer;}
        .btn-cancel { background: #e0e0e0; color: #111; }
        .msg { color: #1976d2; margin-bottom: 10px; font-size: 1.1em;}
        .table-card { background: #fff; padding: 22px 26px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.09); flex: 2; min-width: 750px;}
        .filter-bar { margin-bottom: 18px; display: flex; gap: 14px; align-items: center; }
        .filter-bar label { font-weight: 500; }
        .filter-bar select, .filter-bar input { padding: 5px 7px; border-radius: 4px; border: 1px solid #ccc;}
        .filter-bar button { margin-left: 5px; padding: 6px 12px; background: #1976d2; color: #fff; border: none; border-radius: 4px;}
        .export-bar { float: right; margin-bottom: 14px; }
        .export-bar button { margin-left: 7px; padding: 5px 12px; background: #388e3c; color: #fff; border: none; border-radius: 4px;}
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 9px 7px; text-align: center; font-size: 1rem;}
        th { background: #f2f2f2; }
        tr.row-link:hover { background: #e9f2ff; cursor:pointer;}
        @media print { .form-section, .filter-bar, .export-bar, .msg, .agg-card { display: none !important; } }
    </style>
    <script>
    function editStaff(user_id) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'edit_row_id';
        input.value = user_id;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
    function clearFilters() {
        window.location = 'sp_staff.php';
    }
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll("table tr");
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("th, td");
            for (var j = 0; j < cols.length; j++)
                row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            csv.push(row.join(","));
        }
        var csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
        var downloadLink = document.createElement("a");
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    }
    function printTable() {
        window.print();
    }
    </script>
</head>
<body>
<div class="container">
    <div class="top-row">
        <div class="agg-card">
            <h3>Staff Aggregate Report</h3>
            <ul>
                <li>Total Staff: <?= $total_staff ?></li>
                <li>By Position:
                    <ul>
                        <?php foreach ($positions as $pos => $cnt): ?>
                            <li><?= htmlspecialchars($pos) ?> : <?= $cnt ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
    <div class="flex-box">
        <div class="form-section">
            <?php if ($form_msg): ?><div class="msg"><?= htmlspecialchars($form_msg) ?></div><?php endif; ?>
            <?php if ($form_mode === 'add'): ?>
                <h3>Add New Staff</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="add_step" value="account">
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="add_email" required>
                    <label class="form-label">Password</label>
                    <input class="form-input" type="password" name="add_password" required>
                    <button type="submit" class="btn-add">Next</button>
                </form>
            <?php elseif ($form_mode === 'add2'): ?>
                <h3>Add Staff Info (Step 2)</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="add_step" value="info">
                    <input type="hidden" name="add_user_id" value="<?= htmlspecialchars($new_user_id) ?>">
                    <label class="form-label">Full Name</label>
                    <input class="form-input" type="text" name="add_full_name" required>
                    <label class="form-label">Tel No</label>
                    <input class="form-input" type="text" name="add_tel_no" required>
                    <label class="form-label">Position</label>
                    <input class="form-input" type="text" name="add_position" required>
                    <label class="form-label">Hospital</label>
                    <select class="form-input" name="add_hospital_id" required>
                        <option value="">--Select--</option>
                        <?php foreach ($hospital_options as $hid => $hname): ?>
                            <option value="<?= htmlspecialchars($hid) ?>"><?= htmlspecialchars($hname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-add">Add Staff</button>
                    <button type="submit" name="clear_form" class="btn-cancel btn">Cancel</button>
                </form>
            <?php elseif ($form_mode === 'edit'): ?>
                <h3>Update Staff</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($form_data['user_id']) ?>">
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" readonly>
                    <label class="form-label">Full Name</label>
                    <input class="form-input" type="text" name="full_name" value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                    <label class="form-label">Tel No</label>
                    <input class="form-input" type="text" name="tel_no" value="<?= htmlspecialchars($form_data['tel_no']) ?>" required>
                    <label class="form-label">Position</label>
                    <input class="form-input" type="text" name="position" value="<?= htmlspecialchars($form_data['position']) ?>" required>
                    <label class="form-label">Hospital</label>
                    <select class="form-input" name="hospital_id" required>
                        <option value="">--Select--</option>
                        <?php foreach ($hospital_options as $hid => $hname): ?>
                            <option value="<?= htmlspecialchars($hid) ?>" <?= ($form_data['hospital_id'] == $hid) ? "selected" : "" ?>><?= htmlspecialchars($hname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-update" name="action" value="update">Update</button>
                    <button type="submit" class="btn-delete" name="action" value="delete" onclick="return confirm('Soft delete this staff?');">Delete</button>
                    <button type="submit" name="cancel_edit" class="btn-cancel btn">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <div class="export-bar">
                <button onclick="exportTableToCSV('staff_list.csv')">Export CSV</button>
                <button onclick="printTable()">Print</button>
            </div>
            <form method="get" class="filter-bar">
                <label>Search Name:
                    <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>">
                </label>
                <label>Position:
                    <input type="text" name="filter_position" value="<?= htmlspecialchars($filter_position) ?>">
                </label>
                <label>Hospital:
                    <select name="filter_hospital">
                        <option value="">-- All --</option>
                        <?php foreach ($hospital_options as $hid => $hname): ?>
                            <option value="<?= htmlspecialchars($hid) ?>" <?= ($filter_hospital === $hid) ? 'selected' : '' ?>><?= htmlspecialchars($hname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Sort by:
                    <select name="sort_by">
                        <option value="">-- None --</option>
                        <option value="full_name" <?= $sort_by === 'full_name' ? 'selected' : '' ?>>Name</option>
                        <option value="position" <?= $sort_by === 'position' ? 'selected' : '' ?>>Position</option>
                    </select>
                </label>
                <button type="submit">Apply</button>
                <button type="button" onclick="clearFilters()">Clear</button>
            </form>
            <h3>Staff List</h3>
            <table>
                <tr>
                    <th>No</th>
                 
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Tel No</th>
                    <th>Position</th>
                    <th>Hospital</th>
                </tr>
                <?php foreach ($filtered as $i => $s):
                    $uid = $s['user_id'];
                    $email = (isset($userEmails[$uid]) && $userEmails[$uid]) ? $userEmails[$uid] : '-';
                    $hospital_name = isset($hospital_options[$s['hospital_id']]) ? $hospital_options[$s['hospital_id']] : '-';
                ?>
                <tr class="row-link" onclick="editStaff('<?= htmlspecialchars($uid) ?>')">
                    <td><?= $i + 1 ?></td>
                    
                    <td><?= htmlspecialchars($email) ?></td>
                    <td><?= htmlspecialchars($s['full_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['tel_no'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['position'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($hospital_name) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
