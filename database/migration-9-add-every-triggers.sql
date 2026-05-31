-- Migration 7: Add every_deposit and every_investment to trigger_event enum
-- This migration adds support for recurring referral bonuses on every deposit/investment

ALTER TABLE referrals 
MODIFY COLUMN trigger_event ENUM('registration','first_deposit','first_investment','every_deposit','every_investment') NOT NULL;
