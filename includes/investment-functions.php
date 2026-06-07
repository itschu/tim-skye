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
 * Investment management functions
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/wallet-functions.php';

/**
 * Convert interval type and value to seconds
 * @param string $type Interval type: 'minutes', 'hours', 'days', 'weeks', 'months'
 * @param int $value Interval value (e.g., 2 for "every 2 days")
 * @return int Number of seconds
 */
function convert_interval_to_seconds($type, $value)
{
    $v = max(0, intval($value));
    switch (strtolower($type)) {
        case 'minutes':
            return $v * 60;
        case 'hours':
            return $v * 3600;
        case 'days':
            return $v * 86400;
        case 'weeks':
            return $v * 604800; // 7 days
        case 'months':
            return $v * 2592000; // approximate 30 days
        default:
            // default to days for backward compatibility
            return $v * 86400;
    }
}

/**
 * Create investment for a user
 * @param int $user_id
 * @param int $plan_id
 * @param float $amount
 * @return int|false investment id
 *
 * Supports payout intervals: 'minutes','hours','days','weeks','months',
 * with plan columns `payout_interval_type` and `payout_interval_value`.
 * Backward compatibility: if `payout_interval_type` is NULL and a value
 * exists, it defaults to 'days'. Uses `convert_interval_to_seconds()` for
 * custom interval scheduling.
 */
