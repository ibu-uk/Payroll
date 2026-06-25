<?php
$pageTitle = t('attendance');
$year  = (int)get('year', date('Y'));
$month = (int)get('month', date('m'));
$deptFilter = get('dept');
$page = max(1, (int)get('p', 1));
$perPage = 50; // Show 50 employees per page for attendance

if (isPost()) {
    checkCsrf();
    requireRole('admin', 'manager', 'hr');
    $rows = post('att', []);
    foreach ($rows as $empId => $dates) {
        foreach ($dates as $date => $data) {
            $status = $data['status'] ?? 'present';
            $late   = (int)($data['late_minutes'] ?? 0);
            $ot     = (float)($data['overtime_hours'] ?? 0);
            
            // Check if this date is a holiday
            $holiday = DB::row("SELECT id FROM holidays WHERE holiday_date=? OR (is_recurring=1 AND MONTH(holiday_date)=? AND DAY(holiday_date)=?)", 
                [$date, substr($date, 5, 2), substr($date, 8, 2)]);
            
            // If it's a holiday and status is not explicitly set, mark as holiday
            if ($holiday && $status === 'present') {
                $status = 'holiday';
            }
            
            DB::q("INSERT INTO attendance (employee_id,attendance_date,status,late_minutes,overtime_hours)
                   VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),late_minutes=VALUES(late_minutes),overtime_hours=VALUES(overtime_hours)",
                  [$empId, $date, $status, $late, $ot]);
        }
    }
    setFlash('success', t('saved_success'));
    redirect("index.php?page=attendance&year=$year&month=$month&dept=$deptFilter");
}

$departments = DB::rows("SELECT * FROM departments WHERE is_active=1 ORDER BY name_en");
$where = "WHERE e.status IN ('active','probation')";
$params = [];
if ($deptFilter) { $where .= " AND e.department_id=?"; $params[] = $deptFilter; }

// Get total count for pagination
$totalCount = DB::val("SELECT COUNT(*) FROM employees e $where", $params);
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

