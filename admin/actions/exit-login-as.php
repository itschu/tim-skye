<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/translation-functions.php';

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: /admin/dashboard');
    exit;
}

// Verify CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = 'Invalid security token';
    header('Location: /admin/dashboard');
    exit;
}
// Check if admin_original_id exists
if (!isset($_SESSION['admin_original_id'])) {
    $_SESSION['error'] = 'No admin session to return to';
    header('Location: /user/dashboard');
    exit;
}

// Restore admin session
$_SESSION['user_id'] = $_SESSION['admin_original_id'];
unset($_SESSION['admin_original_id']);
session_regenerate_id(true);
$_SESSION['last_activity'] = time();

$_SESSION['success'] = 'Returned to admin panel';
header('Location: /admin/users');
exit;
