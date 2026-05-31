-- Migration: Add country and profile_picture columns to users table
-- Run this SQL to add the new columns for user profile enhancement

-- Add country column (ISO 3166-1 alpha-2, e.g., 'US', 'NG', 'FR')
ALTER TABLE users ADD COLUMN country varchar(2) DEFAULT NULL AFTER language;

-- Add profile_picture column (stores path to uploaded image)
ALTER TABLE users ADD COLUMN profile_picture varchar(255) DEFAULT NULL AFTER country;
