<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/wallet-functions.php';
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
if ($withdrawal_id <= 0) {
    $_SESSION['error'] = __('Invalid withdrawal ID');
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

$db = db_connect();
try {
    $db->beginTransaction();

    // Try to find an existing linked transaction for this withdrawal
    $existing_tx = db_query("SELECT id FROM transactions WHERE source_id = ? AND type = 'withdrawal' LIMIT 1", [$withdrawal_id]);
    if (!empty($existing_tx)) {
        $existing_tx_id = $existing_tx[0]['id'];
        // Debit balance directly within transaction
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([number_format((float)$withdrawal['amount'], 15, '.', ''), $withdrawal['user_id']]);

        // Update existing transaction to completed
        db_update('transactions', [
            'status' => 'completed',
            'details' => 'Withdrawal #' . $withdrawal_id . ' approved',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing_tx_id]);

        $transaction_id = $existing_tx_id;
    } else {
        // Backward compat: inline debit and create transaction within the current DB transaction
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([number_format((float)$withdrawal['amount'], 15, '.', ''), $withdrawal['user_id']]);

        $tx_data = [
            'user_id' => $withdrawal['user_id'],
            'type' => 'withdrawal',
            'amount' => number_format((float)$withdrawal['amount'], 15, '.', ''),
            'status' => 'completed',
            'details' => 'Withdrawal #' . $withdrawal_id . ' approved',
            'source_id' => $withdrawal_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $transaction_id = db_insert('transactions', $tx_data);
    }

    // Update withdrawal status
    db_update('withdrawals', [
        'status' => 'approved',
        'admin_id' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$withdrawal_id]);

    $db->commit();

    // Get updated balance
    $new_balance = get_user_balance($withdrawal['user_id']);

    // Send approval email (if enabled)
    $email_withdrawal_approved_user = get_setting('email_withdrawal_approved_user', 'yes');
    if ($email_withdrawal_approved_user === 'yes') {
        $site_url = get_site_url();
        $site_logo = get_setting('site_logo', '');
        $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../../' . $site_logo))
            ? $site_url . '/' . ltrim($site_logo, '/')
            : $site_url . '/assets/images/logo.png';
        try {
            // Resolve human-readable method name if configured
            $withdrawal_methods_json = get_setting('withdrawal_methods', '[]');
            $withdrawal_methods = json_decode($withdrawal_methods_json, true) ?: [];
            $method_name_display = isset($withdrawal_methods[$withdrawal['payment_method']]['name']) ? $withdrawal_methods[$withdrawal['payment_method']]['name'] : $withdrawal['payment_method'];
            send_template_email($user['email'], 'withdrawal-approved', [
                'user_name' => e($user['name']),
                'amount' => format_money($withdrawal['amount']),
                'fee' => format_money($withdrawal['fee_amount']),
                'fees' => format_money($withdrawal['fee_amount']),
                'method' => $method_name_display,
                'reference' => $withdrawal_id,
                'net_amount' => format_money($withdrawal['net_amount']),
                'payment_method' => e($withdrawal['payment_method']),
                'dashboard_url' => $site_url . '/user/dashboard',
                'site_name' => e(get_setting('site_name', 'Investment Platform')),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'current_year' => date('Y'),
                'support_email' => e(get_setting('contact_email', 'support@example.com')),

                'company_address' => get_setting('company_address', '')
            ], get_user_language($withdrawal['user_id']));
        } catch (Exception $e) {
            error_log("Failed to send withdrawal approval email: " . $e->getMessage(), 3, __DIR__ . '/../../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('Withdrawal approved and processed');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Withdrawal approval error: " . $e->getMessage(), 3, __DIR__ . '/../../logs/db-errors.log');
    $_SESSION['error'] = __('Error approving withdrawal: ') . $e->getMessage();
}

header('Location: /admin/pending-withdrawals');
exit;
