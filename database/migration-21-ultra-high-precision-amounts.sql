-- Migration 21: Ultra-high precision monetary amounts (DECIMAL(65,30))
-- Date: 2026-06-11
-- Description: Change all monetary amount columns from DECIMAL(30,15) to DECIMAL(65,30)
-- so that full-precision conversions (e.g. local currency -> base currency) are
-- preserved exactly. Also increases exchange_rate_used so cached rates are not
-- truncated.

-- NOTE: investment_plans.roi_percentage is intentionally excluded because it is a
-- percentage, not a monetary amount.

ALTER TABLE users
    MODIFY COLUMN balance DECIMAL(65,30) NOT NULL DEFAULT 0.00;

ALTER TABLE investment_plans
    MODIFY COLUMN min_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN max_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00;

ALTER TABLE investments
    MODIFY COLUMN amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN total_profit_earned DECIMAL(65,30) NOT NULL DEFAULT 0.00;

ALTER TABLE transactions
    MODIFY COLUMN amount DECIMAL(65,30) NOT NULL DEFAULT 0.00;

ALTER TABLE deposits
    MODIFY COLUMN amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN net_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN local_currency_amount DECIMAL(65,30) DEFAULT NULL,
    MODIFY COLUMN exchange_rate_used DECIMAL(65,30) DEFAULT NULL;

ALTER TABLE withdrawals
    MODIFY COLUMN amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN net_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN local_currency_amount DECIMAL(65,30) DEFAULT NULL,
    MODIFY COLUMN exchange_rate_used DECIMAL(65,30) DEFAULT NULL;

ALTER TABLE referrals
    MODIFY COLUMN bonus_amount DECIMAL(65,30) NOT NULL DEFAULT 0.00;
