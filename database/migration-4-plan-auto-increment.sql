-- Migration: Make investment_plans.id auto-increment
-- This migration changes the id column to auto-increment for automatic plan ID generation

-- Step 1: Add a new auto-increment column
ALTER TABLE `investment_plans` 
MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Note: Run this migration if you want plan IDs to be auto-generated
-- instead of manually assigned by administrators.
