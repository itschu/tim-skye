<?php

/**
 * Contact form submission handler
 * Validates input and sends email to site admin.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/session.php';
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/validation-functions.php';
require_once ROOT . '/includes/email-functions.php';
require_once ROOT . '/includes/translation-functions.php';

init_translation();

/**
 * Return a response either as JSON (AJAX) or as a session flash + redirect.
 */
function respond(bool $success, string $message)
{
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    $_SESSION[$success ? 'success' : 'error'] = $message;
    header('Location: /contact');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, __('Invalid request method'));
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    respond(false, __('Invalid security token. Please try again.'));
}

// Honeypot spam check
if (!empty($_POST['website'] ?? '')) {
    respond(false, __('An error occurred. Please try again.'));
}

// Resolve sender identity. If the user is logged in (help-center flow), use the
// authenticated DB record so hidden form fields cannot be spoofed. Public
// contact form falls back to the submitted name/email.
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $authUserRows = db_query('SELECT name, email FROM users WHERE id = ?', [$userId]);
    $authUser = !empty($authUserRows) ? $authUserRows[0] : null;
}

// Sanitize inputs
$name = sanitize_input(($authUser['name'] ?? '') ?: ($_POST['name'] ?? ''));
$email = sanitize_input(($authUser['email'] ?? '') ?: ($_POST['email'] ?? ''));
$subject = sanitize_input($_POST['subject'] ?? '');
$message = sanitize_input($_POST['message'] ?? '');

// Validate required fields
$required = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];
$missing = validate_required($required);
if (!empty($missing)) {
    respond(false, __('Please fill in all required fields.'));
}

// Validate email format
if (!validate_email($email)) {
    respond(false, __('Please enter a valid email address.'));
}

// Determine recipient
$recipient = get_setting('contact_email', $_ENV['SMTP_FROM_EMAIL'] ?? 'admin@example.com');

$email_subject = 'Contact Form: ' . ($subject ?: __('No subject'));

$body_lines = [];
$body_lines[] = __('New contact form submission');
$body_lines[] = "";
$body_lines[] = __('From') . ': ' . $name;
$body_lines[] = __('Email') . ': ' . $email;
$body_lines[] = __('Subject') . ': ' . $subject;
$body_lines[] = "";
$body_lines[] = __('Message') . ':';
$body_lines[] = $message;
$body_lines[] = "";
$body_lines[] = '---';
$body_lines[] = __('Submitted') . ': ' . date('Y-m-d H:i:s');
$body_lines[] = __('IP') . ': ' . ($_SERVER['REMOTE_ADDR'] ?? '');

$body = implode("\n", $body_lines);

$sent = send_email($recipient, $email_subject, $body, false);
if (!$sent) {
    respond(false, __('Failed to send message. Please try again later.'));
}

respond(true, __('Thank you! Your message has been sent. We\'ll get back to you soon.'));
