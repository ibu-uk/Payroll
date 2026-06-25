<?php
// ── Simple caching for frequently accessed data ─────────────────────────────────
$_cache = [];

function cacheGet(string $key, callable $callback, int $ttl = 300) {
    global $_cache;
    if (isset($_cache[$key]) && $_cache[$key]['time'] > time() - $ttl) {
        return $_cache[$key]['data'];
    }
    $data = $callback();
    $_cache[$key] = ['data' => $data, 'time' => time()];
    return $data;
}

function cacheClear(string $key = null) {
    global $_cache;
    if ($key) {
        unset($_cache[$key]);
    } else {
        $_cache = [];
    }
}

// ── Formatting helpers ────────────────────────────────────────────────────────

function money(float $amount, string $dec = ','): string {
    $s = cacheGet('settings_currency', function() {
        return DB::row("SELECT currency,currency_ar FROM settings WHERE id=1");
    }, 300);
    $cur = lang() === 'ar' ? ($s['currency_ar'] ?? 'د.ك') : ($s['currency'] ?? 'KWD');
    return number_format($amount, 3, '.', $dec) . ' ' . $cur;
}

function fdate(?string $date, string $fmt = 'd/m/Y'): string {
    if (!$date || $date === '0000-00-00') return '-';
    return date($fmt, strtotime($date));
}

function ftime(?string $t): string {
    if (!$t) return '-';
    return date('h:i A', strtotime("2000-01-01 $t"));
}

function statusBadge(string $status, string $module = 'general'): string {
    $map = [
        'active'       => ['success', '●'],
        'inactive'     => ['secondary', '○'],
        'terminated'   => ['danger', '✕'],
        'on_leave'     => ['warning', '☽'],
        'probation'    => ['info', '◐'],
        'suspended'    => ['danger', '⊘'],
        'draft'        => ['secondary', '○'],
        'processing'   => ['warning', '⟳'],
        'approved'     => ['primary', '✔'],
        'paid'         => ['success', '✔'],
        'pending'      => ['warning', '…'],
        'rejected'     => ['danger', '✕'],
        'cancelled'    => ['secondary', '✕'],
        'present'      => ['success', '●'],
        'absent'       => ['danger', '✕'],
        'late'         => ['warning', '!'],
        'half_day'     => ['info', '½'],
        'leave'        => ['primary', '☽'],
    ];
    [$color, $icon] = $map[$status] ?? ['secondary', '?'];
    $label = t($status) ?: ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$color}\">{$icon} {$label}</span>";
}

function empName(array $emp): string {
    return lang() === 'ar' && !empty($emp['name_ar']) ? $emp['name_ar'] : $emp['name_en'];
}

function deptName(array $dept): string {
    return lang() === 'ar' && !empty($dept['name_ar']) ? $dept['name_ar'] : $dept['name_en'];
}

// ── Payroll calculation engine ────────────────────────────────────────────────

