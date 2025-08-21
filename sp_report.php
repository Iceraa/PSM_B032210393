<?php
include 'superadmin_header.php';
include 'supabase_connector.php';

// --- GLOBALS ---
$blood_types = ["A+","A-","B+","B-","AB+","AB-","O+","O-"];

// Fetch hospitals for dropdown
$hospitals = fetch_from_supabase('hospital', "select=hospital_id,name");
$hospital_options = [];
foreach ($hospitals as $h) $hospital_options[$h['hospital_id']] = $h['name'];

// --- GET GLOBAL STATS ---
$total_hospitals = count($hospitals);
$total_donors = count(fetch_from_supabase('donor', "select=user_id"));
$total_staff = count(fetch_from_supabase('staff', "select=user_id"));
$total_supply = count(fetch_from_supabase('blood_supply', "select=supply_id"));

// --- TAB AND FILTER LOGIC ---
$tab = $_GET['tab'] ?? 'dashboard';

// Common filters
$selected_hospital = $_GET['hospital'] ?? '';
$selected_blood_type = $_GET['blood_type'] ?? '';
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

function build_date_filter($col, $start, $end) {
    $parts = [];
    if ($start) $parts[] = "$col=gte.$start";
    if ($end) $parts[] = "$col=lte.$end";
    return implode('&', $parts);
}

// --- DASHBOARD DATA ---
$supplies = fetch_from_supabase('blood_supply', "select=hospital_id,volume_ml");
$hospital_supply = [];
foreach ($supplies as $s) {
    $hid = $s['hospital_id'] ?? null;
    $vol = floatval($s['volume_ml'] ?? 0);
    if ($hid) $hospital_supply[$hid] = ($hospital_supply[$hid] ?? 0) + $vol;
}
arsort($hospital_supply);
$top_hospitals = array_slice($hospital_supply, 0, 10, true);

$donations = fetch_from_supabase('blood_donation', "select=user_id,unit_donated");
$donor_units = [];
foreach ($donations as $d) {
    $uid = $d['user_id'] ?? null;
    $unit = floatval($d['unit_donated'] ?? 0);
    if ($uid) $donor_units[$uid] = ($donor_units[$uid] ?? 0) + $unit;
}
arsort($donor_units);
$top_donors = array_slice($donor_units, 0, 10, true);

$donor_profiles = fetch_from_supabase('donor', "select=user_id,full_name");
$donor_names = [];
foreach ($donor_profiles as $dp) $donor_names[$dp['user_id']] = $dp['full_name'] ?? $dp['user_id'];

// --- TRENDS TAB DATA (REAL LOGIC) ---
// Filters
$donation_filters = [];
$request_filters = [];
$supply_filters = [];

if ($selected_hospital) {
    $donation_filters[] = "hospital_id=eq." . urlencode($selected_hospital);
    $supply_filters[]   = "hospital_id=eq." . urlencode($selected_hospital);
    $request_filters[]  = "hospital_id=eq." . urlencode($selected_hospital);
}
if ($selected_blood_type) {
    $donation_filters[] = "blood_type=eq." . urlencode($selected_blood_type);
    $supply_filters[]   = "blood_type=eq." . urlencode($selected_blood_type);
    $request_filters[]  = "blood_type=eq." . urlencode($selected_blood_type);
}
$donation_date = build_date_filter('donation_date', $start_date, $end_date);
$request_date  = build_date_filter('request_date', $start_date, $end_date);
$supply_date   = build_date_filter('supply_date', $start_date, $end_date);

if ($donation_date) $donation_filters[] = $donation_date;
if ($request_date)  $request_filters[]  = $request_date;
if ($supply_date)   $supply_filters[]   = $supply_date;

$donation_query = "select=donation_date";
if ($donation_filters) $donation_query .= "&" . implode("&", $donation_filters);
$donation_rows = fetch_from_supabase("blood_donation", $donation_query);
if (!is_array($donation_rows)) $donation_rows = [];

$supply_query = "select=supply_date";
if ($supply_filters) $supply_query .= "&" . implode("&", $supply_filters);
$supply_rows = fetch_from_supabase("blood_supply", $supply_query);
if (!is_array($supply_rows)) $supply_rows = [];

