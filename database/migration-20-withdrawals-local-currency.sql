-- Migration 20: Add local currency columns to withdrawals table
-- Date: 2026-06-10
-- Description: Add local_currency_amount, local_currency_code, and exchange_rate_used
-- to the withdrawals table so user's local currency entry can be recorded.

ALTER TABLE withdrawals
    ADD COLUMN IF NOT EXISTS `local_currency_amount` DECIMAL(30,15) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `local_currency_code` VARCHAR(3) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `exchange_rate_used` DECIMAL(20,8) DEFAULT NULL;
