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
 * Referral helper functions
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/wallet-functions.php';
require_once __DIR__ . '/email-functions.php';

/**
 * Process referral bonus for a referred user based on trigger event
 * @param int $referred_user_id
 * @param string $trigger_event one of 'registration','first_deposit','first_investment','every_deposit','every_investment'
 * @param float $transaction_amount Optional: the specific transaction amount for percentage calculations
 * @return float|false bonus amount on success, false on failure or not applicable
 */
function process_referral_bonus($referred_user_id, $trigger_event, $transaction_amount = null)
{
    $valid_triggers = ['registration', 'first_deposit', 'first_investment', 'every_deposit', 'every_investment'];
    if (!in_array($trigger_event, $valid_triggers)) {
        error_log("[Referral Debug] Invalid trigger event: {$trigger_event}");
        return false;
    }

    try {
        // Get referred user's referrer
        $rows = db_query("SELECT referred_by, name, referral_code FROM users WHERE id = ?", [$referred_user_id]);
        if (empty($rows)) {
            error_log("[Referral Debug] User not found: {$referred_user_id}");
            return false;
        }
        $referred = $rows[0];
        $referrer_id = $referred['referred_by'] ?? null;
        if (empty($referrer_id)) {
            error_log("[Referral Debug] No referrer for user: {$referred_user_id}");
            return false;
        }

        error_log("[Referral Debug] Processing bonus for referred_id={$referred_user_id}, referrer_id={$referrer_id}, trigger={$trigger_event}");

        // Check settings trigger (default to 'registration' for immediate bonus on signup)
        $configured_trigger = get_setting('referral_bonus_trigger', 'registration');
        if ($configured_trigger !== $trigger_event) {
            error_log("[Referral Debug] Trigger mismatch: configured='{$configured_trigger}', event='{$trigger_event}'");
            return false;
        }

        // Determine bonus settings
        $bonus_type = get_setting('referral_bonus_type', 'flat');
        $bonus_setting = get_setting('referral_bonus_amount', '0');

        // SECURITY CHECK: Prevent percentage bonus on registration (no base amount available)
        if ($trigger_event === 'registration' && $bonus_type === 'percentage') {
            error_log("[Referral Security] Blocked percentage bonus on registration for user {$referred_user_id} - no base amount available");
            return false;
        }

        // Handle duplicate checking based on trigger type
        // One-time events: registration, first_deposit, first_investment - prevent duplicates
        // Recurring events: every_deposit, every_investment - allow duplicates (each transaction gets bonus)
        $one_time_triggers = ['registration', 'first_deposit', 'first_investment'];
        if (in_array($trigger_event, $one_time_triggers)) {
            $existing = db_query("SELECT id FROM referrals WHERE referrer_id = ? AND referred_id = ? AND trigger_event = ?", [$referrer_id, $referred_user_id, $trigger_event]);
            if (!empty($existing)) {
                error_log("[Referral Debug] Bonus already credited for referrer={$referrer_id}, referred={$referred_user_id}, trigger={$trigger_event}");
                return false;
            }
        }
        // For every_deposit and every_investment, we allow multiple entries (no duplicate check)
        // Each transaction will create its own referral record

        // Calculate bonus amount
        $bonus_amount = 0.00;

        if ($bonus_type === 'flat') {
            $bonus_amount = (float)$bonus_setting;
        } else {
            // percentage calculation
            $pct = (float)$bonus_setting;
            $base_amount = 0.00;

            if ($transaction_amount !== null && $transaction_amount > 0) {
                // Use provided transaction amount (for programatically called events)
                $base_amount = (float)$transaction_amount;
            } elseif ($trigger_event === 'first_deposit' || $trigger_event === 'every_deposit') {
                // Use net_amount (amount credited) as base for referral calculation
                $dep = db_query("SELECT net_amount as amount FROM deposits WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1", [$referred_user_id]);
                if (!empty($dep)) {
                    $base_amount = (float)$dep[0]['amount'];
                }
            } elseif ($trigger_event === 'first_investment' || $trigger_event === 'every_investment') {
                $inv = db_query("SELECT amount FROM investments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$referred_user_id]);
                if (!empty($inv)) {
                    $base_amount = (float)$inv[0]['amount'];
                }
            }
            $bonus_amount = $base_amount * ($pct / 100.0);
        }

        if ($bonus_amount <= 0) {
            error_log("[Referral Debug] Bonus amount too low: {$bonus_amount} (type={$bonus_type}, setting={$bonus_setting})");
            return false;
        }

        error_log("[Referral Debug] Awarding bonus: referrer_id={$referrer_id}, referred_id={$referred_user_id}, trigger={$trigger_event}, amount={$bonus_amount}");

        // Perform DB transaction: credit wallet and insert referral record
        $db = db_connect();
        try {
            $db->beginTransaction();

            $tx_id = credit_wallet($referrer_id, $bonus_amount, 'referral', 'Referral bonus for user #' . $referred_user_id, $db);
            if ($tx_id === false) {
                throw new Exception('Failed to credit wallet');
            }

            // Insert record into referrals table to track the bonus
            $ref_data = [
                'referrer_id' => $referrer_id,
                'referred_id' => $referred_user_id,
                'bonus_amount' => number_format((float)$bonus_amount, 15, '.', ''),
                'trigger_event' => $trigger_event,
                'status' => 'credited',
                'created_at' => date('Y-m-d H:i:s')
            ];
            db_insert('referrals', $ref_data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[Referral Error] " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
            return false;
        }

        // Send email to referrer (if enabled) - non-blocking with @ to suppress errors
        $email_referral_bonus_user = get_setting('email_referral_bonus_user', 'yes');
        if ($email_referral_bonus_user === 'yes') {
            require_once __DIR__ . '/email-functions.php';
            $ref_rows = db_query("SELECT * FROM users WHERE id = ?", [$referrer_id]);
            $referrer = $ref_rows[0] ?? null;
            if ($referrer) {
                try {
                    $site_url = get_site_url();
                    $referrer_balance = get_user_balance($referrer_id);
                    @send_template_email($referrer['email'], 'referral-bonus', [
                        'user_name' => e($referrer['name']),
                        'amount' => format_money($bonus_amount),
                        'referred_user_name' => e($referred['name'] ?? ''),
                        'new_balance' => format_money($referrer_balance),
                        'site_name' => e(get_setting('site_name', 'Investment Platform')),
                        'site_logo' => e(get_setting('site_logo', '')),
                        'site_url' => $site_url,
                        'currency' => get_setting('currency', 'USD'),
                        'current_year' => date('Y'),
                        'support_email' => e(get_setting('contact_email', 'support@example.com')),
                        'referral_url' => $site_url . '/register.php',

                    ], get_user_language($referrer['id']));
                } catch (Exception $e) {
                    error_log("[Referral Error] Failed to send referral bonus email: " . $e->getMessage(), 3, __DIR__ . '/../logs/email-errors.log');
                }
            }
        }

        return (float)$bonus_amount;
    } catch (Exception $e) {
        error_log("[Referral Error] " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
        return false;
    }
}

/**
 * Get referral statistics for a referrer
 * @param int $user_id
 * @return array ['count' => int, 'total' => float]
 */
function get_referral_stats($user_id)
{
    try {
        $row = db_query("SELECT COUNT(*) as count, COALESCE(SUM(bonus_amount),0) as total FROM referrals WHERE referrer_id = ? AND status = 'credited'", [$user_id]);
        if (empty($row)) {
            return ['count' => 0, 'total' => 0.00];
        }
        return ['count' => (int)($row[0]['count'] ?? 0), 'total' => (float)($row[0]['total'] ?? 0.00)];
    } catch (Exception $e) {
        error_log("[Referral Stats Error] " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
        return ['count' => 0, 'total' => 0.00];
    }
}

/**
 * Get detailed referral stats for a referrer
 * - successful: users who have made investments
 * - active: users who have made deposits (but not necessarily invested)
 * - pending: users who registered but haven't invested
 * 
 * @param int $user_id
 * @return array ['successful' => int, 'active' => int, 'pending' => int, 'total' => float]
 */
function get_referral_detailed_stats($user_id)
{
    try {
        // Get all referred users
        $referred_users = db_query(
            "SELECT u.id, u.name, u.email, u.created_at as registered_at 
             FROM users u 
             WHERE u.referred_by = ?",
            [$user_id]
        );

        if (empty($referred_users)) {
            return [
                'successful' => 0,
                'active' => 0,
                'pending' => 0,
                'total' => 0.00
            ];
        }

        $successful = 0;  // Has investments
        $active = 0;      // Has deposits
        $pending = 0;     // Registered only
        $total_bonus = 0.00;

        foreach ($referred_users as $user) {
            $referred_id = $user['id'];

            // Check if user has investments
            $has_investments = db_query(
                "SELECT COUNT(*) as count FROM investments WHERE user_id = ? LIMIT 1",
                [$referred_id]
            );

            if (!empty($has_investments) && $has_investments[0]['count'] > 0) {
                $successful++;
            } else {
                // Check if user has deposits
                $has_deposits = db_query(
                    "SELECT COUNT(*) as count FROM deposits WHERE user_id = ? AND status = 'approved' LIMIT 1",
                    [$referred_id]
                );

                if (!empty($has_deposits) && $has_deposits[0]['count'] > 0) {
                    $active++;
                } else {
                    $pending++;
                }
            }

            // Get total bonus amount for this referred user
            $bonus = db_query(
                "SELECT COALESCE(SUM(bonus_amount), 0) as total FROM referrals WHERE referrer_id = ? AND referred_id = ? AND status = 'credited'",
                [$user_id, $referred_id]
            );
            $total_bonus += (float)($bonus[0]['total'] ?? 0);
        }

        return [
            'successful' => $successful,
            'active' => $active,
            'pending' => $pending,
            'total' => $total_bonus
        ];
    } catch (Exception $e) {
        error_log("[Referral Detailed Stats Error] " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
        return [
            'successful' => 0,
            'active' => 0,
            'pending' => 0,
            'total' => 0.00
        ];
    }
}

/**
 * Get referral list for a referrer with detailed status
 * @param int $user_id
 * @return array
 */
function get_referral_list($user_id)
{
    try {
        // Get all referred users from users table (not referrals table)
        $rows = db_query(
            "SELECT u.id as referred_id, u.name as referred_name, u.email as referred_email, u.created_at as registered_at 
             FROM users u 
             WHERE u.referred_by = ? 
             ORDER BY u.created_at DESC",
            [$user_id]
        );

        if (empty($rows)) {
            return [];
        }

        // Enhance with investment/deposit status and bonus info
        foreach ($rows as &$row) {
            $referred_id = $row['referred_id'];

            // Check investments
            $investments = db_query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM investments WHERE user_id = ?",
                [$referred_id]
            );
            $row['has_investments'] = !empty($investments) && $investments[0]['count'] > 0;
            $row['investment_count'] = $investments[0]['count'] ?? 0;
            $row['investment_total'] = $investments[0]['total'] ?? 0;

            // Check deposits - use net credited amounts for totals
            $deposits = db_query(
                "SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total FROM deposits WHERE user_id = ? AND status = 'approved'",
                [$referred_id]
            );
            $row['has_deposits'] = !empty($deposits) && $deposits[0]['count'] > 0;
            $row['deposit_count'] = $deposits[0]['count'] ?? 0;
            $row['deposit_total'] = $deposits[0]['total'] ?? 0;

            // Determine status
            if ($row['has_investments']) {
                $row['status'] = 'successful';  // Has invested
            } elseif ($row['has_deposits']) {
                $row['status'] = 'active';      // Has deposited but not invested
            } else {
                $row['status'] = 'pending';     // Registered only
            }

            // Get bonus amount from referrals table
            $bonus = db_query(
                "SELECT COALESCE(SUM(bonus_amount), 0) as total FROM referrals WHERE referrer_id = ? AND referred_id = ? AND status = 'credited'",
                [$user_id, $referred_id]
            );
            $row['bonus_amount'] = $bonus[0]['total'] ?? 0;
        }

        return $rows;
    } catch (Exception $e) {
        error_log("[Referral List Error] " . $e->getMessage(), 3, __DIR__ . '/../logs/db-errors.log');
        return [];
    }
}
