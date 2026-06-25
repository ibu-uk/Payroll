<?php
requireRole('admin', 'manager', 'hr');
$pageTitle = t('reports');
$reportType = get('type', 'dashboard');
$periods = DB::rows("SELECT * FROM payroll_periods ORDER BY period_year DESC, period_month DESC");
$departments = DB::rows("SELECT * FROM departments WHERE is_active=1 ORDER BY name_en");

// Yearly Summary Report
if ($reportType === 'yearly') {
    $year = (int)get('year', date('Y'));
    $yearlyData = DB::rows("
        SELECT period_month, period_label, total_gross, total_net, total_deductions, employee_count, status
        FROM payroll_periods
        WHERE period_year = ?
        ORDER BY period_month
    ", [$year]);
    
    $yearlyTotals = DB::row("
        SELECT 
            SUM(total_gross) as total_gross,
            SUM(total_net) as total_net,
            SUM(total_deductions) as total_deductions,
            AVG(employee_count) as avg_employees,
            COUNT(*) as total_periods
        FROM payroll_periods
        WHERE period_year = ?
    ", [$year]);
    
    $years = DB::rows("SELECT DISTINCT period_year FROM payroll_periods ORDER BY period_year DESC");
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div>
        <h1 class="page-title"><i class="fas fa-chart-line me-2"></i>Yearly Payroll Summary</h1>
        <p class="text-muted mb-0"><?= $year ?> - Complete breakdown</p>
      </div>
      <div class="d-flex gap-2">
        <select class="form-select" onchange="location.href='?page=reports&type=yearly&year='+this.value">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y['period_year'] ?>" <?= $y['period_year'] == $year ? 'selected' : '' ?>><?= $y['period_year'] ?></option>
          <?php endforeach; ?>
        </select>
        <a href="?page=reports" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
      </div>
    </div>
    
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card card-modern stat-card">
          <div class="card-body">
            <div class="stat-label">Total Gross</div>
            <div class="stat-value text-success"><?= money((float)($yearlyTotals['total_gross'] ?? 0)) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-modern stat-card">
          <div class="card-body">
            <div class="stat-label">Total Net</div>
            <div class="stat-value text-primary"><?= money((float)($yearlyTotals['total_net'] ?? 0)) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-modern stat-card">
          <div class="card-body">
            <div class="stat-label">Total Deductions</div>
            <div class="stat-value text-danger"><?= money((float)($yearlyTotals['total_deductions'] ?? 0)) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-modern stat-card">
          <div class="card-body">
            <div class="stat-label">Avg Employees</div>
            <div class="stat-value"><?= number_format($yearlyTotals['avg_employees'] ?? 0, 0) ?></div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="card card-modern">
      <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Month</th>
              <th>Status</th>
              <th>Employees</th>
              <th>Gross Salary</th>
              <th>Deductions</th>
              <th>Net Salary</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($yearlyData as $row): ?>
            <tr>
              <td><?= h($row['period_label']) ?></td>
              <td><?= statusBadge($row['status']) ?></td>
              <td><?= $row['employee_count'] ?></td>
              <td><?= money((float)$row['total_gross']) ?></td>
              <td><?= money((float)$row['total_deductions']) ?></td>
              <td class="fw-bold text-success"><?= money((float)$row['total_net']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    return;
}

// Year-over-Year Comparison
if ($reportType === 'yoy') {
    $years = DB::rows("SELECT DISTINCT period_year FROM payroll_periods ORDER BY period_year DESC LIMIT 5");
    $year1 = (int)get('year1', date('Y'));
    $year2 = (int)get('year2', date('Y') - 1);
    
    $y1Data = DB::rows("SELECT period_month, total_gross, total_net, employee_count FROM payroll_periods WHERE period_year=? ORDER BY period_month", [$year1]);
    $y2Data = DB::rows("SELECT period_month, total_gross, total_net, employee_count FROM payroll_periods WHERE period_year=? ORDER BY period_month", [$year2]);
    
    $y1Map = array_column($y1Data, null, 'period_month');
    $y2Map = array_column($y2Data, null, 'period_month');
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div>
        <h1 class="page-title"><i class="fas fa-chart-bar me-2"></i>Year-over-Year Comparison</h1>
        <p class="text-muted mb-0">Compare payroll between years</p>
      </div>
      <div class="d-flex gap-2">
        <select class="form-select" onchange="location.href='?page=reports&type=yoy&year1='+this.value+'&year2=<?= $year2 ?>'">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y['period_year'] ?>" <?= $y['period_year'] == $year1 ? 'selected' : '' ?>><?= $y['period_year'] ?></option>
          <?php endforeach; ?>
        </select>
        <span class="align-self-center">vs</span>
        <select class="form-select" onchange="location.href='?page=reports&type=yoy&year1=<?= $year1 ?>&year2='+this.value">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y['period_year'] ?>" <?= $y['period_year'] == $year2 ? 'selected' : '' ?>><?= $y['period_year'] ?></option>
          <?php endforeach; ?>
        </select>
        <a href="?page=reports" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
      </div>
    </div>
    
    <div class="card card-modern">
      <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Month</th>
              <th><?= $year1 ?> Gross</th>
              <th><?= $year2 ?> Gross</th>
              <th>Change</th>
              <th><?= $year1 ?> Net</th>
              <th><?= $year2 ?> Net</th>
              <th>Change</th>
            </tr>
          </thead>
          <tbody>
            <?php for($m = 1; $m <= 12; $m++): 
                $y1 = $y1Map[$m] ?? null;
                $y2 = $y2Map[$m] ?? null;
                if (!$y1 && !$y2) continue;
                $g1 = (float)($y1['total_gross'] ?? 0);
                $g2 = (float)($y2['total_gross'] ?? 0);
                $n1 = (float)($y1['total_net'] ?? 0);
                $n2 = (float)($y2['total_net'] ?? 0);
                $gChange = $g2 > 0 ? (($g1 - $g2) / $g2 * 100) : 0;
                $nChange = $n2 > 0 ? (($n1 - $n2) / $n2 * 100) : 0;
            ?>
            <tr>
              <td><?= date('F', mktime(0,0,0,$m,1)) ?></td>
              <td><?= money($g1) ?></td>
              <td><?= money($g2) ?></td>
              <td class="<?= $gChange >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($gChange, 1) ?>%</td>
              <td><?= money($n1) ?></td>
              <td><?= money($n2) ?></td>
              <td class="<?= $nChange >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($nChange, 1) ?>%</td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    return;
}

// Employee History
if ($reportType === 'employee_history') {
    $empId = (int)get('emp_id');
    if ($empId) {
        $emp = DB::row("SELECT * FROM employees WHERE id=?", [$empId]);
        $historyTableExists = DB::row("SHOW TABLES LIKE 'employee_history'") !== null;
        $history = [];
        if ($historyTableExists) {
            $history = DB::rows("
                SELECT eh.*, u.name as changed_by_name
                FROM employee_history eh
                LEFT JOIN users u ON u.id = eh.changed_by
                WHERE eh.employee_id = ?
                ORDER BY eh.change_date DESC
            ", [$empId]);
        }
        ?>
        <div class="page-header d-flex justify-content-between align-items-start">
          <div>
            <h1 class="page-title"><i class="fas fa-history me-2"></i>Employee History</h1>
            <p class="text-muted mb-0"><?= h($emp['name_en']) ?> - Complete change log</p>
          </div>
          <a href="?page=reports&type=employee_history" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        </div>
        
        <div class="card card-modern">
          <div class="card-body">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Change Type</th>
                  <th>Field</th>
                  <th>Old Value</th>
                  <th>New Value</th>
                  <th>Changed By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= fdate($h['change_date'], 'Y-m-d H:i') ?></td>
                  <td><?= ucfirst(str_replace('_', ' ', $h['change_type'])) ?></td>
                  <td><?= h($h['field_name'] ?? '-') ?></td>
                  <td><?= h($h['old_value'] ?? '-') ?></td>
                  <td><?= h($h['new_value'] ?? '-') ?></td>
                  <td><?= h($h['changed_by_name'] ?? 'System') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">
                  <?php if (!$historyTableExists): ?>
                    Employee history table does not exist. Please run the migration: migrations/add_advanced_features.sql
                  <?php else: ?>
                    No history records found
                  <?php endif; ?>
                </td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php
        return;
    }
    
    ?>
    <div class="page-header"><h1 class="page-title"><i class="fas fa-history me-2"></i>Employee History</h1></div>
    <div class="card card-modern">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Select Employee</label>
          <select class="form-select select2-ajax" data-status="all" onchange="location.href='?page=reports&type=employee_history&emp_id='+this.value" data-placeholder="Search employee...">
            <option value=""></option>
          </select>
        </div>
      </div>
    </div>
    <?php
    return;
}

// Department-wise Report
if ($reportType === 'department') {
    $periodId = (int)get('period_id');
    $deptId = (int)get('dept_id');
    
    if ($periodId) {
        $where = "WHERE pi.payroll_period_id = ?";
        $params = [$periodId];
        if ($deptId) {
            $where .= " AND e.department_id = ?";
            $params[] = $deptId;
        }
        
        $deptData = DB::rows("
            SELECT d.name_en, d.name_ar, 
                   COUNT(pi.id) as emp_count,
                   SUM(pi.basic_salary) as total_basic,
                   SUM(pi.total_allowances) as total_allowances,
                   SUM(pi.gross_salary) as total_gross,
                   SUM(pi.overtime_amount) as total_overtime,
                   SUM(pi.total_deductions) as total_deductions,
                   SUM(pi.net_salary) as total_net
            FROM payroll_items pi
            JOIN employees e ON e.id = pi.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            $where
            GROUP BY d.id, d.name_en, d.name_ar
            ORDER BY total_gross DESC
        ", $params);
        ?>
        <div class="page-header d-flex justify-content-between align-items-start">
          <div>
            <h1 class="page-title"><i class="fas fa-building me-2"></i>Department-wise Payroll</h1>
            <p class="text-muted mb-0">Breakdown by department</p>
          </div>
          <a href="?page=reports" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        </div>
        
        <div class="card card-modern">
          <div class="card-body">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Department</th>
                  <th>Employees</th>
                  <th>Basic Salary</th>
                  <th>Allowances</th>
                  <th>Overtime</th>
                  <th>Gross</th>
                  <th>Deductions</th>
                  <th>Net Salary</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($deptData as $d): ?>
                <tr>
                  <td><?= h(lang() === 'ar' ? ($d['name_ar'] ?? $d['name_en']) : $d['name_en']) ?></td>
                  <td><?= $d['emp_count'] ?></td>
                  <td><?= money((float)$d['total_basic']) ?></td>
                  <td><?= money((float)$d['total_allowances']) ?></td>
                  <td><?= money((float)$d['total_overtime']) ?></td>
                  <td><?= money((float)$d['total_gross']) ?></td>
                  <td><?= money((float)$d['total_deductions']) ?></td>
                  <td class="fw-bold text-success"><?= money((float)$d['total_net']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php
        return;
    }
    ?>
    <div class="page-header"><h1 class="page-title"><i class="fas fa-building me-2"></i>Department-wise Payroll</h1></div>
    <div class="card card-modern">
      <div class="card-body">
        <form method="GET">
          <input type="hidden" name="page" value="reports">
          <input type="hidden" name="type" value="department">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Payroll Period</label>
              <select class="form-select" name="period_id" required>
                <option value="">Select period...</option>
                <?php foreach ($periods as $p): ?>
                <option value="<?= $p['id'] ?>"><?= h($p['period_label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Department (optional)</label>
              <select class="form-select" name="dept_id">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= h($d['name_en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <button class="btn btn-primary">Generate Report</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

// Overtime & Absenteeism Report
if ($reportType === 'overtime_absenteeism') {
    $year = (int)get('year', date('Y'));
    $month = (int)get('month', date('m'));
    
    $attData = DB::rows("
        SELECT e.id, e.name_en, e.employee_no, d.name_en as dept_name,
               SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(a.late_minutes) as total_late_minutes,
               SUM(a.overtime_hours) as total_overtime_hours,
               COUNT(a.id) as total_days
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id 
            AND YEAR(a.attendance_date) = ? 
            AND MONTH(a.attendance_date) = ?
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status IN ('active','probation')
        GROUP BY e.id, e.name_en, e.employee_no, d.name_en
        ORDER BY total_overtime_hours DESC
    ", [$year, $month]);
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div>
        <h1 class="page-title"><i class="fas fa-clock me-2"></i>Overtime & Absenteeism</h1>
        <p class="text-muted mb-0"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></p>
      </div>
      <div class="d-flex gap-2">
        <select class="form-select" onchange="location.href='?page=reports&type=overtime_absenteeism&year='+this.value+'&month=<?= $month ?>'">
          <?php for($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
          <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <select class="form-select" onchange="location.href='?page=reports&type=overtime_absenteeism&year=<?= $year ?>&month='+this.value">
          <?php for($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
        <a href="?page=reports" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
      </div>
    </div>
    
    <div class="card card-modern">
      <div class="card-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>Working Days</th>
              <th>Absent Days</th>
              <th>Late Minutes</th>
              <th>Overtime Hours</th>
              <th>Absenteeism Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attData as $a): 
                $absentRate = $a['total_days'] > 0 ? ($a['absent_days'] / $a['total_days'] * 100) : 0;
            ?>
            <tr>
              <td><?= h($a['name_en']) ?> (<?= h($a['employee_no']) ?>)</td>
              <td><?= h($a['dept_name']) ?></td>
              <td><?= $a['total_days'] ?></td>
              <td class="text-danger"><?= $a['absent_days'] ?></td>
              <td class="text-warning"><?= $a['total_late_minutes'] ?></td>
              <td class="text-success"><?= number_format($a['total_overtime_hours'], 2) ?></td>
              <td><?= number_format($absentRate, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    return;
}

// Default Dashboard
?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-chart-bar me-2"></i><?= t('reports') ?></h1></div>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-calendar-alt me-2 text-primary"></i>Yearly Summary</h5></div>
      <div class="card-body">
        <p class="text-muted">View complete yearly payroll breakdown with totals and monthly trends.</p>
        <a href="?page=reports&type=yearly" class="btn btn-primary w-100">
          <i class="fas fa-chart-line me-2"></i>View Yearly Report
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-chart-bar me-2 text-success"></i>Year-over-Year Comparison</h5></div>
      <div class="card-body">
        <p class="text-muted">Compare payroll data between different years to identify trends.</p>
        <a href="?page=reports&type=yoy" class="btn btn-success w-100">
          <i class="fas fa-chart-bar me-2"></i>Compare Years
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-history me-2 text-warning"></i>Employee History</h5></div>
      <div class="card-body">
        <p class="text-muted">Track complete employee change history including salary, status, and department changes.</p>
        <a href="?page=reports&type=employee_history" class="btn btn-warning w-100">
          <i class="fas fa-history me-2"></i>View History
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-building me-2 text-info"></i>Department-wise Report</h5></div>
      <div class="card-body">
        <p class="text-muted">Analyze payroll costs by department with detailed breakdowns.</p>
        <a href="?page=reports&type=department" class="btn btn-info w-100">
          <i class="fas fa-building me-2"></i>Department Report
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-clock me-2 text-warning"></i>Overtime & Absenteeism</h5></div>
      <div class="card-body">
        <p class="text-muted">Track overtime hours and absenteeism rates by employee and department.</p>
        <a href="?page=reports&type=overtime_absenteeism" class="btn btn-warning w-100">
          <i class="fas fa-clock me-2"></i>Overtime Report
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-file-pdf me-2 text-danger"></i>Payslip PDF</h5></div>
      <div class="card-body">
        <p class="text-muted">Generate individual employee payslip in PDF format (Arabic/English bilingual).</p>
        <form action="index.php" method="GET" target="_blank">
          <input type="hidden" name="page" value="export">
          <input type="hidden" name="type" value="payslip_pdf">
          <div class="mb-3"><label class="form-label"><?= t('payroll_period') ?></label>
            <select class="form-select" name="period_id" required>
              <option value="">Select period...</option>
              <?php foreach ($periods as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['period_label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label"><?= t('employees') ?> (leave blank for all)</label>
            <select class="form-select select2-ajax" name="emp_id" data-placeholder="Search employee...">
              <option value="">All Employees</option>
            </select>
          </div>
          <button class="btn btn-danger w-100"><i class="fas fa-file-pdf me-2"></i>Generate PDF Payslip</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-file-excel me-2 text-success"></i>Payroll Excel Report</h5></div>
      <div class="card-body">
        <p class="text-muted">Export complete payroll breakdown to Excel with multiple sheets.</p>
        <form action="index.php" method="GET">
          <input type="hidden" name="page" value="export">
          <input type="hidden" name="type" value="payroll_excel">
          <div class="mb-3"><label class="form-label"><?= t('payroll_period') ?></label>
            <select class="form-select" name="period_id" required>
              <option value="">Select period...</option>
              <?php foreach ($periods as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['period_label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Department (optional)</label>
            <select class="form-select" name="dept_id">
              <option value="">All Departments</option>
              <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['name_en']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-success w-100"><i class="fas fa-file-excel me-2"></i>Download Excel</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-users me-2 text-primary"></i>Employee List</h5></div>
      <div class="card-body">
        <p class="text-muted">Export full employee directory to Excel including salary info.</p>
        <a href="index.php?page=export&type=employees_excel" class="btn btn-primary w-100">
          <i class="fas fa-file-excel me-2"></i>Export Employee List
        </a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-modern h-100">
      <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-print me-2 text-secondary"></i>Print Reports</h5></div>
      <div class="card-body">
        <p class="text-muted">Quick print any page. Use browser print from any table view.</p>
        <button onclick="window.print()" class="btn btn-secondary w-100"><i class="fas fa-print me-2"></i>Print Current Page</button>
      </div>
    </div>
  </div>
</div>
