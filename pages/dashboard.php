<?php
$pageTitle = t('dashboard');

// Cache dashboard stats for 5 minutes to reduce load on large datasets
$dashCache = cacheFileGet('dashboard_stats', function() {
    $data = [];
    $data['totalEmps']  = DB::val("SELECT COUNT(*) FROM employees WHERE status='active'");
    $data['newEmps']    = DB::val("SELECT COUNT(*) FROM employees WHERE MONTH(hire_date)=MONTH(NOW()) AND YEAR(hire_date)=YEAR(NOW())");
    $data['onLeave']    = DB::val("SELECT COUNT(*) FROM leave_requests WHERE status='approved' AND CURDATE() BETWEEN start_date AND end_date");
    $data['pendLeaves'] = DB::val("SELECT COUNT(*) FROM leave_requests WHERE status='pending'");
    $data['curPeriod']  = DB::row("SELECT * FROM payroll_periods WHERE period_year=? AND period_month=?", [(int)date('Y'), (int)date('m')]);
    $data['chartData']  = DB::rows("SELECT period_year, period_month, total_net, employee_count FROM payroll_periods WHERE status IN ('approved','paid') ORDER BY period_year DESC, period_month DESC LIMIT 6");
    $data['deptBreakdown'] = DB::rows("SELECT d.id, d.name_en, d.name_ar, COUNT(e.id) as cnt, SUM(e.basic_salary) as total_salary FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active' GROUP BY d.id ORDER BY cnt DESC LIMIT 6");
    $data['recentPayrolls'] = DB::rows("SELECT pp.*, COUNT(pi.id) as emp_count FROM payroll_periods pp LEFT JOIN payroll_items pi ON pi.payroll_period_id=pp.id GROUP BY pp.id ORDER BY pp.created_at DESC LIMIT 5");
    $data['pendingLeaves'] = DB::rows("SELECT lr.*, e.name_en, e.name_ar, lt.name_en as ltype_en, lt.name_ar as ltype_ar FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.status='pending' ORDER BY lr.created_at DESC LIMIT 5");
    $data['todayPresent'] = DB::val("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='present'");
    $data['todayAbsent']  = DB::val("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='absent'");
    $data['todayLate']    = DB::val("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='late'");
    return $data;
}, 300);

$totalEmps  = $dashCache['totalEmps'];
$newEmps    = $dashCache['newEmps'];
$onLeave    = $dashCache['onLeave'];
$pendLeaves = $dashCache['pendLeaves'];
$curPeriod  = $dashCache['curPeriod'];
$chartData  = $dashCache['chartData'];
$deptBreakdown = $dashCache['deptBreakdown'];
$recentPayrolls = $dashCache['recentPayrolls'];
$pendingLeaves = $dashCache['pendingLeaves'];
$todayPresent = $dashCache['todayPresent'];
$todayAbsent  = $dashCache['todayAbsent'];
$todayLate    = $dashCache['todayLate'];

$totalPayroll = $curPeriod ? money((float)$curPeriod['total_net']) : money(0);
$curM = (int)date('m');
$chartData = array_reverse($chartData);
$chartLabels = array_map(fn($r) => monthName((int)$r['period_month'], lang()) . ' ' . $r['period_year'], $chartData);
$chartValues = array_map(fn($r) => (float)$r['total_net'], $chartData);
?>

<div class="page-header">
  <h1 class="page-title">Hello, <?= h(explode(' ', $user['name'] ?? 'Admin')[0]) ?> 👋</h1>
  <p class="page-sub text-muted"><?= date('l, d F Y') ?></p>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card stat-blue">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= number_format($totalEmps) ?></div>
        <div class="stat-label"><?= t('total_employees') ?></div>
      </div>
      <div class="stat-badge text-success">+<?= $newEmps ?> <?= t('new_this_month') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-green">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= number_format($todayPresent) ?></div>
        <div class="stat-label"><?= t('present_today') ?></div>
      </div>
      <div class="stat-badge text-muted"><?= date('d M') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-yellow">
      <div class="stat-icon"><i class="fas fa-clock"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= number_format($pendLeaves) ?></div>
        <div class="stat-label"><?= t('pending_requests') ?></div>
      </div>
      <div class="stat-badge text-warning"><?= t('leaves') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card stat-purple">
      <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
      <div class="stat-body">
        <div class="stat-value stat-value-sm"><?= $totalPayroll ?></div>
        <div class="stat-label"><?= t('monthly_payroll') ?></div>
      </div>
      <div class="stat-badge text-muted"><?= monthName($curM, lang()) ?></div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card card-modern">
      <div class="card-header-modern">
        <h5 class="card-title-modern"><i class="fas fa-chart-line me-2 text-primary"></i><?= t('payroll_summary') ?></h5>
      </div>
      <div class="card-body">
        <canvas id="payrollChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="circular-progress-card">
      <div class="circular-progress">
        <svg width="120" height="120">
          <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" style="stop-color:#4a90e2" />
              <stop offset="100%" style="stop-color:#6366f1" />
            </linearGradient>
          </defs>
          <circle class="bg" cx="60" cy="60" r="45"></circle>
          <circle class="progress" cx="60" cy="60" r="45" style="stroke-dashoffset: <?= 283 - (283 * min(100, ($todayPresent / max(1, $totalEmps)) * 100) / 100) ?>"></circle>
        </svg>
        <div class="value"><?= round(($todayPresent / max(1, $totalEmps)) * 100) ?>%</div>
      </div>
      <h5 class="mb-2"><?= t('attendance_rate') ?></h5>
      <div class="progress-breakdown">
        <div class="progress-item">
          <div class="dot" style="background: #27ae60;"></div>
          <div class="label"><?= t('present') ?></div>
          <div class="value"><?= $todayPresent ?></div>
        </div>
        <div class="progress-item">
          <div class="dot" style="background: #e74c3c;"></div>
          <div class="label"><?= t('absent') ?></div>
          <div class="value"><?= $todayAbsent ?></div>
        </div>
        <div class="progress-item">
          <div class="dot" style="background: #f39c12;"></div>
          <div class="label"><?= t('late') ?></div>
          <div class="value"><?= $todayLate ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tables Row -->
