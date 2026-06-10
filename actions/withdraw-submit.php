<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Withdrawal submit handler
 * Processes withdrawal requests with fee calculation and balance validation.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wallet-functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

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
    header('Location: /user/withdraw');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/withdraw');
    exit;
}

$user_id = $_SESSION['user_id'];

// KYC enforcement before withdrawal when configured
$kyc_required = get_setting('kyc_required', 'no');
$kyc_timing = get_setting('kyc_timing', 'immediately');
if ($kyc_required === 'yes' && in_array($kyc_timing, ['before_withdrawal', 'immediately'])) {
    $u = db_query("SELECT kyc_status FROM users WHERE id = ?", [$user_id])[0] ?? null;
    if ($u && $u['kyc_status'] !== 'approved') {
        $ks = $u['kyc_status'];
        if ($ks === 'not_submitted') {
            $_SESSION['error'] = __('Please complete KYC verification before making withdrawals.');
        } elseif ($ks === 'pending') {
            $_SESSION['error'] = __('Your KYC verification is pending approval. Please wait for admin review.');
        } elseif ($ks === 'rejected') {
            $_SESSION['error'] = __('Your KYC verification was rejected. Please resubmit your documents.');
        }
        header('Location: /user/profile');
        exit;
    }
}

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
$method = trim($_POST['method'] ?? '');

// T4: Get local currency info for withdrawal
$user_for_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0] ?? null;
$user_country = $user_for_country['country'] ?? null;
$local_currency_code = null;
$exchange_rate_used = null;
$local_currency_amount = null;

if ($user_country) {
    $local_currency_code = get_user_local_currency($user_country);
    if ($local_currency_code) {
        $exchange_rate_used = get_rate_for_currency($local_currency_code);
        if ($exchange_rate_used) {
            if (!empty($_POST['local_currency_amount'])) {
                $local_currency_amount = floatval($_POST['local_currency_amount']);
            }
        } else {
            $local_currency_code = null;
            $exchange_rate_used = null;
        }
    }
}

// Get withdrawal methods to validate
$withdrawal_methods_json = get_setting('withdrawal_methods', '');
$withdrawal_methods = [];
if (!empty($withdrawal_methods_json)) {
    $withdrawal_methods = json_decode($withdrawal_methods_json, true) ?: [];
}

// Default methods if none configured
if (empty($withdrawal_methods)) {
    $withdrawal_methods = [
        'bank' => ['type' => 'fiat', 'enabled' => true],
        'crypto' => ['type' => 'crypto', 'enabled' => true]
    ];
}

// Validate amount
$minimum_withdrawal = (float)get_setting('minimum_withdrawal', 1);
if ($amount <= 0) {
    $_SESSION['error'] = __('Withdrawal amount must be greater than zero');
    header('Location: /user/withdraw');
    exit;
}
if ($amount < $minimum_withdrawal) {
    $_SESSION['error'] = __('Minimum withdrawal amount is %s', number_format($minimum_withdrawal, 2));
    header('Location: /user/withdraw');
    exit;
}

// Validate method exists and is enabled
if (!isset($withdrawal_methods[$method]) || !($withdrawal_methods[$method]['enabled'] ?? true)) {
    $_SESSION['error'] = __('Invalid withdrawal method');
    header('Location: /user/withdraw');
    exit;
}

$method_type = $withdrawal_methods[$method]['type'] ?? 'other';

// Build account details based on method type
$account_details = [];

