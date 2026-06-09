<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/currency-conversion.php';
try {
    require_not_maintenance();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: /admin/pending-deposits');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = __('Invalid request method');
    header('Location: /admin/pending-deposits');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = __('Invalid request');
    header('Location: /admin/pending-deposits');
    exit;
}

$deposit_id = isset($_POST['deposit_id']) ? intval($_POST['deposit_id']) : 0;
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

if ($deposit_id <= 0) {
    $_SESSION['error'] = __('Invalid deposit ID');
    header('Location: /admin/pending-deposits');
    exit;
}

if (empty($rejection_reason)) {
    $_SESSION['error'] = __('Rejection reason is required');
    header('Location: /admin/pending-deposits');
    exit;
}

// Fetch deposit
$deposits = db_query("SELECT * FROM deposits WHERE id = ? AND status = 'pending'", [$deposit_id]);
if (empty($deposits)) {
    $_SESSION['error'] = __('Deposit not found or already processed');
    header('Location: /admin/pending-deposits');
    exit;
}
$deposit = $deposits[0];

// Fetch user
$users = db_query("SELECT * FROM users WHERE id = ?", [$deposit['user_id']]);
if (empty($users)) {
    $_SESSION['error'] = __('User not found');
    header('Location: /admin/pending-deposits');
    exit;
}
$user = $users[0];

try {
    // Update deposit status
    db_update('deposits', [
        'status' => 'rejected',
        'rejection_reason' => $rejection_reason,
        'admin_id' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$deposit_id]);

    // Update or insert linked transaction record for rejection
    $existing_tx = db_query("SELECT id FROM transactions WHERE source_id = ? AND type = 'deposit' LIMIT 1", [$deposit_id]);
    if (!empty($existing_tx)) {
        $existing_tx_id = $existing_tx[0]['id'];
        db_update('transactions', [
            'status' => 'rejected',
            'amount' => number_format((float)$deposit['net_amount'], 2, '.', ''),
            'details' => "Deposit #{$deposit_id} rejected: {$rejection_reason}",
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing_tx_id]);
    } else {
        // Backward compat: insert a rejected transaction linked to this deposit
        $tx_data = [
            'user_id' => $deposit['user_id'],
            'type' => 'deposit',
            'amount' => number_format((float)$deposit['net_amount'], 2, '.', ''),
            'status' => 'rejected',
            'details' => "Deposit #{$deposit_id} rejected: {$rejection_reason}",
            'source_id' => $deposit_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        db_insert('transactions', $tx_data);
    }

    // Send rejection email (if enabled)
    $email_deposit_rejected_user = get_setting('email_deposit_rejected_user', 'yes');
    if ($email_deposit_rejected_user === 'yes') {
        $site_url = get_site_url();
        $site_logo = get_setting('site_logo', '');
        $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../../' . $site_logo))
            ? $site_url . '/' . ltrim($site_logo, '/')
            : $site_url . '/assets/images/logo.png';
        try {
            send_template_email($user['email'], 'deposit-rejected', [
                'user_name' => e($user['name']),
                'amount' => format_money($deposit['amount']),
                'reason' => e($rejection_reason),
                'support_url' => $site_url . '/support.php',
                'site_name' => e(get_setting('site_name', 'Investment Platform')),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'reference' => $deposit_id,
                'method' => e($deposit['payment_method']),
                'fees' => format_money($deposit['fee_amount']),
                'site_url' => $site_url,
                'current_year' => date('Y'),
                'support_email' => e(get_setting('contact_email', 'support@example.com')),

                'company_address' => get_setting('company_address', '')
            ], get_user_language($deposit['user_id']));
        } catch (Exception $e) {
            error_log("Failed to send deposit rejection email: " . $e->getMessage(), 3, __DIR__ . '/../../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('Deposit rejected');
} catch (Exception $e) {
    error_log("Deposit rejection error: " . $e->getMessage(), 3, __DIR__ . '/../../logs/db-errors.log');
    $_SESSION['error'] = __('Error rejecting deposit: ') . $e->getMessage();
}

header('Location: /admin/pending-deposits');
exit;
