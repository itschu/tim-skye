<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/**
 * Secure Proxy Script for KYC Documents (Admin-Only)
 * 
 * This script serves as a gatekeeper for KYC document files.
 * It validates admin authentication, verifies file existence in database,
 * prevents directory traversal, and logs all access attempts.
 * 
 * Usage: /view-kyc.php?file={filename}
 */

// Include admin authentication guard - ensures user is admin
require_once ROOT . '/includes/admin-auth.php';

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

// Helper function to log KYC access attempt
function log_kyc_access($status, $admin_id, $admin_email, $filename, $user_id = null, $reason = '')
{
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] KYC $status: Admin $admin_id ($admin_email) accessed $filename";

    if ($user_id !== null) {
        $message .= " for user $user_id";
    }

    if (!empty($reason)) {
        $message .= " - $reason";
    }

    error_log($message, 3, ROOT . '/logs/upload-errors.log');
}

// ============================================================================
// INPUT VALIDATION & SECURITY
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
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $file_param, null, 'Directory traversal attempt');
    send_error(400, 'Invalid file format');
}

// Validate file extension is in allowed list (including svg for KYC documents)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'svg'];
$file_extension = strtolower(pathinfo($sanitized_filename, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, null, 'Invalid file extension');
    send_error(400, 'Invalid file format');
}

// Validate filename matches expected pattern: {hash}_{timestamp}.{extension}
if (!preg_match('/^[a-z0-9]+_\d+\.' . preg_quote($file_extension) . '$/i', $sanitized_filename)) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, null, 'Invalid filename pattern');
    send_error(400, 'Invalid file format');
}

// ============================================================================
// FILE PATH VALIDATION (Defense in Depth)
// ============================================================================

// Construct full file path
$full_path = ROOT . '/uploads/kycs/' . $sanitized_filename;

// Resolve path and verify it's within kyc directory
$real_path = realpath($full_path);
$kyc_dir = realpath(ROOT . '/uploads/kycs');

// If realpath fails or path is not within kyc directory, reject
if ($real_path === false || strpos($real_path, $kyc_dir) !== 0) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, null, 'Path traversal attempt');
    send_error(403, 'Access denied');
}

// ============================================================================
// DATABASE VERIFICATION
// ============================================================================

// Query kyc_documents table to verify file exists in database (check all new path columns)
$kyc_docs = db_query(
    "SELECT user_id FROM kyc_documents WHERE id_passport_path = ? OR proof_address_path = ? OR selfie_path = ?",
    [$file_param, $file_param, $file_param]
);

if (empty($kyc_docs)) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, null, 'Document not found in database');
    send_error(404, 'Document not found');
}

$kyc_doc = $kyc_docs[0];
$associated_user_id = $kyc_doc['user_id'];

// ============================================================================
// FILE SERVING
// ============================================================================

// Verify file exists on disk
if (!file_exists($real_path)) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, $associated_user_id, 'File not found on disk');
    send_error(404, 'File not found on server');
}

// Get MIME type
$mime_type = get_mime_type($real_path);
if (!$mime_type) {
    log_kyc_access('DENIED', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, $associated_user_id, 'Could not determine MIME type');
    send_error(500, 'Error serving file');
}

// Get file size
$file_size = filesize($real_path);

// Log successful access
log_kyc_access('SUCCESS', $GLOBALS['current_user']['id'], $GLOBALS['current_user']['email'], $sanitized_filename, $associated_user_id);

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
