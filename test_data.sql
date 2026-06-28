-- Test Data for Payroll System
-- Run this in phpMyAdmin to populate test employees (2024-2026)
-- Make sure to run this AFTER importing install.sql

-- ── Departments ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO departments (id, name_en, name_ar, is_active) VALUES
(1, 'Engineering', 'الهندسة', 1),
(2, 'Finance', 'المالية', 1),
(3, 'Human Resources', 'الموارد البشرية', 1),
(4, 'Sales', 'المبيعات', 1),
(5, 'Marketing', 'التسويق', 1),
(6, 'Operations', 'العمليات', 1);

-- ── Job Titles ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO job_titles (id, title_en, title_ar, working_hours, is_active) VALUES
(1, 'Software Engineer', 'مهندس برمجيات', 8, 1),
(2, 'Senior Software Engineer', 'مهندس برمجيات أول', 8, 1),
(3, 'Accountant', 'محاسب', 8, 1),
(4, 'Finance Manager', 'مدير مالي', 8, 1),
(5, 'HR Specialist', 'أخصائي موارد بشرية', 8, 1),
(6, 'HR Manager', 'مدير موارد بشرية', 8, 1),
(7, 'Sales Executive', 'مندوب مبيعات', 8, 1),
(8, 'Sales Manager', 'مدير مبيعات', 8, 1),
(9, 'Marketing Specialist', 'أخصائي تسويق', 8, 1),
(10, 'Operations Manager', 'مدير عمليات', 8, 1);

-- ── Employees (2024-2026 hire dates) ───────────────────────────────────────────
INSERT IGNORE INTO employees (id, employee_no, name_en, name_ar, email, phone, nationality, 
    hire_date, basic_salary, department_id, job_title_id, status, gender) VALUES
-- 2024 Hires (Long-serving employees)
(1, 'EMP001', 'Ahmed Al-Rashid', 'أحمد الراشد', 'ahmed.alrashid@test.com', '+965 5555 0001', 'Kuwait', '2024-01-15', 850.000, 1, 2, 'active', 'male'),
(2, 'EMP002', 'Sarah Abdullah', 'سارة عبدالله', 'sarah.abdullah@test.com', '+965 5555 0002', 'Kuwait', '2024-02-01', 750.000, 2, 4, 'active', 'female'),
(3, 'EMP003', 'Mohammed Al-Salem', 'محمد السالم', 'mohammed.alsalem@test.com', '+965 5555 0003', 'Kuwait', '2024-03-10', 650.000, 5, 6, 'active', 'male'),
(4, 'EMP004', 'Fatima Hassan', 'فاطمة حسن', 'fatima.hassan@test.com', '+965 5555 0004', 'Kuwait', '2024-04-20', 600.000, 3, 5, 'active', 'female'),
(5, 'EMP005', 'Khalid Al-Otaibi', 'خالد العتيبي', 'khalid.alotaibi@test.com', '+965 5555 0005', 'Kuwait', '2024-05-15', 900.000, 1, 2, 'active', 'male'),
(6, 'EMP006', 'Noura Al-Kandari', 'نورة الكندري', 'noura.alkandari@test.com', '+965 5555 0006', 'Kuwait', '2024-06-01', 700.000, 4, 8, 'active', 'female'),
(7, 'EMP007', 'Omar Al-Mutairi', 'عمر المطيري', 'omar.almutairi@test.com', '+965 5555 0007', 'Kuwait', '2024-07-10', 800.000, 6, 10, 'active', 'male'),
(8, 'EMP008', 'Layla Al-Ajmi', 'ليلى العجمي', 'layla.alajmi@test.com', '+965 5555 0008', 'Kuwait', '2024-08-15', 550.000, 5, 9, 'active', 'female'),
(9, 'EMP009', 'Fahad Al-Shammari', 'فهد الشمري', 'fahad.alshammari@test.com', '+965 5555 0009', 'Kuwait', '2024-09-01', 750.000, 4, 7, 'active', 'male'),
(10, 'EMP010', 'Amira Al-Harbi', 'أميرة الحربي', 'amira.alharbi@test.com', '+965 5555 0010', 'Kuwait', '2024-10-20', 680.000, 2, 3, 'active', 'female'),

