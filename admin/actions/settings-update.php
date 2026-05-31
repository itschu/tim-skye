<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

/**
 * Platform Settings Update Action Handler
 * Processes form submissions from /admin/settings
 */

require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/upload-functions.php';
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/env.php';

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /admin/settings');
    exit;
}

$section = isset($_POST['section']) ? sanitize_input($_POST['section']) : '';

try {
    switch ($section) {
        case 'site':
            handleSiteSettings();
            break;
        case 'payment':
            handlePaymentMethods();
            break;
        case 'deposit':
            handleDepositSettings();
            break;
        case 'referral':
            handleReferralSettings();
            break;
        case 'withdrawal':
            handleWithdrawalSettings();
            break;
        case 'withdrawal_methods':
            handleWithdrawalMethods();
            break;
        case 'kyc':
            handleKYCSettings();
            break;
        case 'cancellation':
            handleCancellationSettings();
            break;
        case 'registration':
            handleRegistrationSettings();
            break;
        case 'email_verification':
            handleEmailVerificationSettings();
            break;
        case 'email_notifications_account':
            handleEmailNotificationsAccount();
            break;
        case 'email_notifications_deposit':
            handleEmailNotificationsDeposit();
            break;
        case 'email_notifications_withdrawal':
            handleEmailNotificationsWithdrawal();
            break;
        case 'email_notifications_investment':
            handleEmailNotificationsInvestment();
            break;
        case 'email_notifications_kyc':
            handleEmailNotificationsKYC();
            break;
        case 'email_notifications_referral':
            handleEmailNotificationsReferral();
            break;
        case 'seo':
            handleSEOSettings();
            break;
        case 'countries':
            handleCountriesSettings();
            break;
        default:
            $_SESSION['error'] = __('Invalid section specified.');
            break;
    }
} catch (Exception $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString(), 3, ROOT . '/logs/db-errors.log');
    $_SESSION['error'] = __('An error occurred while saving settings. Please try again.');
}

// Map section to tab hash for redirect
$tab_mapping = [
    'site' => 'site',
    'payment' => 'payment',
    'deposit' => 'payment',
    'referral' => 'referral',
    'withdrawal' => 'withdrawal',
    'withdrawal_methods' => 'withdrawal',
    'kyc' => 'kyc',
    'cancellation' => 'cancellation',
    'registration' => 'registration',
    'email_verification' => 'registration',
    'email_notifications_account' => 'notifications',
    'email_notifications_deposit' => 'notifications',
    'email_notifications_withdrawal' => 'notifications',
    'email_notifications_investment' => 'notifications',
    'email_notifications_kyc' => 'notifications',
    'email_notifications_referral' => 'notifications',
    'seo' => 'seo',
    'countries' => 'countries'
];

$tab = isset($tab_mapping[$section]) ? $tab_mapping[$section] : '';
$redirect_url = '/admin/settings' . ($tab ? '#' . $tab : '');

header('Location: ' . $redirect_url);
exit;

/**
 * Handle Site Settings Update
 */
