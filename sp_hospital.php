<?php
include 'superadmin_header.php';
include 'supabase_connector.php';

$role = $_SESSION['role'] ?? 'staff';
if ($role !== 'superadmin') {
    header("Location: hospitals.php");
    exit;
}

$form_mode = 'add';
$form_msg = '';
$form_data = [
    'hospital_id' => '',
    'name' => '',
    'location' => '',
    'contact_no' => '',
    'email' => '',
    'hospital_image' => ''
];

// ---- FORM handling ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new hospital
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $payload = [
            'name' => $_POST['name'] ?? '',
            'location' => $_POST['location'] ?? '',
            'contact_no' => $_POST['contact_no'] ?? '',
            'email' => $_POST['email'] ?? '',
            'hospital_image' => $_POST['hospital_image'] ?? ''
        ];
        $result = insert_into_supabase('hospital', $payload);
        $form_msg = $result ? "Hospital added successfully!" : "Failed to add hospital.";
        $form_mode = 'add';
    }
    // Update hospital
    elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $hid = $_POST['hospital_id'] ?? '';
        $payload = [
            'name' => $_POST['name'] ?? '',
            'location' => $_POST['location'] ?? '',
            'contact_no' => $_POST['contact_no'] ?? '',
            'email' => $_POST['email'] ?? '',
            'hospital_image' => $_POST['hospital_image'] ?? ''
        ];
        $result = fetch_from_supabase("hospital?hospital_id=eq.$hid", "", "PATCH", $payload);
        $form_msg = $result ? "Hospital info updated." : "Update failed!";
        $form_mode = 'add';
    }
    // Row select for edit
    elseif (isset($_POST['edit_row_id'])) {
        $hid = $_POST['edit_row_id'];
        $hospital = fetch_from_supabase('hospital', "hospital_id=eq.$hid");
        if (is_array($hospital) && count($hospital)) {
            $form_mode = 'edit';
            $form_data = $hospital[0];
        }
    }
    // Cancel/clear
    elseif (isset($_POST['clear_form']) || isset($_POST['cancel_edit'])) {
        $form_mode = 'add';
        $form_data = [
            'hospital_id' => '',
            'name' => '',
            'location' => '',
            'contact_no' => '',
            'email' => '',
            'hospital_image' => ''
        ];
    }
}

// ---- Filter/Sort ----
$filter_name = $_GET['filter_name'] ?? '';
$filter_location = $_GET['filter_location'] ?? '';
$sort_by = $_GET['sort_by'] ?? '';

$hospitals = fetch_from_supabase('hospital', "select=*");
$filtered = [];
if (is_array($hospitals)) {
    foreach ($hospitals as $h) {
        if (!is_array($h)) continue;
        if ($filter_name && stripos($h['name'] ?? '', $filter_name) === false) continue;
        if ($filter_location && stripos($h['location'] ?? '', $filter_location) === false) continue;
        $filtered[] = $h;
    }
    if ($sort_by) {
        usort($filtered, function ($a, $b) use ($sort_by) {
            return strcmp($a[$sort_by], $b[$sort_by]);
        });
    }
} else {
    $filtered = [];
}

// ---- Aggregate ----
$total_hospitals = is_array($hospitals) ? count($hospitals) : 0;
$locations = [];
foreach ($hospitals as $h) {
    $loc = $h['location'] ?? 'Unknown';
    $locations[$loc] = ($locations[$loc] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hospital Master (Superadmin)</title>
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
    function editHospital(hid) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'edit_row_id';
        input.value = hid;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
    function clearFilters() {
        window.location = 'sp_hospital.php';
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
            <h3>Hospital Aggregate Report</h3>
            <ul>
                <li>Total Hospitals: <?= $total_hospitals ?></li>
                <li>By Location:
                    <ul>
                        <?php foreach ($locations as $loc => $cnt): ?>
                            <li><?= htmlspecialchars($loc) ?> : <?= $cnt ?></li>
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
                <h3>Add New Hospital</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="add">
                    <label class="form-label">Name</label>
                    <input class="form-input" type="text" name="name" required>
                    <label class="form-label">Location</label>
                    <input class="form-input" type="text" name="location" required>
                    <label class="form-label">Contact No</label>
                    <input class="form-input" type="text" name="contact_no" required>
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="email" required>
                    <label class="form-label">Hospital Image (URL)</label>
                    <input class="form-input" type="text" name="hospital_image">
                    <button type="submit" class="btn-add">Add Hospital</button>
                </form>
            <?php elseif ($form_mode === 'edit'): ?>
                <h3>Update Hospital</h3>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="hospital_id" value="<?= htmlspecialchars($form_data['hospital_id']) ?>">
                    <label class="form-label">Name</label>
                    <input class="form-input" type="text" name="name" value="<?= htmlspecialchars($form_data['name']) ?>" required>
                    <label class="form-label">Location</label>
                    <input class="form-input" type="text" name="location" value="<?= htmlspecialchars($form_data['location']) ?>" required>
                    <label class="form-label">Contact No</label>
                    <input class="form-input" type="text" name="contact_no" value="<?= htmlspecialchars($form_data['contact_no']) ?>" required>
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" required>
                    <label class="form-label">Hospital Image (URL)</label>
                    <input class="form-input" type="text" name="hospital_image" value="<?= htmlspecialchars($form_data['hospital_image']) ?>">
                    <button type="submit" class="btn-update">Update</button>
                    <button type="submit" name="cancel_edit" class="btn-cancel btn">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <div class="export-bar">
                <button onclick="exportTableToCSV('hospitals.csv')">Export CSV</button>
                <button onclick="printTable()">Print</button>
            </div>
            <form method="get" class="filter-bar">
                <label>Name:
                    <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>">
                </label>
                <label>Location:
                    <input type="text" name="filter_location" value="<?= htmlspecialchars($filter_location) ?>">
                </label>
                <label>Sort by:
                    <select name="sort_by">
                        <option value="">-- None --</option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="location" <?= $sort_by === 'location' ? 'selected' : '' ?>>Location</option>
                        <option value="contact_no" <?= $sort_by === 'contact_no' ? 'selected' : '' ?>>Contact No</option>
                    </select>
                </label>
                <button type="submit">Apply</button>
                <button type="button" onclick="clearFilters()" style="background:#e0e0e0;color:#222;">Clear</button>
            </form>
            <h3>Hospital List</h3>
            <table>
                <tr>
                    <th>No</th>
                    
                    <th>Name</th>
                    <th>Location</th>
                    <th>Contact No</th>
                    <th>Email</th>
                    <th>Image</th>
                </tr>
                <?php if (is_array($filtered)): foreach ($filtered as $i => $h):
                    $hid = $h['hospital_id'];
                ?>
                    <tr class="row-link" onclick="editHospital('<?= htmlspecialchars($hid) ?>')">
                        <td><?= $i + 1 ?></td>
                        
                        <td><?= htmlspecialchars($h['name']) ?></td>
                        <td><?= htmlspecialchars($h['location']) ?></td>
                        <td><?= htmlspecialchars($h['contact_no']) ?></td>
                        <td><?= htmlspecialchars($h['email']) ?></td>
                        <td>
                            <?php if ($h['hospital_image']): ?>
                                <a href="<?= htmlspecialchars($h['hospital_image']) ?>" target="_blank">View</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
