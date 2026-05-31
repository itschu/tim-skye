<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/users');
    exit;
}

// Verify CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/users');
    exit;
}

// Validate user_id
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    $_SESSION['error'] = __('Invalid user ID');
    header('Location: /admin/users');
    exit;
}

try {
    // Fetch current user
    $user_query = "SELECT id, status FROM users WHERE id = ?";
    $user_result = db_query($user_query, [$user_id]);

    if (empty($user_result)) {
        $_SESSION['error'] = __('User not found');
        header('Location: /admin/users');
        exit;
    }

    $user = $user_result[0];

    // Toggle status
    $new_status = ($user['status'] === 'active') ? 'banned' : 'active';

    // Update user status
    db_update(
        'users',
        [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$user_id]
    );

    if ($new_status === 'banned') {
        $_SESSION['success'] = __('User banned successfully');
    } else {
        $_SESSION['success'] = __('User unbanned successfully');
    }

    header('Location: /admin/users');
    exit;
} catch (Exception $e) {
    error_log('User ban error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while updating user status');
    header('Location: /admin/users');
    exit;
}
