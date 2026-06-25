-- Migration: Rename national_id to civil_id in employees table
-- Run this to update existing database

ALTER TABLE `employees` CHANGE COLUMN `national_id` `civil_id` VARCHAR(100);
