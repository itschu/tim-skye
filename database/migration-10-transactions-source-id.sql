-- Migration: Add source_id to transactions for linking to deposits/withdrawals
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS  source_id INT DEFAULT NULL AFTER details;

ALTER TABLE transactions
  ADD INDEX idx_source_id (source_id);

-- End migration
