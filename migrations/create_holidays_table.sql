-- Migration: Create holidays table for managing government, religious, and company holidays
-- Run this to add holiday management to existing database

CREATE TABLE IF NOT EXISTS `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(255) NOT NULL,
  `name_ar` VARCHAR(255),
  `holiday_date` DATE NOT NULL,
  `holiday_type` ENUM('government','religious','company') DEFAULT 'government',
  `is_recurring` TINYINT(1) DEFAULT 0,
  `recurring_month` INT NULL,
  `recurring_day` INT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_holiday`(`holiday_date`),
  INDEX `idx_holiday_date`(`holiday_date`),
  INDEX `idx_recurring`(`is_recurring`,`recurring_month`,`recurring_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample Kuwait holidays
INSERT INTO `holidays` (`name_en`, `name_ar`, `holiday_date`, `holiday_type`, `is_recurring`, `recurring_month`, `recurring_day`) VALUES
('National Day', 'اليوم الوطني', '2025-02-25', 'government', 1, 2, 25),
('Liberation Day', 'يوم التحرير', '2025-02-26', 'government', 1, 2, 26);