-- 2025 Hires (Mid-tenure employees)
(11, 'EMP011', 'Yousef Al-Bader', 'يوسف البدر', 'yousef.albader@test.com', '+965 5555 0011', 'Kuwait', '2025-01-05', 820.000, 1, 2, 'active', 'male'),
(12, 'EMP012', 'Reem Al-Ansari', 'ريم الأنصاري', 'reem.alansari@test.com', '+965 5555 0012', 'Kuwait', '2025-02-15', 720.000, 3, 5, 'active', 'female'),
(13, 'EMP013', 'Abdullah Al-Enezi', 'عبدالله العنزي', 'abdullah.alenezi@test.com', '+965 5555 0013', 'Kuwait', '2025-03-20', 780.000, 4, 8, 'active', 'male'),
(14, 'EMP014', 'Hind Al-Mutawa', 'هند المطوع', 'hind.almutawa@test.com', '+965 5555 0014', 'Kuwait', '2025-04-10', 640.000, 5, 9, 'active', 'female'),
(15, 'EMP015', 'Saad Al-Dosari', 'سعد الدوسري', 'saad.aldosari@test.com', '+965 5555 0015', 'Kuwait', '2025-05-01', 880.000, 1, 2, 'active', 'male'),
(16, 'EMP016', 'Mona Al-Saeed', 'منى السعيد', 'mona.alsaeed@test.com', '+965 5555 0016', 'Kuwait', '2025-06-15', 690.000, 2, 4, 'active', 'female'),
(17, 'EMP017', 'Turki Al-Qahtani', 'تركي القحطاني', 'turki.alqahtani@test.com', '+965 5555 0017', 'Kuwait', '2025-07-20', 760.000, 6, 10, 'active', 'male'),
(18, 'EMP018', 'Dalal Al-Fahad', 'دلال الفهد', 'dalal.alfahad@test.com', '+965 5555 0018', 'Kuwait', '2025-08-05', 580.000, 5, 9, 'active', 'female'),
(19, 'EMP019', 'Majed Al-Rashdan', 'ماجد الرشدان', 'majed.alrashdan@test.com', '+965 5555 0019', 'Kuwait', '2025-09-10', 740.000, 4, 7, 'active', 'male'),
(20, 'EMP020', 'Shaima Al-Khabbaz', 'شيمة الخباز', 'shaima.alkhabbaz@test.com', '+965 5555 0020', 'Kuwait', '2025-10-25', 660.000, 3, 5, 'active', 'female'),

-- 2026 Hires (New employees)
(21, 'EMP021', 'Bader Al-Hamad', 'بادر الحمد', 'bader.alhamad@test.com', '+965 5555 0021', 'Kuwait', '2026-01-12', 810.000, 1, 2, 'active', 'male'),
(22, 'EMP022', 'Najat Al-Saleh', 'نجاة الصالح', 'najat.alsaleh@test.com', '+965 5555 0022', 'Kuwait', '2026-02-08', 710.000, 2, 3, 'active', 'female'),
(23, 'EMP023', 'Faisal Al-Shuhaib', 'فيصل الشهيب', 'faisal.alshuhaib@test.com', '+965 5555 0023', 'Kuwait', '2026-03-15', 770.000, 4, 8, 'active', 'male'),
(24, 'EMP024', 'Latifa Al-Mansour', 'لطيفة المنصور', 'latifa.almansour@test.com', '+965 5555 0024', 'Kuwait', '2026-04-20', 630.000, 5, 9, 'active', 'female'),
(25, 'EMP025', 'Nawaf Al-Azmi', 'نواف العزمي', 'nawaf.alazmi@test.com', '+965 5555 0025', 'Kuwait', '2026-05-10', 870.000, 1, 2, 'active', 'male'),
(26, 'EMP026', 'Sana Al-Meer', 'سنا المير', 'sana.almeer@test.com', '+965 5555 0026', 'Kuwait', '2026-06-01', 680.000, 3, 5, 'active', 'female'),

-- Terminated employee (for gratuity testing)
(27, 'EMP027', 'Jassim Al-Sabah', 'جاسم الصباح', 'jassim.alsabah@test.com', '+965 5555 0027', 'Kuwait', '2024-06-15', 950.000, 1, 2, 'terminated', 'male'),

-- On leave employee
(28, 'EMP028', 'Salwa Al-Mousa', 'سلوى الموسى', 'salwa.almousa@test.com', '+965 5555 0028', 'Kuwait', '2024-12-01', 720.000, 2, 4, 'on_leave', 'female');

-- ── Employee Allowances ───────────────────────────────────────────────────────
INSERT IGNORE INTO allowance_types (id, name_en, name_ar, calc_type, is_taxable, is_active, sort_order) VALUES
(1, 'Housing Allowance', 'بدل سكن', 'fixed', 0, 1, 1),
(2, 'Transportation Allowance', 'بدل مواصلات', 'fixed', 0, 1, 2),
(3, 'Medical Allowance', 'بدل علاج', 'fixed', 0, 1, 3),
(4, 'Performance Bonus %', 'مكافأة أداء %', 'percentage_basic', 1, 1, 4);