<div class="row g-3 mb-4">
  <div class="col-md-7">
    <div class="card card-modern">
      <div class="card-header-modern d-flex justify-content-between align-items-center">
        <h5 class="card-title-modern"><i class="fas fa-money-bill me-2 text-primary"></i><?= t('recent_payrolls') ?></h5>
        <a href="index.php?page=payroll" class="btn btn-sm btn-outline-primary"><?= t('view') ?> <?= t('payroll') ?></a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr>
              <th><?= t('payroll_period') ?></th>
              <th><?= t('employees') ?></th>
              <th><?= t('total') ?></th>
              <th><?= t('status') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentPayrolls as $rp): ?>
            <tr>
              <td><strong><?= monthName((int)$rp['period_month'], lang()) ?> <?= $rp['period_year'] ?></strong></td>
              <td><?= $rp['employee_count'] ?></td>
              <td><?= money((float)$rp['total_net']) ?></td>
              <td><?= statusBadge($rp['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPayrolls)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3"><?= t('no_records') ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="task-card">
      <div class="task-header">
        <h5 class="task-title"><i class="fas fa-tasks me-2 text-primary"></i><?= t('pending_leaves') ?></h5>
        <a href="index.php?page=leaves" class="btn btn-sm btn-outline-primary"><?= t('view_all') ?></a>
      </div>
      <div class="task-progress">
        <div class="task-progress-bar">
          <div class="task-progress-fill" style="width: <?= min(100, ($pendLeaves / max(1, $pendLeaves + 5)) * 100) ?>%"></div>
        </div>
        <div class="task-progress-text"><?= $pendLeaves ?> <?= t('pending') ?> / <?= $pendLeaves + 5 ?> <?= t('total') ?></div>
      </div>
      <ul class="task-list">
        <?php foreach ($pendingLeaves as $pl): ?>
        <li class="task-item">
          <div class="task-checkbox"></div>
          <div class="task-info">
            <div class="task-name"><?= h(lang() === 'ar' ? $pl['name_ar'] : $pl['name_en']) ?></div>
            <div class="task-meta"><?= h(lang() === 'ar' ? $pl['ltype_ar'] : $pl['ltype_en']) ?> • <?= $pl['days'] ?> <?= t('days') ?></div>
          </div>
          <div class="task-avatar"><?= strtoupper(substr(lang() === 'ar' ? $pl['name_ar'] : $pl['name_en'], 0, 1)) ?></div>
        </li>
        <?php endforeach; ?>
        <?php if (empty($pendingLeaves)): ?>
        <li class="task-item">
          <div class="task-info">
            <div class="task-name"><?= t('no_pending_requests') ?></div>
          </div>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<!-- Dept Breakdown Cards -->
<div class="row g-3 mt-1">
<?php foreach ($deptBreakdown as $d): ?>
<div class="col-6 col-md-2">
  <div class="dept-mini-card">
    <div class="dept-mini-icon"><i class="fas fa-building"></i></div>
    <div class="dept-mini-name"><?= h(lang() === 'ar' ? ($d['name_ar'] ?? $d['name_en']) : $d['name_en']) ?></div>
    <div class="dept-mini-count"><?= $d['cnt'] ?> <small><?= t('employees') ?></small></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<script>
// Payroll Chart - only run if Chart is loaded and element exists
if (typeof Chart !== 'undefined' && document.getElementById('payrollChart')) {
  const pCtx = document.getElementById('payrollChart').getContext('2d');
  new Chart(pCtx, {
    type: 'bar',
    data: {
      labels: <?= j($chartLabels) ?>,
      datasets: [{
        label: '<?= t("net_salary") ?>',
        data: <?= j($chartValues) ?>,
        backgroundColor: 'rgba(74,144,226,0.8)',
        borderColor: '#4a90e2',
        borderWidth: 2,
        borderRadius: 8,
        hoverBackgroundColor: '#6366f1'
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#e8e8e8' },
             ticks: { callback: v => v.toLocaleString() } },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>
