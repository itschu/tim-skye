-- Migration: Add custom payout interval support
-- Adds 'custom' enum option and payout_interval_days columns

START TRANSACTION;

-- Add payout_interval_days to investment_plans (nullable)
ALTER TABLE `investment_plans`
  ADD COLUMN IF NOT EXISTS `payout_interval_days` int(11) DEFAULT NULL AFTER `payout_interval`;

-- Add payout_interval_days to investments (nullable)
ALTER TABLE `investments`
  ADD COLUMN IF NOT EXISTS `payout_interval_days` int(11) DEFAULT NULL AFTER `next_payout_date`;

-- Update enum for payout_interval to include 'custom'
-- MySQL doesn't support direct enum alteration portably, so add a tmp column, copy, drop and rename
ALTER TABLE `investment_plans` ADD COLUMN IF NOT EXISTS `payout_interval_new` ENUM('hourly','daily','end_of_term','custom') AFTER `payout_interval`;
UPDATE `investment_plans` SET `payout_interval_new` = `payout_interval`;
ALTER TABLE `investment_plans` DROP COLUMN `payout_interval`;
ALTER TABLE `investment_plans` CHANGE COLUMN `payout_interval_new` `payout_interval` ENUM('hourly','daily','end_of_term','custom');

COMMIT;
