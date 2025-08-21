<?php
// supabase_connector.php
$SUPABASE_URL = 'https://lorvwulnebjxtipkvsvz.supabase.co';
$SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxvcnZ3dWxuZWJqeHRpcGt2c3Z6Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0NTY0NTM0NiwiZXhwIjoyMDYxMjIxMzQ2fQ.Y2XUrAFk7irzsiUNvEa24BrKUybWQmKLBL7CnxjipX0'; // Do NOT expose this publicly

function fetch_from_supabase($table_name, $filter = "") {
    global $SUPABASE_URL, $SUPABASE_KEY;

    $url = "$SUPABASE_URL/rest/v1/$table_name";
    if ($filter != "") {
        $url .= "?$filter";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function get_auth_user_by_email($email, $SUPABASE_PROJECT_ID, $SUPABASE_SERVICE_ROLE_KEY) {
    $url = "https://$SUPABASE_PROJECT_ID.supabase.co/auth/v1/admin/users?email=" . urlencode($email);
    $headers = [
        "Authorization: Bearer $SUPABASE_SERVICE_ROLE_KEY",
        "apikey: $SUPABASE_SERVICE_ROLE_KEY"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (!empty($data['users'][0]['id'])) {
        return $data['users'][0];
    }
    return null;
}


function insert_into_supabase($table_name, $data) {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = "$SUPABASE_URL/rest/v1/$table_name";
    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response !== false;
}




function update_supabase($table_name, $data, $filter) {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = "$SUPABASE_URL/rest/v1/$table_name?$filter";
    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response !== false;
}

function delete_from_supabase($table_name, $filter) {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = "$SUPABASE_URL/rest/v1/$table_name?$filter";
    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Prefer: return=representation"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

  
}


?>
