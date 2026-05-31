<?php

/**
 * Log File Cleanup Cron Job
 * 
 * This script periodically scans the logs directory and truncates log files
 * that exceed the configured maximum size threshold. It validates API key
 * authentication and provides detailed logging of cleanup operations.
 * 
 * Usage: GET /cron/cleanup-logs.php?key=CRON_API_KEY
 * 
 * Security: Requires valid CRON_API_KEY from .env configuration
 */

// Include necessary files for bootstrap and database initialization
require_once __DIR__ . '/../includes/bootstrap.php';
require_once INCLUDES_PATH . '/env.php';

// ============================================================================
// API KEY VALIDATION
// ============================================================================

// Extract API key from query parameter
$provided_key = isset($_GET['key']) ? $_GET['key'] : null;

// Load expected key from environment
$expected_key = $_ENV['CRON_API_KEY'] ?? null;

// Validate API key using strict comparison
if ($provided_key !== $expected_key || !$expected_key) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// ============================================================================
// LOGGING HELPER FUNCTION
// ============================================================================

/**
 * Log cleanup job messages with structured formatting
 * 
 * @param string $message The message to log
 * @param string $level The log level (INFO, ERROR, WARNING, SUCCESS)
 * @return void
 */
function log_cleanup($message, $level = 'INFO')
{
    $log_dir = ROOT . '/logs';
    $log_file = $log_dir . '/cleanup.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message";

    // Ensure log directory exists
    if (!is_dir($log_dir)) {
        if (!@mkdir($log_dir, 0755, true)) {
            echo "WARNING: Failed to create log directory: $log_dir\n";
            return;
        }
    }

    // Create log file if it doesn't exist (touch)
    if (!file_exists($log_file)) {
        if (!@touch($log_file)) {
            echo "WARNING: Failed to create log file: $log_file\n";
            return;
        }
    }

    // Check if log file is writable
    if (!is_writable($log_file)) {
        echo "WARNING: Log file is not writable: $log_file\n";
        return;
    }

    // Attempt to write to log file and detect write failures
    $result = error_log($log_entry . "\n", 3, $log_file);

    if ($result === false) {
        echo "WARNING: Failed to write to log file: $log_file\n";
    }
}

// ============================================================================
// EXECUTION TIME TRACKING
// ============================================================================

// Initialize performance tracking
$start_time = microtime(true);
$script_start_timestamp = date('Y-m-d H:i:s');

log_cleanup("=== LOG CLEANUP JOB STARTED ===", 'INFO');

// ============================================================================
// READ THRESHOLD FROM ENVIRONMENT
// ============================================================================

// Read LOG_MAX_SIZE from environment variable (in bytes)
$threshold = isset($_ENV['LOG_MAX_SIZE']) ? (int) $_ENV['LOG_MAX_SIZE'] : 0;

// Fall back to 10 MB (10485760 bytes) if not configured or zero
if ($threshold <= 0) {
    $threshold = 10485760; // 10 MB default
    log_cleanup("LOG_MAX_SIZE not configured, using default threshold: 10485760 bytes (10 MB)", 'WARNING');
}

log_cleanup("Using threshold: $threshold bytes", 'INFO');

// ============================================================================
// SCAN LOGS DIRECTORY AND PROCESS FILES
// ============================================================================

// Collect all log files
$log_files = glob(ROOT . '/logs/*.log');

// Initialize counters
$files_scanned = 0;
$files_cleared = 0;
$bytes_freed = 0;

if (!$log_files) {
    log_cleanup("No log files found in logs directory", 'INFO');
} else {
    log_cleanup("Found " . count($log_files) . " log files", 'INFO');

    // Process each log file
    foreach ($log_files as $file) {
        $basename = basename($file);

        // Skip cleanup.log itself to avoid self-referential truncation
        if ($basename === 'cleanup.log') {
            log_cleanup("Skipping {$basename}: cleanup log excluded from rotation", 'INFO');
            continue;
        }

        $files_scanned++;

        // Get file size
        $file_size = @filesize($file);

        // Handle filesize() failure
        if ($file_size === false) {
            log_cleanup("WARNING: Could not determine size of {$basename}, skipping", 'WARNING');
            continue;
        }

        // Check if file exceeds threshold
        if ($file_size > $threshold) {
            // Open file in write mode (which truncates it to zero bytes)
            $handle = @fopen($file, 'w');

            if ($handle === false) {
                log_cleanup("ERROR: Could not open {$basename} for truncation", 'ERROR');
                continue;
            }

            fclose($handle);

            // Track the freed bytes
            $bytes_freed += $file_size;
            $files_cleared++;

            // Log success
            $size_mb = round($file_size / 1024 / 1024, 2);
            $threshold_mb = round($threshold / 1024 / 1024, 2);
            log_cleanup("Cleared {$basename}: was {$file_size} bytes ({$size_mb}MB) - threshold: {$threshold_mb}MB", 'SUCCESS');
        } else {
            // File is within threshold
            $size_kb = round($file_size / 1024, 2);
            $threshold_mb = round($threshold / 1024 / 1024, 2);
            log_cleanup("Skipped {$basename}: {$file_size} bytes ({$size_kb}KB) - under {$threshold_mb}MB threshold", 'INFO');
        }
    }
}

// ============================================================================
// SUMMARY AND COMPLETION
// ============================================================================

// Calculate execution time
$end_time = microtime(true);
$script_end_timestamp = date('Y-m-d H:i:s');
$execution_time = round($end_time - $start_time, 2);

// Format bytes freed
$bytes_freed_mb = round($bytes_freed / 1024 / 1024, 2);

// Log summary
// log_cleanup("=== CLEANUP SUMMARY ===", 'INFO');
// log_cleanup("Start time: $script_start_timestamp", 'INFO');
// log_cleanup("End time: $script_end_timestamp", 'INFO');
// log_cleanup("Execution time: {$execution_time}s", 'INFO');
// log_cleanup("Files scanned: $files_scanned", 'INFO');
// log_cleanup("Files cleared: $files_cleared", 'INFO');
// log_cleanup("Total bytes freed: $bytes_freed ({$bytes_freed_mb}MB)", 'INFO');
// log_cleanup("=== END SUMMARY ===", 'INFO');

// ============================================================================
// COMPLETION MESSAGE
// ============================================================================

echo "Cleanup completed";
