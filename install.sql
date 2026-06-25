-- PayrollPro Database Schema
-- Import via phpMyAdmin or: mysql -u root payroll_db < install.sql

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name_en` VARCHAR(255) DEFAULT 'PayrollPro',
  `company_name_ar` VARCHAR(255) DEFAULT 'نظام الرواتب',
  `company_address_en` TEXT, `company_address_ar` TEXT,
  `company_phone` VARCHAR(50), `company_email` VARCHAR(255),
  `currency` VARCHAR(10) DEFAULT 'KWD', `currency_ar` VARCHAR(10) DEFAULT 'د.ك',
  `country` VARCHAR(100) DEFAULT 'Kuwait',
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `social_insurance_rate` DECIMAL(5,2) DEFAULT 11.00,
  `overtime_rate` DECIMAL(4,2) DEFAULT 1.25,
  `holiday_overtime_rate` DECIMAL(4,2) DEFAULT 2.00,
  `work_days_per_month` INT DEFAULT 22, `work_hours_per_day` INT DEFAULT 8,
  `payroll_day` INT DEFAULT 25, `logo` VARCHAR(500) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `settings` (`id`) VALUES (1);

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL, `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','manager','hr','viewer') DEFAULT 'hr',
  `lang` ENUM('en','ar') DEFAULT 'en', `is_active` TINYINT(1) DEFAULT 1,
  `last_login` TIMESTAMP NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(20),
  `name_en` VARCHAR(255) NOT NULL, `name_ar` VARCHAR(255), `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `job_titles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `title_en` VARCHAR(255) NOT NULL,
  `title_ar` VARCHAR(255), `grade` VARCHAR(50),
  `min_salary` DECIMAL(15,3) DEFAULT 0, `max_salary` DECIMAL(15,3) DEFAULT 0,
  `working_hours` INT DEFAULT 8, `shift_type` ENUM('morning','evening','night','flexible') DEFAULT 'morning',
  `is_active` TINYINT(1) DEFAULT 1, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_no` VARCHAR(50) UNIQUE NOT NULL,
  `name_en` VARCHAR(255) NOT NULL, `name_ar` VARCHAR(255), `email` VARCHAR(255),
  `phone` VARCHAR(50), `nationality` VARCHAR(100), `civil_id` VARCHAR(100),
  `passport_no` VARCHAR(100), `gender` ENUM('male','female','other') DEFAULT 'male',
  `date_of_birth` DATE, `hire_date` DATE, `probation_end` DATE, `termination_date` DATE,
  `department_id` INT, `job_title_id` INT, `direct_manager_id` INT,
  `employment_type` ENUM('full_time','part_time','contractor','intern') DEFAULT 'full_time',
  `status` ENUM('active','on_leave','probation','terminated','suspended') DEFAULT 'active',
  `photo` VARCHAR(500) DEFAULT '', `bank_name` VARCHAR(255),
  `bank_account` VARCHAR(100), `iban` VARCHAR(100),
  `basic_salary` DECIMAL(15,3) NOT NULL DEFAULT 0, `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_dept`(`department_id`), INDEX `idx_status`(`status`),
  INDEX `idx_name`(`name_en`), INDEX `idx_empno`(`employee_no`),
  FULLTEXT INDEX `idx_search`(`name_en`,`name_ar`,`email`,`employee_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `allowance_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `name_en` VARCHAR(255) NOT NULL, `name_ar` VARCHAR(255),
  `calc_type` ENUM('fixed','percentage_basic','percentage_gross') DEFAULT 'fixed',
  `is_taxable` TINYINT(1) DEFAULT 0, `is_active` TINYINT(1) DEFAULT 1, `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_allowances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL,
  `allowance_type_id` INT NOT NULL, `amount` DECIMAL(15,3) DEFAULT 0,
  `effective_date` DATE, `is_active` TINYINT(1) DEFAULT 1,
  INDEX `idx_ea_emp_active`(`employee_id`,`is_active`),
  INDEX `idx_ea_type`(`allowance_type_id`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `deduction_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `name_en` VARCHAR(255) NOT NULL, `name_ar` VARCHAR(255),
  `calc_type` ENUM('fixed','percentage_basic','percentage_gross') DEFAULT 'fixed',
  `is_system` TINYINT(1) DEFAULT 0, `is_active` TINYINT(1) DEFAULT 1, `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_deductions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL,
  `deduction_type_id` INT NOT NULL, `amount` DECIMAL(15,3) DEFAULT 0,
  `effective_date` DATE, `end_date` DATE, `is_active` TINYINT(1) DEFAULT 1,
  INDEX `idx_ed_emp_active`(`employee_id`,`is_active`),
  INDEX `idx_ed_dates`(`employee_id`,`effective_date`,`end_date`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL,
  `loan_no` VARCHAR(50), `loan_amount` DECIMAL(15,3) NOT NULL,
  `number_of_installments` INT NOT NULL DEFAULT 12,
  `total_amount` DECIMAL(15,3) NOT NULL DEFAULT 0,
  `installment_amount` DECIMAL(15,3) NOT NULL DEFAULT 0,
  `amount_paid` DECIMAL(15,3) NOT NULL DEFAULT 0,
  `paid_installments` INT DEFAULT 0,
  `start_date` DATE NOT NULL, `last_payment_date` DATE NULL,
  `status` ENUM('active','paid','closed') DEFAULT 'active',
  `reason` TEXT, `notes` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_loan_emp_status`(`employee_id`,`status`),
  INDEX `idx_loan_status`(`status`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `name_en` VARCHAR(255) NOT NULL, `name_ar` VARCHAR(255),
  `days_per_year` DECIMAL(5,1) DEFAULT 30, `is_paid` TINYINT(1) DEFAULT 1,
  `requires_approval` TINYINT(1) DEFAULT 1, `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL, `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL, `end_date` DATE NOT NULL, `days` DECIMAL(5,1) DEFAULT 0,
  `reason` TEXT, `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` INT, `approved_at` TIMESTAMP NULL, `rejection_reason` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_emp_leave`(`employee_id`,`status`),
  INDEX `idx_leave_status`(`status`),
  INDEX `idx_leave_dates`(`start_date`,`end_date`),
  INDEX `idx_leave_created`(`created_at`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL, `check_in` TIME, `check_out` TIME,
  `status` ENUM('present','absent','late','half_day','leave','holiday','weekend') DEFAULT 'present',
  `late_minutes` INT DEFAULT 0, `overtime_hours` DECIMAL(5,2) DEFAULT 0,
  `working_hours` DECIMAL(5,2) DEFAULT 0, `notes` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_att`(`employee_id`,`attendance_date`),
  INDEX `idx_att_emp_date`(`employee_id`,`attendance_date`),
  INDEX `idx_att_date`(`attendance_date`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `period_year` INT NOT NULL, `period_month` INT NOT NULL,
  `period_label` VARCHAR(100), `start_date` DATE NOT NULL, `end_date` DATE NOT NULL,
  `payment_date` DATE,
  `status` ENUM('draft','processing','approved','paid','cancelled') DEFAULT 'draft',
  `total_gross` DECIMAL(18,3) DEFAULT 0, `total_deductions` DECIMAL(18,3) DEFAULT 0,
  `total_net` DECIMAL(18,3) DEFAULT 0, `employee_count` INT DEFAULT 0, `notes` TEXT,
  `created_by` INT, `approved_by` INT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `approved_at` TIMESTAMP NULL,
  UNIQUE KEY `unique_period`(`period_year`,`period_month`),
  INDEX `idx_period_status`(`status`),
  INDEX `idx_period_year_month`(`period_year`,`period_month`),
  INDEX `idx_period_created`(`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `payroll_period_id` INT NOT NULL, `employee_id` INT NOT NULL,
  `basic_salary` DECIMAL(15,3) DEFAULT 0, `total_allowances` DECIMAL(15,3) DEFAULT 0,
  `gross_salary` DECIMAL(15,3) DEFAULT 0, `overtime_hours` DECIMAL(5,2) DEFAULT 0,
  `overtime_amount` DECIMAL(15,3) DEFAULT 0, `absent_days` DECIMAL(5,1) DEFAULT 0,
  `absent_deduction` DECIMAL(15,3) DEFAULT 0, `late_minutes` INT DEFAULT 0,
  `late_deduction` DECIMAL(15,3) DEFAULT 0, `loan_deduction` DECIMAL(15,3) DEFAULT 0,
  `social_insurance` DECIMAL(15,3) DEFAULT 0, `tax_amount` DECIMAL(15,3) DEFAULT 0,
  `other_deductions` DECIMAL(15,3) DEFAULT 0, `total_deductions` DECIMAL(15,3) DEFAULT 0,
  `net_salary` DECIMAL(15,3) DEFAULT 0,
  `payment_status` ENUM('pending','transferred','paid','hold') DEFAULT 'pending',
  `notes` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_pi`(`payroll_period_id`,`employee_id`),
  INDEX `idx_period`(`payroll_period_id`), INDEX `idx_pi_emp`(`employee_id`),
  FOREIGN KEY(`payroll_period_id`) REFERENCES `payroll_periods`(`id`) ON DELETE CASCADE,
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_item_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `payroll_item_id` INT NOT NULL,
  `item_type` ENUM('allowance','deduction') NOT NULL, `ref_id` INT,
  `name_en` VARCHAR(255), `name_ar` VARCHAR(255), `amount` DECIMAL(15,3) DEFAULT 0,
  FOREIGN KEY(`payroll_item_id`) REFERENCES `payroll_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT, `action` VARCHAR(100),
  `table_name` VARCHAR(100), `record_id` INT, `old_values` JSON, `new_values` JSON,
  `ip_address` VARCHAR(50), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit`(`table_name`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default data
INSERT IGNORE INTO `departments` (`id`,`code`,`name_en`,`name_ar`) VALUES
(1,'HR','Human Resources','الموارد البشرية'),(2,'FIN','Finance','المالية'),
(3,'IT','Information Technology','تقنية المعلومات'),(4,'OPS','Operations','العمليات'),
(5,'MKT','Marketing','التسويق'),(6,'ADM','Administration','الإدارة');

INSERT IGNORE INTO `job_titles` (`id`,`title_en`,`title_ar`,`grade`,`min_salary`,`max_salary`) VALUES
(1,'General Manager','مدير عام','A1',3000,8000),(2,'Department Manager','مدير قسم','A2',2000,4000),
(3,'Senior Engineer','مهندس أول','B1',1200,2500),(4,'Engineer','مهندس','B2',800,1500),
(5,'Senior Accountant','محاسب أول','B1',1000,2000),(6,'Accountant','محاسب','B2',700,1200),
(7,'HR Specialist','أخصائي موارد بشرية','B2',700,1200),(8,'Administrator','إداري','C1',500,900),
(9,'IT Specialist','أخصائي تقنية','B2',800,1500),(10,'Technician','فني','C1',450,800);

INSERT IGNORE INTO `allowance_types` (`id`,`name_en`,`name_ar`,`calc_type`,`sort_order`) VALUES
(1,'Housing Allowance','بدل السكن','fixed',1),(2,'Transportation','بدل المواصلات','fixed',2),
(3,'Food Allowance','بدل الطعام','fixed',3),(4,'Mobile Allowance','بدل الهاتف','fixed',4),
(5,'Child Allowance','بدل الأبناء','fixed',5),(6,'Social Allowance','بدل الاجتماعي','fixed',6);

INSERT IGNORE INTO `deduction_types` (`id`,`name_en`,`name_ar`,`calc_type`,`is_system`,`sort_order`) VALUES
(1,'Social Insurance','تأمين اجتماعي','percentage_basic',1,1),
(2,'Income Tax','ضريبة الدخل','percentage_gross',1,2),
(3,'Loan Deduction','خصم قرض','fixed',1,3),
(4,'Absence Deduction','خصم غياب','fixed',1,4),
(5,'Late Deduction','خصم تأخر','fixed',1,5);

INSERT IGNORE INTO `leave_types` (`id`,`name_en`,`name_ar`,`days_per_year`,`is_paid`) VALUES
(1,'Annual Leave','إجازة سنوية',30,1),(2,'Sick Leave','إجازة مرضية',15,1),
(3,'Emergency Leave','إجازة طارئة',5,1),(4,'Maternity Leave','إجازة أمومة',70,1),
(5,'Paternity Leave','إجازة أبوة',3,1),(6,'Unpaid Leave','إجازة بدون راتب',0,0);

-- Default admin user (email: admin@payroll.local, password: admin123)
INSERT IGNORE INTO `users` (`id`,`name`,`email`,`password`,`role`,`is_active`,`lang`) VALUES
(1,'System Administrator','admin@payroll.local','$2y$10$IPT3O3vzPbkaqMaLVeJMG.SdqXEjZ3b0gVC/OkWEOp8G064LIDauK','admin',1,'en');

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
  INDEX `idx_emp_bonus`(`employee_id`,`status`,`period_year`,`period_month`),
  INDEX `idx_bonus_period`(`period_year`,`period_month`),
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
  INDEX `idx_gratuity_status`(`status`),
  INDEX `idx_gratuity_created`(`created_at`),
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

-- Leave Balance Table - Track employee leave balances per year
CREATE TABLE IF NOT EXISTS `leave_balance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `year` INT NOT NULL,
  `total_days` DECIMAL(5,1) NOT NULL DEFAULT 0,
  `used_days` DECIMAL(5,1) NOT NULL DEFAULT 0,
  `remaining_days` DECIMAL(5,1) GENERATED ALWAYS AS (total_days - used_days) STORED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_emp_leave_year` (`employee_id`, `leave_type_id`, `year`),
  INDEX `idx_emp_year` (`employee_id`, `year`),
  FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY(`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
