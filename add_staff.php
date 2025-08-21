<?php
include 'header.php';
include 'supabase_connector.php';
include 'admin-api/auth_helpers.php';

$hospitalId = $_SESSION['hospital_id'] ?? null;
$role = $_SESSION['role'] ?? 'staff';

$SUPABASE_PROJECT_ID = 'lorvwulnebjxtipkvsvz';
$SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0';

$add_message = '';
$new_staff_id = '';
$email_for_next = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['add_step'] === 'account') {
        $add_email = $_POST['add_email'] ?? '';
        $add_password = $_POST['add_password'] ?? '';

        // Step 1a: Check if this email already has a staff in this hospital
        // 1. Find user in auth.users
        $authUsersRaw = get_auth_users('superadmin', null, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY);
        $targetUser = null;
        foreach (($authUsersRaw['users'] ?? []) as $u) {
            if (strtolower($u['email']) === strtolower($add_email)) {
                $targetUser = $u;
                break;
            }
        }

        if ($targetUser) {
            // Check if staff with this user_id exists in this hospital
            $exists = fetch_from_supabase('staff', "hospital_id=eq.$hospitalId&user_id=eq.{$targetUser['id']}");
            if ($exists && count($exists) > 0) {
                $add_message = "A staff with this email already exists in this hospital.";
            } else {
                // Can use this user for staff, go to Step 2
                $new_staff_id = $targetUser['id'];
                $email_for_next = $add_email;
            }
        } else {
            // Create new auth user
            $url = "https://$SUPABASE_PROJECT_ID.supabase.co/auth/v1/admin/users";
            $payload = json_encode(['email' => $add_email, 'password' => $add_password, 'email_confirm' => true]);
            $headers = [
                "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
                "apikey: $SUPABASE_SERVICE_ROLE_KEY",
                "Content-Type: application/json"
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $auth_response = curl_exec($ch);
            curl_close($ch);
            $auth_data = json_decode($auth_response, true);

            if (!empty($auth_data['id'])) {
                $new_staff_id = $auth_data['id'];
                $email_for_next = $add_email;
            } else {
                $add_message = "Failed to create user. " . ($auth_data['msg'] ?? $auth_data['error_description'] ?? "Check email/password validity.");
            }
        }
    } elseif ($_POST['add_step'] === 'info') {
        $new_staff_id = $_POST['add_user_id'] ?? '';
        $add_full_name = $_POST['add_full_name'] ?? '';
        $add_tel_no = $_POST['add_tel_no'] ?? '';
        $add_position = $_POST['add_position'] ?? '';
        $email_for_next = $_POST['add_email'] ?? '';

        // Insert staff record
        $result = insert_into_supabase('staff', [
            'user_id' => $new_staff_id,
            'full_name' => $add_full_name,
            'tel_no' => $add_tel_no,
            'position' => $add_position,
            'hospital_id' => $hospitalId,
            'is_active' => true
        ]);
        if ($result) {
            $add_message = "Staff account created and added successfully!";
            $new_staff_id = '';
        } else {
            $add_message = "Failed to add staff info. Please check details and try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Staff</title>
    <style>
        body { background: #f8fafc; font-family: Arial, sans-serif; }
        .container { padding: 20px; max-width: 600px; margin: 40px auto; }
        .card { background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 0 16px rgba(0,0,0,0.06);}
        h2, h3 { margin-top: 0; }
        .form-label { font-weight: 500; margin-top: 10px; display: block; }
        .form-input { width: 100%; padding: 6px 8px; margin-bottom: 14px; border-radius: 4px; border: 1px solid #ccc; }
        .btn { background: #1976d2; color: #fff; border: none; border-radius: 4px; padding: 8px 16px; cursor: pointer; margin-top: 8px;}
        .msg { color: #1976d2; margin-bottom: 8px; }
        .btn-cancel { background: #e0e0e0; color: #111; margin-left: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        
        <h2>Add New Staff</h2>
        <?php if ($add_message): ?>
            <div class="msg"><?= htmlspecialchars($add_message) ?></div>
        <?php endif; ?>

        <?php if (!$new_staff_id): ?>
        <!-- STEP 1: Account (email + password) -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="add_step" value="account">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="add_email" required>
            <label class="form-label">Password</label>
            <input class="form-input" type="password" name="add_password" required>
            <button type="submit" class="btn">Next</button>
            <a href="staff_users.php" class="btn btn-cancel">Back</a>
        </form>
        <?php else: ?>
        <!-- STEP 2: Staff Info -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="add_step" value="info">
            <input type="hidden" name="add_user_id" value="<?= htmlspecialchars($new_staff_id) ?>">
            <input type="hidden" name="add_email" value="<?= htmlspecialchars($email_for_next) ?>">
            <label class="form-label">Full Name</label>
            <input class="form-input" type="text" name="add_full_name" required>
            <label class="form-label">Tel No</label>
            <input class="form-input" type="text" name="add_tel_no" required>
            <label class="form-label">Position</label>
            <input class="form-input" type="text" name="add_position" required>
            <button type="submit" class="btn">Add Staff</button>
            <a href="staff_users.php" class="btn btn-cancel">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
