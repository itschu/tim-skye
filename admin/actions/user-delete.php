<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/users');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/users');
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    $_SESSION['error'] = __('Invalid user id');
    header('Location: /admin/users');
    exit;
}

// Prevent deleting currently logged-in admin
$current_admin_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
if ($current_admin_id && intval($current_admin_id) === intval($user_id)) {
    $_SESSION['error'] = __('You cannot delete your own account');
    header('Location: /admin/users');
    exit;
}

try {
    db_delete('users', 'id = ?', [$user_id]);
    $_SESSION['success'] = __('User deleted successfully');
    header('Location: /admin/users');
    exit;
} catch (Exception $e) {
    error_log('User delete error: ' . $e->getMessage(), 3, ROOT . '/logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while deleting the user');
    header('Location: /admin/users');
    exit;
}
