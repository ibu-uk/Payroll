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

// File-based cache for cross-request caching
function cacheFileGet(string $key, callable $callback, int $ttl = 300) {
    $file = CACHE_DIR . 'cache_' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        $data = json_decode(file_get_contents($file), true);
        if ($data !== null) return $data;
    }
    $data = $callback();
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
    file_put_contents($file, json_encode($data), LOCK_EX);
    return $data;
}

function cacheFileClear(string $key = null): void {
    if ($key) {
        $file = CACHE_DIR . 'cache_' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
        if (file_exists($file)) unlink($file);
    } else {
        foreach (glob(CACHE_DIR . 'cache_*.json') as $f) {
            unlink($f);
        }
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
    static $settings = null;
    if ($settings === null) $settings = DB::row("SELECT * FROM settings WHERE id=1");
    $emp = DB::row("SELECT e.*, jt.working_hours FROM employees e LEFT JOIN job_titles jt ON jt.id=e.job_title_id WHERE e.id=?", [$employeeId]);
    if (!$emp) return [];

    $basic = (float) $emp['basic_salary'];
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate   = date('Y-m-t', strtotime($startDate));

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

    // Use job-specific working hours
    $jobWorkHours = (int)($emp['working_hours'] ?? 8);

    // Attendance summary using date range for index usage
    $att = DB::row("
        SELECT
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(late_minutes) as late_minutes,
            SUM(overtime_hours) as overtime_hours,
            SUM(CASE WHEN status='holiday' THEN overtime_hours ELSE 0 END) as holiday_ot
        FROM attendance
        WHERE employee_id=? AND attendance_date BETWEEN ? AND ?",
        [$employeeId, $startDate, $endDate]);

    $workDays   = (int) $settings['work_days_per_month'];
    $workHours  = $jobWorkHours;
    $dailyRate  = $workDays > 0 ? $basic / $workDays : 0;
    $hourlyRate = ($workHours > 0 && $workDays > 0) ? $basic / ($workDays * $workHours) : 0;
    $minuteRate = $hourlyRate / 60;

    $absentDays    = (float)($att['absent_days'] ?? 0);
    $lateMinutes   = (int)($att['late_minutes'] ?? 0);
    $overtimeHours = (float)($att['overtime_hours'] ?? 0);
    $absentDed     = round($dailyRate * $absentDays, 3);
    $lateDed       = round($minuteRate * $lateMinutes, 3);
    
    $holidayOvertimeRate = (float)($settings['holiday_overtime_rate'] ?? 2.0);
    $regularOvertimeRate = (float)$settings['overtime_rate'];
    
    $holidayOvertimeHours = (float)($att['holiday_ot'] ?? 0);
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
        [$employeeId, $startDate]);
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
        [$employeeId, $startDate]);

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

/**
 * Batch payroll calculation - pre-fetches all data for a list of employees
 * to avoid N+1 query issues during payroll processing.
 */
