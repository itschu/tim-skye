-- Migration: Add Email Notification Settings
-- Date: 2026-02-15
-- Description: Adds settings to control which actions trigger email notifications

-- User Account Notifications
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_user_registration', 'yes'),
('email_user_verification', 'yes'),
('email_password_reset', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Deposit Notifications (User)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_deposit_submitted_user', 'yes'),
('email_deposit_approved_user', 'yes'),
('email_deposit_rejected_user', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Deposit Notifications (Admin)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_deposit_submitted_admin', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Withdrawal Notifications (User)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_withdrawal_submitted_user', 'yes'),
('email_withdrawal_approved_user', 'yes'),
('email_withdrawal_rejected_user', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Withdrawal Notifications (Admin)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_withdrawal_submitted_admin', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Investment Notifications (User)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_investment_created_user', 'yes'),
('email_investment_completed_user', 'yes'),
('email_investment_cancelled_user', 'yes'),
('email_profit_payout_user', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- KYC Notifications (User)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_kyc_approved_user', 'yes'),
('email_kyc_rejected_user', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- KYC Notifications (Admin)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_kyc_submitted_admin', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Referral Notifications (User)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
('email_referral_bonus_user', 'yes')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
