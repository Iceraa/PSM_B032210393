<?php
include 'supabase_connector.php';
include 'navigation.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

$message = "";

// Fetch donor and staff names
$donorList = fetch_from_supabase("donor", "select=user_id,full_name");
$donorMap = [];
foreach ($donorList as $d) {
    $donorMap[$d['user_id']] = $d['full_name'];
}

$staffList = fetch_from_supabase("staff", "select=user_id,full_name");
$staffMap = [];
foreach ($staffList as $s) {
    $staffMap[$s['user_id']] = $s['full_name'];
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role !== 'superadmin') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $add = [
                'donor_id' => $_POST['donor_id'] ?? null,
                'hospital_id' => $hospitalId,
                'invited_by' => $_POST['invited_by'] ?? null,
                'reason' => $_POST['reason'] ?? null,
                'invite_date' => $_POST['invite_date'] ?? null,
                'status' => $_POST['status'] ?? 'pending'
            ];
            $ok = insert_into_supabase('donor_invite', $add);
            $message = $ok ? "Invitation added successfully." : "Failed to add invitation.";
        } elseif ($_POST['action'] === 'update' && isset($_POST['invite_id'])) {
            $update = [
                'donor_id' => $_POST['donor_id'] ?? null,
                'invited_by' => $_POST['invited_by'] ?? null,
                'reason' => $_POST['reason'] ?? null,
                'invite_date' => $_POST['invite_date'] ?? null,
                'status' => $_POST['status'] ?? null,
            ];
            $ok = update_supabase('donor_invite', $update, "invite_id=eq.{$_POST['invite_id']}");
            $message = $ok ? "Invitation updated." : "Failed to update invitation.";
        } elseif ($_POST['action'] === 'delete' && isset($_POST['invite_id'])) {
            $ok = update_supabase('donor_invite', ['status' => 'canceled'], "invite_id=eq.{$_POST['invite_id']}");
            $message = $ok ? "Invitation canceled." : "Failed to cancel invitation.";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

$filter = [];
if ($role !== 'superadmin') {
    $filter[] = "hospital_id=eq.$hospitalId";
}
if (!empty($_GET['filter_status'])) {
    $filter[] = "status=eq." . urlencode($_GET['filter_status']);
}
$filterQuery = $filter ? 'select=*' . '&' . implode('&', $filter) : 'select=*';
$invites = fetch_from_supabase("donor_invite", $filterQuery);

$sortBy = $_GET['sort_by'] ?? '';
if ($sortBy && is_array($invites)) {
    usort($invites, function ($a, $b) use ($sortBy) {
        return strcmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
    });
}

// Chart data
$statusCounts = array_fill_keys(["pending", "accepted", "rejected", "canceled"], 0);
foreach ($invites as $inv) {
    $status = $inv['status'] ?? '';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Invitations</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; }
        .container { padding: 20px; max-width: 1400px; margin: auto; margin-left: 230px; margin-top: 40px; }
        .forms-wrapper { display: flex; gap: 20px; margin-top: 20px; }
        .side-form { flex: 0 0 350px; }
        .form-section { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 14px; }
        .form-label { font-weight: 600; display: block; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 6px; border-radius: 4px; border: 1px solid #ccc; }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-right: 8px; }
        .btn-add { background: #388e3c; color: #fff; border: none; margin-bottom: 10px; }
        .btn-update { background: #1976d2; color: #fff; border: none; }
        .btn-delete { background: #e53935; color: #fff; border: none; }
        .btn-clear { background: #ccc; color: #333; border: none; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        tr.selected { background: #fff3cd !important; }
        .chart-wrapper { width: 30%; max-width: 400px; margin: 0 auto; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage Invitations</h2>
    <div class="chart-wrapper">
        <canvas id="inviteChart"></canvas>
    </div>
    <form method="get">
        <label>Status:
            <select name="filter_status">
                <option value="">-- All --</option>
                <?php foreach (["pending", "accepted", "rejected", "canceled"] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['filter_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sort by:
            <select name="sort_by">
                <option value="">-- None --</option>
                <option value="invite_date" <?= ($_GET['sort_by'] ?? '') === 'invite_date' ? 'selected' : '' ?>>Invite Date</option>
                <option value="status" <?= ($_GET['sort_by'] ?? '') === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
        </label>
        <button type="submit">Apply</button>
        <button type="button" onclick="window.print()">🖨️ Print</button>
        <button type="button" onclick="exportTableToCSV('invites.csv')">⬇ Export</button>
    </form>

    <div class="forms-wrapper">
        <div class="side-form">
            <?php if ($role !== 'superadmin'): ?>
                <button class="btn btn-add" onclick="showAddForm()">+ Add Invitation</button>
                <form method="post" class="form-section" id="addForm" style="display:none;">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label">Donor ID (User ID)</label>
                        <input class="form-input" type="text" name="donor_id" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invited By (User ID)</label>
                        <input class="form-input" type="text" name="invited_by" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input class="form-input" type="text" name="reason">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invite Date</label>
                        <input class="form-input" type="date" name="invite_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <?php foreach (["pending","accepted","rejected","canceled"] as $s): ?>
                                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-update">Add</button>
                    <button type="button" class="btn btn-clear" onclick="clearAddForm()">Cancel</button>
                </form>
                <form method="post" class="form-section" id="editForm" style="display:none;">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="form_invite_id" name="invite_id">
                    <div class="form-group">
                        <label class="form-label">Donor ID (User ID)</label>
                        <input class="form-input" type="text" id="form_donor_id" name="donor_id" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invited By (User ID)</label>
                        <input class="form-input" type="text" id="form_invited_by" name="invited_by" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input class="form-input" type="text" id="form_reason" name="reason">
                        
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invite Date</label>
                        <input class="form-input" type="date" id="form_invite_date" name="invite_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" id="form_status" name="status">
                            <?php foreach (["pending","accepted","rejected","canceled"] as $s): ?>
                                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-update">Update</button>
                    <button type="submit" class="btn btn-delete" name="action" value="delete" onclick="return confirm('Cancel this invitation?');">Cancel Invite</button>
                    <button type="button" class="btn btn-clear" onclick="clearEditForm()">Clear</button>
                </form>
            <?php endif; ?>
        </div>

        <table>
            <tr>
                <th>No</th>
                <th>Donor</th>
                <th>Staff</th>
                <th>Reason</th>
                <th>Invite Date</th>
                <th>Status</th>
            </tr>
            <?php if (is_array($invites)): foreach ($invites as $i => $d): ?>
                <tr class="invite-row" id="row_<?= $i ?>" onclick="fillEditForm(<?= $i ?>)">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($donorMap[$d['donor_id']] ?? '-') ?></td>
                    <td><?= htmlspecialchars($staffMap[$d['invited_by']] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['reason'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['invite_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['status'] ?? '-') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">No invitations found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<script>
let invites = <?= json_encode($invites) ?>;
function fillEditForm(idx) {
    document.querySelectorAll('.invite-row').forEach(r => r.classList.remove('selected'));
    let row = document.getElementById('row_' + idx);
    if (row) row.classList.add('selected');
    let inv = invites[idx];
    if (inv) {
        document.getElementById('form_invite_id').value = inv['invite_id'] || '';
        document.getElementById('form_donor_id').value = inv['donor_id'] || '';
        document.getElementById('form_invited_by').value = inv['invited_by'] || '';
        document.getElementById('form_reason').value = inv['reason'] || '';
        document.getElementById('form_invite_date').value = inv['invite_date'] || '';
        document.getElementById('form_status').value = inv['status'] || '';
        document.getElementById('editForm').style.display = '';
        document.getElementById('addForm').style.display = 'none';
    }
}
function clearEditForm() {
    document.getElementById('editForm').reset();
    document.getElementById('editForm').style.display = 'none';
}
function showAddForm() {
    clearEditForm();
    document.getElementById('addForm').style.display = '';
}
function clearAddForm() {
    document.getElementById('addForm').reset();
    document.getElementById('addForm').style.display = 'none';
}
function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("table tr");
    for (let row of rows) {
        let cols = Array.from(row.querySelectorAll("td, th")).map(col => `"${col.innerText}"`);
        csv.push(cols.join(","));
    }
    let blob = new Blob([csv.join("\n")], { type: 'text/csv' });
    let a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
}

// Chart
const ctx = document.getElementById('inviteChart');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Accepted', 'Declined', 'Canceled'],
        datasets: [{
            data: [
                <?= $statusCounts['pending'] ?>,
                <?= $statusCounts['accepted'] ?>,
                <?= $statusCounts['rejected'] ?>,
                <?= $statusCounts['canceled'] ?>
            ],
            backgroundColor: ['#facc15', '#22c55e', '#f87171', '#a3a3a3'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom' },
            title: { display: true, text: 'Invitation Status Distribution' }
        }
    }
});
</script>
</body>
</html>