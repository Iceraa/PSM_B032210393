<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<h1>Welcome, <?= htmlspecialchars($_SESSION['user_email']) ?>!</h1>
<a href="logout.php">Logout</a>
