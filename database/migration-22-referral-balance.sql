-- Migration 22: Separate referral balance wallet
-- Date: 2026-06-13
-- Description:
--   1. Add `referral_balance` to `users` so referral bonuses are isolated from the
--      main wallet balance.
--   2. Add `source` enum to `withdrawals` to distinguish regular balance withdrawals
--      from referral balance withdrawals.
--   3. Expand `transactions.type` enum to include `referral_fund` for funding the
--      main wallet from referral earnings.
--   4. Seed default admin settings for referral fund/withdrawal limits.

-- 1. Add referral_balance column to users (idempotent)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `referral_balance` DECIMAL(65,30) NOT NULL DEFAULT 0.00
    AFTER `balance`;

-- 2. Add source column to withdrawals (idempotent)
ALTER TABLE `withdrawals`
    ADD COLUMN IF NOT EXISTS `source` ENUM('balance','referral') NOT NULL DEFAULT 'balance'
    AFTER `status`;

-- 3. Expand transactions.type enum to include 'referral_fund'
-- MySQL 8.0.16+ supports IF NOT EXISTS on enum members via ALTER ... MODIFY.
-- For broader compatibility we simply modify the column.
ALTER TABLE `transactions`
    MODIFY COLUMN `type` ENUM(
        'deposit',
        'withdrawal',
        'profit',
        'referral',
        'investment',
        'refund',
        'cancellation_penalty',
        'referral_fund'
    ) NOT NULL;

-- 4. Seed default settings for referral wallet limits
-- These are used by the new exact / min-max validation logic.
-- `get_setting()` already supplies defaults, but explicit rows make them visible
-- in the admin panel immediately.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('referral_fund_withdraw_mode', 'exact'),
    ('referral_exact_amount', '0'),
    ('referral_min_amount', '0'),
    ('referral_max_amount', '0');
