-- Migration: 19
-- Date: 2026-09-01
-- Description: Update foreign key constraints to ensure proper cascading behavior on delete and restrict on update.

ALTER TABLE `investments` DROP FOREIGN KEY `investments_ibfk_2`; ALTER TABLE `investments` ADD CONSTRAINT `investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `investment_plans`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;