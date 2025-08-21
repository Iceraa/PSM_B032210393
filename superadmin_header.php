<?php
session_start();

if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Force only for superadmin
$role = $_SESSION['role'] ?? '';
if ($role !== 'superadmin') {
    header('Location: home.php'); // Optional: redirect admin/staff out of superadmin header
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Donation Superadmin Dashboard</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; }
        .top-header {
            background-color: #b7322c;
            color: white; padding: 14px 20px; font-size: 20px; font-weight: bold;
        }
        .sub-header {
            display: flex; justify-content: space-between; align-items: center;
            background-color: #f2f2f2; padding: 10px 20px;
        }
        .sub-header .nav-links a {
            margin-right: 20px; text-decoration: none; color: #222; font-weight: bold;
        }
        .sub-header .nav-links a:hover { text-decoration: underline; }
        .sub-header .logout { color: red; font-weight: bold; text-decoration: none; }
        .sub-header .logout:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="top-header">Blood Donation Management System Dashboard</div>
    <?php if (basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
    <div class="sub-header">
        <div class="nav-links">
            <a href="superadmin_home.php">Home</a>
            <a href="sp_hospital.php">Hospitals</a>
            <a href="sp_donor.php">Donors</a>
            <a href="sp_staff.php">Staff</a>
            <a href="sp_data_entry.php">Data Entry</a>
            <a href="sp_report.php">Reports</a>
           
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </div>
    <?php endif; ?>
</body>
</html>
