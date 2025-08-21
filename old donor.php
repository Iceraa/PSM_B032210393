<?php
include 'header.php';
include 'supabase_connector.php';
include 'admin-api/auth_helpers.php';

$role = $_SESSION['role'] ?? 'staff';
if ($role !== 'superadmin') {
    header("Location: users.php");
    exit;
}

$SUPABASE_PROJECT_ID = 'lorvwulnebjxtipkvsvz';
$SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0'; // your key

$form_mode = 'add'; // add or edit
$form_msg = '';
$form_data = [
    'user_id' => '', 'email' => '', 'full_name' => '', 'gender' => '',
    'birth_date' => '', 'blood_type' => '', 'address' => '', 'phone_no' => '', 'last_donate_date' => ''
];
$new_user_id = '';
$email_for_next = '';

// --- Form handling (add two-step, update, select, clear) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add - step 1 (account)
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
            $exists = fetch_from_supabase('donor', "user_id=eq.{$targetUser['id']}");
            if ($exists && count($exists) > 0) {
                $form_msg = "A donor with this email already exists.";
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
    // Add - step 2 (info)
    elseif (isset($_POST['add_step']) && $_POST['add_step'] === 'info') {
        $new_user_id = $_POST['add_user_id'] ?? '';
        $add_full_name = $_POST['add_full_name'] ?? '';
        $add_gender = $_POST['add_gender'] ?? '';
        $add_birth_date = $_POST['add_birth_date'] ?? '';
        $add_blood_type = $_POST['add_blood_type'] ?? '';
        $add_address = $_POST['add_address'] ?? '';
        $add_phone_no = $_POST['add_phone_no'] ?? '';
        $add_last_donate = $_POST['add_last_donate'] ?? null;

        $result = insert_into_supabase('donor', [
            'user_id' => $new_user_id,
            'full_name' => $add_full_name,
            'gender' => $add_gender,
            'birth_date' => $add_birth_date,
            'blood_type' => $add_blood_type,
            'address' => $add_address,
            'phone_no' => $add_phone_no,
            'last_donate_date' => $add_last_donate,
        ]);
        if ($result) {
            $form_msg = "Donor created!";
            $form_mode = 'add';
        } else {
            $form_msg = "Failed to add donor info. Please check details and try again.";
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
            'gender' => $_POST['gender'],
            'birth_date' => $_POST['birth_date'],
            'blood_type' => $_POST['blood_type'],
            'address' => $_POST['address'],
            'phone_no' => $_POST['phone_no'],
            'last_donate_date' => $_POST['last_donate_date']
        ];
        $payload = $form_data;
        unset($payload['user_id'], $payload['email']);
        $result = fetch_from_supabase("donor?user_id=eq.{$form_data['user_id']}", "", "PATCH", $payload);
        if ($result) $form_msg = "Donor info updated.";
        else $form_msg = "Update failed!";
    }
    // Row select
    elseif (isset($_POST['edit_row_id'])) {
        $edit_id = $_POST['edit_row_id'];
        $donor_list = fetch_from_supabase('donor', "user_id=eq.$edit_id");
        $users = fetch_from_supabase('users', "user_id=eq.$edit_id");
        if (is_array($donor_list) && count($donor_list)) {
            $form_mode = 'edit';
            $d = $donor_list[0];
            $form_data = [
                'user_id' => $d['user_id'],
                'email' => (is_array($users) && isset($users[0]['email'])) ? $users[0]['email'] : '',
                'full_name' => $d['full_name'] ?? '',
                'gender' => $d['gender'] ?? '',
                'birth_date' => $d['birth_date'] ?? '',
                'blood_type' => $d['blood_type'] ?? '',
                'address' => $d['address'] ?? '',
                'phone_no' => $d['phone_no'] ?? '',
                'last_donate_date' => $d['last_donate_date'] ?? ''
            ];
        }
    }
    // Clear/cancel
    elseif (isset($_POST['clear_form']) || isset($_POST['cancel_edit'])) {
        $form_mode = 'add';
        $form_data = [
            'user_id' => '',
            'email' => '',
            'full_name' => '',
            'gender' => '',
            'birth_date' => '',
            'blood_type' => '',
            'address' => '',
            'phone_no' => '',
            'last_donate_date' => ''
        ];
    }
}

