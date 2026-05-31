<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

// Load bootstrap if not already loaded
if (!defined('INCLUDES_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/**
 * Email sending utilities using PHPMailer
 */
require_once __DIR__ . '/validation-functions.php';
require_once __DIR__ . '/translation-functions.php';

function send_email($to, $subject, $body, $is_html = true)
{
    if (!validate_email($to)) {
        log_email_error('Invalid recipient email', $to);
        return false;
    }

    // Check if we're in development mode and should log emails instead
    $app_env = $_ENV['APP_ENV'] ?? 'production';
    $smtp_host = $_ENV['SMTP_HOST'] ?? '';

    // Auto-detect localhost
    $http_host   = $_SERVER['HTTP_HOST']   ?? '';
    $server_name = $_SERVER['SERVER_NAME'] ?? '';

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SCHEME']) && $_SERVER['HTTP_X_FORWARDED_SCHEME'] === 'https');

    $is_localhost =
        empty($http_host) ||
        in_array($http_host,   ['localhost', '127.0.0.1', '::1'], true) ||
        in_array($server_name, ['localhost', '127.0.0.1', '::1'], true) ||
        strpos($http_host,   'localhost:') === 0 ||
        strpos($http_host,   '127.0.0.1:') === 0 ||
        strpos($server_name, 'localhost')  === 0;

    // If no SMTP is configured OR we're in local development, log to file instead
    if (empty($smtp_host) || $app_env === 'development' || $app_env === 'local' || $is_localhost || !$is_https) {
        return log_email_to_file($to, $subject, $body, $is_html);
    }

    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPDebug = 0;
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com';
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'Site';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_email_error($e->getMessage(), $to);
        return false;
    }
}

/**
 * Log email to file instead of sending (for development)
 */
function log_email_to_file($to, $subject, $body, $is_html = true)
{
    $logs_root = ROOT . '/logs';
    $log_dir   = $logs_root . '/emails';

    if (!is_dir($logs_root) && !mkdir($logs_root, 0755)) {
        log_email_error('Cannot create logs/ directory', $to);
        return false;
    }
    if (!is_dir($log_dir) && !mkdir($log_dir, 0755)) {
        log_email_error('Cannot create logs/emails/ directory', $to);
        return false;
    }

    $filename = $log_dir . '/email_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.html';

    $content = "<!--\n";
    $content .= "To: {$to}\n";
    $content .= "Subject: {$subject}\n";
    $content .= "Date: " . date('c') . "\n";
    $content .= "Type: " . ($is_html ? 'HTML' : 'Plain Text') . "\n";
    $content .= "-->\n\n";
    $content .= $body;

    $saved = @file_put_contents($filename, $content);

    if ($saved !== false) {
        // Also log to the regular log that an email was "sent" (logged)
        $entry = '[' . date('c') . '] To: ' . $to . ' | Subject: ' . $subject . ' | [LOGGED TO FILE: ' . basename($filename) . ']' . "\n";
        @file_put_contents(ROOT . '/logs/email-sent.log', $entry, FILE_APPEND | LOCK_EX);
    } else {
        log_email_error('file_put_contents failed writing to ' . $filename, $to);
    }

    return (bool) $saved;
}

function send_template_email($to, $template_name, $variables, $language = null)
{
    // Save original global locale state
    $original_language = $GLOBALS['current_language'] ?? 'en_US';
    $original_translations = $GLOBALS['translations'] ?? [];

    // Resolve language: if null, use current global locale (respects externally set locales from cron)
    if ($language === null) {
        $language = $GLOBALS['current_language'] ?? 'en_US';
    }

    // Switch to target language if it differs from current global state
    if ($language !== $original_language) {
        init_translation($language);
    }

    $tpl_file = __DIR__ . '/email-templates/' . $template_name . '.html';
    if (!file_exists($tpl_file)) {
        log_email_error('Template not found: ' . $tpl_file, $to);
        // Restore original locale before returning
        if ($language !== $original_language) {
            init_translation($original_language);
        }
        return false;
    }
    $content = file_get_contents($tpl_file);
    // extract subject
    $subject = '';
    if (preg_match('/<!--\s*SUBJECT:\s*(.*?)\s*-->/', $content, $m)) {
        $subject = trim($m[1]);
        $subject = __($subject);
        // remove subject comment from body
        $content = preg_replace('/<!--\s*SUBJECT:.*?-->/', '', $content, 1);
    }

    // Regex pass to resolve all [[__("string")]] markers to translated strings
    $content = preg_replace_callback(
        '/\[\[__\("((?:[^"\\\\]|\\\\.)*?)"\)\]\]/',
        function ($m) {
            // Unescape escaped quotes and backslashes in the captured string
            $unescaped = stripslashes($m[1]);
            return __($unescaped);
        },
        $content
    );

    foreach ($variables as $k => $v) {
        $content = str_replace('{{' . $k . '}}', $v, $content);
        $subject = str_replace('{{' . $k . '}}', $v, $subject);
    }
    // Second translation pass on the subject after variable replacement
    $subject = __($subject);
    $result = send_email($to, $subject, $content, true);

    // Restore original locale only if we switched languages
    if ($language !== $original_language) {
        init_translation($original_language);
    }

    return $result;
}

function log_email_error($error_message, $recipient = null)
{
    $entry = '[' . date('c') . "] To: " . ($recipient ?? 'n/a') . " | Error: " . $error_message . "\n";
    @file_put_contents(__DIR__ . '/../logs/email-errors.log', $entry, FILE_APPEND | LOCK_EX);
}
