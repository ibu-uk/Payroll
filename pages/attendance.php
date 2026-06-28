<?php
$pageTitle = t('attendance');
$mode = get('mode', 'monthly');
$year  = (int)get('year', date('Y'));
$month = (int)get('month', date('m'));
$deptFilter = get('dept');
$page = max(1, (int)get('p', 1));
$perPage = 50; // Show 50 employees per page for attendance

if (isPost()) {
    checkCsrf();
    requireRole('admin', 'manager', 'hr');
    $mode = post('mode', 'monthly');

    // Daily attendance entry with check-in/out times
    if ($mode === 'daily') {
        $date = post('date');
        $entries = post('daily', []);
        foreach ($entries as $empId => $data) {
            $empId = (int)$empId;
            if (!$empId) continue;

            $checkIn = $data['check_in'] ?? null;
            $checkOut = $data['check_out'] ?? null;
            $status = $data['status'] ?? '';
            $lateMinutes = (int)($data['late_minutes'] ?? 0);
            $overtimeHours = (float)($data['overtime_hours'] ?? 0);

            // Skip empty rows
            if (!$status && !$checkIn && !$checkOut) {
                continue;
            }

            // Auto-calculate status, late, and overtime from check-in/out
            if ($checkIn && $checkOut) {
                $emp = DB::row("SELECT e.*, jt.working_hours FROM employees e LEFT JOIN job_titles jt ON jt.id=e.job_title_id WHERE e.id=?", [$empId]);
                $workHours = (int)($emp['working_hours'] ?? 8);
                $startTime = '08:00';
                $endTime = date('H:i', strtotime($startTime . " + {$workHours} hours"));

                $checkInMin = strtotime($date . ' ' . $checkIn) / 60;
                $startMin = strtotime($date . ' ' . $startTime) / 60;
                $checkOutMin = strtotime($date . ' ' . $checkOut) / 60;
                $endMin = strtotime($date . ' ' . $endTime) / 60;

                if ($checkInMin > $startMin) {
                    $lateMinutes = max(0, (int)($checkInMin - $startMin));
                }
                if ($checkOutMin > $endMin) {
                    $overtimeHours = max(0, round(($checkOutMin - $endMin) / 60, 2));
                }

                if (!$status) {
                    $status = $lateMinutes > 0 ? 'late' : 'present';
                }
            }

            if (!$status) $status = 'present';

            // Check if this date is a holiday
            $holiday = DB::row("SELECT id FROM holidays WHERE holiday_date=? OR (is_recurring=1 AND MONTH(holiday_date)=? AND DAY(holiday_date)=?)",
                [$date, substr($date, 5, 2), substr($date, 8, 2)]);
            if ($holiday && $status === 'present') {
                $status = 'holiday';
            }

            DB::q("INSERT INTO attendance (employee_id,attendance_date,check_in,check_out,status,late_minutes,overtime_hours)
                   VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE check_in=VALUES(check_in),check_out=VALUES(check_out),status=VALUES(status),late_minutes=VALUES(late_minutes),overtime_hours=VALUES(overtime_hours)",
                  [$empId, $date, $checkIn, $checkOut, $status, $lateMinutes, $overtimeHours]);
        }
        setFlash('success', 'Daily attendance saved.');
        redirect("index.php?page=attendance&mode=daily&date=$date");
    }

    // Monthly grid save
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
    <a href="?page=attendance&mode=monthly" class="btn btn-<?= $mode === 'monthly' ? 'primary' : 'outline-secondary' ?> btn-sm">
      <i class="fas fa-calendar-alt me-1"></i><?= t('monthly_grid') ?>
    </a>
    <a href="?page=attendance&mode=daily" class="btn btn-<?= $mode === 'daily' ? 'primary' : 'outline-secondary' ?> btn-sm">
      <i class="fas fa-clock me-1"></i><?= t('daily_entry') ?>
    </a>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i><?= t('print') ?></button>
  </div>
</div>

<?php if ($mode === 'daily'): ?>
<!-- Daily Attendance Entry -->
<?php
$dailyDate = get('date', date('Y-m-d'));
$dailyEmps = DB::rows("SELECT e.id, e.name_en, e.name_ar, e.employee_no, d.name_en as dept_en, jt.working_hours
    FROM employees e
    LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN job_titles jt ON jt.id=e.job_title_id
    WHERE e.status IN ('active','probation')
    ORDER BY e.name_en");
$dailyRecords = [];
if ($dailyDate) {
    $recs = DB::rows("SELECT employee_id, check_in, check_out, status, late_minutes, overtime_hours FROM attendance WHERE attendance_date=?", [$dailyDate]);
    foreach ($recs as $r) {
        $dailyRecords[$r['employee_id']] = $r;
    }
}
?>
<div class="card card-modern mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex align-items-center gap-3">
      <input type="hidden" name="page" value="attendance">
      <input type="hidden" name="mode" value="daily">
      <label class="form-label mb-0"><strong><?= t('date') ?>:</strong></label>
      <input type="date" class="form-control form-control-sm w-auto" name="date" value="<?= h($dailyDate) ?>" onchange="this.form.submit()">
    </form>
  </div>
</div>

<form method="POST">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="mode" value="daily">
  <input type="hidden" name="date" value="<?= h($dailyDate) ?>">
  <div class="card card-modern">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th><?= t('employee_name') ?></th>
              <th><?= t('department') ?></th>
              <th><?= t('check_in') ?></th>
              <th><?= t('check_out') ?></th>
              <th><?= t('status') ?></th>
              <th><?= t('late_minutes') ?></th>
              <th><?= t('overtime_hours') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dailyEmps as $e):
                $rec = $dailyRecords[$e['id']] ?? [];
                $workHours = (int)($e['working_hours'] ?? 8);
            ?>
            <tr>
              <td>
                <strong><?= h(lang() === 'ar' ? ($e['name_ar'] ?: $e['name_en']) : $e['name_en']) ?></strong>
                <small class="text-muted d-block"><?= h($e['employee_no']) ?></small>
              </td>
              <td><?= h($e['dept_en'] ?? '-') ?></td>
              <td><input type="time" class="form-control form-control-sm" name="daily[<?= $e['id'] ?>][check_in]" value="<?= h($rec['check_in'] ?? '') ?>" onchange="calculateDailyRow(this)"></td>
              <td><input type="time" class="form-control form-control-sm" name="daily[<?= $e['id'] ?>][check_out]" value="<?= h($rec['check_out'] ?? '') ?>" onchange="calculateDailyRow(this)"></td>
              <td>
                <select class="form-select form-select-sm" name="daily[<?= $e['id'] ?>][status]">
                  <?php foreach ($statuses as $s): ?>
                  <option value="<?= $s ?>" <?= ($rec['status'] ?? '') === $s ? 'selected' : '' ?>><?= t($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" class="form-control form-control-sm" name="daily[<?= $e['id'] ?>][late_minutes]" value="<?= (int)($rec['late_minutes'] ?? 0) ?>" min="0" style="width:80px"></td>
              <td><input type="number" step="0.5" class="form-control form-select-sm" name="daily[<?= $e['id'] ?>][overtime_hours]" value="<?= (float)($rec['overtime_hours'] ?? 0) ?>" min="0" style="width:80px"></td>
              <td class="d-none work-hours" data-hours="<?= $workHours ?>"></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($dailyEmps)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted"><?= t('no_active_employees') ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= t('save_daily_attendance') ?></button>
    </div>
  </div>
</form>
<script>
function calculateDailyRow(input) {
  const row = input.closest('tr');
  const checkIn = row.querySelector('input[type="time"]').value;
  const checkOut = row.querySelectorAll('input[type="time"]')[1].value;
  const workHours = parseInt(row.querySelector('.work-hours').dataset.hours || 8);
  if (!checkIn || !checkOut) return;

  const startTime = '08:00';
  const endTime = new Date('2000-01-01T' + startTime).getTime() + (workHours * 3600000);
  const endTimeStr = new Date(endTime).toTimeString().slice(0, 5);

  const date = '2000-01-01';
  const checkInMin = new Date(date + 'T' + checkIn).getTime() / 60000;
  const startMin = new Date(date + 'T' + startTime).getTime() / 60000;
  const checkOutMin = new Date(date + 'T' + checkOut).getTime() / 60000;
  const endMin = new Date(date + 'T' + endTimeStr).getTime() / 60000;

  let late = 0, ot = 0;
  if (checkInMin > startMin) late = Math.round(checkInMin - startMin);
  if (checkOutMin > endMin) ot = Math.round((checkOutMin - endMin) / 60 * 10) / 10;

  row.querySelector('input[name$="[late_minutes]"]').value = late;
  row.querySelector('input[name$="[overtime_hours]"]').value = ot;
  const statusSelect = row.querySelector('select[name$="[status]"]');
  if (statusSelect.value === 'present' || statusSelect.value === '') {
    statusSelect.value = late > 0 ? 'late' : 'present';
  }
}
</script>
<?php return; endif; ?>

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
