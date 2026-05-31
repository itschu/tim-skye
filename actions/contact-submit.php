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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /contact');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    $_SESSION['error'] = __('Invalid security token. Please try again.');
    header('Location: /contact');
    exit;
}

// Honeypot spam check
if (!empty($_POST['website'] ?? '')) {
    $_SESSION['error'] = __('An error occurred. Please try again.');
    header('Location: /contact');
    exit;
}

// Sanitize inputs
$name = sanitize_input($_POST['name'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$subject = sanitize_input($_POST['subject'] ?? '');
$message = sanitize_input($_POST['message'] ?? '');

// Validate required fields
$required = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];
$missing = validate_required($required);
if (!empty($missing)) {
    $_SESSION['error'] = __('Please fill in all required fields.');
    header('Location: /contact');
    exit;
}

// Validate email format
if (!validate_email($email)) {
    $_SESSION['error'] = __('Please enter a valid email address.');
    header('Location: /contact');
    exit;
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
    $_SESSION['error'] = __('Failed to send message. Please try again later.');
    header('Location: /contact');
    exit;
}

$_SESSION['success'] = __('Thank you! Your message has been sent. We\'ll get back to you soon.');
header('Location: /contact');
exit;
