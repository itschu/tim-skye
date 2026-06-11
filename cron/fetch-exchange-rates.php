<?php

/**
 * Exchange Rate Fetching Cron Job
 * 
 * This script fetches current exchange rates from external APIs and caches them
 * in a PHP return array format. It uses a primary API (ExchangeRate-API) with
 * automatic fallback to a secondary API (convertz.app) on failure. Implements
 * file-based locking to prevent concurrent execution and provides automatic
 * recovery from stale locks using TTL-based detection.
 * 
 * Usage: GET /cron/fetch-exchange-rates.php?key=CRON_API_KEY
 * 
 * Security: Requires valid CRON_API_KEY from .env configuration
 */

// Include necessary files only after API key validation
require_once __DIR__ . '/../includes/bootstrap.php';

require_once ROOT . '/includes/db.php';

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
$lock_file = __DIR__ . '/fetch-exchange-rates.lock';

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
log_cron("fetch-exchange-rates: started", 'INFO');
flush(); // Ensure log is written immediately

// ============================================================================
// HTTP FETCH HELPER FUNCTION
// ============================================================================

/**
 * Fetch URL content using cURL or file_get_contents fallback
 * 
 * @param string $url The URL to fetch
 * @param int $timeout Connection timeout in seconds
 * @return string|false The response body on success, false on failure
 */
function fetch_url($url, $timeout = 10)
{
    // Try cURL first if available
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $response;
    }

    // Fallback to file_get_contents with stream context
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    return $response !== false ? $response : false;
}

// ============================================================================
// EXCHANGE RATE FETCHING WITH COMPREHENSIVE ERROR HANDLING
// ============================================================================

// Initialize variables
$rates = [];
$source = null;
$rates_count = 0;

