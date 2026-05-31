-- Migration 6: Enhanced Custom Intervals with Multi-Unit Support
-- Description: Adds support for minutes, hours, days, weeks, months intervals
--              and compounding for end_of_term investments
-- Date: 2026-02-14
-- Backward Compatible: Yes (existing custom intervals default to 'days')

START TRANSACTION;

-- ============================================================================
-- INVESTMENT_PLANS TABLE ENHANCEMENTS
-- ============================================================================

-- Add payout_interval_type for custom intervals
ALTER TABLE `investment_plans`
  ADD COLUMN IF NOT EXISTS `payout_interval_type` ENUM('minutes', 'hours', 'days', 'weeks', 'months') DEFAULT NULL 
  AFTER `payout_interval`;

-- Rename payout_interval_days to payout_interval_value (more generic)
ALTER TABLE `investment_plans`
  CHANGE COLUMN `payout_interval_days` `payout_interval_value` INT(11) DEFAULT NULL;

-- Add is_compounding flag for end_of_term plans
ALTER TABLE `investment_plans`
  ADD COLUMN IF NOT EXISTS `is_compounding` TINYINT(1) NOT NULL DEFAULT 0 
  AFTER `capital_return`;

-- Backfill existing custom intervals to use 'days' as default type
UPDATE `investment_plans` 
SET `payout_interval_type` = 'days' 
WHERE `payout_interval` = 'custom' AND `payout_interval_value` IS NOT NULL;

-- ============================================================================
-- INVESTMENTS TABLE ENHANCEMENTS (Mirror plan structure)
-- ============================================================================

-- Add payout_interval_type for historical tracking
ALTER TABLE `investments`
  ADD COLUMN IF NOT EXISTS `payout_interval_type` ENUM('minutes', 'hours', 'days', 'weeks', 'months') DEFAULT NULL 
  AFTER `payout_interval_days`;

-- Rename payout_interval_days to payout_interval_value
ALTER TABLE `investments`
  CHANGE COLUMN `payout_interval_days` `payout_interval_value` INT(11) DEFAULT NULL;

-- Add is_compounding for historical tracking
ALTER TABLE `investments`
  ADD COLUMN IF NOT EXISTS `is_compounding` TINYINT(1) NOT NULL DEFAULT 0 
  AFTER `total_profit_earned`;

-- Backfill existing custom investments to use 'days' as default type
UPDATE `investments` 
SET `payout_interval_type` = 'days' 
WHERE `payout_interval_value` IS NOT NULL;

-- ============================================================================
-- PERFORMANCE OPTIMIZATION
-- ============================================================================

-- Add composite index for efficient cron queries
ALTER TABLE `investments`
  ADD KEY `idx_custom_interval` (`payout_interval_type`, `payout_interval_value`);

COMMIT;

-- ============================================================================
-- POST-MIGRATION VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify success:
--
-- 1. Check investment_plans structure:
--    DESCRIBE investment_plans;
--
-- 2. Verify custom intervals backfilled:
--    SELECT id, name, payout_interval, payout_interval_type, payout_interval_value 
--    FROM investment_plans WHERE payout_interval = 'custom';
--
-- 3. Check investments structure:
--    DESCRIBE investments;
--
-- 4. Verify investments backfilled:
--    SELECT id, payout_interval_type, payout_interval_value 
--    FROM investments WHERE payout_interval_value IS NOT NULL LIMIT 10;