INSERT IGNORE INTO employee_allowances (employee_id, allowance_type_id, amount, is_active) VALUES
-- 2024 employees with allowances
(1, 1, 150.000, 1), (1, 2, 80.000, 1), (1, 3, 50.000, 1),
(2, 1, 150.000, 1), (2, 2, 80.000, 1),
(3, 1, 150.000, 1), (3, 2, 80.000, 1), (3, 3, 50.000, 1),
(4, 1, 150.000, 1), (4, 2, 80.000, 1),
(5, 1, 150.000, 1), (5, 2, 80.000, 1), (5, 3, 50.000, 1),
(6, 1, 150.000, 1), (6, 2, 80.000, 1),
(7, 1, 150.000, 1), (7, 2, 80.000, 1), (7, 3, 50.000, 1),
(8, 1, 150.000, 1), (8, 2, 80.000, 1),
(9, 1, 150.000, 1), (9, 2, 80.000, 1),
(10, 1, 150.000, 1), (10, 2, 80.000, 1), (10, 3, 50.000, 1),
-- 2025 employees
(11, 1, 150.000, 1), (11, 2, 80.000, 1),
(12, 1, 150.000, 1), (12, 2, 80.000, 1), (12, 3, 50.000, 1),
(13, 1, 150.000, 1), (13, 2, 80.000, 1),
(14, 1, 150.000, 1), (14, 2, 80.000, 1),
(15, 1, 150.000, 1), (15, 2, 80.000, 1), (15, 3, 50.000, 1),
(16, 1, 150.000, 1), (16, 2, 80.000, 1),
(17, 1, 150.000, 1), (17, 2, 80.000, 1), (17, 3, 50.000, 1),
(18, 1, 150.000, 1), (18, 2, 80.000, 1),
(19, 1, 150.000, 1), (19, 2, 80.000, 1),
(20, 1, 150.000, 1), (20, 2, 80.000, 1), (20, 3, 50.000, 1),
-- 2026 employees
(21, 1, 150.000, 1), (21, 2, 80.000, 1),
(22, 1, 150.000, 1), (22, 2, 80.000, 1), (22, 3, 50.000, 1),
(23, 1, 150.000, 1), (23, 2, 80.000, 1),
(24, 1, 150.000, 1), (24, 2, 80.000, 1),
(25, 1, 150.000, 1), (25, 2, 80.000, 1), (25, 3, 50.000, 1),
(26, 1, 150.000, 1), (26, 2, 80.000, 1);

-- ── Attendance Records (Sample for current month) ───────────────────────────────
INSERT IGNORE INTO attendance (employee_id, attendance_date, status, check_in, check_out, late_minutes, overtime_hours) VALUES
-- June 2026 attendance samples
(1, '2026-06-01', 'present', '08:00:00', '17:00:00', 0, 0),
(1, '2026-06-02', 'present', '08:00:00', '17:00:00', 0, 0),
(1, '2026-06-03', 'present', '08:00:00', '17:00:00', 0, 0),
(1, '2026-06-04', 'late', '08:45:00', '17:00:00', 45, 0),
(1, '2026-06-05', 'present', '08:00:00', '18:30:00', 0, 1.5),
(2, '2026-06-01', 'present', '08:00:00', '17:00:00', 0, 0),
(2, '2026-06-02', 'present', '08:00:00', '17:00:00', 0, 0),
(2, '2026-06-03', 'absent', NULL, NULL, 0, 0),
(2, '2026-06-04', 'present', '08:00:00', '17:00:00', 0, 0),
(2, '2026-06-05', 'present', '08:00:00', '17:00:00', 0, 0),
(3, '2026-06-01', 'present', '08:00:00', '17:00:00', 0, 0),
(3, '2026-06-02', 'present', '08:00:00', '17:00:00', 0, 0),
(3, '2026-06-03', 'present', '08:00:00', '17:00:00', 0, 0),
(3, '2026-06-04', 'present', '08:00:00', '17:00:00', 0, 0),
(3, '2026-06-05', 'present', '08:00:00', '17:00:00', 0, 0);

-- ── Leave Types ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO leave_types (id, name_en, name_ar, days_per_year, is_paid, is_active) VALUES
(1, 'Annual Leave', 'إجازة سنوية', 30, 1, 1),
(2, 'Sick Leave', 'إجازة مرضية', 15, 1, 1),
(3, 'Paternity Leave', 'إجازة أبوة', 3, 1, 1),
(4, 'Unpaid Leave', 'إجازة بدون راتب', 0, 0, 1);