try {
    // Read base currency from settings
    $base = strtoupper(get_setting('currency', 'USD'));
    log_cron("Base currency: $base", 'INFO');

    // ========================================================================
    // PRIMARY SOURCE: ExchangeRate-API
    // ========================================================================

    log_cron("Attempting primary source: exchangerate-api.com", 'INFO');

    $api_key = $_ENV['EXCHANGERATE_API_KEY'] ?? '';
    $primary_url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$base}";

    $primary_response = fetch_url($primary_url);

    if ($primary_response !== false) {
        $data = json_decode($primary_response, true);

        // Validate response structure
        if (
            $data !== null && isset($data['result']) && $data['result'] === 'success' &&
            isset($data['conversion_rates']) && is_array($data['conversion_rates'])
        ) {

            $rates = $data['conversion_rates'];
            $source = 'exchangerate-api';
            $rates_count = count($rates);
            log_cron("[SUCCESS] ExchangeRate-API returned $rates_count currencies", 'SUCCESS');
        } else {
            log_cron("Primary source returned invalid response structure", 'WARNING');
        }
    } else {
        log_cron("Primary source fetch failed (timeout or connection error)", 'WARNING');
    }

    // ========================================================================
    // FALLBACK SOURCE: convertz.app (only if primary failed)
    // ========================================================================

    if (empty($rates)) {
        log_cron("Primary failed. Attempting fallback: convertz.app", 'WARNING');

        $fallback_response = fetch_url('https://api.convertz.app/api/currency');

        if ($fallback_response !== false) {
            // Extract the raw decimal strings from the JSON body so we never
            // lose precision through json_decode float parsing.
            $raw_rates = [];
            if (preg_match('/"rates"\s*:\s*\{([^}]+)\}/s', $fallback_response, $m)) {
                $rates_block = $m[1];
                preg_match_all('/"([A-Z]{3})"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $rates_block, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $raw_rates[$match[1]] = $match[2];
                }
            }

            if (empty($raw_rates)) {
                log_cron("Fallback source returned unparseable rates block", 'WARNING');
            } else {
                // Handle base currency conversion if not USD
                if ($base !== 'USD') {
                    if (!isset($raw_rates[$base]) || bccomp($raw_rates[$base], '0', 20) <= 0) {
                        log_cron("Fallback source does not have rate for base currency: $base", 'ERROR');
                    } else {
                        // Convert all rates from USD to the selected base currency
                        // using exact BCMath string division.
                        $converted = [];
                        $base_rate_str = $raw_rates[$base];

                        foreach ($raw_rates as $code => $usd_rate_str) {
                            $converted[$code] = bcdiv($usd_rate_str, $base_rate_str, 20);
                        }

                        $rates = $converted;
                        $source = 'convertz.app';
                        $rates_count = count($rates);
                        log_cron("[SUCCESS] convertz.app fallback succeeded with $rates_count currencies (converted to $base)", 'SUCCESS');
                    }
                } else {
                    // Base is USD, use raw rates directly
                    $rates = $raw_rates;
                    $source = 'convertz.app';
                    $rates_count = count($rates);
                    log_cron("[SUCCESS] convertz.app fallback succeeded with $rates_count currencies", 'SUCCESS');
                }
            }
        } else {
            log_cron("Fallback source fetch failed (timeout or connection error)", 'WARNING');
        }
    }

    // ========================================================================
    // PRIMARY SOURCE: ExchangeRate-API
    // ========================================================================

    log_cron("Attempting primary source: exchangerate-api.com", 'INFO');

    $api_key = $_ENV['EXCHANGERATE_API_KEY'] ?? '';
    $primary_url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$base}";

    $primary_response = fetch_url($primary_url);

    if ($primary_response !== false) {
        $data = json_decode($primary_response, true);

        // Validate response structure
        if (
            $data !== null && isset($data['result']) && $data['result'] === 'success' &&
            isset($data['conversion_rates']) && is_array($data['conversion_rates'])
        ) {

            $rates = $data['conversion_rates'];
            $source = 'exchangerate-api';
            $rates_count = count($rates);
            log_cron("[SUCCESS] ExchangeRate-API returned $rates_count currencies", 'SUCCESS');
        } else {
            log_cron("Primary source returned invalid response structure", 'WARNING');
        }
    } else {
        log_cron("Primary source fetch failed (timeout or connection error)", 'WARNING');
    }

    // ========================================================================
    // FALLBACK SOURCE: convertz.app (only if primary failed)
    // ========================================================================

    if (empty($rates)) {
        log_cron("Primary failed. Attempting fallback: convertz.app", 'WARNING');

        $fallback_response = fetch_url('https://api.convertz.app/api/currency');

        if ($fallback_response !== false) {
            $data = json_decode($fallback_response, true);

            // Validate response structure
            if ($data !== null && isset($data['rates']) && is_array($data['rates'])) {

                // Handle base currency conversion if not USD
                if ($base !== 'USD') {
                    // Check if base currency exists in the rates
                    if (!isset($data['rates'][$base]) || $data['rates'][$base] <= 0) {
                        log_cron("Fallback source does not have rate for base currency: $base", 'ERROR');
                    } else {
                        // Convert all rates from USD to the selected base currency.
                        // Use BCMath string division so the computed rates stay exact
                        // instead of inheriting PHP float rounding errors.
                        $converted = [];
                        $base_rate_str = (string) $data['rates'][$base];

                        foreach ($data['rates'] as $code => $usd_rate) {
                            $usd_rate_str = (string) $usd_rate;
                            $converted[$code] = bcdiv($usd_rate_str, $base_rate_str, 20);
                        }

                        $rates = $converted;
                        $source = 'convertz.app';
                        $rates_count = count($rates);
                        log_cron("[SUCCESS] convertz.app fallback succeeded with $rates_count currencies (converted to $base)", 'SUCCESS');
                    }
                } else {
                    // Base is USD, use rates directly
                    $rates = $data['rates'];
                    $source = 'convertz.app';
                    $rates_count = count($rates);
                    log_cron("[SUCCESS] convertz.app fallback succeeded with $rates_count currencies", 'SUCCESS');
                }
            } else {
                log_cron("Fallback source returned invalid response structure", 'WARNING');
            }
        } else {
            log_cron("Fallback source fetch failed (timeout or connection error)", 'WARNING');
        }
    }

    // ========================================================================
    // CACHE FILE WRITE (only if both sources succeeded)
    // ========================================================================

    if (!empty($rates) && $source !== null) {
        $cache_file = ROOT . '/cache/exchange-rates.php';
        $cache_temp_file = $cache_file . '.tmp';

        // Build PHP return array with rates stored as quoted strings.
        // Using strings prevents PHP from parsing them as binary floats when
        // the cache file is included, preserving exact decimal precision for
        // MySQL DECIMAL arithmetic and JavaScript display.
        $rates_parts = [];
        foreach ($rates as $code => $val) {
            $str = is_float($val) || is_int($val)
                ? number_format((float) $val, 30, '.', '')
                : (string) $val;
            // Store as a quoted string literal so PHP keeps it as a string
            $rates_parts[] = var_export($code, true) . ' => ' . var_export($str, true);
        }
        $rates_export = '[' . implode(', ', $rates_parts) . ']';
        $cache_content = "<?php return ['base' => '{$base}', 'rates' => {$rates_export}, 'updated_at' => " . time() . ", 'source' => '{$source}'];";

        // Write to temporary file first (atomic write pattern)
        if (@file_put_contents($cache_temp_file, $cache_content) !== false) {
            // Atomic rename to final location
            if (@rename($cache_temp_file, $cache_file)) {
                log_cron("[SUCCESS] Cache written to cache/exchange-rates.php (source: $source, base: $base, $rates_count rates)", 'SUCCESS');
            } else {
                log_cron("Failed to rename cache temporary file to final location", 'ERROR');
                if (file_exists($cache_temp_file)) {
                    @unlink($cache_temp_file);
                }
            }
        } else {
            log_cron("Failed to write cache temporary file", 'ERROR');
        }
    } else {
        if (empty($rates)) {
            log_cron("Both APIs failed. Cache left untouched.", 'WARNING');
        }
    }
} catch (Exception $e) {
    // Unexpected error during processing
    log_cron("Unexpected error: " . $e->getMessage(), 'ERROR');
    log_cron("Stack trace: " . $e->getTraceAsString(), 'ERROR');
} finally {
    // ========================================================================
    // EXECUTION TIME CALCULATION AND SUMMARY LOGGING
    // ========================================================================

    $execution_time = round(microtime(true) - $start_time, 2);
    $script_end_timestamp = date('Y-m-d H:i:s');
    $peak_memory_usage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    // Log detailed metrics
    log_cron("=== CRON JOB SUMMARY ===", 'INFO');
    log_cron("Start time: $script_start_timestamp", 'INFO');
    log_cron("End time: $script_end_timestamp", 'INFO');
    log_cron("Execution time: {$execution_time}s", 'INFO');
    log_cron("Source used: " . ($source ?? 'none'), 'INFO');
    log_cron("Rates cached: $rates_count", 'INFO');
    log_cron("Peak memory usage: {$peak_memory_usage}MB", 'INFO');
    log_cron("=== END SUMMARY ===", 'INFO');

    // ========================================================================
    // LOCK FILE CLEANUP (guaranteed by finally block)
    // ========================================================================

    try {
        if (file_exists($lock_file)) {
            @unlink($lock_file);
            log_cron("Lock file removed", 'INFO');
        }
    } catch (Exception $e) {
        log_cron("Warning: Failed to remove lock file - " . $e->getMessage(), 'WARNING');
    }
}

// ============================================================================
// COMPLETION MESSAGE
// ============================================================================

echo "Cron completed";
