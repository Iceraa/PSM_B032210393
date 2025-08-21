<?php
include 'header.php';
include 'supabase_connector.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

// Fetch staff from same hospital only
$staffList = [];
if ($hospitalId) {
    $staffList = fetch_from_supabase("staff", "hospital_id=eq.$hospitalId");
    if (!empty($_GET['sort_by'])) {
        $sortKey = $_GET['sort_by'];
        usort($staffList, function ($a, $b) use ($sortKey) {
            return strcmp($b[$sortKey], $a[$sortKey]);
        });
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Staff</title>
    <style>
        .container { padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        .controls { margin-bottom: 20px; }
        .flex-box { display: flex; gap: 20px; margin-top: 20px; }
        .section-box { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.05); flex: 1; }
        body { background: #f8fafc; font-family: Arial, sans-serif; }
        h2 { border-left: 5px solid #dc3545; padding-left: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage STAFF</h2>

    <a href="users.php">
        <button style="margin-bottom: 10px; background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px;">View Donor Information</button>
    </a>

    <div class="controls">
        <form method="get">
            <label>Sort by:
                <select name="sort_by">
                    <option value="">-- None --</option>
                    <option value="full_name" <?= ($_GET['sort_by'] ?? '') === 'full_name' ? 'selected' : '' ?>>Name</option>
                    <option value="email" <?= ($_GET['sort_by'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                </select>
            </label>
            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="flex-box">
        <div class="section-box">
            <h3>Aggregate Report</h3>
            <ul>
                <li>Total Staff: <?= count($staffList) ?></li>
                <li>Hospital ID: <?= $hospitalId ?></li>
            </ul>
        </div>

        <div class="section-box">
            <h3>Staff List</h3>
            <table>
                <tr><th>No</th><th>Staff ID</th><th>Full Name</th><th>Tel No</th><th>Email</th></tr>
                <?php if (is_array($staffList)): foreach ($staffList as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= $s['staff_id'] ?></td>
                        <td><?= $s['full_name'] ?></td>
                        <td><?= $s['tel_no'] ?></td>
                        <td><?= $s['email'] ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