-- ── Leave Requests ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO leave_requests (employee_id, leave_type_id, start_date, end_date, status, reason, created_at) VALUES
(1, 1, '2026-06-10', '2026-06-15', 'approved', 'Family vacation', '2026-06-01'),
(2, 2, '2026-06-05', '2026-06-06', 'approved', 'Medical appointment', '2026-06-03'),
(3, 1, '2026-06-20', '2026-06-25', 'pending', 'Personal trip', '2026-06-05'),
(4, 3, '2026-06-01', '2026-06-03', 'approved', 'Newborn child', '2026-05-30'),
(5, 1, '2026-06-15', '2026-06-20', 'rejected', 'Insufficient leave balance', '2026-06-05');

-- ── Loans ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO loans (employee_id, loan_amount, number_of_installments, installment_amount, total_amount, amount_paid, paid_installments, start_date, status, reason) VALUES
(1, 2000.000, 10, 200.000, 2000.000, 0, 0, '2026-01-01', 'active', 'Personal loan'),
(2, 1500.000, 10, 150.000, 1500.000, 0, 0, '2026-02-01', 'active', 'Emergency fund'),
(5, 3000.000, 10, 300.000, 3000.000, 0, 0, '2026-03-01', 'active', 'Home renovation'),
(11, 1800.000, 10, 180.000, 1800.000, 0, 0, '2026-04-01', 'active', 'Car repair'),
(15, 2500.000, 10, 250.000, 2500.000, 0, 0, '2026-05-01', 'active', 'Medical expenses');

-- ── Bonuses ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO bonuses (employee_id, amount, bonus_type, period_year, period_month, status, description, created_at) VALUES
(1, 500.000, 'performance', 2026, 5, 'approved', 'Excellent performance', '2026-05-30'),
(5, 750.000, 'project_bonus', 2026, 5, 'approved', 'Project completion', '2026-05-30'),
(11, 400.000, 'performance', 2026, 5, 'approved', 'Good performance', '2026-05-30'),
(15, 600.000, 'sales_commission', 2026, 5, 'approved', 'Sales target achieved', '2026-05-30'),
(21, 300.000, 'signing_bonus', 2026, 1, 'approved', 'Welcome bonus', '2026-01-15');

-- ── Payroll Periods (May 2026 - ready for processing) ───────────────────────────
INSERT IGNORE INTO payroll_periods (id, period_year, period_month, period_label, start_date, end_date, status, total_gross, total_deductions, total_net, employee_count, created_at) VALUES
(1, 2026, 5, 'May 2026', '2026-05-01', '2026-05-31', 'draft', 0, 0, 0, 0, '2026-06-01'),
(2, 2026, 4, 'April 2026', '2026-04-01', '2026-04-30', 'approved', 18500.000, 3300.000, 15200.000, 20, '2026-05-01'),
(3, 2026, 3, 'March 2026', '2026-03-01', '2026-03-31', 'paid', 18000.000, 3200.000, 14800.000, 20, '2026-04-01');

-- ── Deduction Types ────────────────────────────────────────────────────────────
INSERT IGNORE INTO deduction_types (id, name_en, name_ar, calc_type, is_system, is_active) VALUES
(1, 'Social Insurance', 'تأمينات اجتماعية', 'percentage_basic', 1, 1),
(2, 'Health Insurance', 'تأمين صحي', 'fixed', 0, 1),
(3, 'Union Fee', 'رسوم نقابة', 'fixed', 0, 1);

-- ── Employee Deductions ────────────────────────────────────────────────────────
INSERT IGNORE INTO employee_deductions (employee_id, deduction_type_id, amount, is_active, effective_date) VALUES
(1, 2, 25.000, 1, '2024-01-15'),
(2, 2, 25.000, 1, '2024-02-01'),
(3, 2, 25.000, 1, '2024-03-10'),
(5, 2, 25.000, 1, '2024-05-15'),
(11, 2, 25.000, 1, '2025-01-05'),
(15, 2, 25.000, 1, '2025-05-01'),
(21, 2, 25.000, 1, '2026-01-12');

-- ── Settings ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO settings (id, company_name_en, company_name_ar, currency, currency_ar, 
    work_days_per_month, work_hours_per_day, overtime_rate, holiday_overtime_rate, social_insurance_rate, tax_rate) VALUES
(1, 'PayrollPro Demo', 'باي رول برو تجريبي', 'KWD', 'د.ك', 26, 8, 1.5, 2.0, 11.5, 0.0);
