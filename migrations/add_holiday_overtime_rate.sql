-- Migration: Add holiday_overtime_rate to settings table
-- Run this to add holiday overtime rate setting to existing database

ALTER TABLE `settings` ADD COLUMN `holiday_overtime_rate` DECIMAL(4,2) DEFAULT 2.00 AFTER `overtime_rate`;
