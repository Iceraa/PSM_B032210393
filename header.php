<?php
session_start();

if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Force only for staff/admin
$role = $_SESSION['role'] ?? 'staff';
if ($role === 'superadmin') {
    header('Location: superadmin_home.php'); // Optional: redirect superadmin out of admin header
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Donation Admin Dashboard</title>
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
            <a href="home.php">Home</a>
            <a href="staff_users.php">Users</a>
            <a href="data_entry.php">Data Entry</a>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </div>
    <?php endif; ?>
</body>
</html>
