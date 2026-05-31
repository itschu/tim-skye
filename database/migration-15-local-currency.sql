-- Migration 15: Add local currency columns to deposits
START TRANSACTION;
ALTER TABLE `deposits`
ADD COLUMN IF NOT EXISTS `local_currency_amount` DECIMAL(15,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `local_currency_code` VARCHAR(3) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `exchange_rate_used` DECIMAL(20,8) DEFAULT NULL;
COMMIT;