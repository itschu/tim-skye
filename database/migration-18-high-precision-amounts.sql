-- Migration 18: Increase monetary amount precision to DECIMAL(30,15)
-- Date: 2026-06-08
-- Description: Change all monetary amount columns from DECIMAL(15,2) to DECIMAL(30,15)
-- so full-precision values can be stored while rounding to 2 decimal places for display.

-- NOTE: investment_plans.roi_percentage is intentionally excluded because it is a
-- percentage, not a monetary amount.

ALTER TABLE users
    MODIFY COLUMN balance DECIMAL(30,15) NOT NULL DEFAULT 0.00;

ALTER TABLE investment_plans
    MODIFY COLUMN min_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN max_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00;

ALTER TABLE investments
    MODIFY COLUMN amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN total_profit_earned DECIMAL(30,15) NOT NULL DEFAULT 0.00;

ALTER TABLE transactions
    MODIFY COLUMN amount DECIMAL(30,15) NOT NULL DEFAULT 0.00;

ALTER TABLE deposits
    MODIFY COLUMN amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN net_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN local_currency_amount DECIMAL(30,15) DEFAULT NULL;

ALTER TABLE withdrawals
    MODIFY COLUMN amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN net_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00;

ALTER TABLE referrals
    MODIFY COLUMN bonus_amount DECIMAL(30,15) NOT NULL DEFAULT 0.00;
