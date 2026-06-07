<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * User Registration Submission Handler
 * 
 * Processes user registration form submission
 */

// Secure session bootstrap
require_once __DIR__ . '/../includes/session.php';

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation-functions.php';
require_once __DIR__ . '/../includes/translation-functions.php';
require_once __DIR__ . '/../includes/email-functions.php';

// Initialize translation
init_translation();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /register');
    exit;
}

// Extract referral code early for preservation in error redirects
$referral_code = sanitize_input($_POST['referral_code'] ?? '');

// Validate country
$country          = sanitize_input($_POST['country'] ?? '');
$accepted_countries = get_accepted_countries();

if (empty($country) || !in_array($country, $accepted_countries, true)) {
    $_SESSION['error'] = __('The selected country is no longer available. Please refresh and try again.');
    if (!empty($referral_code)) {
        header('Location: /register?ref=' . urlencode($referral_code));
    } else {
        header('Location: /register');
    }
    exit;
}

// Sanitize inputs - handle both 'name' and 'first_name/last_name' formats
$first_name = sanitize_input($_POST['first_name'] ?? '');
$last_name = sanitize_input($_POST['last_name'] ?? '');
$name = sanitize_input($_POST['name'] ?? '');

// If first_name and last_name are provided, combine them
if (!empty($first_name) && !empty($last_name)) {
    $name = $first_name . ' ' . $last_name;
}

