<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/translation-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => __('Invalid request method')]);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => __('Invalid security token')]);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => __('Invalid user id')]);
    exit;
}

$u = db_query('SELECT id, name, email FROM users WHERE id = ?', [$user_id]);
if (empty($u)) {
    echo json_encode(['success' => false, 'message' => __('User not found')]);
    exit;
}
$user = $u[0];

try {
    $new_password = trim($_POST['new_password'] ?? '');
    if (empty($new_password) || strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => __('Password must be at least 6 characters')]);
        exit;
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT);

    db_update('users', ['password' => $hash, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$user_id]);

    echo json_encode(['success' => true, 'message' => __('Password updated successfully')]);
    exit;
} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage(), 3, ROOT . '/logs/email-errors.log');
    echo json_encode(['success' => false, 'message' => __('An error occurred while resetting the password')]);
    exit;
}