$request_query = "select=request_date";
if ($request_filters) $request_query .= "&" . implode("&", $request_filters);
$request_rows = fetch_from_supabase("blood_request", $request_query);
if (!is_array($request_rows)) $request_rows = [];

// Count by month
$donation_by_month = [];
$supply_by_month = [];
$request_by_month = [];

if (is_array($donation_rows)) {
    foreach ($donation_rows as $row) {
        if (is_array($row) && isset($row['donation_date'])) {
            $month = date('Y-m', strtotime($row['donation_date']));
            $donation_by_month[$month] = ($donation_by_month[$month] ?? 0) + 1;
        }
    }
}

if (is_array($supply_rows)) {
    foreach ($supply_rows as $row) {
        if (is_array($row) && isset($row['supply_date'])) {
            $month = date('Y-m', strtotime($row['supply_date']));
            $supply_by_month[$month] = ($supply_by_month[$month] ?? 0) + 1;
        }
    }
}

if (is_array($request_rows)) {
    foreach ($request_rows as $row) {
        if (is_array($row) && isset($row['request_date'])) {
            $month = date('Y-m', strtotime($row['request_date']));
            $request_by_month[$month] = ($request_by_month[$month] ?? 0) + 1;
        }
    }
}

// Merge all months
$all_months = array_unique(array_merge(
    array_keys($donation_by_month),
    array_keys($supply_by_month),
    array_keys($request_by_month)
));
sort($all_months);

$trends_labels = json_encode($all_months);
$trends_values = json_encode(array_map(fn($m) => $donation_by_month[$m] ?? 0, $all_months));
$supply_values = json_encode(array_map(fn($m) => $supply_by_month[$m] ?? 0, $all_months));
$request_values = json_encode(array_map(fn($m) => $request_by_month[$m] ?? 0, $all_months));

// --- HOSPITALS TAB DATA ---
$hospital_comparison = $hospital_supply;

// --- DONORS TAB DATA ---
$donor_query = [];
if ($tab == 'donors') {
    if ($selected_blood_type) $donor_query[] = "blood_type=eq.$selected_blood_type";
    $donor_date = build_date_filter('last_donate_date', $start_date, $end_date);
    if ($donor_date) $donor_query[] = $donor_date;
}
$donor_filter = "select=user_id,full_name,blood_type,last_donate_date";
if ($donor_query) $donor_filter .= "&" . implode('&', $donor_query);
$donors_tab = fetch_from_supabase('donor', $donor_filter);

// --- BLOOD TYPES TAB DATA ---
$blood_type_query = ["exp_date=gte." . date('Y-m-d')]; // Only include unexpired supply
if ($tab == 'blood_types') {
    if ($selected_blood_type) $blood_type_query[] = "blood_type=eq." . urlencode($selected_blood_type);
}

$query = "select=blood_type,volume_ml";
if ($blood_type_query) $query .= "&" . implode('&', $blood_type_query);

$blood_supply_types = fetch_from_supabase('blood_supply', $query);

$blood_type_vols = [];
if (is_array($blood_supply_types)) {
    foreach ($blood_supply_types as $row) {
        $bt = $row['blood_type'] ?? '';
        $v = floatval($row['volume_ml'] ?? 0);
        if ($bt) $blood_type_vols[$bt] = ($blood_type_vols[$bt] ?? 0) + $v;
    }
}
ksort($blood_type_vols);

// --- APPOINTMENTS TAB DATA ---
$appt_query = [];
if ($tab == 'appointments') {
    if ($selected_hospital) $appt_query[] = "hospital_id=eq." . urlencode($selected_hospital);
    $appt_date = build_date_filter('appointment_date', $start_date, $end_date);
    if ($appt_date) $appt_query[] = $appt_date;
}
$appt_filter = "select=appointment_id,appointment_date,invite_id,request_id,status";
if ($appt_query) $appt_filter .= "&" . implode('&', $appt_query);
$appointments = fetch_from_supabase('appointment', $appt_filter);

