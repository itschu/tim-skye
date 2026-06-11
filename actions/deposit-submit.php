<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Deposit submit handler
 * Accepts deposit requests with optional proof upload.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload-functions.php';
require_once __DIR__ . '/../includes/referral-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';
require_once __DIR__ . '/../includes/wallet-functions.php';

require_once ROOT . '/includes/currency-conversion.php';
try {
    require_not_maintenance();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: /user/dashboard');
    exit;
}

// Initialize translation
init_translation(get_user_language($_SESSION['user_id']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/deposit');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/deposit');
    exit;
}

$user_id = $_SESSION['user_id'];

// KYC Enforcement Check
$kyc_required = get_setting('kyc_required', 'no');
if ($kyc_required === 'yes') {
    $kyc_timing = get_setting('kyc_timing', 'before_withdrawal');
    if ($kyc_timing === 'before_deposit' || $kyc_timing === 'immediately') {
        $user = db_query("SELECT kyc_status FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user && $user['kyc_status'] !== 'approved') {
            $kyc_status = $user['kyc_status'];
            if ($kyc_status === 'not_submitted') {
                $_SESSION['error'] = __('Please complete KYC verification before making deposits.');
            } elseif ($kyc_status === 'pending') {
                $_SESSION['error'] = __('Your KYC verification is pending approval. Please wait for admin review.');
            } elseif ($kyc_status === 'rejected') {
                $_SESSION['error'] = __('Your KYC verification was rejected. Please resubmit your documents.');
            }
            header('Location: /user/profile');
            exit;
        }
    }
}

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
$payment_method = trim($_POST['payment_method'] ?? 'manual');

// Validate payment method against configured methods
$payment_methods_json = get_setting('payment_methods', '[]');
$payment_methods = json_decode($payment_methods_json, true) ?: [];
if (!isset($payment_methods[$payment_method])) {
    $_SESSION['error'] = __('Invalid payment method selected');
    header('Location: /user/deposit');
    exit;
} else {
    $payment_method = $payment_methods[$payment_method]["name"];
}

if ($amount <= 0) {
    $_SESSION['error'] = __('Invalid deposit amount');
    header('Location: /user/deposit');
    exit;
}

// Calculate deposit fee
$deposit_fee_percentage = (float)get_setting('deposit_fee_percentage', 0);
$fee_amount = $amount * ($deposit_fee_percentage / 100);
$net_amount = $amount - $fee_amount;

// T4: Get local currency server-side for deposit
$user_for_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null;
$user_country = $user_for_country['country'] ?? null;
$local_currency_code = null;
$exchange_rate_used = null;
$local_currency_amount = null;

if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code) {
        $exchange_rate_used = get_rate_for_currency_raw($local_currency_code);
        if ($exchange_rate_used) {
            // If rate is available and local_currency_amount is posted, use it
            // But derive the amount from the posted local_currency_amount to convert to base
            if (!empty($_POST['local_currency_amount'])) {
                $posted_local_amount = floatval($_POST['local_currency_amount']);
                // The amount should already be in base currency (USD)
                // But we store what user entered in local currency
                $local_currency_amount = $posted_local_amount;
            }
        } else {
            $local_currency_code = null;
            $exchange_rate_used = null;
        }
    }
} // Handle proof upload if provided
$proof_path = null;
if (isset($_FILES['proof']) && $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploaded = upload_file($_FILES['proof'], 'proof');
    if ($uploaded === false) {
        $_SESSION['error'] = __('Failed to upload proof file. Please try again.');
        header('Location: /user/deposit');
        exit;
    }
    $proof_path = $uploaded;
}

// Persist deposit request
try {
    // Persist deposit request (store gross amount, and record fee/net)
    $deposit_data = [
        'user_id' => $user_id,
        'amount' => number_format($amount, 30, '.', ''), // gross amount
        'fee_amount' => number_format($fee_amount, 30, '.', ''),
        'net_amount' => number_format($net_amount, 30, '.', ''),
        'payment_method' => $payment_method,
        'proof_path' => $proof_path,
        'status' => 'pending',
        'local_currency_amount' => $local_currency_amount ? number_format($local_currency_amount, 30, '.', '') : null,
        'local_currency_code' => $local_currency_code,
        'exchange_rate_used' => $exchange_rate_used ? number_format($exchange_rate_used, 30, '.', '') : null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $deposit_id = db_insert('deposits', $deposit_data);

    // Create a pending transaction linked to this deposit
    try {
        // Record transaction amount as the net amount (after fee) to match credited funds
        $tx_details = "Deposit via {$payment_method}";
        if ($fee_amount > 0) {
            $tx_details .= ". Fee: " . number_format($fee_amount, 2);
        }
        create_transaction(
            $user_id,
            'deposit',
            $net_amount,
            'pending',
            $tx_details,
            $deposit_id
        );
    } catch (Exception $e) {
        error_log("Failed to create linked transaction for deposit {$deposit_id}: " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    }

    // Referral bonus for first_deposit is processed when deposit is approved by admin

    // Send email notifications
    $user = db_query("SELECT name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
    if ($user) {
        $site_url = get_site_url();
        $site_logo_url = get_site_logo_url();

        // Notify user (if enabled)
        $email_deposit_submitted_user = get_setting('email_deposit_submitted_user', 'yes');
        if ($email_deposit_submitted_user === 'yes') {
            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $user['name'],
                'amount' => number_format($amount, 2),
                'payment_method' => $payment_method,
                'method' => $payment_method,
                'reference' => $deposit_id,
                'fees' => format_money($fee_amount),
                'currency' => get_setting('currency', 'USD'),
                'transaction_id' => $deposit_id,
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),

                'company_address' => get_setting('company_address', '')
            ];

            try {
                send_template_email($user['email'], 'deposit-submitted', $email_vars, get_user_language($_SESSION['user_id']));
            } catch (Exception $e) {
                error_log("Failed to send deposit submitted email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }

        // Notify admin (if enabled)
        $email_deposit_submitted_admin = get_setting('email_deposit_submitted_admin', 'yes');
        if ($email_deposit_submitted_admin === 'yes') {
            $admin_email = get_setting('contact_email', 'admin@example.com');
            $admin_subject = sprintf(__('New Deposit Submitted - %s'), get_setting('site_name', 'Investment Platform'));
            $admin_body = sprintf(
                __("A new deposit has been submitted:

User: %s (%s)
Amount: %s %s
Payment Method: %s
Deposit ID: %s

Please review and approve/reject this deposit in the admin panel."),
                $user['name'],
                $user['email'],
                number_format($amount, 2),
                get_setting('currency', 'USD'),
                $payment_method,
                $deposit_id
            );

            try {
                send_email($admin_email, $admin_subject, nl2br($admin_body), true);
            } catch (Exception $e) {
                error_log("Failed to send admin deposit notification: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    $_SESSION['success'] = __('Deposit request submitted successfully.');
    header('Location: /user/deposit?success=1');
    exit;
} catch (Exception $e) {
    error_log('[Deposit Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
    $_SESSION['error'] = __('Failed to submit deposit request. Please try again later.') . ' (' . $e->getMessage() . ')';
    header('Location: /user/deposit');
    exit;
}
