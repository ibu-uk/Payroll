-- Migration: Add working_hours and shift_type to job_titles table
-- Run this to update existing database

ALTER TABLE `job_titles` 
ADD COLUMN `working_hours` INT DEFAULT 8 AFTER `max_salary`,
ADD COLUMN `shift_type` ENUM('morning','evening','night','flexible') DEFAULT 'morning' AFTER `working_hours`;

-- Update existing job titles with default values
UPDATE `job_titles` SET `working_hours` = 8 WHERE `working_hours` IS NULL;
UPDATE `job_titles` SET `shift_type` = 'morning' WHERE `shift_type` IS NULL;
