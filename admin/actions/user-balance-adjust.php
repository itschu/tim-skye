<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
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
$amount_raw = $_POST['adjust_amount'] ?? '';
$type = $_POST['adjust_type'] ?? '';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => __('Invalid user id')]);
    exit;
}

if (!is_numeric($amount_raw) || floatval($amount_raw) <= 0) {
    echo json_encode(['success' => false, 'message' => __('Invalid amount')]);
    exit;
}

$amount = round(floatval($amount_raw), 2);

if (!in_array($type, ['credit', 'debit'])) {
    echo json_encode(['success' => false, 'message' => __('Invalid adjustment type')]);
    exit;
}

$user = db_query('SELECT id, balance FROM users WHERE id = ?', [$user_id]);
if (empty($user)) {
    echo json_encode(['success' => false, 'message' => __('User not found')]);
    exit;
}

try {
    if ($type === 'credit') {
        credit_wallet($user_id, $amount, 'refund', 'Admin credit adjustment');
    } else {
        // debit
        debit_wallet($user_id, $amount, 'withdrawal', 'Admin debit adjustment');
    }

    $updated = db_query('SELECT balance FROM users WHERE id = ?', [$user_id]);
    $new_balance = $updated[0]['balance'] ?? 0.00;

    echo json_encode(['success' => true, 'message' => __('Balance updated successfully'), 'new_balance' => $new_balance]);
    exit;
} catch (Exception $e) {
    error_log('Balance adjust error: ' . $e->getMessage(), 3, ROOT . '/logs/db-errors.log');
    echo json_encode(['success' => false, 'message' => __('An error occurred while adjusting balance')]);
    exit;
}
