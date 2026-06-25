-- Migration: Optimize database for 10,000+ employees
-- Run this to add proper indexes and optimizations for large-scale operations

-- Add composite indexes for employees table (common query patterns)
ALTER TABLE `employees` ADD INDEX `idx_status_dept`(`status`,`department_id`);
ALTER TABLE `employees` ADD INDEX `idx_status_job`(`status`,`job_title_id`);
ALTER TABLE `employees` ADD INDEX `idx_hire_date`(`hire_date`);
ALTER TABLE `employees` ADD INDEX `idx_employment_type`(`employment_type`);

-- Add indexes for allowance/deduction tables
ALTER TABLE `employee_allowances` ADD INDEX `idx_emp_active`(`employee_id`,`is_active`);
ALTER TABLE `employee_allowances` ADD INDEX `idx_effective_date`(`effective_date`);

ALTER TABLE `employee_deductions` ADD INDEX `idx_emp_active`(`employee_id`,`is_active`);
ALTER TABLE `employee_deductions` ADD INDEX `idx_effective_range`(`effective_date`,`end_date`);

-- Add indexes for loans table
ALTER TABLE `loans` ADD INDEX `idx_emp_status`(`employee_id`,`status`);
ALTER TABLE `loans` ADD INDEX `idx_start_date`(`start_date`);

-- Add indexes for payroll items
ALTER TABLE `payroll_items` ADD INDEX `idx_payment_status`(`payment_status`);
ALTER TABLE `payroll_items` ADD INDEX `idx_period_status`(`payroll_period_id`,`payment_status`);

-- Add composite index for attendance for date range queries
ALTER TABLE `attendance` ADD INDEX `idx_date_status`(`attendance_date`,`status`);

-- Add index for leave requests date range
ALTER TABLE `leave_requests` ADD INDEX `idx_date_range`(`start_date`,`end_date`);

-- Optimize job titles for filtering
ALTER TABLE `job_titles` ADD INDEX `idx_active_grade`(`is_active`,`grade`);

-- Add index for users table
ALTER TABLE `users` ADD INDEX `idx_active_role`(`is_active`,`role`);
