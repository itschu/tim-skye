-- Migration: Add fee_amount and net_amount to deposits
-- Adds fee tracking columns and backfills existing rows
START TRANSACTION;

ALTER TABLE `deposits`
  ADD COLUMN  IF NOT EXISTS `fee_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `amount`,
  ADD COLUMN  IF NOT EXISTS `net_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `fee_amount`;

-- Backfill: previous `amount` column stored net amounts
UPDATE `deposits` SET `net_amount` = `amount`, `fee_amount` = 0.00;

COMMIT;
