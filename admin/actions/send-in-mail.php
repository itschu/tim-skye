<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once ROOT . '/includes/db.php';  // This loads environment variables
require_once ROOT . '/includes/admin-auth.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/email-functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check CSRF token
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => __('Invalid security token', true)]);
    exit;
}

// Get form data
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$recipients_json = $_POST['recipients'] ?? '[]';

// Parse recipients
$recipients = json_decode($recipients_json, true);
if (!is_array($recipients) || empty($recipients)) {
    echo json_encode(['success' => false, 'message' => __('No recipients selected', true)]);
    exit;
}

// Validate inputs
if (empty($subject)) {
    echo json_encode(['success' => false, 'message' => __('Subject is required', true)]);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => __('Message is required', true)]);
    exit;
}

$site_url = get_site_url();
$site_name = get_setting('site_name', 'Investment Platform');
$site_logo = get_setting('site_logo', '');
$site_logo_url = (!empty($site_logo) && file_exists(ROOT . '/' . $site_logo))
    ? $site_url . '/' . ltrim($site_logo, '/')
    : $site_url . '/assets/images/logo.png';
$support_email = get_setting('support_email', '');
$currency = get_currency_symbol(get_currency_code());

// Debug: Check if SMTP is configured (only in development)
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
    $smtp_host = $_ENV['SMTP_HOST'] ?? 'NOT SET';
    $smtp_user = $_ENV['SMTP_USER'] ?? 'NOT SET';
    if (empty($smtp_host) || empty($smtp_user)) {
        echo json_encode([
            'success' => false,
            'message' => __('SMTP not configured. Please check your .env file.', true),
            'debug' => ['smtp_host' => $smtp_host, 'smtp_user' => $smtp_user]
        ]);
        exit;
    }
}

// Check if email template exists
$template_path = ROOT . '/includes/email-templates/in-mail.html';
if (!file_exists($template_path)) {
    error_log('In-Mail Error: Template not found at ' . $template_path);
    echo json_encode(['success' => false, 'message' => __('Email template not found', true)]);
    exit;
}

// Send emails
$success_count = 0;
$fail_count = 0;
$errors = [];

foreach ($recipients as $index => $recipient) {
    $email = $recipient['email'] ?? '';
    $name = $recipient['name'] ?? '';
    $first_name = $recipient['first_name'] ?? $name;
    $last_name = $recipient['last_name'] ?? '';
    $full_name = $recipient['full_name'] ?? $name;
    $balance = $recipient['balance'] ?? '$0.00';

    if (empty($email)) {
        $fail_count++;
        $errors[] = "Recipient #$index: Empty email";
        continue;
    }

    // Parse name parts if not provided
    if (empty($first_name) || empty($last_name)) {
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? $name;
        $last_name = $name_parts[1] ?? '';
    }

    // Prepare template variables
    $variables = [
        'user_name' => $name,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'full_name' => $full_name,
        'email' => $email,
        'balance' => $balance,
        'user_email' => $email,
        'subject' => $subject,
        'message_content' => nl2br(htmlspecialchars($message)),
        'site_name' => $site_name,
        'site_logo' => $site_logo_url,
        'logo_url' => $site_logo_url,
        'support_email' => $support_email,
        'currency' => $currency,
        'current_year' => date('Y'),
        'company_address' => get_setting('company_address', '')
    ];



    // Fetch recipient's language preference from database, default to 'en_US'
    $recipient_user = db_query("SELECT language FROM users WHERE LOWER(email) = LOWER(?)", [$email]);
    $recipient_language = (!empty($recipient_user) ? $recipient_user[0]['language'] ?? 'en_US' : 'en_US');

    // Initialize translation context for this recipient
    init_translation($recipient_language);

    // Protect placeholder tokens before translation to avoid translators altering them
    $protected = $message;
    foreach ($variables as $key => $value) {
        $protected = str_replace('{{' . $key . '}}', '__PLH_' . $key . '__', $protected);
    }

    // Translate the protected string
    $translated_protected = __($protected, true);

    // Restore original placeholder tokens after translation
    $translated_message = $translated_protected;
    foreach ($variables as $key => $value) {
        $translated_message = str_replace('__PLH_' . $key . '__', '{{' . $key . '}}', $translated_message);
    }

    // Translate the subject as well so it benefits from the same fallback/API path
    $translated_subject = __($subject, true);
    $variables['subject'] = $translated_subject;

    // Replace placeholders in the translated message with actual values
    $processed_message = $translated_message;
    foreach ($variables as $key => $value) {
        $processed_message = str_replace('{{' . $key . '}}', $value, $processed_message);
    }
    $variables['message_content'] = nl2br(htmlspecialchars($processed_message));

    // Send email using the in-mail template
    $result = send_template_email($email, 'in-mail', $variables, $recipient_language);

    if ($result) {
        $success_count++;
    } else {
        $fail_count++;
        $errors[] = "Failed to send to: $email";
        error_log("In-Mail Error: Failed to send email to $email");
    }
}

$admin_lang = get_user_language($_SESSION['user_id']);
init_translation($admin_lang);

// Return response with debugging info in development
if ($success_count > 0) {
    $response_message = format_translated(
        __('Message sent successfully to %d recipient(s)', true),
        $success_count
    );
    if ($fail_count > 0) {
        $response_message .= format_translated(__(' (%d failed)', true), $fail_count);
    }
    $response = ['success' => true, 'message' => $response_message];
    // Include errors in development mode
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development' && !empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
} else {
    $response = ['success' => false, 'message' => __('Failed to send message to any recipient', true)];
    // Include errors in development mode
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development' && !empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
}
exit;