$employees = DB::rows("SELECT e.id, e.name_en, e.name_ar, e.employee_no, d.name_en as dept_en FROM employees e
    LEFT JOIN departments d ON d.id=e.department_id $where ORDER BY e.name_en LIMIT $perPage OFFSET $offset", $params);

// Fetch existing attendance only for employees on this page (use date range for index usage)
$empIds = array_column($employees, 'id');
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-t', strtotime($startDate));
$attMap = [];
if (!empty($empIds)) {
    $placeholders = implode(',', array_fill(0, count($empIds), '?'));
    $attRecords = DB::rows("SELECT employee_id, attendance_date, status, late_minutes, overtime_hours FROM attendance
        WHERE employee_id IN ($placeholders) AND attendance_date BETWEEN ? AND ?",
        array_merge($empIds, [$startDate, $endDate]));
    foreach ($attRecords as $r) {
        $attMap[$r['employee_id']][$r['attendance_date']] = $r;
    }
}

// Fetch holidays for the month (check if table exists first)
$holidaysTableExists = DB::row("SHOW TABLES LIKE 'holidays'") !== null;
$holidayMap = [];
if ($holidaysTableExists) {
    $holidays = DB::rows("SELECT holiday_date, name_en, name_ar FROM holidays 
        WHERE (holiday_date BETWEEN ? AND ?) OR is_recurring=1", [$startDate, $endDate]);
    foreach ($holidays as $h) {
        $date = $h['holiday_date'];
        // For recurring holidays, check if this year's date matches
        if (substr($date, 0, 4) != $year) {
            $recurringMonth = substr($date, 5, 2);
            $recurringDay = substr($date, 8, 2);
            $date = sprintf('%04d-%02d-%02d', $year, $recurringMonth, $recurringDay);
        }
        $holidayMap[$date] = $h;
    }
}

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$today = date('Y-m-d');
$statuses = ['present','absent','late','half_day','leave','holiday'];
$statusShort = ['present'=>'P','absent'=>'A','late'=>'L','half_day'=>'H','leave'=>'V','holiday'=>'H'];
$statusColors = ['present'=>'success','absent'=>'danger','late'=>'warning','half_day'=>'info','leave'=>'primary','holiday'=>'danger'];
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-calendar-check me-2"></i><?= t('attendance') ?></h1>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i><?= t('print') ?></button>
  </div>
</div>

<!-- Month navigation -->
<div class="card card-modern mb-3">
  <div class="card-body py-2">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <?php
      $prevMonth = $month == 1 ? 12 : $month - 1;
      $prevYear  = $month == 1 ? $year - 1 : $year;
      $nextMonth = $month == 12 ? 1 : $month + 1;
      $nextYear  = $month == 12 ? $year + 1 : $year;
      ?>
      <a href="?page=attendance&year=<?= $prevYear ?>&month=<?= $prevMonth ?>&dept=<?= $deptFilter ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-chevron-left"></i>
      </a>
      <h5 class="mb-0 fw-700"><?= monthName($month, lang()) ?> <?= $year ?></h5>
      <a href="?page=attendance&year=<?= $nextYear ?>&month=<?= $nextMonth ?>&dept=<?= $deptFilter ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-chevron-right"></i>
      </a>
      <select class="form-select form-select-sm w-auto" onchange="window.location='?page=attendance&year=<?= $year ?>&month=<?= $month ?>&dept='+this.value">
        <option value=""><?= t('department') ?></option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
          <?= h(lang() === 'ar' ? ($d['name_ar'] ?? $d['name_en']) : $d['name_en']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <!-- Legend -->
      <div class="d-flex gap-2 ms-auto flex-wrap">
        <?php foreach ($statusShort as $s => $sh): ?>
        <span class="badge bg-<?= $statusColors[$s] ?>"><?= $sh ?> = <?= t($s) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<form method="POST">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <div class="card card-modern">
    <div class="card-body p-0">
      <div class="table-responsive attendance-grid">
        <table class="table table-bordered table-sm mb-0" id="attTable">
          <thead class="table-dark sticky-top">
            <tr>
              <th class="sticky-col" style="min-width:160px"><?= t('employee_name') ?></th>
              <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $dateStr  = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-" . str_pad($d,2,'0',STR_PAD_LEFT);
                $dayName  = date('D', strtotime($dateStr));
                $isHoliday = isset($holidayMap[$dateStr]);
                $holidayName = $isHoliday ? (lang() === 'ar' ? ($holidayMap[$dateStr]['name_ar'] ?? $holidayMap[$dateStr]['name_en']) : $holidayMap[$dateStr]['name_en']) : '';
                $isWeekend = in_array($dayName, ['Fri','Sat']);
                $isToday   = $dateStr === $today;
              ?>
              <th class="text-center small <?= $isWeekend ? 'table-secondary' : '' ?> <?= $isToday ? 'table-primary' : '' ?> <?= $isHoliday ? 'table-danger' : '' ?>" style="min-width:42px" title="<?= $holidayName ?>">
                <div><?= $dayName ?></div>
                <div class="fw-bold"><?= $d ?></div>
                <?php if ($isHoliday): ?><div class="badge bg-danger" style="font-size:0.6rem">H</div><?php endif; ?>
              </th>
              <?php endfor; ?>
              <th class="text-center">P</th>
              <th class="text-center text-danger">A</th>
              <th class="text-center text-warning">L</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($employees as $emp):
            $empAtt = $attMap[$emp['id']] ?? [];
            $countP = 0; $countA = 0; $countL = 0;
          ?>
          <tr>
            <td class="sticky-col fw-500">
              <div><?= h(lang() === 'ar' ? ($emp['name_ar'] ?: $emp['name_en']) : $emp['name_en']) ?></div>
              <small class="text-muted"><?= h($emp['employee_no']) ?></small>
            </td>
            <?php for ($d = 1; $d <= $daysInMonth; $d++):
              $dateStr = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-" . str_pad($d,2,'0',STR_PAD_LEFT);
              $dayName = date('D', strtotime($dateStr));
              $isWeekend = in_array($dayName, ['Fri','Sat']);
              $isHoliday = isset($holidayMap[$dateStr]);
              $rec    = $empAtt[$dateStr] ?? null;
              $curSt  = $rec ? $rec['status'] : ($isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : ''));
              if ($curSt === 'present') $countP++;
              elseif ($curSt === 'absent') $countA++;
              elseif ($curSt === 'late') $countL++;
              $bgClass = isset($statusColors[$curSt]) ? 'bg-' . $statusColors[$curSt] . ' bg-opacity-25' : '';
            ?>
            <td class="text-center p-0 <?= $isWeekend ? 'table-secondary' : $bgClass ?>">
              <?php if ($isWeekend): ?>
                <span class="text-muted small">—</span>
              <?php else: ?>
                <div class="d-flex flex-column gap-1 p-1">
                  <select class="form-select form-select-sm att-select border-0 bg-transparent" style="font-size:10px;padding:1px 0"
                          name="att[<?= $emp['id'] ?>][<?= $dateStr ?>][status]"
                          onchange="updateCell(this)">
                    <option value=""></option>
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $curSt === $s ? 'selected' : '' ?>><?= $statusShort[$s] ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="number" class="form-control form-control-sm border-0 bg-transparent text-center p-0" style="font-size:9px;height:16px"
                         name="att[<?= $emp['id'] ?>][<?= $dateStr ?>][late_minutes]"
                         placeholder="Late min" value="<?= $rec ? (int)$rec['late_minutes'] : '' ?>" min="0" max="480">
                  <input type="number" step="0.5" class="form-control form-control-sm border-0 bg-transparent text-center p-0" style="font-size:9px;height:16px"
                         name="att[<?= $emp['id'] ?>][<?= $dateStr ?>][overtime_hours]"
                         placeholder="OT hrs" value="<?= $rec ? (float)$rec['overtime_hours'] : '' ?>" min="0" max="24">
                </div>
              <?php endif; ?>
            </td>
            <?php endfor; ?>
            <td class="text-center text-success fw-bold"><?= $countP ?></td>
            <td class="text-center text-danger fw-bold"><?= $countA ?></td>
            <td class="text-center text-warning fw-bold"><?= $countL ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($employees)): ?>
          <tr><td colspan="<?= $daysInMonth + 4 ?>" class="text-center py-4 text-muted"><?= t('no_records') ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div>
        <small class="text-muted">Showing <?= ($offset + 1) ?>-<?= min($offset + $perPage, $totalCount) ?> of <?= $totalCount ?> employees</small>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=attendance&year=<?= $year ?>&month=<?= $month ?>&dept=<?= $deptFilter ?>&p=<?= $page - 1 ?>">«</a>
            </li>
            <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=attendance&year=<?= $year ?>&month=<?= $month ?>&dept=<?= $deptFilter ?>&p=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=attendance&year=<?= $year ?>&month=<?= $month ?>&dept=<?= $deptFilter ?>&p=<?= $page + 1 ?>">»</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= t('save') ?></button>
      </div>
    </div>
  </div>
</form>
<script>
function updateCell(sel) {
  const val = sel.value;
  const colors = {present:'rgba(16,185,129,0.15)',absent:'rgba(239,68,68,0.15)',late:'rgba(245,158,11,0.15)',half_day:'rgba(6,182,212,0.15)',leave:'rgba(59,130,246,0.15)'};
  sel.closest('td').style.backgroundColor = colors[val] || '';
}
// Mark all present for weekdays
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.createElement('button');
  btn.type = 'button'; btn.className = 'btn btn-sm btn-outline-success me-2';
  btn.innerHTML = '<i class="fas fa-check-double me-1"></i>Mark All Present';
  btn.onclick = () => document.querySelectorAll('.att-select').forEach(s => { if(!s.value){s.value='present';updateCell(s);} });
  document.querySelector('.card-footer').prepend(btn);
});
</script>
