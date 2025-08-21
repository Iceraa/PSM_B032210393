<?php


function get_auth_users($role, $hospitalId, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY) {
    $url = "https://$SUPABASE_PROJECT_ID.supabase.co/auth/v1/admin/users";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
            "apikey: $SUPABASE_SERVICE_ROLE_KEY",
            "Content-Type: application/json"
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $authUsers = json_decode($response, true);

    // DEBUG: If error, show details
    if (!isset($authUsers['users'])) {
        error_log('Supabase Auth fetch failed: ' . print_r($authUsers, true));
    }

    // Filter for admin to only their hospital's staff
    if ($role === 'admin' && $hospitalId) {
        include_once __DIR__ . '/../supabase_connector.php';
        $staffs = fetch_from_supabase('staff', "hospital_id=eq.$hospitalId");
        $allowedIds = array_column($staffs, 'user_id');
        $authUsers['users'] = array_values(array_filter($authUsers['users'] ?? [], function ($u) use ($allowedIds) {
            return in_array($u['id'], $allowedIds);
        }));
    }
    return $authUsers;
}
