<?php
include 'superadmin_header.php';
include 'supabase_connector.php';

// Always use rawurldecode for GET parameters!
$get = fn($k) => isset($_GET[$k]) ? rawurldecode($_GET[$k]) : '';

// Tabs/entities setup
$tab = $_GET['tab'] ?? 'appointments';
$tabs = [
    'appointments'    => 'Appointments',
    'blood_donation'  => 'Blood Donation',
    'blood_request'   => 'Blood Request',
    'blood_supply'    => 'Blood Supply',
    'donor_invite'    => 'Donor Invites',
    'donor_request'   => 'Donor Requests',
    'blood_given'     => 'Blood Given',        // NEW
];

$hospitals = fetch_from_supabase('hospital', "select=hospital_id,name");
$hospital_opts = [];
foreach ($hospitals as $h) $hospital_opts[$h['hospital_id']] = $h['name'];
$blood_types = ["A+","A-","B+","B-","AB+","AB-","O+","O-"];

$donors = fetch_from_supabase("donor", "select=user_id,full_name");
$donor_names = [];
foreach ($donors as $d) $donor_names[$d['user_id']] = $d['full_name'];

$staffs = fetch_from_supabase("staff", "select=user_id,full_name");
$staff_names = [];
foreach ($staffs as $s) $staff_names[$s['user_id']] = $s['full_name'];

$hospital_names = [];
foreach ($hospitals as $h) $hospital_names[$h['hospital_id']] = $h['name'];

function build_date_filter($col, $start, $end) {
    if ($start && $end) return "$col=gte.$start&$col=lte.$end";
    if ($start) return "$col=gte.$start";
    if ($end) return "$col=lte.$end";
    return '';
}

$selected_hospital = $get('hospital');
$blood_type = $get('blood_type');
$status = $get('status');
$name = $get('name');
$start = $get('start');
$end = $get('end');
$sort = $get('sort');

$data = [];
$filters = ["select=*"];

function clean_data(&$data, $tab, $donor_names, $staff_names, $hospital_names) {
    foreach ($data as $i => &$row) {
        $row = array_merge(['No' => $i + 1], $row);

        if (isset($row['user_id']) && isset($donor_names[$row['user_id']]))
            $row['Donor'] = $donor_names[$row['user_id']];

        if (isset($row['donor_id']) && isset($donor_names[$row['donor_id']]))
            $row['Donor'] = $donor_names[$row['donor_id']];

        if (isset($row['invited_by']) && isset($staff_names[$row['invited_by']]))
            $row['Invited By'] = $staff_names[$row['invited_by']];

        // NEW: map given_by → staff full name
        if (isset($row['given_by']) && isset($staff_names[$row['given_by']]))
            $row['Given By'] = $staff_names[$row['given_by']];

        if (isset($row['hospital_id']) && isset($hospital_names[$row['hospital_id']]))
            $row['Hospital'] = $hospital_names[$row['hospital_id']];

        if ($tab === 'appointments') {
            $row['Request'] = isset($row['request_id']) ? '✔' : '';
            $row['Invite'] = isset($row['invite_id']) ? '✔' : '';
        }

        // hide *_id columns (keep invited_by for mapping above)
        foreach (array_keys($row) as $key) {
            if (str_ends_with($key, '_id') && $key !== 'No' && $key !== 'invited_by') unset($row[$key]);
        }

        if (isset($row['invited_by'])) unset($row['invited_by']);
        if (isset($row['given_by'])) unset($row['given_by']); // NEW hide raw id after mapping
    }
}

function fetch_and_filter($table, $base_filters, $sort, $tab, $donor_names, $staff_names, $hospital_names) {
    $filter = implode('&', $base_filters);
    $data = fetch_from_supabase($table, $filter);
    if (is_array($data) && !empty($sort) && isset($data[0][$sort])) {
        usort($data, fn($a, $b) => strcmp($a[$sort] ?? '', $b[$sort] ?? ''));
    }
    clean_data($data, $tab, $donor_names, $staff_names, $hospital_names);
    return $data;
}

