<?php
include 'header.php';
include 'supabase_connector.php';

$role = $_SESSION['role'] ?? 'staff';
$hospitalId = $_SESSION['hospital_id'] ?? null;
$type = $_GET['type'] ?? 'donor'; // donor or staff

$users = [];
$filter = '';

if ($type === 'staff') {
    if ($hospitalId) {
        $filter = "hospital_id=eq.$hospitalId";
        $users = fetch_from_supabase("staff", $filter);
    }
} else if ($type === 'donor') {
    if ($role === 'superadmin') {
        $users = fetch_from_supabase("donor");
    } else {
        // Hospital admin can only view donors (e.g., who have appointments at their hospital)
        $appointments = fetch_from_supabase("appointment", "hospital_id=eq.$hospitalId");
        $donorIds = [];
        if (is_array($appointments)) {
            foreach ($appointments as $app) {
                if (!in_array($app['user_id'], $donorIds)) {
                    $donorIds[] = $app['user_id'];
                }
            }
        }
        foreach ($donorIds as $id) {
            $result = fetch_from_supabase("donor", "user_id=eq.$id");
            if (is_array($result)) {
                $users = array_merge($users, $result);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        .container { padding: 20px; display: flex; gap: 20px; }
        .form-box, .table-box { flex: 1; }
        input, select { display: block; margin-bottom: 10px; width: 100%; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: center; }
        th { background: #f4f4f4; }
        button { padding: 8px 16px; margin-right: 8px; }
    </style>
</head>
<body>
<div class="container">
    <div class="form-box">
        <h3>Manage <?= strtoupper($type) ?></h3>
        <?php if ($type === 'staff' || $role === 'superadmin'): ?>
        <form method="POST" action="user_action.php">
            <?php if ($type === 'donor'): ?>
                <input type="text" name="user_id" placeholder="Donor ID" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="text" name="gender" placeholder="Gender">
                <input type="text" name="blood_type" placeholder="Blood Type">
            <?php else: ?>
                <input type="text" name="staff_id" placeholder="Staff ID" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="text" name="phone_no" placeholder="Tel No">
                <input type="email" name="email" placeholder="Email">
            <?php endif; ?>
            <input type="hidden" name="type" value="<?= $type ?>">
            <button type="submit" name="action" value="add">Add</button>
            <button type="submit" name="action" value="update">Update</button>
            <button type="submit" name="action" value="delete">Delete</button>
        </form>
        <?php else: ?>
            <p>You have read-only access to donor data. To modify donor info, please contact a superadmin.</p>
        <?php endif; ?>
    </div>

    <div class="table-box">
        <h3><?= ucfirst($type) ?> List</h3>
        <table>
            <tr>
                <th>No</th>
                <?php if ($type === 'donor'): ?>
                    <th>Donor ID</th><th>Full Name</th><th>Gender</th><th>Blood Type</th>
                <?php else: ?>
                    <th>Staff ID</th><th>Full Name</th><th>Tel No</th><th>Email</th>
                <?php endif; ?>
            </tr>
            <?php $i = 1; foreach ($users as $u): ?>
            <tr>
                <td><?= $i++ ?></td>
                <?php if ($type === 'donor'): ?>
                    <td><?= $u['user_id'] ?></td>
                    <td><?= $u['full_name'] ?></td>
                    <td><?= $u['gender'] ?></td>
                    <td><?= $u['blood_type'] ?></td>
                <?php else: ?>
                    <td><?= $u['staff_id'] ?></td>
                    <td><?= $u['full_name'] ?></td>
                    <td><?= $u['phone_no'] ?></td>
                    <td><?= $u['email'] ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