if ($method_type === 'crypto') {
    $crypto_address = trim($_POST['crypto_address'] ?? '');
    $network = trim($_POST['network'] ?? '');

    if (empty($crypto_address) || strlen($crypto_address) < 6) {
        $_SESSION['error'] = __('Please enter a valid wallet address');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($network)) {
        $_SESSION['error'] = __('Please select a network');
        header('Location: /user/withdraw');
        exit;
    }

    $account_details = [
        'type' => 'crypto',
        'address' => $crypto_address,
        'network' => $network
    ];
} elseif ($method_type === 'fiat') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if (empty($bank_name)) {
        $_SESSION['error'] = __('Please enter your bank name');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($account_name)) {
        $_SESSION['error'] = __('Please enter the account name');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($account_number)) {
        $_SESSION['error'] = __('Please enter your account number');
        header('Location: /user/withdraw');
        exit;
    }

    $account_details = [
        'type' => 'fiat',
        'bank_name' => $bank_name,
        'account_name' => $account_name,
        'account_number' => $account_number
    ];
} elseif ($method_type === 'momo') {
    $mobile_provider = trim($_POST['mobile_provider'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $mobile_name = trim($_POST['mobile_name'] ?? '');
    $mobile_reference = trim($_POST['mobile_reference'] ?? '');

    if (empty($mobile_provider)) {
        $_SESSION['error'] = __('Please select a mobile money provider');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($mobile_number) || strlen($mobile_number) < 5) {
        $_SESSION['error'] = __('Please enter a valid mobile number');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($mobile_name)) {
        $_SESSION['error'] = __('Please enter the full name for this mobile money account');
        header('Location: /user/withdraw');
        exit;
    }

    $account_details = [
        'type' => 'momo',
        'provider' => $mobile_provider,
        'phone_number' => $mobile_number,
        'full_name' => $mobile_name
    ];
    if (!empty($mobile_reference)) {
        $account_details['reference'] = $mobile_reference;
    }
} elseif ($method_type === 'ewallet') {
    $ewallet_provider = trim($_POST['ewallet_provider'] ?? '');
    $ewallet_id = trim($_POST['ewallet_id'] ?? '');

    if (empty($ewallet_provider)) {
        $_SESSION['error'] = __('Please enter the e-wallet provider');
        header('Location: /user/withdraw');
        exit;
    }
    if (empty($ewallet_id)) {
        $_SESSION['error'] = __('Please enter your wallet ID or email');
        header('Location: /user/withdraw');
        exit;
    }

    $account_details = [
        'type' => 'ewallet',
        'provider' => $ewallet_provider,
        'wallet_id' => $ewallet_id
    ];
} else {
    // Generic/other method - require at least some details
    $_SESSION['error'] = __('Invalid withdrawal method type');
    header('Location: /user/withdraw');
    exit;
}

// Check available balance
$available = get_available_balance($user_id);
if ($amount > $available) {
    $_SESSION['error'] = __('Insufficient available balance');
    header('Location: /user/withdraw');
    exit;
}

// Calculate fee
$fee_percent = (float)get_setting('withdrawal_fee_percentage', 2);
$fee_amount = ($amount * $fee_percent) / 100;
$net_amount = $amount - $fee_amount;

try {
    $db = db_connect();
    $db->beginTransaction();

    // Create withdrawal record
    $withdrawal_id = db_insert('withdrawals', [
        'user_id' => $user_id,
        'amount' => number_format($amount, 15, '.', ''),
        'fee_amount' => number_format($fee_amount, 15, '.', ''),
        'net_amount' => number_format($net_amount, 15, '.', ''),
        'payment_method' => $method,
        'account_details' => json_encode($account_details),
        'status' => 'pending',
        'local_currency_amount' => $local_currency_amount ? number_format($local_currency_amount, 15, '.', '') : null,
        'local_currency_code' => $local_currency_code,
        'exchange_rate_used' => $exchange_rate_used ? number_format($exchange_rate_used, 8, '.', '') : null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // Create transaction record
    $method_name = isset($withdrawal_methods[$method]['name']) ? $withdrawal_methods[$method]['name'] : $method;
    create_transaction(
        $user_id,
        'withdrawal',
        $amount,
        'pending',
        "Withdrawal request via {$method_name}. Fee: " . number_format($fee_amount, 2),
        $withdrawal_id
    );

    $db->commit();

    // Send email notifications
    $user = db_query("SELECT name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
    if ($user) {
        $site_url = get_site_url();
        $site_logo_url = get_site_logo_url();

        // Notify user (if enabled)
        $email_withdrawal_submitted_user = get_setting('email_withdrawal_submitted_user', 'yes');
        if ($email_withdrawal_submitted_user === 'yes') {
            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $user['name'],
                'amount' => number_format($amount, 2),
                'fee_amount' => number_format($fee_amount, 2),
                'net_amount' => number_format($net_amount, 2),
                'payment_method' => $method_name,
                'method' => $method_name,
                'reference' => $withdrawal_id,
                'fees' => format_money($fee_amount),
                'currency' => get_setting('currency', 'USD'),
                'withdrawal_id' => $withdrawal_id,
                'dashboard_url' => $site_url . '/user/dashboard',
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),

                'company_address' => get_setting('company_address', '')
            ];

            try {
                send_template_email($user['email'], 'withdrawal-submitted', $email_vars, get_user_language($_SESSION['user_id']));
            } catch (Exception $e) {
                error_log("Failed to send withdrawal submitted email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }

        // Notify admin (if enabled)
        $email_withdrawal_submitted_admin = get_setting('email_withdrawal_submitted_admin', 'yes');
        if ($email_withdrawal_submitted_admin === 'yes') {
            $admin_email = get_setting('contact_email', 'admin@example.com');
            $admin_subject = sprintf(__('New Withdrawal Request - %s'), get_setting('site_name', 'Investment Platform'));
            $admin_body = sprintf(
                __("A new withdrawal has been submitted:

User: %s (%s)
Amount: %s %s
Fee: %s %s
Net Amount: %s %s
Payment Method: %s
Withdrawal ID: %s

Please review and process this withdrawal in the admin panel."),
                $user['name'],
                $user['email'],
                number_format($amount, 2),
                get_setting('currency', 'USD'),
                number_format($fee_amount, 2),
                get_setting('currency', 'USD'),
                number_format($net_amount, 2),
                get_setting('currency', 'USD'),
                $method_name,
                $withdrawal_id
            );

            try {
                send_email($admin_email, $admin_subject, nl2br($admin_body), true);
            } catch (Exception $e) {
                error_log("Failed to send admin withdrawal notification: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    $_SESSION['success'] = __('Withdrawal request submitted successfully. Net amount after fee: ') .
        format_money($net_amount);
    header('Location: /user/withdraw?success=1');
    exit;
} catch (Exception $e) {
    $db->rollBack();
    error_log('[Withdrawal Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to process withdrawal request. Please try again later.');
    header('Location: /user/withdraw');
    exit;
}
