-- Migration 13: Ensure company_address setting exists
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('company_address', '');