// ---- FILTER & SORT (for donor list display) ----
$filter_blood = $_GET['filter_blood'] ?? '';
$filter_gender = $_GET['filter_gender'] ?? '';
$search_name = $_GET['search_name'] ?? '';
$sort_by = $_GET['sort_by'] ?? '';

$donors = fetch_from_supabase('donor', "select=*");
$authUsersRaw = get_auth_users('superadmin', null, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY);
$userEmails = [];
foreach (($authUsersRaw['users'] ?? []) as $u) {
    $userEmails[$u['id']] = $u['email'] ?? '';
}

// --- Apply filter/sort on donor data ---
$filtered = [];
if (is_array($donors)) {
    foreach ($donors as $d) {
        if (!is_array($d)) continue;
        if ($filter_blood && ($d['blood_type'] ?? '') !== $filter_blood) continue;
        if ($filter_gender && ($d['gender'] ?? '') !== $filter_gender) continue;
        if ($search_name && stripos($d['full_name'] ?? '', $search_name) === false) continue;
        $filtered[] = $d;
    }
    // Sort
    if ($sort_by) {
        usort($filtered, function ($a, $b) use ($sort_by) {
            if ($sort_by === 'full_name') return strcmp($a['full_name'], $b['full_name']);
            if ($sort_by === 'blood_type') return strcmp($a['blood_type'], $b['blood_type']);
            if ($sort_by === 'last_donate_date') return strcmp($b['last_donate_date'] ?? '', $a['last_donate_date'] ?? '');
            return 0;
        });
    }
} else {
    $filtered = [];
}

