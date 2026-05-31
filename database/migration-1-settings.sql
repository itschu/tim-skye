-- Database Settings Initialization
-- Run this SQL to initialize missing settings with default values

INSERT IGNORE INTO settings (setting_key, setting_value, created_at, updated_at) VALUES
('payment_methods', '[]', NOW(), NOW()),
('post_registration_action', 'dashboard', NOW(), NOW());
