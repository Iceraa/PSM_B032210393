<?php
 

include 'navigation.php'; 


include 'supabase_connector.php';
$staffCount = count(fetch_from_supabase('staff', 'select=user_id'));
$donorCount = count(fetch_from_supabase('donor', 'select=user_id'));
$appCount = count(fetch_from_supabase('appointment', 'select=appointment_id'));
$bloodReqCount = count(fetch_from_supabase('blood_request', 'select=request_id'));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Entry Home</title>
    <style>
        body { background: #f8fafc; font-family: Arial, sans-serif; }
        .container {
            padding: 30px 24px;
            max-width: 1200px;
            margin-left: 220px; /* must be at least the width of sidebar (200px + 20px spacing) */
        }
        .dashboard-cards { display: flex; flex-wrap: wrap; gap: 32px; margin-top: 32px; }
        .card-link { text-decoration: none; color: inherit; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 7px rgba(0,0,0,0.07);
                padding: 24px 34px; min-width: 220px; min-height: 140px; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: box-shadow 0.2s;}
        .card:hover { box-shadow: 0 2px 16px rgba(200,0,0,0.13);}
        .card-title { font-size: 1.2rem; font-weight: 600; }
        .card-value { font-size: 2.1rem; margin: 16px 0 6px; color: #b30000; }
        .stats-header { margin-bottom: 18px; font-size: 1.3rem; }
    </style>
</head>
<body>
<div class="container">
    <h2>Data Entry Dashboard</h2>
    <div class="stats-header">Quick Overview</div>
    <div class="dashboard-cards">
        <div class="card"><div class="card-title">Donors</div><div class="card-value"><?= $donorCount ?></div></div></a>
        <a href="staff_users.php" class="card-link"><div class="card"><div class="card-title">Staff</div><div class="card-value"><?= $staffCount ?></div></div></a>
        <a href="appointment.php" class="card-link"><div class="card"><div class="card-title">Appointments</div><div class="card-value"><?= $appCount ?></div></div></a>
        <a href="blood_request.php" class="card-link"><div class="card"><div class="card-title">Blood Requests</div><div class="card-value"><?= $bloodReqCount ?></div></div></a>
        <!-- Add more cards for other modules as needed -->
    </div>

    <div style="margin-top:40px;">
        <h3>What would you like to manage?</h3>
        <ul>
           
            <li><a href="appointment.php">Manage Appointments</a></li>
            <li><a href="blood_request.php">Manage Blood Requests</a></li>
            <li><a href="blood_supply.php">Manage Blood Supply</a></li>
            <li><a href="blood_donation.php">View Donation History</a></li>
            <li><a href="donor_invite.php">Manage Donor Invitations</a></li>
            <li><a href="blood_given.php">View Blood Given History</a></li>
            <li><a href="staff_shift.php">Manage Staff Shifts</a></li>
            
            <!-- Add more as you go -->
        </ul>
    </div>
</div>
</body>
</html>