function handleSiteSettings()
{
    // Validate required fields
    if (empty($_POST['site_name']) || empty($_POST['contact_email'])) {
        $_SESSION['error'] = __('Site name and contact email are required.');
        return;
    }

    $site_name = sanitize_input($_POST['site_name']);
    $contact_email = sanitize_input($_POST['contact_email']);
    $secondary_language = sanitize_input($_POST['secondary_language']);
    $translation_mode = sanitize_input($_POST['translation_mode'] ?? 'local');
    $currency = sanitize_input($_POST['currency'] ?? 'USD');

    // Contact phone and address
    $contact_phone = isset($_POST['contact_phone']) ? sanitize_input($_POST['contact_phone']) : '';
    $contact_address = isset($_POST['contact_address']) ? sanitize_input($_POST['contact_address']) : '';

    // Validate contact phone if provided
    if (!empty($contact_phone) && !validate_phone($contact_phone)) {
        $_SESSION['error'] = __('Invalid contact phone format.');
        return;
    }

    // Validate email
    if (!validate_email($contact_email)) {
        $_SESSION['error'] = __('Invalid email format.');
        return;
    }

    // Validate currency
    $currencies = get_currencies();
    if (!isset($currencies[$currency])) {
        $_SESSION['error'] = __('Invalid currency selected.');
        return;
    }

    // Handle logo upload
    if (!empty($_FILES['site_logo']['name'])) {
        $upload_result = upload_file($_FILES['site_logo'], 'logo');

        if ($upload_result === false) {
            $_SESSION['error'] = __('Logo upload failed. Please check the file type and size.');
            return;
        }

        // Get old logo path and delete it
        $old_logo = get_setting('site_logo');
        if ($old_logo && file_exists('../../' . $old_logo)) {
            @unlink('../../' . $old_logo);
        }
        update_setting('site_logo', $upload_result);
    }

    // Update settings
    update_setting('site_name', $site_name);
    update_setting('contact_email', $contact_email);
    update_setting('secondary_language', $secondary_language);
    update_setting('translation_mode', $translation_mode);
    update_setting('currency', strtoupper($currency));

    // Trigger async exchange rate fetch when currency changes (fire-and-forget with 1s timeout)
    $cron_key = $_ENV['CRON_API_KEY'] ?? null;
    if ($cron_key) {
        $fetch_url = get_site_url() . '/cron/fetch-exchange-rates.php?key=' . urlencode($cron_key);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1,
                'ignore_errors' => true
            ]
        ]);
        @file_get_contents($fetch_url, false, $context);
    }

    // Save contact phone/address
    update_setting('contact_phone', $contact_phone);
    update_setting('contact_address', $contact_address);

    // Social links - collect known platforms and save as JSON
    $platforms = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'telegram'];
    $social_links = [];
    foreach ($platforms as $p) {
        $field = $p . '_url';
        $val = isset($_POST[$field]) ? sanitize_input($_POST[$field]) : '';
        if (!empty($val)) {
            // Basic URL validation
            if (filter_var($val, FILTER_VALIDATE_URL) === false) {
                $_SESSION['error'] = sprintf(__('Invalid URL for %s'), ucfirst($p));
                return;
            }
            $social_links[$p] = $val;
        }
    }
    update_setting('social_links', json_encode($social_links));

    // Instant message / chat snippet (store raw trimmed value)
    $instant_message_code = isset($_POST['instant_message_code']) ? trim($_POST['instant_message_code']) : '';
    update_setting('instant_message_code', $instant_message_code);

    $_SESSION['success'] = __('Site settings updated successfully.');
}

/**
 * Handle Payment Methods Update
 */
function handlePaymentMethods()
{
    if (empty($_POST['payment_methods'])) {
        $_SESSION['error'] = __('Payment methods data is missing.');
        return;
    }

    $payment_methods = sanitize_input($_POST['payment_methods']);

    // Decode and validate JSON
    $decoded = json_decode($payment_methods, true);
    if ($decoded === null && $payment_methods !== '[]') {
        $_SESSION['error'] = __('Invalid payment methods format.');
        return;
    }

    // Validate array structure
    if (is_array($decoded)) {
        foreach ($decoded as $key => $method) {
            if (empty($method['name']) || empty($method['type'])) {
                $_SESSION['error'] = __('Each payment method must have name and type.');
                return;
            }

            // Validate type-specific required fields
            switch ($method['type']) {
                case 'Cryptocurrency':
                    if (empty($method['network']) || empty($method['wallet_address'])) {
                        $_SESSION['error'] = sprintf(__('Payment method "%s" must have network and wallet address.'), $method['name']);
                        return;
                    }
                    break;
                case 'Bank Transfer':
                    if (empty($method['bank_name']) || empty($method['account_name']) || empty($method['account_number'])) {
                        $_SESSION['error'] = sprintf(__('Payment method "%s" must have bank name, account name, and account number.'), $method['name']);
                        return;
                    }
                    break;
                case 'E-Wallet':
                    if (empty($method['provider']) || empty($method['wallet_id'])) {
                        $_SESSION['error'] = sprintf(__('Payment method "%s" must have provider and wallet ID.'), $method['name']);
                        return;
                    }
                    break;
                case 'Mobile Money':
                    if (empty($method['provider']) || empty($method['phone_number'])) {
                        $_SESSION['error'] = sprintf(__('Payment method "%s" must have provider and phone number.'), $method['name']);
                        return;
                    }
                    break;
                case 'Other':
                    if (empty($method['details'])) {
                        $_SESSION['error'] = sprintf(__('Payment method "%s" must have payment details.'), $method['name']);
                        return;
                    }
                    break;
            }
        }
    }

    // Update setting as JSON string
    update_setting('payment_methods', $payment_methods);
    $_SESSION['success'] = __('Payment methods updated successfully.');
}

/**
 * Handle Deposit Settings Update
 */
function handleDepositSettings()
{
    $deposit_fee_percentage = sanitize_input($_POST['deposit_fee_percentage'] ?? '0');

    // Validate percentage
    if (!is_numeric($deposit_fee_percentage) || $deposit_fee_percentage < 0 || $deposit_fee_percentage > 100) {
        $_SESSION['error'] = __('Deposit fee percentage must be between 0 and 100.');
        return;
    }

    // Update setting
    update_setting('deposit_fee_percentage', $deposit_fee_percentage);
    $_SESSION['success'] = __('Deposit fee updated successfully.');
}

