<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/wallet-functions.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/referral-functions.php';
require_once ROOT . '/includes/translation-functions.php';

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
if ($deposit_id <= 0) {
    $_SESSION['error'] = __('Invalid deposit ID');
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

$db = db_connect();
$transaction_id = false;
try {
    $db->beginTransaction();

    // Credit wallet within transaction (use net credited amount)
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([number_format((float)$deposit['net_amount'], 2, '.', ''), $deposit['user_id']]);

    // Try to update an existing linked transaction (source_id) if present
    $existing_tx = db_query("SELECT id FROM transactions WHERE source_id = ? AND type = 'deposit' LIMIT 1", [$deposit_id]);
    if (!empty($existing_tx)) {
        $existing_tx_id = $existing_tx[0]['id'];
        db_update('transactions', [
            'status' => 'completed',
            'amount' => number_format((float)$deposit['net_amount'], 2, '.', ''),
            'details' => __('Deposit #') . $deposit_id . ' ' . __('approved'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing_tx_id]);
        $transaction_id = $existing_tx_id;
    } else {
        // Backward compat: insert a new transaction if no linked one exists
        $tx_data = [
            'user_id' => $deposit['user_id'],
            'type' => 'deposit',
            'amount' => number_format((float)$deposit['net_amount'], 2, '.', ''),
            'status' => 'completed',
            'details' => __('Deposit #') . $deposit_id . ' ' . __('approved'),
            'source_id' => $deposit_id,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $transaction_id = db_insert('transactions', $tx_data);
    }

    // Update deposit status after successful credit
    db_update('deposits', [
        'status' => 'approved',
        'admin_id' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$deposit_id]);

    $db->commit();

    // Handle referral bonus AFTER main transaction commits (to avoid nested transaction issues)
    // Check deposit count for referral bonus logic
    $first_deps = db_query(
        "SELECT COUNT(*) as count FROM deposits WHERE user_id = ? AND status = 'approved' AND id != ?",
        [$deposit['user_id'], $deposit_id]
    );
    $is_first_deposit = ($first_deps[0]['count'] ?? 0) === 0;

    // Get configured referral trigger
    $configured_trigger = get_setting('referral_bonus_trigger', 'registration');

    // Process referral bonus if applicable
    $should_process_referral = false;
    $trigger_event = '';
    if ($configured_trigger === 'first_deposit' && $is_first_deposit) {
        $should_process_referral = true;
        $trigger_event = 'first_deposit';
    } elseif ($configured_trigger === 'every_deposit') {
        $should_process_referral = true;
        $trigger_event = 'every_deposit';
    }

    if ($should_process_referral) {
        try {
            // Use net credited amount for referral bonus base (exclude fees)
            $referral_base_amount = isset($deposit['net_amount']) ? (float)$deposit['net_amount'] : ((float)$deposit['amount'] - (float)$deposit['fee_amount']);
            @process_referral_bonus($deposit['user_id'], $trigger_event, $referral_base_amount);
        } catch (Exception $e) {
            error_log("Referral processing error on deposit approval: " . $e->getMessage(), 3, __DIR__ . '/../../logs/db-errors.log');
        }
    }

    // Get updated balance
    $new_balance = get_user_balance($deposit['user_id']);

    // Send approval email (if enabled)
    $email_deposit_approved_user = get_setting('email_deposit_approved_user', 'yes');
    if ($email_deposit_approved_user === 'yes') {
        $site_url = get_site_url();
        $dashboard_url = $site_url . '/user/dashboard';
        $site_logo = get_setting('site_logo', '');
        $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../../' . $site_logo))
            ? $site_url . '/' . ltrim($site_logo, '/')
            : $site_url . '/assets/images/logo.png';
        try {
            send_template_email($user['email'], 'deposit-approved', [
                'user_name' => e($user['name']),
                'gross_amount' => format_money($deposit['amount']),
                'fee_amount' => format_money($deposit['fee_amount']),
                'net_amount' => format_money($deposit['net_amount']),
                // Provide legacy `amount` variable expected by template (display credited amount)
                'amount' => format_money($deposit['net_amount']),
                'new_balance' => format_money($new_balance),
                'transaction_id' => $transaction_id ?: 'N/A',
                'dashboard_url' => $dashboard_url,
                'site_name' => e(get_setting('site_name', 'Investment Platform')),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'method' => e($deposit['payment_method']),
                'reference' => $deposit_id,
                'fees' => format_money($deposit['fee_amount']),
                'site_url' => $site_url,
                'current_year' => date('Y'),
                'support_email' => e(get_setting('contact_email', 'support@example.com')),

                'company_address' => get_setting('company_address', '')
            ], get_user_language($deposit['user_id']));
        } catch (Exception $e) {
            error_log("Failed to send deposit approval email: " . $e->getMessage(), 3, __DIR__ . '/../../logs/email-errors.log');
        }
    }

    $_SESSION['success'] = __('Deposit approved and wallet credited');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Deposit approval error: " . $e->getMessage(), 3, __DIR__ . '/../../logs/db-errors.log');
    $_SESSION['error'] = __('Error approving deposit: ') . $e->getMessage();
}

header('Location: /admin/pending-deposits');
exit;
