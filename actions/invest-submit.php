<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Investment creation submit handler
 * Creates a new investment for a user from a selected plan.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/wallet-functions.php';
require_once __DIR__ . '/../includes/investment-functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/referral-functions.php';
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
    header('Location: /user/invest');
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid CSRF token');
    header('Location: /user/invest');
    exit;
}

$user_id = $_SESSION['user_id'];

// KYC Enforcement Check
$kyc_required = get_setting('kyc_required', 'no');
if ($kyc_required === 'yes') {
    $kyc_timing = get_setting('kyc_timing', 'before_withdrawal');
    if ($kyc_timing === 'before_investment' || $kyc_timing === 'immediately') {
        $user = db_query("SELECT kyc_status FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user && $user['kyc_status'] !== 'approved') {
            $kyc_status = $user['kyc_status'];
            if ($kyc_status === 'not_submitted') {
                $_SESSION['error'] = __('Please complete KYC verification before making investments.');
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

$plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;

// Validate plan ID
if ($plan_id <= 0) {
    $_SESSION['error'] = __('Invalid investment plan selected');
    header('Location: /user/invest');
    exit;
}

// Validate amount
if ($amount <= 0) {
    $_SESSION['error'] = __('Investment amount must be greater than zero');
    header('Location: /user/invest');
    exit;
}

// Get user's country for plan eligibility check
$user_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0]['country'] ?? null;

// Check if plan exists and is active and eligible for user's country
$plan = db_query("SELECT * FROM investment_plans WHERE id = ? AND status = 'active' AND (country IS NULL OR country = '' OR country = ?)", [$plan_id, $user_country]);
if (empty($plan)) {
    $_SESSION['error'] = __('Selected plan is not available in your country');
    header('Location: /user/invest');
    exit;
}

$plan = $plan[0];
$min_amount = (float)$plan['min_amount'];
$max_amount = (float)$plan['max_amount'];

// Determine display currency and converted amounts for error messages
$plan_country = $plan['country'] ?? null;
$display_currency = null;
$display_min = $min_amount;
$display_max = $max_amount;

if (!empty($plan_country)) {
    $local_currency = get_user_local_currency($plan_country);
    if (!empty($local_currency)) {
        $rate = get_rate_for_currency($local_currency);
        if ($rate && $rate > 0) {
            $display_currency = $local_currency;
            $display_min = $min_amount * $rate;
            $display_max = $max_amount * $rate;
        }
    }
}

// Validate amount against plan limits
if ($amount < $min_amount) {
    $_SESSION['error'] = __('Investment amount is below plan minimum of ') . format_money($display_min, $display_currency);
    header('Location: /user/invest?plan=' . $plan_id);
    exit;
}

if ($max_amount > 0 && $amount > $max_amount) {
    $_SESSION['error'] = __('Investment amount exceeds plan maximum of ') . format_money($display_max, $display_currency);
    header('Location: /user/invest?plan=' . $plan_id);
    exit;
}

// Check available balance
if (!has_sufficient_balance($user_id, $amount)) {
    $_SESSION['error'] = __('Insufficient available balance');
    header('Location: /user/invest');
    exit;
}

try {
    // Create investment using helper function
    $investment_id = create_investment($user_id, $plan_id, $amount);

    if ($investment_id === false) {
        $_SESSION['error'] = __('Failed to create investment. Please try again.');
        header('Location: /user/invest');
        exit;
    }

    // Handle referral bonus based on trigger setting
    try {
        $configured_trigger = get_setting('referral_bonus_trigger', 'registration');
        $count_row = db_query("SELECT COUNT(*) as count FROM investments WHERE user_id = ? AND id != ?", [$user_id, $investment_id]);
        $is_first_invest = (($count_row[0]['count'] ?? 0) === 0);

        if ($configured_trigger === 'first_investment' && $is_first_invest) {
            // First investment only - one-time bonus
            @process_referral_bonus($user_id, 'first_investment', $amount);
        } elseif ($configured_trigger === 'every_investment') {
            // Every investment gets a bonus
            @process_referral_bonus($user_id, 'every_investment', $amount);
        }
    } catch (Exception $e) {
        error_log("Referral processing error on investment creation: " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    }

    // Calculate total return (profit portion only)
    $total_return = $amount * $plan['roi_percentage'] / 100 * $plan['duration_days'];

    // Send email notification (if enabled)
    $email_investment_created_user = get_setting('email_investment_created_user', 'yes');
    if ($email_investment_created_user === 'yes') {
        $user = db_query("SELECT name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
        if ($user) {
            $site_url = get_site_url();
            $site_logo_url = get_site_logo_url();

            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $user['name'],
                'amount' => number_format($amount, 2),
                'plan_name' => $plan['name'],
                'roi_percentage' => $plan['roi_percentage'],
                'duration_days' => $plan['duration_days'],
                'total_return' => number_format($total_return, 2),
                'logo_url' => $site_logo_url,
                // add next payout date from investment record
                'next_payout_date' => (isset($investment_id) ? (db_query("SELECT next_payout_date FROM investments WHERE id = ?", [$investment_id])[0]['next_payout_date'] ? date('M d, Y', strtotime(db_query("SELECT next_payout_date FROM investments WHERE id = ?", [$investment_id])[0]['next_payout_date'])) : '') : ''),
                'dashboard_url' => $site_url . '/user/dashboard',
                'currency' => get_setting('currency', 'USD'),
                'investment_id' => $investment_id,
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),

                'company_address' => get_setting('company_address', '')
            ];

            try {
                send_template_email($user['email'], 'investment-created', $email_vars, get_user_language($_SESSION['user_id']));
            } catch (Exception $e) {
                error_log("Failed to send investment created email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    $_SESSION['success'] = __('Investment created successfully! ROI: ') .
        format_percentage($plan['roi_percentage']);
    header('Location: /user/my-investments?success=1');
    exit;
} catch (Exception $e) {
    error_log('[Investment Error] ' . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
    $_SESSION['error'] = __('Failed to create investment. Please try again later.');
    header('Location: /user/invest');
    exit;
}
