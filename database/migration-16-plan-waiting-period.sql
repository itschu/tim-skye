-- Migration 16: Add waiting period columns to investment_plans and investments; add country settings
START TRANSACTION;
ALTER TABLE `investment_plans`
ADD COLUMN IF NOT EXISTS `waiting_period_value` INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `waiting_period_unit` ENUM('seconds','minutes','hours','days','weeks') NOT NULL DEFAULT 'days';
ALTER TABLE `investments`
ADD COLUMN IF NOT EXISTS `waiting_period_value` INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS `waiting_period_unit` ENUM('seconds','minutes','hours','days','weeks') NOT NULL DEFAULT 'days';
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('accepted_countries', '[]'),
('default_country', '');
COMMIT;