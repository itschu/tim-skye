<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Secure Proxy Script for Deposit/Withdrawal Proofs
 * 
 * This script serves as a gatekeeper for deposit and withdrawal proof files.
 * It validates authentication, verifies file ownership, prevents directory traversal,
 * and logs all access attempts.
 * 
 * Usage: /view-proof.php?file={filename}
 */

// Include authentication guard - ensures user is logged in
require_once ROOT . '/includes/auth.php';

// Include upload functions for MIME type detection
require_once ROOT . '/includes/upload-functions.php';

// Helper function to send error response
function send_error($status_code, $message)
{
    http_response_code($status_code);
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

// Helper function to log access attempt
function log_access($status, $user_id, $filename, $reason = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] PROOF $status: User $user_id accessed $filename";
    if (!empty($reason)) {
        $message .= " - $reason";
    }
    error_log($message, 3, ROOT . '/logs/upload-errors.log');
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

// Validate file parameter exists and is not empty
if (!isset($_GET['file']) || empty($_GET['file'])) {
    send_error(400, 'File parameter required');
}

$file_param = $_GET['file'];

// Sanitize filename using basename to prevent directory traversal
$sanitized_filename = basename($file_param);

// Validate that filename does not contain .. or / characters
if (strpos($sanitized_filename, '..') !== false || strpos($sanitized_filename, '/') !== false) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $file_param, 'Directory traversal attempt');
    send_error(400, 'Invalid file format');
}

// Validate file extension is in allowed list
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$file_extension = strtolower(pathinfo($sanitized_filename, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $sanitized_filename, 'Invalid file extension');
    send_error(400, 'Invalid file format');
}

// Validate filename matches expected pattern: {hash}_{timestamp}.{extension}
// Pattern: alphanumeric/underscore, underscore, digits, dot, extension
if (!preg_match('/^[a-z0-9]+_\d+\.' . preg_quote($file_extension) . '$/i', $sanitized_filename)) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $sanitized_filename, 'Invalid filename pattern');
    send_error(400, 'Invalid file format');
}

// ============================================================================
// DIRECTORY TRAVERSAL PREVENTION
// ============================================================================

// Construct full file path
$full_path = ROOT . '/uploads/proofs/' . $sanitized_filename;
// Resolve path and verify it's within proofs directory
$real_path = realpath($full_path);
$proofs_dir = realpath(ROOT . '/uploads/proofs');

// If realpath fails or path is not within proofs directory, reject
if ($real_path === false || strpos($real_path, $proofs_dir) !== 0) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $sanitized_filename, 'Path traversal attempt');
    send_error(403, 'Access denied');
}

// ============================================================================
// OWNERSHIP VERIFICATION
// ============================================================================

// Query deposits table to find record with this proof
// Look up the file in the database. Require an entry in either `deposits`
// or `withdrawals` before serving. This prevents admins from viewing
// arbitrary files that are not registered in the DB.
$record = null;
$record_table = null;

$deposits = db_query(
    "SELECT user_id FROM deposits WHERE proof_path = ?",
    [$file_param]
);


if (!empty($deposits)) {
    $record = $deposits[0];
    $record_table = 'deposits';
}

// If no DB record exists for this filename, deny access for everyone
if (empty($record)) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $file_param, 'File not registered in database');
    send_error(404, 'File not found');
}

// Enforce ownership or admin role
$owner_id = $record['user_id'];
$current_user_id = $GLOBALS['current_user']['id'];
$is_admin = $GLOBALS['current_user']['role'] === 'admin';

if ($current_user_id !== $owner_id && !$is_admin) {
    log_access('DENIED', $current_user_id, $sanitized_filename, "User does not own this file (owner: $owner_id, table: $record_table)");
    send_error(403, 'Access denied');
}

// ============================================================================
// FILE SERVING
// ============================================================================

// Verify file exists on disk
if (!file_exists($real_path)) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $sanitized_filename, 'File not found on disk');
    send_error(404, 'File not found on server');
}

// Get MIME type
$mime_type = get_mime_type($real_path);
if (!$mime_type) {
    log_access('DENIED', $GLOBALS['current_user']['id'], $sanitized_filename, 'Could not determine MIME type');
    send_error(500, 'Error serving file');
}

// Get file size
$file_size = filesize($real_path);

// Log successful access
log_access('SUCCESS', $GLOBALS['current_user']['id'], $sanitized_filename);

// Set security and content headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $file_size);
header('Content-Disposition: inline; filename="' . $sanitized_filename . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Pragma: private');

// Serve the file
readfile($real_path);
exit;