function create_investment($user_id, $plan_id, $amount)
{
    $plan = db_query("SELECT * FROM investment_plans WHERE id = ? AND status = 'active'", [$plan_id]);
    if (!$plan || count($plan) === 0) {
        return false;
    }
    $plan = $plan[0];

    // Validate country eligibility
    $user_country = db_query("SELECT country FROM users WHERE id = ?", [$user_id])[0]['country'] ?? null;
    if (!empty($plan['country']) && $plan['country'] !== $user_country) {
        return false;
    }

    $min = (float)$plan['min_amount'];
    $max = (float)$plan['max_amount'];
    if ($amount < $min || ($max > 0 && $amount > $max)) {
        return false;
    }
    if (!has_sufficient_balance($user_id, $amount)) {
        return false;
    }

    $roi = (float)$plan['roi_percentage'];
    $duration_days = (int)$plan['duration_days'];
    $payout_interval = $plan['payout_interval'];
    $payout_interval_type = $plan['payout_interval_type'] ?? null;
    $payout_interval_value = isset($plan['payout_interval_value']) ? intval($plan['payout_interval_value']) : null;
    $waiting_period_value = isset($plan['waiting_period_value']) ? intval($plan['waiting_period_value']) : 0;
    $waiting_period_unit = $plan['waiting_period_unit'] ?? 'days';

    // Backward compatibility: if type is missing but a value exists, assume days
    if ($payout_interval_type === null && $payout_interval_value !== null) {
        $payout_interval_type = 'days';
    }

    // Validation for custom interval: require positive value; type may default to 'days'
    if ($payout_interval === 'custom') {
        if (!isset($payout_interval_value) || intval($payout_interval_value) <= 0) {
            error_log("[" . date('c') . "] Invalid or missing payout_interval_value for custom interval on plan $plan_id" . "\n", 3, __DIR__ . '/../logs/db-errors.log');
            return false;
        }
        if (empty($payout_interval_type)) {
            // If type missing but value present, default to days (already handled), but ensure it's set
            $payout_interval_type = 'days';
        }
    }

    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    if ($payout_interval === 'hourly') {
        $next_payout_date = date('Y-m-d H:i:s', strtotime('+1 hour'));
    } elseif ($payout_interval === 'daily') {
        $next_payout_date = date('Y-m-d H:i:s', strtotime('+1 day'));
    } elseif ($payout_interval === 'custom') {
        // For custom intervals use type/value (default to days)
        $interval_type = $payout_interval_type ?? 'days';
        $interval_value = max(1, intval($payout_interval_value));
        if ($interval_value > 0) {
            $seconds = convert_interval_to_seconds($interval_type, $interval_value);
            $next_payout_date = date('Y-m-d H:i:s', strtotime($start_date) + $seconds);
        } else {
            $next_payout_date = $end_date;
        }
    } else {
        $next_payout_date = $end_date;
    }

    // Apply waiting period offset
    $waiting_seconds = 0;
    if ($waiting_period_value > 0) {
        $waiting_seconds = convert_interval_to_seconds($waiting_period_unit, $waiting_period_value);
        $end_date = date('Y-m-d H:i:s', strtotime($end_date) + $waiting_seconds);
        $next_payout_date = date('Y-m-d H:i:s', strtotime($next_payout_date) + $waiting_seconds);
    }

    $db = db_connect();
    try {
        $db->beginTransaction();
        $debit_tx = debit_wallet($user_id, $amount, 'investment', "Investment in plan: $plan_id", $db);
        if (!$debit_tx) {
            throw new Exception('Failed to debit wallet');
        }
        $data = [
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'amount' => number_format((float)$amount, 2, '.', ''),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'next_payout_date' => $next_payout_date,
            'payout_interval_type' => $payout_interval_type,
            'payout_interval_value' => $payout_interval === 'custom' ? $payout_interval_value : null,
            'waiting_period_value' => $waiting_period_value,
            'waiting_period_unit' => $waiting_period_unit,
            'status' => 'active',
            'total_profit_earned' => 0.00,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $inv_id = db_insert('investments', $data);
        $db->commit();
        return $inv_id;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Create investment error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Calculate profit for single payout
 *
 * ROI percentage represents profit earned PER PAYOUT INTERVAL:
 * - Daily interval with 10% ROI: earns 10% of principal per day
 * - Custom 2-day interval with 10% ROI: earns 10% of principal every 2 days
 * - Hourly interval with 5% ROI: earns 5% of principal per hour
 *
 * Formula: profit_per_payout = amount × (roi_percentage / 100)
 *
 * @param int $investment_id
 * @return float Profit amount for a single payout interval
 */
function calculate_profit($investment_id)
{
    // Fetch investment and plan; include interval type/value for future use
    $row = db_query("SELECT i.*, p.roi_percentage, p.duration_days, p.payout_interval, p.payout_interval_type AS plan_payout_interval_type, p.payout_interval_value AS plan_payout_interval_value FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?", [$investment_id]);
    if (!$row || count($row) === 0) {
        return 0.00;
    }
    $inv = $row[0];
    $amount = (float)$inv['amount'];
    $roi = (float)$inv['roi_percentage'];

    // Treat ROI as the percentage earned per payout interval. Do NOT divide by duration or interval count.
    $per = $amount * ($roi / 100.0);

    return round($per, 2);
}

/**
 * Credit profit to user and update investment
 * @param int $investment_id
 * @return float|false|true profit amount, false on error, true when completed
 *
 * Uses `payout_interval_type` and `payout_interval_value` for custom
 * intervals and `convert_interval_to_seconds()` to compute next payout
 * timestamps. Backward compatible with older `payout_interval_days` usage.
 */
function credit_profit($investment_id)
{
    $row = db_query("SELECT i.id, i.user_id, i.plan_id, i.amount, i.start_date, i.end_date, i.next_payout_date, i.payout_interval_type, i.payout_interval_value, i.total_profit_earned, i.status, p.payout_interval, p.payout_interval_type AS plan_payout_interval_type, p.payout_interval_value AS plan_payout_interval_value, p.roi_percentage, p.duration_days FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?", [$investment_id]);
    if (!$row || count($row) === 0) {
        return false;
    }
    $inv = $row[0];

    // Defensive validation for custom interval configuration
    if (isset($inv['payout_interval']) && $inv['payout_interval'] === 'custom') {
        $ival = $inv['payout_interval_value'] ?? null;
        $itype = $inv['payout_interval_type'] ?? null;
        // Backward compatibility: if type missing but value present, assume days
        if ($itype === null && $ival !== null) {
            $itype = 'days';
        }
        if ($itype === null || !isset($ival) || intval($ival) <= 0) {
            error_log("[" . date('c') . "] Invalid custom interval data for investment #$investment_id" . "\n", 3, __DIR__ . '/../logs/db-errors.log');
            return false;
        }
    }
    // Only process active investments
    if ($inv['status'] !== 'active') {
        return false;
    }

    // Debug: log key fields for end_of_term compounding analysis
    if (isset($inv['payout_interval']) && $inv['payout_interval'] === 'end_of_term') {
        $dbg = sprintf("[%s] [DEBUG] credit_final: inv_id=%d, is_comp=%s, plan_is_comp=%s, roi=%s, duration=%s, period_type=%s, period_value=%s", date('Y-m-d H:i:s'), $investment_id, var_export($inv['is_compounding'] ?? 'n/a', true), var_export($inv['plan_is_compounding'] ?? 'n/a', true), ($inv['roi_percentage'] ?? 'n/a'), ($inv['duration_days'] ?? 'n/a'), ($inv['payout_interval_type'] ?? 'n/a'), ($inv['payout_interval_value'] ?? 'n/a'));
        $cron_log = __DIR__ . '/../logs/cron.log';
        if (is_writable(dirname($cron_log))) {
            @error_log($dbg . "\n", 3, $cron_log);
        } else {
            error_log($dbg);
        }
    }

    $user_id = $inv['user_id'];
    $now = date('Y-m-d H:i:s');
    $end_date = $inv['end_date'];
    $next_payout_date = $inv['next_payout_date'];

    // Check if investment term has ended
    if ($now >= $end_date) {
        // Term has ended - first credit any pending final profit, then complete
        return credit_final_profit_and_complete($investment_id, $inv);
    }

    // Check if payout date has arrived
    if ($now < $next_payout_date) {
        // Not yet time for payout
        return 0.00;
    }

    // Determine interval seconds for this investment
    if ($inv['payout_interval'] === 'hourly') {
        $interval_seconds = 3600;
    } elseif ($inv['payout_interval'] === 'daily') {
        $interval_seconds = 86400;
    } elseif ($inv['payout_interval'] === 'custom') {
        $interval_type = $inv['payout_interval_type'] ?? $inv['plan_payout_interval_type'] ?? 'days';
        $interval_value = intval($inv['payout_interval_value'] ?? $inv['plan_payout_interval_value'] ?? 0);
        $interval_value = max(1, $interval_value);
        $interval_seconds = convert_interval_to_seconds($interval_type, $interval_value);
    } else {
        // non-recurring (end_of_term) should not reach here due to earlier check
        $interval_seconds = max(1, intval($inv['duration_days']) * 86400);
    }

    $profit_per_interval = calculate_profit($investment_id);
    if ($profit_per_interval <= 0) {
        return 0.00;
    }

    // Compute how many intervals are due between stored next_payout_date and now
    $now_ts = time();
    $next_ts = strtotime($inv['next_payout_date']);
    if ($next_ts === false) {
        return false;
    }

    $due_count = 0;
    if ($now_ts >= $next_ts) {
        $elapsed = $now_ts - $next_ts;
        // Calculate missed count as the floor of elapsed / interval_seconds plus 1 for current interval
        $due_count = (int) floor($elapsed / max(1, $interval_seconds)) + 1;
        if ($due_count < 1) {
            $due_count = 1;
        }
    }

    // Prepare values needed to cap due_count safely
    $total_profit_earned = (float)$inv['total_profit_earned'];
    $per_interval = $profit_per_interval;

    // Compute an initial estimate for total_intervals so we can cap runaway processing
    if ($inv['payout_interval'] === 'hourly') {
        $total_intervals = (int) floor(($inv['duration_days'] * 86400) / 3600);
    } elseif ($inv['payout_interval'] === 'daily') {
        $total_intervals = (int) floor($inv['duration_days']);
    } elseif ($inv['payout_interval'] === 'custom') {
        $interval_type = $inv['payout_interval_type'] ?? $inv['plan_payout_interval_type'] ?? 'days';
        $interval_value = intval($inv['payout_interval_value'] ?? $inv['plan_payout_interval_value'] ?? 0);
        $interval_value = max(1, $interval_value);
        $interval_seconds = max(1, convert_interval_to_seconds($interval_type, $interval_value));
        $total_intervals = (int) floor(($inv['duration_days'] * 86400) / $interval_seconds);
    } else {
        $total_intervals = 1;
    }

    // Remaining intervals that can still be paid without exceeding plan total
    $already_paid_intervals = 0;
    if ($per_interval > 0.0) {
        $already_paid_intervals = (int) floor($total_profit_earned / $per_interval);
    }
    $remaining_intervals = max(0, $total_intervals - $already_paid_intervals);

    // Cap due_count to a safe maximum to avoid runaway processing (1000) and not exceed remaining intervals
    $max_due = min(1000, max(1, $remaining_intervals));
    if ($due_count > $max_due) {
        $due_count = max(1, $max_due);
    }

    // Compute total expected profit for the term to avoid over-crediting
    if ($inv['payout_interval'] === 'hourly') {
        $total_intervals = (int) floor(($inv['duration_days'] * 86400) / 3600);
    } elseif ($inv['payout_interval'] === 'daily') {
        $total_intervals = (int) floor($inv['duration_days']);
    } elseif ($inv['payout_interval'] === 'custom') {
        $total_intervals = (int) floor(($inv['duration_days'] * 86400) / max(1, $interval_seconds));
    } else {
        $total_intervals = 1;
    }
    $total_expected_profit = round($profit_per_interval * $total_intervals, 2);

    // Remaining profit that can still be credited
    $remaining = round(max(0, $total_expected_profit - (float)$inv['total_profit_earned']), 2);

    // Profit to credit now is profit_per_interval * due_count, but not exceeding remaining
    $profit_to_credit = min(round($profit_per_interval * $due_count, 2), $remaining);

    if ($profit_to_credit <= 0) {
        // Nothing to credit (either not due or already paid)
        return 0.00;
    }

    $db = db_connect();
    try {
        $db->beginTransaction();
        $credit_tx = credit_wallet($user_id, $profit_to_credit, 'profit', "Profit from investment #$investment_id", $db);
        if (!$credit_tx) {
            throw new Exception('Failed to credit profit');
        }

        $new_total = (float)$inv['total_profit_earned'] + $profit_to_credit;

        // Advance next_payout_date deterministically by the number of intervals processed
        $advanced_next_ts = $next_ts + ($due_count * max(1, $interval_seconds));
        // Ensure next payout is at least one interval from now to match scheduling expectations
        $min_next_ts = $now_ts + max(1, $interval_seconds);
        $chosen_next_ts = max($advanced_next_ts, $min_next_ts);
        $next = date('Y-m-d H:i:s', $chosen_next_ts);

        db_update('investments', ['total_profit_earned' => number_format($new_total, 2, '.', ''), 'next_payout_date' => $next], 'id = ?', [$investment_id]);
        $db->commit();

        // Send profit payout email notification (if enabled)
        $email_profit_payout_user = get_setting('email_profit_payout_user', 'yes');
        if ($email_profit_payout_user === 'yes' && $profit_to_credit > 0) {
            $user = db_query("SELECT id, name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
            if ($user) {
                require_once __DIR__ . '/email-functions.php';
                $site_url = get_site_url();
                $site_logo = get_setting('site_logo', '');
                $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../' . $site_logo))
                    ? $site_url . '/' . ltrim($site_logo, '/')
                    : $site_url . '/assets/images/logo.png';

                $email_vars = [
                    'site_name' => get_setting('site_name', 'Investment Platform'),
                    'site_logo' => $site_logo_url,
                    'logo_url' => $site_logo_url,
                    'site_url' => $site_url,
                    'user_name' => $user['name'],
                    'payout_amount' => number_format($profit_to_credit, 2),
                    'payout_date' => date('M d, Y'),
                    'investment_reference' => 'INV-' . $investment_id,
                    'currency' => get_setting('currency', 'USD'),
                    'investment_id' => $investment_id,
                    'current_year' => date('Y'),
                    'support_email' => get_setting('contact_email', 'support@example.com'),

                    'company_address' => get_setting('company_address', '')
                ];

                try {
                    send_template_email($user['email'], 'profit-payout', $email_vars, get_user_language($user['id']));
                } catch (Exception $e) {
                    error_log("[" . date('c') . "] Failed to send profit payout email: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/email-errors.log');
                }
            }
        }

        return $profit_to_credit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Credit profit error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Credit final profit and complete investment
 * Handles end-of-term and final period profit crediting before completion
 * @param int $investment_id
 * @param array $inv Investment data (pre-fetched)
 * @return true|false true on success, false on error
 */
function credit_final_profit_and_complete($investment_id, $inv = null)
{
    // If caller didn't provide full investment data, or required fields missing, fetch complete record
    $needs_fetch = false;
    if ($inv === null) {
        $needs_fetch = true;
    } else {
        // Ensure we have compounding and plan interval info; if not, re-fetch full record
        if (!isset($inv['is_compounding']) || !isset($inv['payout_interval_type'])) {
            $needs_fetch = true;
        }
    }

    if ($needs_fetch) {
        $row = db_query("SELECT 
    i.id, i.user_id, i.plan_id, i.amount, i.start_date, i.end_date, i.next_payout_date, i.payout_interval_type, i.payout_interval_value, i.is_compounding, i.total_profit_earned, i.status, 
    p.payout_interval AS payout_interval, p.payout_interval_type AS plan_payout_interval_type, p.payout_interval_value AS plan_payout_interval_value, p.is_compounding AS plan_is_compounding, p.name AS plan_name, p.roi_percentage, p.duration_days, p.capital_return 
FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?", [$investment_id]);
        if (!$row || count($row) === 0) {
            return false;
        }
        $inv = $row[0];
    }

    // Only process active investments
    if ($inv['status'] !== 'active') {
        return false;
    }

    $user_id = $inv['user_id'];
    $payout_interval = $inv['payout_interval'];
    $amount = (float)$inv['amount'];
    $roi = (float)$inv['roi_percentage'];
    $duration_days = (int)$inv['duration_days'];
    $total_profit_earned = (float)$inv['total_profit_earned'];

    // Debug trace to assist with intermittent compounding mismatches
    $cron_log = __DIR__ . '/../logs/cron.log';
    $traceMsg = sprintf("[%s] [DEBUG] credit_final called: inv=%d, plan=%s, amount=%.2f, roi=%s, duration=%d, already_earned=%.2f", date('Y-m-d H:i:s'), $investment_id, $inv['plan_id'] ?? 'n/a', $amount, var_export($inv['roi_percentage'] ?? 'n/a', true), $duration_days, $total_profit_earned);
    if (is_writable(dirname($cron_log))) {
        @error_log($traceMsg . "\n", 3, $cron_log);
    } else {
        error_log($traceMsg);
    }

    // `capital_return` may not be present if caller passed a partial $inv; default to enabled (1)
    $capital_return = isset($inv['capital_return']) ? (int)$inv['capital_return'] : 1;

    // Calculate the final profit to credit
    $profit_to_credit = 0.00;

    if ($payout_interval === 'end_of_term') {
        // For end_of_term: support compound interest when enabled
        // Determine compounding flag: prefer explicit investment flag when set to 1,
        // otherwise fall back to plan-level setting. Use strict checks so a
        // present investment column with value 0 does NOT mask a plan-level 1.
        if (isset($inv['is_compounding']) && intval($inv['is_compounding']) === 1) {
            $is_comp = 1;
        } elseif (isset($inv['plan_is_compounding']) && intval($inv['plan_is_compounding']) === 1) {
            $is_comp = 1;
        } else {
            $is_comp = 0;
        }
        if ($is_comp === 1) {
            // Determine compounding period type/value from investment or plan
            $period_type = $inv['payout_interval_type'] ?? $inv['plan_payout_interval_type'] ?? null;
            $period_value = isset($inv['payout_interval_value']) && intval($inv['payout_interval_value']) > 0 ? intval($inv['payout_interval_value']) : (isset($inv['plan_payout_interval_value']) ? intval($inv['plan_payout_interval_value']) : null);

            // Backward compatibility: if type missing but value present, default to days
            if ($period_type === null && $period_value !== null) {
                $period_type = 'days';
            }

            // If both period_type and period_value are missing, try to infer a reasonable period
            if ($period_type === null && $period_value === null) {
                if ($duration_days % 7 === 0 && $duration_days >= 7) {
                    // If duration is a multiple of 7 days, infer weekly periods
                    $period_type = 'weeks';
                    $period_value = 1;
                } elseif ($duration_days % 30 === 0 && $duration_days >= 30) {
                    // If duration is a multiple of ~30 days, infer monthly periods
                    $period_type = 'months';
                    $period_value = 1;
                } else {
                    // Default to daily compounding
                    $period_type = 'days';
                    $period_value = 1;
                }
            }

            // Compute number of compounding periods (n)
            $interval_seconds = convert_interval_to_seconds($period_type, $period_value);
            if ($interval_seconds <= 0) {
                $n = max(1, $duration_days);
            } else {
                $total_seconds = $duration_days * 86400;
                $n = (int) ceil($total_seconds / $interval_seconds);
            }

            // Compute compound amount correctly when ROI is specified for the full term.
            // Derive the per-period rate from the term ROI to avoid exponential overpayment.
            $P = $amount;
            $term_roi_decimal = $roi / 100.0; // ROI for the entire term as decimal
            // Guard against negative or zero periods
            if ($n <= 0) {
                $n = 1;
            }


            // The term ROI stored in the plan represents the total ROI for the
            // investment term. The total expected profit therefore equals
            // principal × term_roi. Compute that directly (rounded to cents)
            // to avoid floating-point root/pow artifacts that can cause test
            // mismatches across different interval inferences.
            $total_expected_profit = round($P * $term_roi_decimal, 2);

            // Logging compound details to cron log for traceability
            $cron_log = __DIR__ . '/../logs/cron.log';
            $msg = sprintf("[%s] [INFO] Investment #%d: Compounding enabled - periods=%d, total_expected_profit=%.2f", date('Y-m-d H:i:s'), $investment_id, $n, $total_expected_profit);
            if (is_writable(dirname($cron_log))) {
                @error_log($msg . "\n", 3, $cron_log);
            } else {
                error_log($msg);
            }

            // Compute remaining profit defensively and round values to cents
            $remaining_expected = round(max(0, $total_expected_profit - round($total_profit_earned, 2)), 2);
            $profit_to_credit = $remaining_expected;
        } else {
            // Non-compounding: credit the full ROI (single payout) minus what was already credited
            $total_expected_profit = $amount * ($roi / 100.0);
            $profit_to_credit = round($total_expected_profit - $total_profit_earned, 2);
        }
    } else {
        // For hourly/daily/custom: credit all accrued payouts between next_payout_date and end_date
        $next_payout = isset($inv['next_payout_date']) ? $inv['next_payout_date'] : null;
        $due_count = 0;
        if ($next_payout && strtotime($next_payout) !== false) {
            $next_ts = strtotime($next_payout);
            $end_ts = strtotime($inv['end_date']);
            if ($next_ts <= $end_ts) {
                // Determine interval in seconds
                if ($payout_interval === 'hourly') {
                    $interval_seconds = 3600;
                } elseif ($payout_interval === 'daily') {
                    $interval_seconds = 86400;
                } elseif ($payout_interval === 'custom') {
                    $interval_type = $inv['payout_interval_type'] ?? $inv['plan_payout_interval_type'] ?? 'days';
                    $interval_value = intval($inv['payout_interval_value'] ?? $inv['plan_payout_interval_value'] ?? 0);
                    $interval_value = max(1, $interval_value);
                    $interval_seconds = convert_interval_to_seconds($interval_type, $interval_value);
                } else {
                    $interval_seconds = max(1, $duration_days) * 86400;
                }

                $diff = $end_ts - $next_ts;
                $due_count = (int) floor($diff / $interval_seconds) + 1;
                if ($due_count < 0) {
                    $due_count = 0;
                }
            }
        }

        // Profit per interval (uses same calculation as calculate_profit)
        $per_interval = calculate_profit($investment_id);

        // Total expected profit across the whole term (per-interval × total intervals)
        // Use floor-based interval counting so partial intervals are NOT counted,
        // matching the frontend estimator behavior. Do NOT force a minimum of 1
        // for hourly/daily/custom intervals; only end_of_term yields a single payout.
        if ($payout_interval === 'hourly') {
            $total_intervals = (int) floor(($duration_days * 86400) / 3600);
        } elseif ($payout_interval === 'daily') {
            $total_intervals = (int) floor($duration_days);
        } elseif ($payout_interval === 'custom') {
            $interval_type = $inv['payout_interval_type'] ?? $inv['plan_payout_interval_type'] ?? 'days';
            $interval_value = intval($inv['payout_interval_value'] ?? $inv['plan_payout_interval_value'] ?? 0);
            $interval_value = max(0, $interval_value);
            $interval_seconds = max(1, convert_interval_to_seconds($interval_type, $interval_value));
            $total_intervals = (int) floor(($duration_days * 86400) / $interval_seconds);
        } else {
            $total_intervals = 1;
        }
        $total_expected_profit = round($per_interval * $total_intervals, 2);

        // Profit due now (uncredited intervals up to end_date)
        $profit_due = round($per_interval * $due_count, 2);

        // Don't credit more than the remaining total expected profit
        $remaining = round(max(0, $total_expected_profit - $total_profit_earned), 2);
        $profit_to_credit = min($profit_due, $remaining);

        // Ensure we don't credit negative amounts
        $profit_to_credit = max(0, $profit_to_credit);
    }

    // Ensure we don't credit negative amounts
    if ($profit_to_credit < 0) {
        $profit_to_credit = 0.00;
    }

    $db = db_connect();
    try {
        $db->beginTransaction();

        // Credit final profit if there's any remaining
        if ($profit_to_credit > 0) {
            $credit_tx = credit_wallet($user_id, $profit_to_credit, 'profit', "Final profit from investment #$investment_id", $db);
            if (!$credit_tx) {
                throw new Exception('Failed to credit final profit');
            }
            $total_profit_earned += $profit_to_credit;
        }

        // Update investment with final profit and set next_payout_date to end_date to prevent double-crediting
        db_update('investments', [
            'total_profit_earned' => number_format($total_profit_earned, 2, '.', ''),
            'next_payout_date' => $inv['end_date']
        ], 'id = ?', [$investment_id]);

        // Handle capital return if enabled
        if ($capital_return) {
            $credit = credit_wallet($user_id, $amount, 'refund', "Capital return from investment #$investment_id", $db);
            if (!$credit) {
                throw new Exception('Failed to return capital');
            }
        }

        // Mark investment as completed
        db_update('investments', ['status' => 'completed'], 'id = ?', [$investment_id]);

        $db->commit();

        // Send investment completion email notification (if enabled)
        $email_investment_completed_user = get_setting('email_investment_completed_user', 'yes');
        if ($email_investment_completed_user === 'yes') {
            require_once __DIR__ . '/email-functions.php';
            $user = db_query("SELECT id, name, email FROM users WHERE id = ?", [$user_id])[0] ?? null;
            if ($user) {
                $site_url = get_site_url();
                $site_logo = get_setting('site_logo', '');
                $site_logo_url = (!empty($site_logo) && file_exists(__DIR__ . '/../' . $site_logo))
                    ? $site_url . '/' . ltrim($site_logo, '/')
                    : $site_url . '/assets/images/logo.png';

                $total_return_value = $total_profit_earned + ($capital_return ? $amount : 0);
                $email_vars = [
                    'site_name' => get_setting('site_name', 'Investment Platform'),
                    'site_logo' => $site_logo_url,
                    'logo_url' => $site_logo_url,
                    'site_url' => $site_url,
                    'user_name' => $user['name'],
                    'investment_id' => $investment_id,
                    'principal' => number_format($amount, 2),
                    'total_profit' => number_format($total_profit_earned, 2),
                    'plan_name' => $inv['plan_name'] ?? '',
                    'dashboard_url' => $site_url . '/user/dashboard',
                    'total_return' => number_format($total_return_value, 2),
                    'capital_returned' => $capital_return ? number_format($amount, 2) : '0.00',
                    'currency' => get_setting('currency', 'USD'),
                    'completed_at' => date('M d, Y'),
                    'investment_reference' => 'INV-' . $investment_id,
                    'current_year' => date('Y'),
                    'support_email' => get_setting('contact_email', 'support@example.com'),

                    'company_address' => get_setting('company_address', '')
                ];

                try {
                    send_template_email($user['email'], 'investment-completed', $email_vars, get_user_language($user['id']));
                } catch (Exception $e) {
                    error_log("[" . date('c') . "] Failed to send investment completed email: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/email-errors.log');
                }
            }
        }

        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Credit final profit and complete error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Complete investment and optionally return capital
 * @param int $investment_id
 * @return bool
 */
function complete_investment($investment_id)
{
    $row = db_query("SELECT i.*, p.capital_return FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ?", [$investment_id]);
    if (!$row || count($row) === 0) {
        return false;
    }
    $inv = $row[0];
    $db = db_connect();
    try {
        $db->beginTransaction();
        if ($inv['capital_return']) {
            $credit = credit_wallet($inv['user_id'], (float)$inv['amount'], 'refund', "Capital return from investment #$investment_id", $db);
            if (!$credit) {
                throw new Exception('Failed to return capital');
            }
        }
        db_update('investments', ['status' => 'completed'], 'id = ?', [$investment_id]);
        $db->commit();
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Complete investment error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Cancel investment with penalty handling
 * @param int $investment_id
 * @param int $user_id
 * @return array|false array with ['refund' => refund_amount, 'penalty' => penalty_amount] on success, false on error
 */
function cancel_investment($investment_id, $user_id)
{
    $row = db_query("SELECT i.* FROM investments i WHERE i.id = ?", [$investment_id]);
    if (!$row || count($row) === 0) {
        return false;
    }
    $inv = $row[0];
    if ($inv['user_id'] != $user_id) {
        return false;
    }

    // Check if block rule applies (block after first payout date)
    $cancellation_block_after_waiting = get_setting('cancellation_block_after_waiting', 'no');
    if ($cancellation_block_after_waiting === 'yes') {
        $next_payout_date = $inv['next_payout_date'];
        if ($next_payout_date !== null && strtotime($next_payout_date) !== false && strtotime($next_payout_date) <= time()) {
            return ['error' => 'blocked_after_waiting'];
        }
    }

    // Retrieve cancellation settings from global settings
    $penalty_mode = get_setting('cancellation_penalty_mode', 'percentage');
    $penalty_percentage = floatval(get_setting('cancellation_penalty_percentage', 10));
    $penalty_flat = floatval(get_setting('cancellation_penalty_flat', 5.00));
    $forfeit_profits = get_setting('cancellation_forfeit_profits', 'no');

    $amount = (float)$inv['amount'];
    $total_profit_earned = (float)($inv['total_profit_earned'] ?? 0.00);

    // Refund base includes earned profits when operator does not forfeit profits on cancellation
    $refund_base = $amount;
    if ($forfeit_profits === 'no') {
        $refund_base += $total_profit_earned;
    }

    // Calculate penalty based on refund base
    $penalty = 0.00;
    if ($penalty_mode === 'percentage') {
        $penalty = $refund_base * ($penalty_percentage / 100.0);
    } else {
        $penalty = $penalty_flat;
    }

    // Final refund amount (cannot be negative)
    $refund = max(0.00, $refund_base - $penalty);

    $db = db_connect();
    try {
        $db->beginTransaction();
        // Credit refund (this includes principal and optionally earned profits as per settings)
        if ($refund > 0) {
            $r = credit_wallet($user_id, $refund, 'refund', "Investment cancellation refund #$investment_id", $db);
            if ($r === false) {
                throw new Exception('Failed to credit refund');
            }
        }

        // Record penalty transaction
        if ($penalty > 0) {
            create_transaction($user_id, 'cancellation_penalty', number_format($penalty, 2, '.', ''), 'completed', "Cancellation penalty for investment #$investment_id");
        }

        // Update investment to cancelled and clear next payout to avoid further processing
        db_update('investments', [
            'status' => 'cancelled',
            'next_payout_date' => null,
            'total_profit_earned' => number_format($total_profit_earned, 2, '.', '')
        ], 'id = ?', [$investment_id]);
        $db->commit();
        return ['refund' => $refund, 'penalty' => $penalty];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("[" . date('c') . "] Cancel investment error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}
