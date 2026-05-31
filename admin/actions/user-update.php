<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
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
    $current_user_query = "SELECT * FROM users WHERE id = ?";
    $current_user_result = db_query($current_user_query, [$user_id]);

    if (empty($current_user_result)) {
        $_SESSION['error'] = __('User not found');
        header('Location: /admin/users');
        exit;
    }

    $current_user = $current_user_result[0];

    // Sanitize inputs
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $balance_raw = $_POST['balance'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    $status = sanitize_input($_POST['status'] ?? '');

    // Validate country
    if (!empty($country)) {
        $countries_list = get_countries();
        if (!isset($countries_list[$country])) {
            $_SESSION['error'] = __('Invalid country selected');
            header('Location: /admin/users');
            exit;
        }
    }

    // Validate required fields
    if (empty($name) || empty($email)) {
        $_SESSION['error'] = __('Name and email are required');
        header('Location: /admin/users');
        exit;
    }

    // Validate email format
    if (!validate_email($email)) {
        $_SESSION['error'] = __('Invalid email format');
        header('Location: /admin/users');
        exit;
    }

    // Validate balance - must be numeric before casting
    if (!is_numeric($balance_raw)) {
        $_SESSION['error'] = __('Balance must be a valid number');
        header('Location: /admin/users');
        exit;
    }

    $new_balance = floatval($balance_raw);

    if ($new_balance < 0) {
        $_SESSION['error'] = __('Balance must be a non-negative number');
        header('Location: /admin/users');
        exit;
    }

    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        $_SESSION['error'] = __('Invalid role');
        header('Location: /admin/users');
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'banned'])) {
        $_SESSION['error'] = __('Invalid status');
        header('Location: /admin/users');
        exit;
    }

    // Check email uniqueness
    if ($email !== $current_user['email']) {
        $email_check = db_query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
        if (!empty($email_check)) {
            $_SESSION['error'] = __('Email already in use');
            header('Location: /admin/users');
            exit;
        }
    }

    // Handle balance adjustment
    $balance_diff = $new_balance - $current_user['balance'];
    if ($balance_diff > 0) {
        credit_wallet($user_id, $balance_diff, 'refund', 'Admin balance adjustment: +' . format_money($balance_diff));
    } elseif ($balance_diff < 0) {
        debit_wallet($user_id, abs($balance_diff), 'withdrawal', 'Admin balance adjustment: -' . format_money(abs($balance_diff)));
    }

    // Update user record
    db_update(
        'users',
        [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'country' => $country,
            'balance' => $new_balance,
            'role' => $role,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$user_id]
    );

    // If called via AJAX (client sets ajax=1), return JSON
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => __('User updated successfully')]);
        exit;
    }

    $_SESSION['success'] = __('User updated successfully');
    header('Location: /admin/users');
    exit;
} catch (Exception $e) {
    error_log('User update error: ' . $e->getMessage(), 3, '../logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while updating the user');
    header('Location: /admin/users');
    exit;
}
