<?php
session_start();

$SUPABASE_PROJECT_ID = 'lorvwulnebjxtipkvsvz'; // YOUR Supabase Project ID
$SUPABASE_SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0'; // Keep this secure!

$role = $_SESSION['role'] ?? 'staff';
$hospitalId = $_SESSION['hospital_id'] ?? null;

// Only allow admin/superadmin
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch all users from Supabase Auth (Admin API)
$url = "https://lorvwulnebjxtipkvsvz.supabase.co/auth/v1/admin/users";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
        "Content-Type: application/json"
    ]
]);
$response = curl_exec($ch);
curl_close($ch);
$authUsers = json_decode($response, true);

// Optionally filter users for admin (not superadmin)
if ($role === 'admin' && $hospitalId) {
    include '../supabase_connector.php';
    $staffs = fetch_from_supabase('staff', "hospital_id=eq.$hospitalId");
    $allowedIds = array_column($staffs, 'user_id');
    $authUsers['users'] = array_values(array_filter($authUsers['users'] ?? [], function ($u) use ($allowedIds) {
        return in_array($u['id'], $allowedIds);
    }));
}


// Output only the final JSON (no HTML or debug)
header('Content-Type: application/json');
echo json_encode($authUsers);
