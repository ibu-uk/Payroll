-- Migration: Add Advanced Payroll Features
-- Run this to add tables for employee history, bonuses, gratuity, audit logs

-- Employee History Table - Track all changes to employee records
CREATE TABLE IF NOT EXISTS `employee_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `changed_by` INT NOT NULL,
  `change_type` ENUM('created','updated','salary_change','status_change','department_change','job_change','terminated') NOT NULL,
  `field_name` VARCHAR(50),
  `old_value` TEXT,
  `new_value` TEXT,
  `change_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  INDEX `idx_emp_history`(`employee_id`,`change_date`),
  INDEX `idx_change_type`(`change_type`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY(`changed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bonus and Commission Table
CREATE TABLE IF NOT EXISTS `bonuses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `bonus_type` ENUM('performance','sales_commission','project_bonus','signing_bonus','referral_bonus','year_end_bonus','other') DEFAULT 'performance',
  `amount` DECIMAL(15,3) NOT NULL,
  `period_year` INT,
  `period_month` INT,
  `description` TEXT,
  `approved_by` INT,
  `approved_at` TIMESTAMP NULL,
  `status` ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
  `payment_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_emp_bonus`(`employee_id`,`period_year`,`period_month`),
  INDEX `idx_bonus_status`(`status`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY(`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gratuity / End of Service Calculations
CREATE TABLE IF NOT EXISTS `gratuity` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `calculation_date` DATE NOT NULL,
  `hire_date` DATE NOT NULL,
  `termination_date` DATE,
  `years_of_service` DECIMAL(5,2),
  `last_salary` DECIMAL(15,3),
  `gratuity_amount` DECIMAL(15,3),
  `calculation_method` ENUM('kuwait_labor_law','custom') DEFAULT 'kuwait_labor_law',
  `notes` TEXT,
  `approved_by` INT,
  `approved_at` TIMESTAMP NULL,
  `status` ENUM('calculated','approved','rejected','paid') DEFAULT 'calculated',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_emp_gratuity`(`employee_id`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY(`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Logs - Track all system actions
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50),
  `entity_id` INT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `old_data` JSON,
  `new_data` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_audit`(`user_id`,`created_at`),
  INDEX `idx_entity_audit`(`entity_type`,`entity_id`),
  INDEX `idx_action_audit`(`action`),
  INDEX `idx_date_audit`(`created_at`),
  FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Archived Payroll Data (for archiving old periods)
CREATE TABLE IF NOT EXISTS `archived_payroll_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `original_period_id` INT NOT NULL,
  `period_year` INT NOT NULL,
  `period_month` INT NOT NULL,
  `period_label` VARCHAR(100),
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `payment_date` DATE,
  `status` ENUM('draft','processing','approved','paid','cancelled'),
  `total_gross` DECIMAL(18,3),
  `total_deductions` DECIMAL(18,3),
  `total_net` DECIMAL(18,3),
  `employee_count` INT,
  `archived_by` INT,
  `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `archive_reason` TEXT,
  INDEX `idx_archived_year`(`period_year`,`period_month`),
  FOREIGN KEY(`archived_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `archived_payroll_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `archived_period_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `basic_salary` DECIMAL(15,3),
  `total_allowances` DECIMAL(15,3),
  `gross_salary` DECIMAL(15,3),
  `overtime_hours` DECIMAL(5,2),
  `overtime_amount` DECIMAL(15,3),
  `absent_days` DECIMAL(5,1),
  `absent_deduction` DECIMAL(15,3),
  `late_minutes` INT,
  `late_deduction` DECIMAL(15,3),
  `loan_deduction` DECIMAL(15,3),
  `social_insurance` DECIMAL(15,3),
  `tax_amount` DECIMAL(15,3),
  `other_deductions` DECIMAL(15,3),
  `total_deductions` DECIMAL(15,3),
  `net_salary` DECIMAL(15,3),
  `payment_status` ENUM('pending','transferred','paid','hold'),
  INDEX `idx_archived_period`(`archived_period_id`),
  INDEX `idx_archived_emp`(`employee_id`),
  FOREIGN KEY(`archived_period_id`) REFERENCES `archived_payroll_periods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
