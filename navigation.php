<!-- navigation.php -->
<?php
// Start session for every page using navigation
session_start();

// Only protect if NOT the login page (allow login page to load)
if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
// You can also set a default time zone, etc. here if you want

?>

<style>
/* Header */
.header-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 54px;
    background: #b7322c;
    color: #fff;
    font-size: 1.2rem;
    z-index: 100;
    padding-left: 16px;
    display: flex;
    align-items: center;
}
/* Sidebar */
.sidebar {
    position: fixed;
    top: 54px; /* header height */
    left: 0;
    width: 200px;
    height: calc(100vh - 54px);
    background: #f8f8f8;
    border-right: 1px solid #e4e4e4;
    z-index: 10;
    padding-top: 0;
}
.sidebar ul {
    margin: 0; padding: 0; list-style: none;
}
.sidebar li {
    padding: 18px 24px;
    color: #b30000;
    cursor: pointer;
    font-size: 1.1rem;
    border-left: 4px solid transparent;
    transition: background .13s, border .13s;
}
.sidebar li:hover, .sidebar .active {
    background: #ececec;
    border-left: 4px solid #b30000;
    font-weight: 600;
}
@media (max-width: 800px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
        border-right: none;
        border-bottom: 1px solid #eee;
    }
    .header-bar { position: static; }
}
</style>

<div class="header-bar">
  Blood Donation Management System Dashboard
  <span style="flex:1"></span>
  <a href="logout.php" style="color:#f2f2f2;font-weight:600; text-decoration:none; padding:0 16px;">Logout</a>
</div>

<div class="sidebar">
  <ul>
    <li class="active" onclick="window.location='data_entry.php'">Dashboard</li>
    
    
    <li onclick="window.location='appointment.php'">Appointments</li>
    <li onclick="window.location='blood_request.php'">Blood Requests</li>
    <li onclick="window.location='blood_supply.php'">Blood Supply</li>
    <li onclick="window.location='blood_donation.php'">Donations</li>
    <li onclick="window.location='donor_invite.php'">Invitations</li>
    <li onclick="window.location='staff_shift.php'">Staff Shifts</li>
    <li onclick="window.location='blood_given.php'">Blood Given</li>
    <li onclick="window.location='home.php'">Back to Home</li>
  </ul>
</div>
