<?php
// Prevent direct access
if (!defined('ROOT')) {
    die('Direct access denied');
}

require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/functions.php';

/**
 * Currency conversion helpers and maintenance-mode utilities.
 *
 * When the admin changes the platform base currency (e.g. XAF -> USD), every
 * monetary value stored in the database must be divided by the new currency's
 * rate relative to the old base so that the real-world purchasing power stays
 * the same (200 000 XAF / 568.846999999999980 = 351.58838844188333 USD).
 */

/**
 * Convert all monetary values in the database by dividing by a single rate.
 *
 * The rate is the cached rate for the NEW currency relative to the OLD base,
 * passed as an exact decimal string so MySQL performs exact DECIMAL division.
 *
 * @param string $rate     Cached rate for new currency relative to old base,
 *                         e.g. '568.846999999999980'.
 * @param string $old_base Previous base currency code (e.g. 'XAF').
 * @param string $new_base New base currency code (e.g. 'USD').
 * @return bool True on success.
 * @throws Exception on invalid input or database failure.
 */
function convert_all_monetary_values(string $rate, string $old_base, string $new_base): bool
{
    if (!is_numeric($rate) || (float) $rate <= 0 || !is_finite((float) $rate)) {
        throw new Exception(__('Invalid conversion rate. Rate must be a positive finite number.'));
    }

    global $db;
    $log_file = ROOT . '/logs/db-errors.log';

    // MySQL converts string parameters to DOUBLE in arithmetic expressions.
    // We force DECIMAL(30,15) so the division is exact, not floating-point.
    $d = 'CAST(? AS DECIMAL(30,15))';

    try {
        $db->beginTransaction();

        // --- User balances ---
        db_query("UPDATE `users` SET `balance` = `balance` / {$d} WHERE `balance` <> 0", [$rate]);

        // --- Investment plan thresholds ---
        db_query(
            "UPDATE `investment_plans` SET `min_amount` = `min_amount` / {$d}, `max_amount` = `max_amount` / {$d} WHERE `min_amount` <> 0 OR `max_amount` <> 0",
            [$rate, $rate]
        );

        // --- Active investments ---
        db_query(
            "UPDATE `investments` SET `amount` = `amount` / {$d}, `total_profit_earned` = `total_profit_earned` / {$d} WHERE `amount` <> 0 OR `total_profit_earned` <> 0",
            [$rate, $rate]
        );

        // --- Transactions ---
        db_query("UPDATE `transactions` SET `amount` = `amount` / {$d} WHERE `amount` <> 0", [$rate]);

        // --- Deposits ---
        // NOTE: local_currency_amount is intentionally NOT updated (it's in the user's local currency).
        // exchange_rate_used is multiplied by rate (inverse of the division on amounts).
        db_query(
            "UPDATE `deposits`
                SET `amount`           = `amount` / {$d},
                    `fee_amount`       = `fee_amount` / {$d},
                    `net_amount`       = `net_amount` / {$d},
                    `exchange_rate_used` = `exchange_rate_used` * {$d}
              WHERE `amount` <> 0 OR `fee_amount` <> 0 OR `net_amount` <> 0 OR `exchange_rate_used` <> 0",
            [$rate, $rate, $rate, $rate]
        );

        // --- Withdrawals ---
        db_query(
            "UPDATE `withdrawals`
                SET `amount`     = `amount` / {$d},
                    `fee_amount` = `fee_amount` / {$d},
                    `net_amount` = `net_amount` / {$d}
              WHERE `amount` <> 0 OR `fee_amount` <> 0 OR `net_amount` <> 0",
            [$rate, $rate, $rate]
        );

        // --- Referral bonuses ---
        db_query("UPDATE `referrals` SET `bonus_amount` = `bonus_amount` / {$d} WHERE `bonus_amount` <> 0", [$rate]);

        // --- Settings: minimum_withdrawal (always convert if numeric) ---
        $minimum_withdrawal = get_setting('minimum_withdrawal');
        if (is_numeric($minimum_withdrawal)) {
            $result = db_query("SELECT CAST(? AS DECIMAL(30,15)) / {$d} AS v", [$minimum_withdrawal, $rate]);
            update_setting('minimum_withdrawal', $result[0]['v']);
        }

        // --- Settings: cancellation_penalty_flat (always convert if numeric) ---
        $cancellation_penalty_flat = get_setting('cancellation_penalty_flat');
        if (is_numeric($cancellation_penalty_flat)) {
            $result = db_query("SELECT CAST(? AS DECIMAL(30,15)) / {$d} AS v", [$cancellation_penalty_flat, $rate]);
            update_setting('cancellation_penalty_flat', $result[0]['v']);
        }

        // --- Settings: referral_bonus_amount (only convert when bonus type is flat) ---
        $referral_bonus_type = get_setting('referral_bonus_type');
        if ($referral_bonus_type === 'flat') {
            $referral_bonus_amount = get_setting('referral_bonus_amount');
            if (is_numeric($referral_bonus_amount)) {
                $result = db_query("SELECT CAST(? AS DECIMAL(30,15)) / {$d} AS v", [$referral_bonus_amount, $rate]);
                update_setting('referral_bonus_amount', $result[0]['v']);
            }
        }

        // Do NOT convert percentage-based settings:
        // - withdrawal_fee_percentage
        // - deposit_fee_percentage
        // - cancellation_penalty_percentage

        // Atomically update the base-currency setting so converted amounts
        // and the currency code never drift out of sync.
        update_setting('currency', $new_base);

        $db->commit();
        return true;

    } catch (PDOException $e) {
        try {
            $db->rollBack();
        } catch (PDOException $rollbackEx) {
            error_log("[convert_all_monetary_values] Rollback failed: " . $rollbackEx->getMessage(), 3, $log_file);
        }

        error_log(
            "[convert_all_monetary_values] Conversion failed ({$old_base} -> {$new_base}, rate={$rate}): "
            . $e->getMessage(),
            3,
            $log_file
        );

        throw new Exception(__('Currency conversion failed. All changes have been rolled back. Please try again.'));
    }
}

/**
 * Check whether the platform is currently in maintenance mode.
 *
 * @return bool True if maintenance_mode is set to 'yes'.
 */
function get_maintenance_mode(): bool
{
    return get_setting('maintenance_mode', 'no') === 'yes';
}

/**
 * Enable or disable platform maintenance mode.
 *
 * @param bool $enabled True to enable, false to disable.
 * @return void
 */
function set_maintenance_mode(bool $enabled): void
{
    update_setting('maintenance_mode', $enabled ? 'yes' : 'no');
}

/**
 * Throw if the platform is in maintenance mode.
 *
 * Use at entry points that should reject new requests while conversion or
 * other critical maintenance is in progress.
 *
 * @return void
 * @throws Exception when maintenance mode is active.
 */
function require_not_maintenance(): void
{
    if (get_maintenance_mode()) {
        throw new Exception(__('Platform is temporarily under maintenance. Please try again shortly.'));
    }
}