/**
 * Handle Referral Settings Update
 */
function handleReferralSettings()
{
    $referral_bonus_type = sanitize_input($_POST['referral_bonus_type'] ?? '');
    $referral_bonus_amount = sanitize_input($_POST['referral_bonus_amount'] ?? '');
    $referral_bonus_trigger = sanitize_input($_POST['referral_bonus_trigger'] ?? '');

    // Validate bonus type
    if (!in_array($referral_bonus_type, ['flat', 'percentage'])) {
        $_SESSION['error'] = __('Invalid referral bonus type.');
        return;
    }

    // Validate bonus amount
    if (!is_numeric($referral_bonus_amount) || $referral_bonus_amount < 0) {
        $_SESSION['error'] = __('Referral bonus amount must be a positive number.');
        return;
    }

    // Validate bonus trigger
    if (!in_array($referral_bonus_trigger, ['first_deposit', 'registration', 'first_investment', 'every_deposit', 'every_investment'])) {
        $_SESSION['error'] = __('Invalid referral bonus trigger.');
        return;
    }

    // Update settings
    update_setting('referral_bonus_type', $referral_bonus_type);
    update_setting('referral_bonus_amount', $referral_bonus_amount);
    update_setting('referral_bonus_trigger', $referral_bonus_trigger);

    $_SESSION['success'] = __('Referral settings updated successfully.');
}

/**
 * Handle Withdrawal Settings Update
 */
function handleWithdrawalSettings()
{
    $withdrawal_fee_percentage = sanitize_input($_POST['withdrawal_fee_percentage'] ?? '');
    $minimum_withdrawal = sanitize_input($_POST['minimum_withdrawal'] ?? '');

    // Validate percentages
    if (!is_numeric($withdrawal_fee_percentage) || $withdrawal_fee_percentage < 0 || $withdrawal_fee_percentage > 100) {
        $_SESSION['error'] = __('Withdrawal fee percentage must be between 0 and 100.');
        return;
    }

    if (!is_numeric($minimum_withdrawal) || $minimum_withdrawal < 0) {
        $_SESSION['error'] = __('Minimum withdrawal must be a positive number.');
        return;
    }

    // Update settings
    update_setting('withdrawal_fee_percentage', $withdrawal_fee_percentage);
    update_setting('minimum_withdrawal', $minimum_withdrawal);

    $_SESSION['success'] = __('Withdrawal settings updated successfully.');
}

/**
 * Handle KYC Settings Update
 */