function calculatePayrollItem(int $employeeId, int $periodId, int $year, int $month): array {
    $settings = DB::row("SELECT * FROM settings WHERE id=1");
    $emp = DB::row("SELECT * FROM employees WHERE id=?", [$employeeId]);
    if (!$emp) return [];

    $basic = (float) $emp['basic_salary'];

    // Allowances
    $allowanceRows = DB::rows("
        SELECT ea.amount, ea.allowance_type_id, at.calc_type, at.name_en, at.name_ar, at.is_taxable
        FROM employee_allowances ea
        JOIN allowance_types at ON at.id = ea.allowance_type_id
        WHERE ea.employee_id = ? AND ea.is_active = 1", [$employeeId]);

    $allowanceDetails = [];
    $totalAllowances = 0;
    foreach ($allowanceRows as $a) {
        $amt = match($a['calc_type']) {
            'percentage_basic' => $basic * $a['amount'] / 100,
            default            => (float) $a['amount'],
        };
        $totalAllowances += $amt;
        $allowanceDetails[] = ['type' => 'allowance', 'ref_id' => $a['allowance_type_id'], 'name_en' => $a['name_en'], 'name_ar' => $a['name_ar'], 'amount' => $amt];
    }

    // Approved bonuses for the period
    $bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
    $bonusAmt = 0;
    if ($bonusesTableExists) {
        $bonuses = DB::val("
            SELECT COALESCE(SUM(amount), 0)
            FROM bonuses
            WHERE employee_id=? AND status='approved'
            AND period_year=? AND period_month=?",
            [$employeeId, $year, $month]);
        $bonusAmt = (float)$bonuses;
    }

    $gross = $basic + $totalAllowances + $bonusAmt;

    // Get employee's job title for working hours
    $jobInfo = DB::row("SELECT e.job_title_id, jt.working_hours FROM employees e LEFT JOIN job_titles jt ON jt.id = e.job_title_id WHERE e.id=?", [$employeeId]);
    $jobWorkHours = (int)($jobInfo['working_hours'] ?? 8); // Default to 8 if not set

    // Attendance summary
    $att = DB::row("
        SELECT
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(late_minutes) as late_minutes,
            SUM(overtime_hours) as overtime_hours
        FROM attendance
        WHERE employee_id=? AND YEAR(attendance_date)=? AND MONTH(attendance_date)=?",
        [$employeeId, $year, $month]);

    $workDays   = (int) $settings['work_days_per_month'];
    $workHours  = $jobWorkHours; // Use job-specific working hours
    $dailyRate  = $workDays > 0 ? $basic / $workDays : 0;
    $hourlyRate = ($workHours > 0 && $workDays > 0) ? $basic / ($workDays * $workHours) : 0;
    $minuteRate = $hourlyRate / 60;

    $absentDays    = (float)($att['absent_days'] ?? 0);
    $lateMinutes   = (int)($att['late_minutes'] ?? 0);
    $overtimeHours = (float)($att['overtime_hours'] ?? 0);
    $absentDed     = round($dailyRate * $absentDays, 3);
    $lateDed       = round($minuteRate * $lateMinutes, 3);
    
    // Check for holiday overtime - use holiday rate if employee worked on holidays
    $holidayOvertimeRate = (float)($settings['holiday_overtime_rate'] ?? 2.0);
    $regularOvertimeRate = (float)$settings['overtime_rate'];
    
    // Get holiday work hours for the month
    $holidayAtt = DB::rows("
        SELECT SUM(overtime_hours) as holiday_ot 
        FROM attendance 
        WHERE employee_id=? AND YEAR(attendance_date)=? AND MONTH(attendance_date)=? 
        AND status='holiday' AND overtime_hours > 0",
        [$employeeId, $year, $month]);
    $holidayOvertimeHours = (float)($holidayAtt[0]['holiday_ot'] ?? 0);
    $regularOvertimeHours = $overtimeHours - $holidayOvertimeHours;
    
    $overtimeAmt = round(
        $hourlyRate * $regularOvertimeRate * $regularOvertimeHours +
        $hourlyRate * $holidayOvertimeRate * $holidayOvertimeHours,
        3
    );

    // Social insurance
    $siRate = (float)$settings['social_insurance_rate'];
    $siAmt  = round($basic * $siRate / 100, 3);

    // Tax
    $taxRate = (float)$settings['tax_rate'];
    $taxAmt  = round($gross * $taxRate / 100, 3);

    // Loan deductions
    $loans = DB::rows("
        SELECT id, installment_amount FROM loans
        WHERE employee_id=? AND status='active' AND start_date<=?",
        [$employeeId, "$year-$month-01"]);
    $loanDed = 0;
    foreach ($loans as $l) $loanDed += (float)$l['installment_amount'];
    $loanDed = round($loanDed, 3);

    // Custom deductions
    $deductionRows = DB::rows("
        SELECT ed.amount, ed.deduction_type_id, dt.calc_type, dt.name_en, dt.name_ar, dt.is_system
        FROM employee_deductions ed
        JOIN deduction_types dt ON dt.id = ed.deduction_type_id
        WHERE ed.employee_id=? AND ed.is_active=1 AND dt.is_system=0
        AND (ed.end_date IS NULL OR ed.end_date >= ?)",
        [$employeeId, "$year-$month-01"]);

    $dedDetails = [];
    $otherDeds = 0;
    foreach ($deductionRows as $d) {
        $amt = match($d['calc_type']) {
            'percentage_basic' => $basic * $d['amount'] / 100,
            'percentage_gross' => $gross * $d['amount'] / 100,
            default            => (float)$d['amount'],
        };
        $otherDeds += $amt;
        $dedDetails[] = ['type' => 'deduction', 'ref_id' => $d['deduction_type_id'], 'name_en' => $d['name_en'], 'name_ar' => $d['name_ar'], 'amount' => $amt];
    }

    $totalDeds = round($siAmt + $taxAmt + $loanDed + $absentDed + $lateDed + $otherDeds, 3);
    $netSalary = round($gross + $overtimeAmt - $totalDeds, 3);

    return [
        'payroll_period_id' => $periodId,
        'employee_id'       => $employeeId,
        'basic_salary'      => $basic,
        'total_allowances'  => round($totalAllowances, 3),
        'gross_salary'      => round($gross, 3),
        'overtime_hours'    => $overtimeHours,
        'overtime_amount'   => $overtimeAmt,
        'absent_days'       => $absentDays,
        'absent_deduction'  => $absentDed,
        'late_minutes'      => $lateMinutes,
        'late_deduction'    => $lateDed,
        'loan_deduction'    => $loanDed,
        'social_insurance'  => $siAmt,
        'tax_amount'        => $taxAmt,
        'other_deductions'  => round($otherDeds, 3),
        'total_deductions'  => $totalDeds,
        'net_salary'        => $netSalary,
        'allowance_details' => $allowanceDetails,
        'deduction_details' => $dedDetails,
    ];
}

function monthName(int $m, string $lang = 'en'): string {
    $en = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $ar = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $idx = $m - 1;
    return $lang === 'ar' ? ($ar[$idx] ?? '') : ($en[$idx] ?? '');
}

function generateEmpNo(): string {
    $last = DB::val("SELECT MAX(CAST(SUBSTRING(employee_no, 4) AS UNSIGNED)) FROM employees WHERE employee_no LIKE 'EMP%'");
    return 'EMP' . str_pad(($last ?? 0) + 1, 5, '0', STR_PAD_LEFT);
}

function uploadFile(array $file, string $dir, string $prefix = ''): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMG)) return null;
    if ($file['size'] > MAX_FILE_SIZE) return null;
    $name = $prefix . uniqid() . '.' . $ext;
    $path = UPLOAD_DIR . $dir . '/' . $name;
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
    return move_uploaded_file($file['tmp_name'], $path) ? $name : null;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function j(mixed $v): string  { return json_encode($v, JSON_UNESCAPED_UNICODE); }
function redirect(string $url): never { header("Location: $url"); exit; }
function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function post(string $key, mixed $default = ''): mixed { return $_POST[$key] ?? $default; }
function get(string $key, mixed $default = ''): mixed  { return $_GET[$key] ?? $default; }
