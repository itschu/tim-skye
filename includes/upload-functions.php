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
 * Upload Functions Library
 * 
 * Handles file uploads with MIME validation, unique filename generation,
 * and directory-based security for the investment platform.
 */

/**
 * Upload a file with validation and unique filename generation
 * 
 * Main upload handler that validates file, generates unique filename,
 * moves to appropriate directory, and returns file path.
 * 
 * @param array $file The $_FILES array element for the uploaded file
 * @param string $type Type of upload: 'proof', 'kyc', 'profile', 'logo'
 * @return string|false Relative path from web root (e.g., 'uploads/proofs/abc123_1234567890.jpg') on success, false on error
 */
function upload_file($file, $type)
{
    // Validate type parameter
    $allowed_types = ['proof', 'kyc', 'profile', 'logo'];
    if (!in_array($type, $allowed_types)) {
        error_log("[Upload Error] Invalid upload type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    // Validate file data
    if (!validate_file($file, $type)) {
        error_log("[Upload Error] File validation failed for type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    try {
        // Generate unique filename
        $filename = generate_filename($file['name']);

        // Determine target directory (note: pluralized)
        $target_dir = __DIR__ . '/../uploads/' . $type . 's';
        $target_path = $target_dir . '/' . $filename;

        // Ensure directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            error_log("[Upload Error] Failed to move uploaded file to: $target_path", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        // Return relative path from web root
        return 'uploads/' . $type . 's/' . $filename;
    } catch (Exception $e) {
        error_log("[Upload Error] Exception: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }
}

/**
 * Validate uploaded file for type, size, and MIME type
 * 
 * Checks file upload error, validates size limits based on type,
 * verifies MIME type and extension match allowed types.
 * 
 * @param array $file The $_FILES array element for the uploaded file
 * @param string $type Type of upload: 'proof', 'kyc', 'profile', 'logo'
 * @return bool True if valid, false otherwise
 */
function validate_file($file, $type)
{
    // Check for upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Define size limits (in bytes)
    $size_limits = [
        'proof'   => 5 * 1024 * 1024,      // 5MB
        'kyc'     => 10 * 1024 * 1024,     // 10MB
        'profile' => 2 * 1024 * 1024,      // 2MB
        'logo'    => 1 * 1024 * 1024       // 1MB
    ];

    // Validate file size
    $max_size = $size_limits[$type] ?? 0;
    if ($file['size'] > $max_size) {
        error_log("[Validation Error] File size ({$file['size']}) exceeds limit ($max_size) for type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    // Get MIME type
    $mime_type = get_mime_type($file['tmp_name']);
    if (!$mime_type) {
        error_log("[Validation Error] Could not determine MIME type for upload type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    // Define allowed MIME types per upload type
    $allowed_mimes = [
        'proof'   => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
        'kyc'     => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
        'profile' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'logo'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']
    ];

    // Validate MIME type
    $allowed = $allowed_mimes[$type] ?? [];
    if (!in_array($mime_type, $allowed)) {
        error_log("[Validation Error] Invalid MIME type ($mime_type) for upload type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    // Validate extension matches MIME type
    $extension = get_file_extension($file['name']);
    $allowed_extensions = get_allowed_extensions($type);
    if (!in_array($extension, $allowed_extensions)) {
        error_log("[Validation Error] Invalid extension ($extension) for upload type: $type", 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }

    return true;
}

/**
 * Generate unique filename with hash and timestamp
 * 
 * Creates a unique filename by combining random hash, timestamp,
 * and original file extension.
 * 
 * Format: {hash}_{timestamp}.{extension}
 * Example: a3f2c1b9d8e7f6a5_1707321456.jpg
 * 
 * @param string $original_filename Original filename from upload
 * @return string Unique filename with extension
 */
function generate_filename($original_filename)
{
    $extension = get_file_extension($original_filename);
    $hash = bin2hex(random_bytes(16));
    $timestamp = time();

    return "{$hash}_{$timestamp}.{$extension}";
}

/**
 * Delete uploaded file safely
 * 
 * Safely deletes an uploaded file with path validation to prevent
 * directory traversal attacks.
 * 
 * @param string $file_path Relative path to file (e.g., 'uploads/proofs/abc123_1234567890.jpg')
 * @return bool True on success, false on failure
 */
function delete_file($file_path)
{
    try {
        // Validate path starts with 'uploads/' to prevent directory traversal
        if (strpos($file_path, 'uploads/') !== 0) {
            error_log("[Delete Error] Invalid file path (security check failed): $file_path", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        // Construct full path
        $full_path = __DIR__ . '/../' . $file_path;

        // Check file exists
        if (!file_exists($full_path)) {
            error_log("[Delete Error] File does not exist: $file_path", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        // Delete file
        if (!unlink($full_path)) {
            error_log("[Delete Error] Failed to delete file: $file_path", 3, __DIR__ . '/../logs/upload-errors.log');
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("[Delete Error] Exception: " . $e->getMessage(), 3, __DIR__ . '/../logs/upload-errors.log');
        return false;
    }
}

/**
 * Extract file extension from filename
 * 
 * Extracts and returns the file extension in lowercase without the dot.
 * 
 * @param string $filename Filename to extract extension from
 * @return string File extension in lowercase (without dot)
 */
function get_file_extension($filename)
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Get allowed file extensions for upload type
 * 
 * Returns an array of allowed extensions per upload type.
 * 
 * @param string $type Type of upload: 'proof', 'kyc', 'profile', 'logo'
 * @return array Array of allowed extensions (lowercase, without dots)
 */
function get_allowed_extensions($type)
{
    $extensions = [
        'proof'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
        'kyc'     => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
        'profile' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'logo'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
    ];

    return $extensions[$type] ?? [];
}

/**
 * Get MIME type of file
 * 
 * Determines MIME type using finfo_file() or mime_content_type()
 * as fallback.
 * 
 * @param string $file_path Path to file to check
 * @return string|false MIME type string or false on failure
 */
function get_mime_type($file_path)
{
    // Try finfo_file first (more reliable)
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            return $mime_type;
        }
    }

    // Fallback to mime_content_type (deprecated but may work)
    if (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file_path);
        if ($mime_type !== false) {
            return $mime_type;
        }
    }

    return false;
}