function calculatePayrollBatch(array $employeeIds, int $periodId, int $year, int $month): array {
    if (empty($employeeIds)) return [];

    static $settings = null;
    if ($settings === null) {
        $settings = DB::row("SELECT * FROM settings WHERE id=1");
    }

    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate   = date('Y-m-t', strtotime($startDate));

    // Pre-fetch employees + job hours
    $employees = DB::rows("SELECT e.*, jt.working_hours FROM employees e LEFT JOIN job_titles jt ON jt.id=e.job_title_id WHERE e.id IN ($placeholders)", $employeeIds);
    $empMap = array_column($employees, null, 'id');

    // Pre-fetch active allowances
    $allowanceRows = DB::rows("
        SELECT ea.employee_id, ea.amount, ea.allowance_type_id, at.calc_type, at.name_en, at.name_ar
        FROM employee_allowances ea
        JOIN allowance_types at ON at.id = ea.allowance_type_id
        WHERE ea.employee_id IN ($placeholders) AND ea.is_active = 1", $employeeIds);
    $allowanceMap = [];
    foreach ($allowanceRows as $a) {
        $allowanceMap[$a['employee_id']][] = $a;
    }

    // Pre-fetch attendance summaries
    $attRows = DB::rows("
        SELECT employee_id, status, late_minutes, overtime_hours
        FROM attendance
        WHERE employee_id IN ($placeholders) AND attendance_date BETWEEN ? AND ?",
        array_merge($employeeIds, [$startDate, $endDate]));
    $attMap = [];
    foreach ($attRows as $r) {
        $eid = $r['employee_id'];
        if (!isset($attMap[$eid])) {
            $attMap[$eid] = ['absent_days' => 0, 'late_minutes' => 0, 'overtime_hours' => 0, 'holiday_ot' => 0];
        }
        $attMap[$eid]['late_minutes'] += (int)$r['late_minutes'];
        $attMap[$eid]['overtime_hours'] += (float)$r['overtime_hours'];
        if ($r['status'] === 'absent') $attMap[$eid]['absent_days'] += 1;
        if ($r['status'] === 'holiday' && (float)$r['overtime_hours'] > 0) {
            $attMap[$eid]['holiday_ot'] += (float)$r['overtime_hours'];
        }
    }

    // Pre-fetch bonuses
    $bonusMap = [];
    $bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
    if ($bonusesTableExists) {
        $bonusRows = DB::rows("
            SELECT employee_id, COALESCE(SUM(amount), 0) as amount
            FROM bonuses
            WHERE employee_id IN ($placeholders) AND status='approved'
            AND period_year=? AND period_month=?
            GROUP BY employee_id",
            array_merge($employeeIds, [$year, $month]));
        foreach ($bonusRows as $b) {
            $bonusMap[(int)$b['employee_id']] = (float)$b['amount'];
        }
    }

    // Pre-fetch active loans
    $loanRows = DB::rows("
        SELECT employee_id, installment_amount
        FROM loans
        WHERE employee_id IN ($placeholders) AND status='active' AND start_date <= ?",
        array_merge($employeeIds, [$startDate]));
    $loanMap = [];
    foreach ($loanRows as $l) {
        $loanMap[(int)$l['employee_id']] = ($loanMap[(int)$l['employee_id']] ?? 0) + (float)$l['installment_amount'];
    }

    // Pre-fetch active custom deductions
    $deductionRows = DB::rows("
        SELECT ed.employee_id, ed.amount, ed.deduction_type_id, dt.calc_type, dt.name_en, dt.name_ar
        FROM employee_deductions ed
        JOIN deduction_types dt ON dt.id = ed.deduction_type_id
        WHERE ed.employee_id IN ($placeholders) AND ed.is_active=1 AND dt.is_system=0
        AND (ed.end_date IS NULL OR ed.end_date >= ?)", array_merge($employeeIds, [$startDate]));
    $deductionMap = [];
    foreach ($deductionRows as $d) {
        $deductionMap[(int)$d['employee_id']][] = $d;
    }

    // Calculate per employee
    $results = [];
    $workDays = (int)($settings['work_days_per_month'] ?? 22);
    $regularOvertimeRate = (float)($settings['overtime_rate'] ?? 1.25);
    $holidayOvertimeRate = (float)($settings['holiday_overtime_rate'] ?? 2.0);
    $siRate = (float)($settings['social_insurance_rate'] ?? 11.0);
    $taxRate = (float)($settings['tax_rate'] ?? 0.0);

    foreach ($employeeIds as $empId) {
        $emp = $empMap[$empId] ?? null;
        if (!$emp) continue;

        $basic = (float)$emp['basic_salary'];
        $jobHours = (int)($emp['working_hours'] ?? 8);
        $hourlyRate = ($jobHours > 0 && $workDays > 0) ? ($basic / ($workDays * $jobHours)) : 0;
        $minuteRate = $hourlyRate / 60;
        $dailyRate = $workDays > 0 ? $basic / $workDays : 0;

        $allowanceDetails = [];
        $totalAllowances = 0;
        foreach ($allowanceMap[$empId] ?? [] as $a) {
            $amt = match($a['calc_type']) {
                'percentage_basic' => $basic * $a['amount'] / 100,
                default            => (float)$a['amount'],
            };
            $totalAllowances += $amt;
            $allowanceDetails[] = ['type' => 'allowance', 'ref_id' => $a['allowance_type_id'], 'name_en' => $a['name_en'], 'name_ar' => $a['name_ar'], 'amount' => $amt];
        }

        $bonusAmt = $bonusMap[$empId] ?? 0;
        $gross = $basic + $totalAllowances + $bonusAmt;

        $att = $attMap[$empId] ?? ['absent_days' => 0, 'late_minutes' => 0, 'overtime_hours' => 0, 'holiday_ot' => 0];
        $absentDays = (float)$att['absent_days'];
        $lateMinutes = (int)$att['late_minutes'];
        $overtimeHours = (float)$att['overtime_hours'];
        $holidayOvertimeHours = (float)$att['holiday_ot'];
        $regularOvertimeHours = $overtimeHours - $holidayOvertimeHours;

        $absentDed = round($dailyRate * $absentDays, 3);
        $lateDed = round($minuteRate * $lateMinutes, 3);
        $overtimeAmt = round(
            $hourlyRate * $regularOvertimeRate * $regularOvertimeHours +
            $hourlyRate * $holidayOvertimeRate * $holidayOvertimeHours,
            3
        );

        $siAmt = round($basic * $siRate / 100, 3);
        $taxAmt = round($gross * $taxRate / 100, 3);
        $loanDed = round($loanMap[$empId] ?? 0, 3);

        $dedDetails = [];
        $otherDeds = 0;
        foreach ($deductionMap[$empId] ?? [] as $d) {
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

        $results[$empId] = [
            'payroll_period_id' => $periodId,
            'employee_id'       => $empId,
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

    return $results;
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
