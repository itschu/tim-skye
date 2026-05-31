<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/translation-functions.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/plans');
    exit;
}

// Verify CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid security token');
    header('Location: /admin/plans');
    exit;
}

// Validate plan_id
$plan_id = isset($_POST['plan_id']) ? sanitize_input($_POST['plan_id']) : '';
if (empty($plan_id)) {
    $_SESSION['error'] = __('Plan ID is required');
    header('Location: /admin/plans');
    exit;
}

try {
    // Fetch existing plan
    $existing = db_query("SELECT * FROM investment_plans WHERE id = ?", [$plan_id]);
    if (empty($existing)) {
        $_SESSION['error'] = __('Plan not found');
        header('Location: /admin/plans');
        exit;
    }

    // Check for active investments
    $active_check = db_query("SELECT COUNT(*) as count FROM investments WHERE plan_id = ? AND status = 'active'", [$plan_id]);
    if ($active_check[0]['count'] > 0) {
        $_SESSION['error'] = __('Cannot delete plan with active investments. Archive it instead.');
        header('Location: /admin/plans');
        exit;
    }

    // Check for any historical investments
    $history_check = db_query("SELECT COUNT(*) as count FROM investments WHERE plan_id = ?", [$plan_id]);
    if ($history_check[0]['count'] > 0) {
        $_SESSION['error'] = __('This plan has historical investments. Consider archiving instead of deleting. Archive allows the plan to be preserved for records while preventing new investments.');
        header('Location: /admin/plans');
        exit;
    }

    // Delete plan
    db_delete('investment_plans', 'id = ?', [$plan_id]);

    $_SESSION['success'] = __('Investment plan deleted successfully');
    header('Location: /admin/plans');
    exit;
} catch (Exception $e) {
    error_log('Plan delete error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('Cannot delete plan due to existing references');
    header('Location: /admin/plans');
    exit;
}
