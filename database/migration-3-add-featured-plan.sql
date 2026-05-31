-- Migration: Add is_featured column to investment_plans table
-- Run this if you have an existing installation before the is_featured column was added

ALTER TABLE `investment_plans` 
ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `is_featured` tinyint(1) NOT NULL DEFAULT 0 AFTER `capital_return`,
ADD INDEX IF NOT EXISTS `is_featured` (`is_featured`);

-- Optional: Set a default featured plan (the middle tier plan)
-- UPDATE investment_plans SET is_featured = 1 WHERE id = 'your-plan-id';
