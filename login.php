<?php
session_start();

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl = 'https://lorvwulnebjxtipkvsvz.supabase.co/auth/v1/token?grant_type=password';
    $apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDU2NDUzNDYsImV4cCI6MjA2MTIyMTM0Nn0.B_PVLtcRp4NqGy1GyFV54ArKuyWld9LxIMJxYsVH1Q0';

    $payload = json_encode([
        'email' => $email,
        'password' => $password,
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['access_token'])) {
        $userId = $result['user']['id'];

        // --- Fetch the user's meta data from Auth API ---
        $userApiUrl = "https://lorvwulnebjxtipkvsvz.supabase.co/auth/v1/user";
        $chUser = curl_init($userApiUrl);
        curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chUser, CURLOPT_HTTPHEADER, [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $result['access_token'],
        ]);
        $userResponse = curl_exec($chUser);
        curl_close($chUser);

        $userObj = json_decode($userResponse, true);

        // Check for superadmin role in metadata (either user_metadata or app_metadata)
        $customRole = $userObj['user_metadata']['role'] ?? null;
        if (!$customRole && isset($userObj['app_metadata']['role'])) {
            $customRole = $userObj['app_metadata']['role'];
        }
        // If also using raw_app_meta_data as object:
        if (!$customRole && isset($userObj['raw_app_meta_data']['role'])) {
            $customRole = $userObj['raw_app_meta_data']['role'];
        }

        // --- If SUPERADMIN, redirect to separate homepage
        if ($customRole === 'superadmin') {
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $result['user']['email'];
            $_SESSION['role'] = 'superadmin';
            header("Location: superadmin_home.php"); // <-- set your superadmin home
            exit;
        }

        // --- Otherwise, check staff table for Admin login ---
        $staffUrl = "https://lorvwulnebjxtipkvsvz.supabase.co/rest/v1/staff?user_id=eq.$userId&select=position,hospital_id";
        $ch2 = curl_init($staffUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $result['access_token'],
        ]);
        $staffResponse = curl_exec($ch2);
        curl_close($ch2);

        $staffData = json_decode($staffResponse, true);
//         echo "<pre>";
// print_r($staffData);
// echo "</pre>";
// exit;

        if (!empty($staffData[0]['hospital_id'])) {
            $_SESSION['hospital_id'] = $staffData[0]['hospital_id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $result['user']['email'];
            header("Location: home.php");
            exit;
        } else {
            $error = "Hospital ID missing.";
        }


    } elseif ($curlError) {
        $error = "Connection error: $curlError";
    } elseif (isset($result['error_description'])) {
        $error = $result['error_description'];
    } else {
        $error = "Login failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Blood Donation Admin</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f3f3f3;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background-color: #b7322c;
            padding: 14px 24px;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        .container {
            max-width: 420px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 64px;
            color: #b7322c;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 24px;
        }
        input[type=email], input[type=password] {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        button {
            background-color: #b7322c;
            color: white;
            font-size: 16px;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-bottom: 16px;
        }
        .footer-link {
            margin-top: 16px;
            display: block;
            font-size: 14px;
            color: #444;
        }
    </style>
</head>
<body>

    <div class="navbar">Home</div>

    <div class="container">
        <div class="logo">🩸</div>
        <div class="title">Blood Donation Management System Administration</div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Staff Email" value="<?= htmlspecialchars($email) ?>" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>

        <a href="#" class="footer-link">Forgot Password?</a>
    </div>

</body>
</html>
