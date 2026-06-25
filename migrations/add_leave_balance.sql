-- Migration: Add Leave Balance Tracking
-- Run this to track employee leave balances per year

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

-- Initialize leave balances for all active employees for current year
INSERT IGNORE INTO leave_balance (employee_id, leave_type_id, year, total_days)
SELECT e.id, lt.id, YEAR(CURDATE()), lt.days_per_year
FROM employees e
CROSS JOIN leave_types lt
WHERE e.status = 'active' AND lt.is_active = 1;

-- Trigger to auto-update used_days when leave is approved
DELIMITER $$

DROP TRIGGER IF EXISTS `after_leave_approve`$$

CREATE TRIGGER `after_leave_approve`
AFTER UPDATE ON `leave_requests`
FOR EACH ROW
BEGIN
  IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
    INSERT INTO leave_balance (employee_id, leave_type_id, year, total_days, used_days)
    VALUES (NEW.employee_id, NEW.leave_type_id, YEAR(NEW.start_date),
            (SELECT days_per_year FROM leave_types WHERE id = NEW.leave_type_id),
            NEW.days)
    ON DUPLICATE KEY UPDATE
      used_days = used_days + NEW.days,
      updated_at = NOW();
  END IF;

  IF NEW.status != 'approved' AND OLD.status = 'approved' THEN
    -- Revert used days if approval is cancelled
    UPDATE leave_balance
    SET used_days = used_days - OLD.days,
        updated_at = NOW()
    WHERE employee_id = OLD.employee_id
      AND leave_type_id = OLD.leave_type_id
      AND year = YEAR(OLD.start_date);
  END IF;
END$$

DELIMITER ;