// Trend data for appointments
$appt_trend_months = [];
$invite_count = 0;
$request_count = 0;
$fulfilled = 0;
$total_appt = 0;
foreach ($appointments as $appt) {
    $date = $appt['appointment_date'] ?? '';
    $month = $date ? date('Y-m', strtotime($date)) : '';
    if ($month) $appt_trend_months[$month] = ($appt_trend_months[$month] ?? 0) + 1;
    if (!empty($appt['invite_id'])) $invite_count++;
    elseif (!empty($appt['request_id'])) $request_count++;
    if (($appt['status'] ?? '') === 'completed') $fulfilled++;
    $total_appt++;
}
ksort($appt_trend_months);
$appt_trend_labels = json_encode(array_keys($appt_trend_months));
$appt_trend_values = json_encode(array_values($appt_trend_months));
$fulfillment_rate = ($total_appt > 0) ? round($fulfilled / $total_appt * 100, 1) : 0;

?>

<!DOCTYPE html>
<html>
<head>
    <title>Superadmin Reports</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; }
        .card-metric { background: #fbeee5; color: #600; font-weight: bold; border: 2px solid #ebc2b8; border-radius: 13px; text-align:center; margin: 0 12px 0 0; }
        .metric-value { font-size: 2.4rem; }
        .tab-pane { padding-top: 24px; }
        .dashboard-card { background: #fff; border-radius: 14px; box-shadow: 0 0 12px #e8e6e6; padding: 22px; margin-bottom: 20px; }
        .card-title { color: #b7322c; font-size: 1.22rem; font-weight: 600; }
        .export-btns { float: right; }
        .nav-tabs .nav-link.active { background: #b7322c; color: #fff; }
        .nav-tabs .nav-link { color: #b7322c; }
        th { background: #ffe2d7; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container py-4">

    <!-- METRIC CARDS -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card-metric p-3">
                <div class="metric-value"><?= $total_hospitals ?></div>
                Hospitals
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-metric p-3">
                <div class="metric-value"><?= $total_donors ?></div>
                Donors
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-metric p-3">
                <div class="metric-value"><?= $total_staff ?></div>
                Staff
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-metric p-3">
                <div class="metric-value"><?= $total_supply ?></div>
                Supply Records
            </div>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='dashboard'?' active':'' ?>" href="?tab=dashboard">Dashboard</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='trends'?' active':'' ?>" href="?tab=trends">Trends</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='hospitals'?' active':'' ?>" href="?tab=hospitals">Hospitals</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='donors'?' active':'' ?>" href="?tab=donors">Donors</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='blood_types'?' active':'' ?>" href="?tab=blood_types">Blood Types</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link<?= $tab=='appointments'?' active':'' ?>" href="?tab=appointments">Appointments</a>
        </li>
    </ul>
    <div class="tab-content">

        <!-- DASHBOARD -->
        <?php if ($tab=='dashboard'): ?>
        <div class="tab-pane fade show active" id="dash" role="tabpanel">
            <div class="dashboard-card">
                <div class="card-title">Hospital Leaderboard (by Blood Supply Volume)</div>
                <table class="table table-bordered table-hover mt-2">
                    <thead><tr><th>Rank</th><th>Hospital</th><th>Total Supply (ml)</th></tr></thead>
                    <tbody>
                    <?php $rank=1; foreach ($top_hospitals as $hid=>$vol): ?>
                        <tr>
                            <td><?= $rank++ ?></td>
                            <td><?= htmlspecialchars($hospital_options[$hid] ?? $hid) ?></td>
                            <td><?= number_format($vol, 1) ?></td>
                        </tr>
                    <?php endforeach; if ($rank==1): ?>
                        <tr><td colspan="3">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="dashboard-card">
                <div class="card-title">Top 10 Blood Donor Contributors</div>
                <table class="table table-bordered mt-2">
                    <thead><tr><th>Rank</th><th>Name/Email</th><th>Total Donated Units</th></tr></thead>
                    <tbody>
                    <?php $rank=1; foreach ($top_donors as $uid=>$units): ?>
                        <tr>
                            <td><?= $rank++ ?></td>
                            <td><?= htmlspecialchars($donor_names[$uid] ?? $uid) ?></td>
                            <td><?= number_format($units, 1) ?></td>
                        </tr>
                    <?php endforeach; if ($rank==1): ?>
                        <tr><td colspan="3">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- TRENDS -->
        <?php if ($tab=='trends'): ?>
        <div class="tab-pane fade show active" id="trends" role="tabpanel">
            <form class="row g-3 align-items-end mb-4" method="get">
                <input type="hidden" name="tab" value="trends">
                <div class="col-md-3">
                    <label class="form-label">Hospital</label>
                    <select class="form-select" name="hospital">
                        <option value="">All Hospitals</option>
                        <?php foreach ($hospital_options as $hid => $hn): ?>
                            <option value="<?= $hid ?>" <?= $selected_hospital == $hid ? 'selected' : '' ?>><?= htmlspecialchars($hn) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Blood Type</label>
                    <select class="form-select" name="blood_type">
                        <option value="">All Types</option>
                        <?php foreach ($blood_types as $b): ?>
                            <option <?= $selected_blood_type==$b?'selected':''?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100" type="submit">Apply</button>
                </div>
            </form>
            <div class="dashboard-card">
                <div class="card-title">Donation Trends (Monthly)</div>
                <canvas id="donationTrends" height="70"></canvas>
            </div>
            <div class="dashboard-card">
                <div class="card-title">Supply vs. Demand</div>
                <canvas id="supplyDemand" height="70"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- HOSPITALS -->
        <?php if ($tab=='hospitals'): ?>
        <div class="tab-pane fade show active" id="hosp" role="tabpanel">
            <form class="row g-3 align-items-end mb-4" method="get">
                <input type="hidden" name="tab" value="hospitals">
                <div class="col-md-3">
                    <label class="form-label">Hospital</label>
                    <select class="form-select" name="hospital">
                        <option value="">All Hospitals</option>
                        <?php foreach ($hospital_options as $hid => $hn): ?>
                            <option value="<?= $hid ?>" <?= $selected_hospital == $hid ? 'selected' : '' ?>><?= htmlspecialchars($hn) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100" type="submit">Apply</button>
                </div>
            </form>
            <div class="dashboard-card">
                <div class="card-title">Hospital Comparison (Key KPIs)</div>
                <table class="table table-bordered mt-2">
                    <thead><tr><th>Hospital</th><th>Total Blood Supply (ml)</th></tr></thead>
                    <tbody>
                    <?php foreach ($hospital_comparison as $hid => $vol): ?>
                        <?php if (!$selected_hospital || $selected_hospital == $hid): ?>
                        <tr>
                            <td><?= htmlspecialchars($hospital_options[$hid] ?? $hid) ?></td>
                            <td><?= number_format($vol, 1) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; if (empty($hospital_comparison)): ?>
                        <tr><td colspan="2">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- DONORS -->
        <?php if ($tab=='donors'): ?>
        <div class="tab-pane fade show active" id="donor" role="tabpanel">
            <form class="row g-3 align-items-end mb-4" method="get">
                <input type="hidden" name="tab" value="donors">
                <div class="col-md-2">
                    <label class="form-label">Blood Type</label>
                    <select class="form-select" name="blood_type">
                        <option value="">All Types</option>
                        <?php foreach ($blood_types as $b): ?>
                            <option <?= $selected_blood_type==$b?'selected':''?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100" type="submit">Apply</button>
                </div>
                <div class="col-md-1 export-btns text-end">
                    <button type="button" onclick="window.print()" class="btn btn-outline-dark">Print</button>
                </div>
            </form>
            <div class="dashboard-card">
                <div class="card-title">Donor List</div>
                <table class="table table-bordered mt-2">
                    <thead><tr><th>Name</th><th>Blood Type</th><th>Last Donated</th></tr></thead>
                    <tbody>
                    <?php foreach ($donors_tab as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['full_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['blood_type'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['last_donate_date'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; if (empty($donors_tab)): ?>
                        <tr><td colspan="3">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- BLOOD TYPES -->
        <?php if ($tab=='blood_types'): ?>
        <div class="tab-pane fade show active" id="blood" role="tabpanel">
            <form class="row g-3 align-items-end mb-4" method="get">
                <input type="hidden" name="tab" value="blood_types">
                <div class="col-md-2">
                    <label class="form-label">Blood Type</label>
                    <select class="form-select" name="blood_type">
                        <option value="">All Types</option>
                        <?php foreach ($blood_types as $b): ?>
                            <option <?= $selected_blood_type==$b?'selected':''?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100" type="submit">Apply</button>
                </div>
            </form>
            <div class="dashboard-card">
                <div class="card-title">Blood Type Distribution (Current Supply)</div>
                <canvas id="bloodTypesChart" height="60" style="max-height:300px;"></canvas>
            </div>
            <div class="dashboard-card">
                <div class="card-title">Blood Type Table</div>
                <table class="table table-bordered mt-2">
                    <thead><tr><th>Blood Type</th><th>Total Volume (ml)</th></tr></thead>
                    <tbody>
                    <?php foreach ($blood_type_vols as $bt=>$vol): ?>
                        <tr>
                            <td><?= htmlspecialchars($bt) ?></td>
                            <td><?= number_format($vol,1) ?></td>
                        </tr>
                    <?php endforeach; if (empty($blood_type_vols)): ?>
                        <tr><td colspan="2">No data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- APPOINTMENTS -->
        <?php if ($tab=='appointments'): ?>
        <div class="tab-pane fade show active" id="appt" role="tabpanel">
            <form class="row g-3 align-items-end mb-4" method="get">
                <input type="hidden" name="tab" value="appointments">
                <div class="col-md-3">
                    <label class="form-label">Hospital</label>
                    <select class="form-select" name="hospital">
                        <option value="">All Hospitals</option>
                        <?php foreach ($hospital_options as $hid => $hn): ?>
                            <option value="<?= $hid ?>" <?= $selected_hospital == $hid ? 'selected' : '' ?>><?= htmlspecialchars($hn) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-danger w-100" type="submit">Apply</button>
                </div>
            </form>
            <div class="dashboard-card">
                <div class="card-title">Appointments Trend (Monthly)</div>
                <canvas id="apptTrendChart" height="70"></canvas>
            </div>
            <div class="dashboard-card">
                <div class="card-title">Appointment Source Breakdown</div>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <canvas id="apptSourceChart" height="90"></canvas>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr><th>Type</th><th>Count</th></tr>
                            <tr><td>Invite-based</td><td><?= $invite_count ?></td></tr>
                            <tr><td>Request-based</td><td><?= $request_count ?></td></tr>
                        </table>
                        <div class="mt-2"><b>Fulfillment Rate:</b> <?= $fulfillment_rate ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener('DOMContentLoaded', function () {
    // Only render charts if element exists!
    if (document.getElementById('donationTrends')) {
        new Chart(document.getElementById('donationTrends'), {
            type: 'line',
            data: {
                labels: <?= $trends_labels ?>,
                datasets: [{ label: 'Donations', data: <?= $trends_values ?>, borderColor: '#b7322c', fill: false }]
            }
        });
    }
    if (document.getElementById('bloodTypesChart')) {
        new Chart(document.getElementById('bloodTypesChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($blood_type_vols)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($blood_type_vols)) ?>,
                    backgroundColor: [
                        '#fa8072', '#fbb4ae', '#f9dd94', '#b3cde3', '#b7322c', '#c9c9ff', '#a1d99b', '#fb8072'
                    ]
                }]
            }
        });
    }
    if (document.getElementById('repeatDonors')) {
        new Chart(document.getElementById('repeatDonors'), {
            type: 'doughnut',
            data: {
                labels: ['Repeat', 'First-time'],
                datasets: [{
                    data: [65, 35], // Dummy
                    backgroundColor: ['#b7322c', '#f9dd94']
                }]
            }
        });
    }
    if (document.getElementById('supplyDemand')) {
        new Chart(document.getElementById('supplyDemand'), {
            type: 'bar',
            data: {
                labels: <?= $trends_labels ?>,
                datasets: [
                    { label: 'Supply', data: <?= $supply_values ?>, backgroundColor: '#fa8072' },
                    { label: 'Demand', data: <?= $request_values ?>, backgroundColor: '#b7322c' }
                ]
            }
        });
    }
    // Appointments
    if (document.getElementById('apptTrendChart')) {
        new Chart(document.getElementById('apptTrendChart'), {
            type: 'line',
            data: {
                labels: <?= $appt_trend_labels ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?= $appt_trend_values ?>,
                    borderColor: '#b7322c',
                    fill: false
                }]
            }
        });
    }
    if (document.getElementById('apptSourceChart')) {
        new Chart(document.getElementById('apptSourceChart'), {
            type: 'doughnut',
            data: {
                labels: ['Invite-based', 'Request-based'],
                datasets: [{
                    data: [<?= $invite_count ?>, <?= $request_count ?>],
                    backgroundColor: ['#fa8072', '#b7322c']
                }]
            }
        });
    }
});
</script>
</body>
</html>
