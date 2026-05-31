-- Migration: Add Email Verification Support
-- Date: 2026-02-15
-- Description: Adds email verification columns to users table and related settings

-- Add email verification columns to users table
ALTER TABLE `users` 
  ADD COLUMN IF NOT EXISTS `email_verified` tinyint(1) NOT NULL DEFAULT 0 AFTER `email`,
  ADD COLUMN IF NOT EXISTS `email_verification_token` varchar(255) DEFAULT NULL AFTER `email_verified`,
  ADD COLUMN IF NOT EXISTS `email_verification_sent_at` datetime DEFAULT NULL AFTER `email_verification_token`;

-- Add index for token lookup
ALTER TABLE `users` ADD INDEX `email_verification_token` (`email_verification_token`);

-- Add require_email_verification setting (default: no - disabled)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('require_email_verification', 'no')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Note: Existing users will have email_verified = 0 (unverified)
-- When the feature is enabled, only new registrations will require verification
-- Existing users can continue to log in normally until they verify their email
