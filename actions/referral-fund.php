<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/referral-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/referrals');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/referrals');
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;

if ($amount <= 0) {
    $_SESSION['error'] = __('Amount must be greater than zero');
    header('Location: /user/referrals');
    exit;
}

// Validate against admin settings
$validation = validate_referral_fund_withdraw_amount($amount);
if ($validation !== true) {
    $_SESSION['error'] = $validation;
    header('Location: /user/referrals');
    exit;
}

if (!has_sufficient_referral_balance($user_id, $amount)) {
    $_SESSION['error'] = __('Insufficient referral balance');
    header('Location: /user/referrals');
    exit;
}

try {
    $db = db_connect();
    $db->beginTransaction();

    // Debit referral balance
    $stmt = $db->prepare("UPDATE users SET referral_balance = referral_balance - ? WHERE id = ?");
    $stmt->execute([number_format((float)$amount, 30, '.', ''), $user_id]);

    // Credit main balance
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([number_format((float)$amount, 30, '.', ''), $user_id]);

    // Log fund transaction
    create_transaction($user_id, 'referral_fund', $amount, 'completed', __('Funded wallet from referral balance'));

    $db->commit();

    $_SESSION['success'] = sprintf(__('Successfully funded wallet with %s from referral balance'), format_money($amount));
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Referral Fund Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to fund wallet. Please try again.');
}

header('Location: /user/referrals');
exit;
