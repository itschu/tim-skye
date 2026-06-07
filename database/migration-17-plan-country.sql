-- Migration: Add country column to investment_plans for localized/global plan support
-- Run this SQL to enable country-specific and global investment plans

-- Add country column (ISO 3166-1 alpha-2, e.g., 'US', 'NG', 'FR')
-- NULL or empty string = global plan (visible to all countries)
ALTER TABLE investment_plans ADD COLUMN country varchar(2) DEFAULT NULL AFTER waiting_period_unit;

-- Add index for efficient country filtering
ALTER TABLE investment_plans ADD KEY idx_country (country);
