-- Migration 12: KYC Banner Display Settings
-- Adds settings for persistent KYC banner display and dismissibility

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('kyc_always_show_message', 'no'),
('kyc_banner_dismissible', 'yes');