// --- AGGREGATE ---
$bloodCounts = [];
$genderCounts = ['M' => 0, 'F' => 0];
$recentDate = '';
if (is_array($donors)) {
    foreach ($donors as $d) {
        if (!is_array($d)) continue;
        $bt = $d['blood_type'] ?? '-';
        $gender = $d['gender'] ?? '-';
        $bloodCounts[$bt] = ($bloodCounts[$bt] ?? 0) + 1;
        $genderCounts[$gender] = ($genderCounts[$gender] ?? 0) + 1;
        if (!empty($d['last_donate_date']) && $d['last_donate_date'] > $recentDate) {
            $recentDate = $d['last_donate_date'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Donor Master (Superadmin)</title>
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
        .filter-bar { margin-bottom: 18px; display: flex; gap: 18px; align-items: center; }
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
    function editDonor(user_id) {
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
        window.location = 'sp_donor.php';
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
        // Download
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
            <h3>Aggregate Report</h3>
            <ul>
                <li>Total Donors: <?= is_array($donors) ? count($donors) : 0 ?></li>
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
    </div>
    <div class="flex-box">
        <div class="form-section">
            <?php if ($form_msg): ?><div class="msg"><?= htmlspecialchars($form_msg) ?></div><?php endif; ?>
            <?php if ($form_mode === 'add'): ?>
                <h3>Add New Donor</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="add_step" value="account">
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="add_email" required>
                    <label class="form-label">Password</label>
                    <input class="form-input" type="password" name="add_password" required>
                    <button type="submit" class="btn-add">Next</button>
                </form>
            <?php elseif ($form_mode === 'add2'): ?>
                <h3>Add New Donor (Step 2)</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="add_step" value="info">
                    <input type="hidden" name="add_user_id" value="<?= htmlspecialchars($new_user_id) ?>">
                    <label class="form-label">Full Name</label>
                    <input class="form-input" type="text" name="add_full_name" required>
                    <label class="form-label">Gender</label>
                    <select class="form-input" name="add_gender" required>
                        <option value="">--Select--</option>
                        <option value="F">F</option>
                        <option value="M">M</option>
                    </select>
                    <label class="form-label">Birth Date</label>
                    <input class="form-input" type="date" name="add_birth_date" required>
                    <label class="form-label">Blood Type</label>
                    <select class="form-input" name="add_blood_type" required>
                        <option value="">--Select--</option>
                        <?php foreach (["A+","A-","B+","B-","AB+","AB-","O+","O-"] as $b): ?>
                            <option value="<?= $b ?>"><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="add_address" required>
                    <label class="form-label">Phone No</label>
                    <input class="form-input" type="text" name="add_phone_no" required>
                    <label class="form-label">Last Donated</label>
                    <input class="form-input" type="date" name="add_last_donate">
                    <button type="submit" class="btn-add">Add Donor</button>
                    <button type="submit" name="clear_form" class="btn-cancel btn">Cancel</button>
                </form>
            <?php elseif ($form_mode === 'edit'): ?>
                <h3>Update Donor</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($form_data['user_id']) ?>">
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" readonly>
                    <label class="form-label">Full Name</label>
                    <input class="form-input" type="text" name="full_name" value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                    <label class="form-label">Gender</label>
                    <select class="form-input" name="gender" required>
                        <option value="F" <?= ($form_data['gender'] ?? '') === 'F' ? 'selected' : '' ?>>F</option>
                        <option value="M" <?= ($form_data['gender'] ?? '') === 'M' ? 'selected' : '' ?>>M</option>
                    </select>
                    <label class="form-label">Birth Date</label>
                    <input class="form-input" type="date" name="birth_date" value="<?= htmlspecialchars($form_data['birth_date']) ?>" required>
                    <label class="form-label">Blood Type</label>
                    <select class="form-input" name="blood_type" required>
                        <?php foreach (["A+","A-","B+","B-","AB+","AB-","O+","O-"] as $b): ?>
                            <option value="<?= $b ?>" <?= ($form_data['blood_type'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="address" value="<?= htmlspecialchars($form_data['address']) ?>" required>
                    <label class="form-label">Phone No</label>
                    <input class="form-input" type="text" name="phone_no" value="<?= htmlspecialchars($form_data['phone_no']) ?>" required>
                    <label class="form-label">Last Donated</label>
                    <input class="form-input" type="date" name="last_donate_date" value="<?= htmlspecialchars($form_data['last_donate_date']) ?>">
                    <button type="submit" class="btn-update" name="action" value="update">Update</button>
                    <button type="submit" name="cancel_edit" class="btn-cancel btn">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <div class="export-bar">
                <button onclick="exportTableToCSV('donors.csv')">Export CSV</button>
                <button onclick="printTable()">Print</button>
            </div>
            <form method="get" class="filter-bar">
                <label>Blood Type:
                    <select name="filter_blood">
                        <option value="">All</option>
                        <?php foreach (["A+","A-","B+","B-","AB+","AB-","O+","O-"] as $b): ?>
                            <option value="<?= $b ?>" <?= $filter_blood === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Gender:
                    <select name="filter_gender">
                        <option value="">All</option>
                        <option value="F" <?= $filter_gender === 'F' ? 'selected' : '' ?>>F</option>
                        <option value="M" <?= $filter_gender === 'M' ? 'selected' : '' ?>>M</option>
                    </select>
                </label>
                <label>Search Name:
                    <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>">
                </label>
                <label>Sort by:
                    <select name="sort_by">
                        <option value="">-- None --</option>
                        <option value="full_name" <?= $sort_by === 'full_name' ? 'selected' : '' ?>>Name</option>
                        <option value="blood_type" <?= $sort_by === 'blood_type' ? 'selected' : '' ?>>Blood Type</option>
                        <option value="last_donate_date" <?= $sort_by === 'last_donate_date' ? 'selected' : '' ?>>Last Donated</option>
                    </select>
                </label>
                <button type="submit">Apply</button>
                <button type="button" onclick="clearFilters()" style="background:#e0e0e0;color:#222;">Clear</button>
            </form>
            <h3>Donor List</h3>
            <table>
                <tr>
                    <th>No</th>
                    <th>Donor ID</th>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Birth Date</th>
                    <th>Blood Type</th>
                    <th>Address</th>
                    <th>Phone No</th>
                    <th>Last Donated</th>
                </tr>
                <?php if (is_array($filtered)): foreach ($filtered as $i => $d):
                    $uid = $d['user_id'];
                    $email = (isset($userEmails[$uid]) && $userEmails[$uid]) ? $userEmails[$uid] : '-';
                ?>
                    <tr class="row-link" onclick="editDonor('<?= htmlspecialchars($uid) ?>')">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($uid) ?></td>
                        <td><?= htmlspecialchars($email) ?></td>
                        <td><?= htmlspecialchars($d['full_name']) ?></td>
                        <td><?= htmlspecialchars($d['gender']) ?></td>
                        <td><?= htmlspecialchars($d['birth_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['blood_type']) ?></td>
                        <td><?= htmlspecialchars($d['address'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['phone_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['last_donate_date'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
