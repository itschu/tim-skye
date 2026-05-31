<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Get search query
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// If empty search, check if we should return all users (for "Select All" feature)
// or just return empty array for normal search
if (strlen($search) === 0) {
    // Check if this is a request for all users (all=1 parameter)
    $get_all = isset($_GET['all']) && $_GET['all'] === '1';
    
    if ($get_all) {
        // Return all active users
        $users = db_query(
            "SELECT id, name, email, balance FROM users 
             WHERE role = 'user'
             AND status = 'active'
             ORDER BY name ASC 
             LIMIT 1000",
            []
        );
    } else {
        // Empty search with no all flag = return empty
        echo json_encode(['users' => []]);
        exit;
    }
} else {
    // Search for users with matching name/email
    $search_param = "%{$search}%";
    $users = db_query(
        "SELECT id, name, email, balance FROM users 
         WHERE (name LIKE ? OR email LIKE ?) 
         AND role = 'user'
         AND status = 'active'
         ORDER BY name ASC 
         LIMIT 50",
        [$search_param, $search_param]
    );
}

// Format user data with name parts
$formatted_users = [];
foreach ($users as $user) {
    $name_parts = explode(' ', trim($user['name']), 2);
    $first_name = $name_parts[0] ?? $user['name'];
    $last_name = $name_parts[1] ?? '';
    
    $formatted_users[] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'first_name' => $first_name,
        'last_name' => $last_name,
        'full_name' => $user['name'],
        'email' => $user['email'],
        'balance' => format_money($user['balance'])
    ];
}

// Return results
echo json_encode(['users' => $formatted_users]);
exit;