function handleKYCSettings()
{
    $kyc_required = sanitize_input($_POST['kyc_required'] ?? '');
    $kyc_timing = sanitize_input($_POST['kyc_timing'] ?? '');
    $kyc_always_show_message = sanitize_input($_POST['kyc_always_show_message'] ?? 'no');
    $kyc_banner_dismissible = sanitize_input($_POST['kyc_banner_dismissible'] ?? 'yes');

    // Validate kyc_required
    if (!in_array($kyc_required, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid KYC required setting.');
        return;
    }

    // Validate kyc_timing only if KYC is required
    if ($kyc_required === 'yes') {
        if (!in_array($kyc_timing, ['before_withdrawal', 'before_investment', 'immediately'])) {
            $_SESSION['error'] = __('Invalid KYC timing setting.');
            return;
        }
    }

    // Validate kyc_always_show_message
    if (!in_array($kyc_always_show_message, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid KYC always show message setting.');
        return;
    }

    // Validate kyc_banner_dismissible
    if (!in_array($kyc_banner_dismissible, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid KYC banner dismissible setting.');
        return;
    }

    // Update settings
    update_setting('kyc_required', $kyc_required);
    if ($kyc_required === 'yes') {
        update_setting('kyc_timing', $kyc_timing);
    }
    update_setting('kyc_always_show_message', $kyc_always_show_message);
    update_setting('kyc_banner_dismissible', $kyc_banner_dismissible);

    $_SESSION['success'] = __('KYC settings updated successfully.');
}

/**
 * Handle Investment Cancellation Settings Update
 */
function handleCancellationSettings()
{
    $cancellation_penalty_mode = sanitize_input($_POST['cancellation_penalty_mode'] ?? '');
    $cancellation_penalty_percentage = sanitize_input($_POST['cancellation_penalty_percentage'] ?? '');
    $cancellation_penalty_flat = sanitize_input($_POST['cancellation_penalty_flat'] ?? '');
    $cancellation_forfeit_profits = sanitize_input($_POST['cancellation_forfeit_profits'] ?? '');
    $cancellation_block_after_waiting = sanitize_input($_POST['cancellation_block_after_waiting'] ?? '');

    // Validate penalty mode
    if (!in_array($cancellation_penalty_mode, ['percentage', 'flat', 'none'])) {
        $_SESSION['error'] = __('Invalid cancellation penalty mode.');
        return;
    }

    // Validate penalty values based on mode
    if ($cancellation_penalty_mode === 'percentage') {
        if (!is_numeric($cancellation_penalty_percentage) || $cancellation_penalty_percentage < 0 || $cancellation_penalty_percentage > 100) {
            $_SESSION['error'] = __('Cancellation penalty percentage must be between 0 and 100.');
            return;
        }
    } elseif ($cancellation_penalty_mode === 'flat') {
        if (!is_numeric($cancellation_penalty_flat) || $cancellation_penalty_flat < 0) {
            $_SESSION['error'] = __('Cancellation penalty flat amount must be a positive number.');
            return;
        }
    }

    // Validate forfeit profits
    if (!in_array($cancellation_forfeit_profits, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid forfeit profits setting.');
        return;
    }

    // Validate block after waiting
    if (!in_array($cancellation_block_after_waiting, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid block after waiting setting.');
        return;
    }

    // Update settings
    update_setting('cancellation_penalty_mode', $cancellation_penalty_mode);
    if ($cancellation_penalty_mode === 'percentage') {
        update_setting('cancellation_penalty_percentage', $cancellation_penalty_percentage);
    } elseif ($cancellation_penalty_mode === 'flat') {
        update_setting('cancellation_penalty_flat', $cancellation_penalty_flat);
    }
    update_setting('cancellation_forfeit_profits', $cancellation_forfeit_profits);
    update_setting('cancellation_block_after_waiting', $cancellation_block_after_waiting);

    $_SESSION['success'] = __('Investment cancellation policy updated successfully.');
}

/**
 * Handle Post-Registration Settings Update
 */
function handleRegistrationSettings()
{
    $post_registration_action = sanitize_input($_POST['post_registration_action'] ?? '');

    // Validate action
    if (!in_array($post_registration_action, ['dashboard', 'deposit', 'invest'])) {
        $_SESSION['error'] = __('Invalid post-registration action.');
        return;
    }

    // Update setting
    update_setting('post_registration_action', $post_registration_action);

    $_SESSION['success'] = __('Post-registration settings updated successfully.');
}

/**
 * Handle Email Verification Settings Update
 */
function handleEmailVerificationSettings()
{
    $require_email_verification = sanitize_input($_POST['require_email_verification'] ?? '');

    // Validate setting
    if (!in_array($require_email_verification, ['yes', 'no'])) {
        $_SESSION['error'] = __('Invalid email verification setting.');
        return;
    }

    // Update setting
    update_setting('require_email_verification', $require_email_verification);

    $_SESSION['success'] = __('Email verification settings updated successfully.');
}


/**
 * Handle SEO Settings Update
 */
function handleSEOSettings()
{
    $site_description = sanitize_input($_POST['site_description'] ?? '');
    $site_keywords = sanitize_input($_POST['site_keywords'] ?? '');
    $default_mode = sanitize_input($_POST['default_mode'] ?? 'system');

    // Validate default mode
    if (!in_array($default_mode, ['light', 'dark', 'system'])) {
        $_SESSION['error'] = __('Invalid default mode selected.');
        return;
    }

    // Update settings
    update_setting('site_description', $site_description);
    update_setting('site_keywords', $site_keywords);
    update_setting('default_mode', $default_mode);

    $_SESSION['success'] = __('SEO settings updated successfully.');
}

/**
 * Handle Countries Settings Update
 */
function handleCountriesSettings()
{
    // Read inputs
    $accepted_input = $_POST['accepted_countries'] ?? [];
    $default_country = sanitize_input($_POST['default_country'] ?? '');
    $confirm_reassign = !empty($_POST['confirm_reassign']);

    // Normalize accepted countries into an array
    if (!is_array($accepted_input)) {
        if (is_string($accepted_input)) {
            $accepted_input = array_filter(array_map('trim', explode(',', $accepted_input)));
        } else {
            $accepted_input = [];
        }
    }

    // Only keep valid, normalized country codes
    $all_countries = get_countries();
    $accepted = [];
    foreach ($accepted_input as $country_code) {
        $code = sanitize_input($country_code);
        if ($code !== '' && array_key_exists($code, $all_countries)) {
            $accepted[] = $code;
        }
    }
    $accepted = array_values(array_unique($accepted));

    // Validate - at least one country
    if (empty($accepted)) {
        $_SESSION['error'] = __('At least one country must be accepted.');
        header('Location: /admin/settings#countries');
        exit;
    }

    // Validate - default in accepted list
    if ($default_country === '' || !in_array($default_country, $accepted, true)) {
        $_SESSION['error'] = __('The default country must be in the accepted list.');
        header('Location: /admin/settings#countries');
        exit;
    }

    // Find deselected countries with users
    $current_accepted = get_accepted_countries();
    $deselected = array_diff($current_accepted, $accepted);
    $affected_users = [];

    if (!empty($deselected)) {
        $placeholders = implode(',', array_fill(0, count($deselected), '?'));
        $affected_users = db_query("SELECT id, name, email, country FROM users WHERE country IN ($placeholders)", array_values($deselected));
    }

    // If affected users exist and no confirm
    if (!empty($affected_users) && !$confirm_reassign) {
        header('Content-Type: application/json');
        echo json_encode(['affected' => $affected_users]);
        exit;
    }

    // If affected users exist and confirm posted
    if (!empty($affected_users) && $confirm_reassign) {
        $placeholders2 = implode(',', array_fill(0, count($deselected), '?'));
        db_update('users', ['country' => $default_country], "country IN ($placeholders2)", array_values($deselected));
    }

    // Save settings
    update_setting('accepted_countries', json_encode(array_values($accepted)));
    update_setting('default_country', $default_country);
    unset($GLOBALS['accepted_countries']);

    // Flash and redirect
    $_SESSION['success'] = __('Country settings saved.');
}


/**
 * Handle Withdrawal Methods Update
 */
function handleWithdrawalMethods()
{
    if (empty($_POST['withdrawal_methods'])) {
        $_SESSION['error'] = __('Withdrawal methods data is missing.');
        return;
    }

    $withdrawal_methods = sanitize_input($_POST['withdrawal_methods']);

    // Decode and validate JSON
    $decoded = json_decode($withdrawal_methods, true);
    if ($decoded === null && $withdrawal_methods !== '[]') {
        $_SESSION['error'] = __('Invalid withdrawal methods format.');
        return;
    }

    // Validate array structure
    if (is_array($decoded)) {
        foreach ($decoded as $key => $method) {
            if (empty($method['name']) || empty($method['type'])) {
                $_SESSION['error'] = __('Each withdrawal method must have a name and type.');
                return;
            }
        }
    }

    // Update setting as JSON string
    update_setting('withdrawal_methods', $withdrawal_methods);
    $_SESSION['success'] = __('Withdrawal methods updated successfully.');
}

/**
 * Handle Email Notifications - Account Settings
 */
function handleEmailNotificationsAccount()
{
    $settings = [
        'email_user_registration',
        'email_password_reset'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('Account notification settings updated successfully.');
}

/**
 * Handle Email Notifications - Deposit Settings
 */
function handleEmailNotificationsDeposit()
{
    $settings = [
        'email_deposit_submitted_user',
        'email_deposit_approved_user',
        'email_deposit_rejected_user',
        'email_deposit_submitted_admin'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('Deposit notification settings updated successfully.');
}

/**
 * Handle Email Notifications - Withdrawal Settings
 */
function handleEmailNotificationsWithdrawal()
{
    $settings = [
        'email_withdrawal_submitted_user',
        'email_withdrawal_approved_user',
        'email_withdrawal_rejected_user',
        'email_withdrawal_submitted_admin'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('Withdrawal notification settings updated successfully.');
}

/**
 * Handle Email Notifications - Investment Settings
 */
function handleEmailNotificationsInvestment()
{
    $settings = [
        'email_investment_created_user',
        'email_investment_completed_user',
        'email_investment_cancelled_user',
        'email_profit_payout_user'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('Investment notification settings updated successfully.');
}

/**
 * Handle Email Notifications - KYC Settings
 */
function handleEmailNotificationsKYC()
{
    $settings = [
        'email_kyc_approved_user',
        'email_kyc_rejected_user',
        'email_kyc_submitted_admin'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('KYC notification settings updated successfully.');
}

/**
 * Handle Email Notifications - Referral Settings
 */
function handleEmailNotificationsReferral()
{
    $settings = [
        'email_referral_bonus_user'
    ];

    foreach ($settings as $setting) {
        $value = isset($_POST[$setting]) && $_POST[$setting] === 'yes' ? 'yes' : 'no';
        update_setting($setting, $value);
    }

    $_SESSION['success'] = __('Referral notification settings updated successfully.');
}
