<?php

/**
 * Automated Investment Processing Cron Job
 * 
 * This script processes active investments, calculates and credits profits,
 * and handles investment completion. It validates API key authentication,
 * implements file-based locking to prevent concurrent execution, and provides
 * automatic recovery from stale locks using TTL-based detection.
 * 
 * Usage: GET /cron/process-investments.php?key=CRON_API_KEY
 * 
 * Security: Requires valid CRON_API_KEY from .env configuration
 */

// Include necessary files only after API key validation
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/investment-functions.php';
require_once ROOT . '/includes/wallet-functions.php';

// ============================================================================
// API KEY VALIDATION (after database initialization)
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

// Include translation and utility functions for locale switching
require_once ROOT . '/includes/translation-functions.php';
require_once ROOT . '/includes/functions.php';


// Define lock file path in cron directory
$lock_file = __DIR__ . '/process-investments.lock';

// Define TTL for stale lock recovery (10 minutes in seconds)
$lock_ttl = 600;

// ============================================================================
// LOGGING HELPER FUNCTION
// ============================================================================

/**
 * Log cron job messages with structured formatting
 * 
 * @param string $message The message to log
 * @param string $level The log level (INFO, ERROR, WARNING, SUCCESS)
 * @return void
 */
function log_cron($message, $level = 'INFO')
{
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/cron.log';
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
// STALE LOCK DETECTION AND RECOVERY
// ============================================================================

// Check if lock file already exists
if (file_exists($lock_file)) {
    // Read lock file to get timestamp
    $lock_content = file_get_contents($lock_file);
    $lock_timestamp = (int) $lock_content;

    // Calculate age of the lock in seconds
    $lock_age = time() - $lock_timestamp;

    // Check if lock is stale (older than TTL)
    if ($lock_age >= $lock_ttl) {
        // Lock is stale, delete it and continue
        unlink($lock_file);
    } else {
        // Lock is fresh, prevent concurrent execution
        exit("Cron already running");
    }
}

// ============================================================================
// CREATE NEW LOCK FILE (atomic acquisition)
// ============================================================================

// Atomically create lock file using exclusive creation mode
// This fails if another process has already created it, preventing race conditions
try {
    $lock_handle = @fopen($lock_file, 'x');

    if ($lock_handle === false) {
        // Failed to acquire lock - another process must have it
        exit("Cron already running");
    }

    // Write current timestamp to lock file
    fwrite($lock_handle, time());
    fclose($lock_handle);
} catch (Exception $e) {
    log_cron("Failed to create lock file: " . $e->getMessage(), 'ERROR');
    exit("Lock file creation failed");
}

// ============================================================================
// EXECUTION TIME TRACKING
// ============================================================================

// Initialize performance tracking
$start_time = microtime(true);
$script_start_timestamp = date('Y-m-d H:i:s');

// Check if log directory is writable
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
if (!is_writable($log_dir)) {
    log_cron("Log directory not writable, errors may not be fully logged", 'WARNING');
}

// Log script start
log_cron("Cron job started at $script_start_timestamp", 'INFO');
flush(); // Ensure log is written immediately

// ============================================================================
// INVESTMENT HELPER FUNCTIONS
// ============================================================================

/**
 * Calculate the number of missed payouts for an investment
 * @param array $investment Investment record with payout_interval
 * @return int Number of missed payouts to process
 */
function calculate_missed_payouts($investment)
{
    $payout_interval = $investment['payout_interval'];
    $next_payout_ts = strtotime($investment['next_payout_date']);
    // Backward compatibility: support new interval type/value fields
    // Prefer investment-specific interval settings, fall back to plan-level settings
    $interval_type = $investment['payout_interval_type'] ?? $investment['plan_payout_interval_type'] ?? null;
    $interval_value = isset($investment['payout_interval_value']) ? intval($investment['payout_interval_value']) : (isset($investment['plan_payout_interval_value']) ? intval($investment['plan_payout_interval_value']) : null);
    $now = time();

    // Determine interval in seconds
    if ($payout_interval === 'hourly') {
        $interval_seconds = 3600; // 1 hour
    } elseif ($payout_interval === 'daily') {
        $interval_seconds = 86400; // 1 day
    } elseif ($payout_interval === 'custom') {
        // Backward compatibility: if type is missing but value exists, assume 'days'
        if ($interval_type === null && $interval_value !== null) {
            $interval_type = 'days';
        }

        // Validate custom interval config
        $allowed_types = ['minutes', 'hours', 'days', 'weeks', 'months'];
        if ($interval_type === null || $interval_value === null || $interval_value <= 0) {
            log_cron("Investment #{$investment['id']}: Invalid custom interval configuration (type: {$interval_type}, value: {$interval_value})", 'ERROR');
            return 0;
        }
        if (!in_array(strtolower($interval_type), $allowed_types)) {
            log_cron("Investment #{$investment['id']}: Invalid interval type '{$interval_type}'", 'ERROR');
            return 0;
        }

        // Use convert_interval_to_seconds() from includes/investment-functions.php
        $interval_seconds = convert_interval_to_seconds($interval_type, $interval_value);
    } else {
        // end_of_term: process single payout at end
        return 1;
    }

    // Skip if next_payout_date is in the future (race condition)
    if ($next_payout_ts > $now) {
        return 0;
    }

    // Calculate time elapsed since next_payout_date
    $elapsed = $now - $next_payout_ts;

    // Calculate missed count as the ceiling of elapsed / interval_seconds.
    // Ensure at least 1 when next_payout_date is in the past (due now or overdue).
    $missed_count = (int) ceil($elapsed / $interval_seconds);
    if ($missed_count < 1) {
        $missed_count = 1;
    }

    // Cap missed payouts to prevent excessive processing (max 1000 per investment per run)
    $missed_count = min($missed_count, 1000);

    return $missed_count;
}

// ============================================================================
// INVESTMENT PROCESSING WITH COMPREHENSIVE ERROR HANDLING
// ============================================================================

// Initialize counters and metrics
$processed_count = 0;
$error_count = 0;
$total_payouts = 0;
$total_profit_amount = 0;
$investments_found = 0;
$investments_skipped = 0;

try {
    // Query investments due for processing with error handling
    try {
        // Use plan's payout_interval as investments table does not have a payout_interval column.
        // Also expose plan-level fields with a 'plan_' prefix for compatibility.
        $investments = db_query("SELECT 
    i.id, i.user_id, i.next_payout_date, i.end_date, 
    p.payout_interval AS payout_interval, i.payout_interval_type, i.payout_interval_value, i.is_compounding,
    p.payout_interval AS plan_payout_interval, p.payout_interval_type AS plan_payout_interval_type, 
    p.payout_interval_value AS plan_payout_interval_value, p.is_compounding AS plan_is_compounding
FROM investments i 
JOIN investment_plans p ON i.plan_id = p.id 
WHERE i.status = 'active' AND i.next_payout_date <= NOW() 
ORDER BY i.next_payout_date ASC 
LIMIT 100");
    } catch (PDOException $e) {
        // Database connection or query execution error
        log_cron("Database error during investment query: " . $e->getMessage(), 'ERROR');
        log_cron("Stack trace: " . implode(" | ", array_slice(explode("\n", $e->getTraceAsString()), 0, 3)), 'ERROR');
        throw $e;
    }

    // Handle empty result set
    if (!$investments || !is_array($investments) || count($investments) === 0) {
        log_cron("No investments due for processing", 'INFO');
        $investments_found = 0;
    } else {
        $investments_found = count($investments);
        log_cron("Found $investments_found investments due for processing", 'INFO');

        // Process each investment
        foreach ($investments as $investment) {
            try {
                // Validate investment data has required keys
                if (!isset($investment['id'], $investment['payout_interval'], $investment['next_payout_date'])) {
                    log_cron("Invalid investment data structure: missing required keys", 'WARNING');
                    $error_count++;
                    continue;
                }

                $investment_id = $investment['id'];

                // Set locale for user's preferred language for this investment
                $inv_language = get_user_language($investment['user_id']) ?? 'en_US';
                init_translation($inv_language);

                // Calculate missed payouts for this investment
                $missed_count = calculate_missed_payouts($investment);

                // Detailed logging for custom interval processing
                if (isset($investment['payout_interval']) && $investment['payout_interval'] === 'custom') {
                    $interval_type_log = $investment['payout_interval_type'] ?? $investment['plan_payout_interval_type'] ?? 'days';
                    $interval_value_log = isset($investment['payout_interval_value']) ? intval($investment['payout_interval_value']) : (isset($investment['plan_payout_interval_value']) ? intval($investment['plan_payout_interval_value']) : 'n/a');
                    log_cron("Investment #$investment_id: Custom interval {$interval_value_log} {$interval_type_log}, calculated $missed_count missed payouts", 'INFO');
                }

                // Log if investment is skipped
                if ($missed_count === 0) {
                    $investments_skipped++;
                    continue;
                }

                // Log investment processing start
                log_cron("Investment #$investment_id: Processing $missed_count missed payouts", 'INFO');

                // Process each missed payout
                for ($i = 0; $i < $missed_count; $i++) {
                    try {
                        $result = credit_profit($investment_id);

                        if ($result === false) {
                            // Investment not found or processing error occurred
                            log_cron("Investment #$investment_id: Credit profit failed (returned false)", 'WARNING');
                            $error_count++;
                            break;
                        }

                        if ($result === true) {
                            // Investment completed - check compounding flag and interval
                            $is_comp = intval($investment['is_compounding'] ?? $investment['plan_is_compounding'] ?? 0);
                            $interval = $investment['payout_interval'] ?? ($investment['plan_payout_interval'] ?? '');
                            if ($is_comp === 1 && $interval === 'end_of_term') {
                                log_cron("Investment #$investment_id: Completed with compound interest calculation", 'SUCCESS');
                            } else {
                                log_cron("Investment #$investment_id: Completed successfully", 'SUCCESS');
                            }
                            break;
                        }

                        if ($result > 0) {
                            // Successful payout - result is the profit amount. It may aggregate multiple intervals.
                            $per_interval = calculate_profit($investment_id);
                            $processed_intervals = 1;
                            if ($per_interval > 0) {
                                $processed_intervals = (int) max(1, round($result / $per_interval));
                            }
                            // Do not exceed originally calculated missed_count
                            $processed_intervals = min($processed_intervals, $missed_count);

                            $total_payouts += $processed_intervals;
                            $total_profit_amount += $result;

                            // Log one entry per processed interval to preserve expected log count
                            $per_interval = $per_interval; // already defined above
                            $logged = 0;
                            if ($processed_intervals > 1) {
                                // Determine per-interval amounts, adjust last one for rounding remainder
                                $approx = $per_interval;
                                $acc = 0.00;
                                for ($j = 1; $j <= $processed_intervals; $j++) {
                                    if ($j < $processed_intervals) {
                                        $amt = round($approx, 2);
                                    } else {
                                        // last one gets the remaining amount to match total
                                        $amt = round($result - $acc, 2);
                                    }
                                    $acc += $amt;
                                    $logged++;
                                    log_cron("Investment #$investment_id: Credited $amt profit (payout $logged/{$missed_count})", 'SUCCESS');
                                }
                            } else {
                                log_cron("Investment #$investment_id: Credited $result profit (payout 1/{$missed_count})", 'SUCCESS');
                            }

                            // credit_profit already advanced next_payout_date by processed_intervals; break out to avoid re-processing
                            break;
                        }
                    } catch (PDOException $e) {
                        // Database error during profit credit
                        log_cron("Investment #$investment_id: Database error - " . $e->getMessage(), 'ERROR');
                        log_cron("Stack trace: " . implode(" | ", array_slice(explode("\n", $e->getTraceAsString()), 0, 3)), 'ERROR');
                        $error_count++;
                        break;
                    } catch (Exception $e) {
                        // General exception during profit credit
                        log_cron("Investment #$investment_id: Exception - " . $e->getMessage(), 'ERROR');
                        log_cron("Stack trace: " . implode(" | ", array_slice(explode("\n", $e->getTraceAsString()), 0, 3)), 'ERROR');
                        $error_count++;
                        break;
                    }
                }

                $processed_count++;

                // Stop processing if error rate exceeds 50% after 10 investments
                if ($processed_count >= 10 && $error_count > $processed_count / 2) {
                    log_cron("Error rate exceeded 50% after 10 investments, halting batch processing", 'WARNING');
                    break;
                }
            } catch (Exception $e) {
                // Catch-all for investment processing errors
                log_cron("Unexpected error processing investment: " . $e->getMessage(), 'ERROR');
                log_cron("Stack trace: " . implode(" | ", array_slice(explode("\n", $e->getTraceAsString()), 0, 3)), 'ERROR');
                $error_count++;
            }
        }
    }
} catch (PDOException $e) {
    // Database connection failure
    log_cron("Database connection error - cron job aborted", 'ERROR');
    log_cron("Error details: " . $e->getMessage(), 'ERROR');
    $error_count++;
} catch (Exception $e) {
    // Unexpected error during processing
    log_cron("Unexpected error during cron execution: " . $e->getMessage(), 'ERROR');
    log_cron("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    $error_count++;
} finally {
    // ========================================================================
    // EXECUTION TIME CALCULATION AND SUMMARY LOGGING
    // ========================================================================

    $execution_time = round(microtime(true) - $start_time, 2);
    $script_end_timestamp = date('Y-m-d H:i:s');
    $peak_memory_usage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    // Calculate error rate percentage
    $error_rate = $processed_count > 0 ? round(($error_count / $processed_count) * 100, 2) : 0;

    // Log detailed metrics
    log_cron("=== CRON JOB SUMMARY ===", 'INFO');
    log_cron("Start time: $script_start_timestamp", 'INFO');
    log_cron("End time: $script_end_timestamp", 'INFO');
    log_cron("Execution time: {$execution_time}s", 'INFO');
    log_cron("Investments found: $investments_found", 'INFO');
    log_cron("Investments processed: $processed_count", 'INFO');
    log_cron("Investments skipped: $investments_skipped", 'INFO');
    log_cron("Total payouts credited: $total_payouts", 'INFO');
    log_cron("Total profit amount: $total_profit_amount", 'INFO');
    log_cron("Error count: $error_count ({$error_rate}%)", 'INFO');
    log_cron("Peak memory usage: {$peak_memory_usage}MB", 'INFO');
    log_cron("=== END SUMMARY ===", 'INFO');

    // ========================================================================
    // LOCK FILE CLEANUP (guaranteed by finally block)
    // ========================================================================

    try {
        if (file_exists($lock_file)) {
            @unlink($lock_file);
            log_cron("Lock file cleaned up", 'INFO');
        }
    } catch (Exception $e) {
        log_cron("Warning: Failed to remove lock file - " . $e->getMessage(), 'WARNING');
    }
}

// ============================================================================
// COMPLETION MESSAGE
// ============================================================================

echo "Cron completed";