$email = sanitize_input($_POST['email'] ?? '');
$phone = sanitize_input($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Check if email verification is required
$require_email_verification = get_setting('require_email_verification', 'no');

// Validate required fields
$required_fields = [
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'confirm_password' => $confirm_password
];

$missing_fields = validate_required($required_fields);
if (!empty($missing_fields)) {
    $_SESSION['error'] = __('Please fill in all required fields.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Validate email format
if (!validate_email($email)) {
    $_SESSION['error'] = __('Please enter a valid email address.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Validate password strength
if (!validate_password_strength($password, 6)) {
    $_SESSION['error'] = __('Password must be at least 6 characters long.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Check password match
if ($password !== $confirm_password) {
    $_SESSION['error'] = __('Passwords do not match.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Check email uniqueness
try {
    $existing_user = db_query("SELECT id FROM users WHERE email = ?", [$email]);
    if (!empty($existing_user)) {
        $_SESSION['error'] = __('An account with this email already exists.');
        header('Location: /register?ref=' . urlencode($referral_code));
        exit;
    }
} catch (Exception $e) {
    error_log("Registration error - email check: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Check if referral code is required
$require_referral_code = get_setting('require_referral_code', 'no');

if ($require_referral_code === 'yes' && empty($referral_code)) {
    $_SESSION['error'] = __('A valid referral code is required to complete registration.');
    header('Location: /register');
    exit;
}

// Validate referral code if provided
$referrer_id = null;
if (!empty($referral_code)) {
    try {
        $referrer = db_query("SELECT id, name FROM users WHERE referral_code = ?", [$referral_code]);
        if (empty($referrer)) {
            $_SESSION['error'] = __('Referral code is invalid.');
            header('Location: /register?ref=' . urlencode($referral_code));
            exit;
        }
        $referrer_id = $referrer[0]['id'];
        error_log("[Referral Debug] Valid referral code '{$referral_code}' for referrer_id={$referrer_id}, name={$referrer[0]['name']}");
    } catch (Exception $e) {
        error_log("Registration error - referral code check: " . $e->getMessage());
        $_SESSION['error'] = __('An error occurred. Please try again.');
        header('Location: /register?ref=' . urlencode($referral_code));
        exit;
    }
}

// Generate unique referral code
$generated_code = null;
$max_attempts = 10;
$attempt = 0;

try {
    do {
        $generated_code = strtoupper(substr(md5(uniqid($email . $attempt, true)), 0, 8));
        $existing_code = db_query("SELECT id FROM users WHERE referral_code = ?", [$generated_code]);
        $attempt++;
    } while (!empty($existing_code) && $attempt < $max_attempts);

    if ($attempt >= $max_attempts) {
        $_SESSION['error'] = __('An error occurred during registration. Please try again.');
        header('Location: /register?ref=' . urlencode($referral_code));
        exit;
    }
} catch (Exception $e) {
    error_log("Registration error - referral code generation: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Generate email verification token if required
$email_verification_token = null;
$email_verified = 1; // Default to verified (if verification is not required)
$verification_sent_at = null;

if ($require_email_verification === 'yes') {
    $email_verified = 0;
    $email_verification_token = bin2hex(random_bytes(32));
    $verification_sent_at = date('Y-m-d H:i:s');
}

// Insert user
try {
    $user_data = [
        'name' => $name,
        'email' => $email,
        'email_verified' => $email_verified,
        'email_verification_token' => $email_verification_token,
        'email_verification_sent_at' => $verification_sent_at,
        'country' => $country,
        'phone' => $phone,
        'password_hash' => $password_hash,
        'referral_code' => $generated_code,
        'referred_by' => $referrer_id,
        'status' => 'active',
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $user_id = db_insert('users', $user_data);
    error_log("[Referral Debug] User created: user_id={$user_id}, referred_by={$referrer_id}, referral_code_used='{$referral_code}'");

    // Process referral bonus if applicable (only if trigger is 'registration')
    // The users table already tracks the referral relationship via referred_by column
    if (!empty($referrer_id)) {
        require_once __DIR__ . '/../includes/referral-functions.php';

        // Only process bonus at registration if trigger is set to 'registration'
        $referral_bonus_trigger = get_setting('referral_bonus_trigger', 'registration');
        $referral_bonus_type = get_setting('referral_bonus_type', 'flat');

        // Security check: registration bonus cannot be percentage (no amount to calculate from)
        if ($referral_bonus_trigger === 'registration' && $referral_bonus_type === 'percentage') {
            error_log("[Referral Security] Registration bonus with percentage type is not allowed. Check admin settings.");
        } elseif ($referral_bonus_trigger === 'registration') {
            try {
                $bonus_result = @process_referral_bonus($user_id, 'registration');
                if ($bonus_result === false) {
                    error_log("[Referral Debug] No bonus awarded for user_id={$user_id}. Bonus may have already been processed or amount is 0.");
                } else {
                    error_log("[Referral Debug] Bonus awarded: user_id={$user_id}, amount={$bonus_result}");
                }
            } catch (Exception $e) {
                // Do not block registration on referral errors - log only
                error_log("Referral processing error on registration: " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
            }
        } else {
            error_log("[Referral Debug] Bonus not processed at registration. Trigger is set to '{$referral_bonus_trigger}'");
        }
    }

    // Set redirect URL and message BEFORE sending emails (so user isn't waiting)
    if ($require_email_verification === 'yes') {
        $_SESSION['message'] = __('Registration successful! Please check your email to verify your account.');
        $redirect_url = '/login?registered=1&verify_required=1';
    } else {
        $_SESSION['message'] = __('Account created successfully! Please log in with your credentials.');
        $redirect_param = isset($_SESSION['redirect_after_login']) ? '&redirect=1' : '';
        $redirect_url = '/login?registered=1' . $redirect_param;
    }

    // Send emails asynchronously (don't block user redirect)
    // Use fastcgi_finish_request() if available to close connection early
    if (function_exists('fastcgi_finish_request')) {
        // Send headers and close connection
        header('Location: ' . $redirect_url);
        session_write_close();
        fastcgi_finish_request();
    }

    // Prepare and send emails (this runs after redirect when possible)
    // Always send welcome email if enabled
    $email_user_registration = get_setting('email_user_registration', 'yes');

    if ($email_user_registration === 'yes') {
        $site_url = get_site_url();
        $site_logo_url = get_site_logo_url();

        $welcome_email_vars = [
            'site_name' => get_setting('site_name', 'Investment Platform'),
            'site_logo' => $site_logo_url,
            'logo_url' => $site_logo_url,
            'site_url' => $site_url,
            'user_name' => $name,
            'login_url' => $site_url . '/login',
            'current_year' => date('Y'),
            'support_email' => get_setting('contact_email', 'support@example.com'),
            'company_address' => get_setting('company_address', '')
        ];

        try {
            @send_template_email($email, 'registration-confirmation', $welcome_email_vars, 'en_US');
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
        }
    }

    // Send verification email if required and enabled
    if ($require_email_verification === 'yes') {
        $email_user_verification = get_setting('email_user_verification', 'yes');

        if ($email_user_verification === 'yes') {
            $site_url = get_site_url();
            $verification_link = $site_url . '/actions/verify-email?token=' . $email_verification_token;

            $site_logo_url = get_site_logo_url();

            $email_vars = [
                'site_name' => get_setting('site_name', 'Investment Platform'),
                'site_logo' => $site_logo_url,
                'logo_url' => $site_logo_url,
                'site_url' => $site_url,
                'user_name' => $name,
                'verification_url' => $verification_link,
                'current_year' => date('Y'),
                'support_email' => get_setting('contact_email', 'support@example.com'),
                'company_address' => get_setting('company_address', '')
            ];

            try {
                @send_template_email($email, 'email-verification', $email_vars, 'en_US');
            } catch (Exception $e) {
                error_log("Failed to send verification email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
            }
        }
    }

    // Redirect if not already done via fastcgi_finish_request
    if (!function_exists('fastcgi_finish_request')) {
        header('Location: ' . $redirect_url);
        exit;
    }

    // For fastcgi, we already sent headers, just exit
    exit;
} catch (Exception $e) {
    error_log("Registration error - user insert: " . $e->getMessage());
    $_SESSION['error'] = __('An error occurred during registration. Please try again.');
    header('Location: /register?ref=' . urlencode($referral_code));
    exit;
}