if ($tab === 'appointments') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    if ($status) $filters[] = "status=eq.$status";
    $date_filter = build_date_filter('appointment_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('appointment', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
elseif ($tab === 'blood_donation') {
    if ($blood_type) $filters[] = "blood_type=eq." . urlencode($blood_type);
    $date_filter = build_date_filter('donation_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('blood_donation', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
elseif ($tab === 'blood_request') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    if ($blood_type) $filters[] = "blood_type=eq." . urlencode($blood_type);
    if ($status) $filters[] = "status=eq.$status";
    $date_filter = build_date_filter('request_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('blood_request', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
elseif ($tab === 'blood_supply') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    if ($blood_type) $filters[] = "blood_type=eq." . urlencode($blood_type);
    $date_filter = build_date_filter('exp_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('blood_supply', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
elseif ($tab === 'donor_invite') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    $date_filter = build_date_filter('invite_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('donor_invite', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
elseif ($tab === 'donor_request') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    $date_filter = build_date_filter('acceptance_date', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('donor_request', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}
// NEW: blood_given
elseif ($tab === 'blood_given') {
    if ($selected_hospital) $filters[] = "hospital_id=eq.$selected_hospital";
    if ($blood_type)        $filters[] = "blood_type=eq." . urlencode($blood_type);
    $date_filter = build_date_filter('date_given', $start, $end);
    if ($date_filter) $filters[] = $date_filter;
    $data = fetch_and_filter('blood_given', $filters, $sort, $tab, $donor_names, $staff_names, $hospital_names);
}

function arrayToTable($data) {
    if (!is_array($data) || empty($data)) return '<div style="color:#b7322c;font-weight:bold;">No data found.</div>';
    $columns = array_keys($data[0]);
    $html = '<form method="GET" class="row g-2 align-items-end mb-4">';
    $html .= '<input type="hidden" name="tab" value="' . htmlspecialchars($_GET['tab'] ?? '') . '">';
    $html .= '<div class="col-auto"><label>Hospital</label><select name="hospital" class="form-select">';
    $html .= '<option value="">All</option>';
    global $hospital_opts;
    foreach ($hospital_opts as $hid => $hname) {
        $selected = ($hid == ($_GET['hospital'] ?? '')) ? 'selected' : '';
        $html .= "<option value='$hid' $selected>$hname</option>";
    }
    $html .= '</select></div>';
    $html .= '<div class="col-auto"><label>Blood Type</label><select name="blood_type" class="form-select">';
    $html .= '<option value="">All</option>';
    global $blood_types;
    foreach ($blood_types as $b) {
        $selected = ($b == ($_GET['blood_type'] ?? '')) ? 'selected' : '';
        $html .= '<option value="' . urlencode($b) . '" ' . $selected . '>' . $b . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="col-auto"><label>Start Date</label><input type="date" name="start" value="' . htmlspecialchars($_GET['start'] ?? '') . '" class="form-control"></div>';
    $html .= '<div class="col-auto"><label>End Date</label><input type="date" name="end" value="' . htmlspecialchars($_GET['end'] ?? '') . '" class="form-control"></div>';
    $html .= '<div class="col-auto"><label>Name</label><input type="text" name="name" value="' . htmlspecialchars($_GET['name'] ?? '') . '" class="form-control"></div>';
    $html .= '<div class="col-auto"><label>Sort</label><input type="text" name="sort" value="' . htmlspecialchars($_GET['sort'] ?? '') . '" class="form-control"></div>';
    $html .= '<div class="col-auto"><button class="btn btn-danger">Apply</button></div>';
    $html .= '</form>';

    $html .= '<div class="card p-3"><table class="table table-bordered table-hover">';
    $html .= '<thead class="table-danger"><tr>' . implode('', array_map(fn($c) => "<th>$c</th>", $columns)) . '</tr></thead>';
    foreach ($data as $row) {
        $html .= '<tr>' . implode('', array_map(fn($c) => "<td>" . htmlspecialchars($row[$c] ?? '') . "</td>", $columns)) . '</tr>';
    }
    $html .= '</table></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Superadmin Data Entry</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        .nav-tabs .nav-link.active { background-color: #b7322c; color: white; }
        .nav-tabs .nav-link { color: #b7322c; font-weight: 500; }
        h2 { color: #b7322c; }
        label { font-weight: 500; color: #333; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">Superadmin Data Entry</h2>
    <ul class="nav nav-tabs mb-3">
        <?php foreach ($tabs as $key=>$label): ?>
            <li class="nav-item">
                <a class="nav-link<?= $tab==$key?' active':'' ?>" href="?tab=<?= urlencode($key) ?>"><?= htmlspecialchars($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?= arrayToTable($data) ?>
</div>
</body>
</html>
