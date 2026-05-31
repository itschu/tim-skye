<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/pending-withdrawals');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid request');
    header('Location: /admin/pending-withdrawals');
    exit;
}

$withdrawal_id = isset($_POST['withdrawal_id']) ? intval($_POST['withdrawal_id']) : 0;
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

if ($withdrawal_id <= 0) {
    $_SESSION['error'] = __('Invalid withdrawal ID');
    header('Location: /admin/pending-withdrawals');
    exit;
}

if (empty($rejection_reason)) {
    $_SESSION['error'] = __('Rejection reason is required');
    header('Location: /admin/pending-withdrawals');
    exit;
}

// Fetch withdrawal
$withdrawals = db_query("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'", [$withdrawal_id]);
if (empty($withdrawals)) {
    $_SESSION['error'] = __('Withdrawal not found or already processed');
    header('Location: /admin/pending-withdrawals');
    exit;
}
$withdrawal = $withdrawals[0];

// Fetch user
$users = db_query("SELECT * FROM users WHERE id = ?", [$withdrawal['user_id']]);
if (empty($users)) {
    $_SESSION['error'] = __('User not found');
    header('Location: /admin/pending-withdrawals');
    exit;
}
$user = $users[0];

try {
    // Update withdrawal status
    // Funds are auto-released since get_locked_balance() only counts pending withdrawals
    db_update('withdrawals', [
        'status' => 'rejected',
        'rejection_reason' => $rejection_reason,
        'admin_id' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$withdrawal_id]);

    // Update or insert linked transaction record for rejection
    $existing_tx = db_query("SELECT id FROM transactions WHERE source_id = ? AND type = 'withdrawal' LIMIT 1", [$withdrawal_id]);
    if (!empty($existing_tx)) {
        $existing_tx_id = $existing_tx[0]['id'];
        db_update('transactions', [
            'status' => 'rejected',
            'details' => "Withdrawal #{$withdrawal_id} rejected: {$rejection_reason}",
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing_tx_id]);
    } else {
        // Backward compat: insert a rejected transaction linked to this withdrawal
        $tx_data = [
            'user_id' => $withdrawal['user_id'],
            'type' => 'withdrawal',
            'amount' => number_format((float)$withdrawal['amount'], 2, '.', ''),
            'status' => 'rejected',
            'details' => "Withdrawal #{$withdrawal_id} rejected: {$rejection_reason}",
            'source_id' => $withdrawal_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        db_insert('transactions', $tx_data);
    }

    // Send rejection email (if enabled)
    $email_withdrawal_rejected_user = get_setting('email_withdrawal_rejected_user', 'yes');
    if ($email_withdrawal_rejected_user === 'yes') {
        $site_url = get_site_url();
        $site_logo = get_setting('site_logo', '');
        $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../../' . $site_logo))
            ? $site_url . '/' . ltrim($site_logo, '/')
            : $site_url . '/assets/images/logo.png';
        $withdrawal_methods_json = get_setting('withdrawal_methods', '[]');
        $withdrawal_methods = json_decode($withdrawal_methods_json, true) ?: [];
        $method_name_display = isset($withdrawal_methods[$withdrawal['payment_method']]['name']) ? $withdrawal_methods[$withdrawal['payment_method']]['name'] : $withdrawal['payment_method'];
        try {
            send_template_email($user['email'], 'withdrawal-rejected', [
                'user_name' => e($user['name']),
                'amount' => format_money($withdrawal['amount']),
                'reason' => e($rejection_reason),
                'guidance' => e($rejection_reason),
                'support_url' => $site_url . '/support.php',
                'site_name' => e(get_setting('site_name', 'Investment Platform')),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'reference' => $withdrawal_id,
                'method' => $method_name_display,
                'fees' => format_money($withdrawal['fee_amount']),
                'site_url' => $site_url,
                'current_year' => date('Y'),
                'support_email' => e(get_setting('contact_email', 'support@example.com')),

                'company_address' => get_setting('company_address', '')
            ], get_user_language($withdrawal['user_id']));
        } catch (Exception $e) {
            error_log("Failed to send withdrawal rejection email: " . $e->getMessage(), 3, __DIR__ . '/../../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('Withdrawal rejected');
} catch (Exception $e) {
    error_log("Withdrawal rejection error: " . $e->getMessage(), 3, __DIR__ . '/../../logs/db-errors.log');
    $_SESSION['error'] = __('Error rejecting withdrawal: ') . $e->getMessage();
}

header('Location: /admin/pending-withdrawals');
exit;
