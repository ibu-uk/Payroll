<?php
$pageTitle = t('leaves');
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    if ($sub === 'request') {
        requireRole('admin', 'manager', 'hr');
        $empId = (int)post('employee_id');
        $ltId  = (int)post('leave_type_id');
        $start = post('start_date');
        $end   = post('end_date');
        $days  = max(1, (int)((strtotime($end) - strtotime($start)) / 86400) + 1);
        DB::insert('leave_requests', ['employee_id'=>$empId,'leave_type_id'=>$ltId,'start_date'=>$start,'end_date'=>$end,'days'=>$days,'reason'=>post('reason'),'status'=>'pending']);
        setFlash('success', t('saved_success'));
    } elseif ($sub === 'approve') {
        requireRole('admin', 'manager', 'hr');
        $reqId = (int)post('id');
        $req = DB::row("SELECT * FROM leave_requests WHERE id=?", [$reqId]);
        if ($req && $req['status'] !== 'approved') {
            $year = (int)date('Y', strtotime($req['start_date']));
            updateLeaveBalance($req['employee_id'], $req['leave_type_id'], $year, (float)$req['days']);
        }
        DB::update('leave_requests',['status'=>'approved','approved_by'=>currentUser()['id'],'approved_at'=>date('Y-m-d H:i:s')],'id=?',[$reqId]);
        setFlash('success', t('approved_status'));
    } elseif ($sub === 'reject') {
        requireRole('admin', 'manager', 'hr');
        $reqId = (int)post('id');
        $req = DB::row("SELECT * FROM leave_requests WHERE id=?", [$reqId]);
        if ($req && $req['status'] === 'approved') {
            // Restore balance if it was previously approved
            $year = (int)date('Y', strtotime($req['start_date']));
            updateLeaveBalance($req['employee_id'], $req['leave_type_id'], $year, -1 * (float)$req['days']);
        }
        DB::update('leave_requests',['status'=>'rejected','rejection_reason'=>post('reason')],'id=?',[$reqId]);
        setFlash('success', t('rejected'));
    }
    redirect('index.php?page=leaves');
}
$employees  = DB::rows("SELECT id,name_en,name_ar FROM employees WHERE status='active' ORDER BY name_en LIMIT 200");
$leaveTypes = DB::rows("SELECT * FROM leave_types WHERE is_active=1");
$currentYear = (int)date('Y');

$filter = get('status','pending');
$where = $filter ? "WHERE lr.status=?" : "WHERE 1=1";
$params = $filter ? [$filter] : [];

$leavePage = max(1, (int)get('p', 1));
$leavePerPage = 50;
$totalLeaveCount = DB::val("SELECT COUNT(*) FROM leave_requests lr $where", $params);
$totalLeavePages = ceil($totalLeaveCount / $leavePerPage);
$leaveOffset = ($leavePage - 1) * $leavePerPage;
$requests = DB::rows("SELECT lr.*,e.name_en,e.name_ar,lt.name_en as lt_en,lt.name_ar as lt_ar,lt.is_paid
    FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id JOIN leave_types lt ON lt.id=lr.leave_type_id
    $where ORDER BY lr.created_at DESC LIMIT $leavePerPage OFFSET $leaveOffset", $params);

// Get leave balance for current year (only for employees visible in dropdown to reduce memory)
$balanceTableExists = DB::row("SHOW TABLES LIKE 'leave_balance'") !== null;
$leaveBalances = [];
if ($balanceTableExists) {
    $empIds = array_column($employees, 'id');
    if (!empty($empIds)) {
        $placeholders = implode(',', array_fill(0, count($empIds), '?'));
        $leaveBalances = DB::rows("
            SELECT lb.employee_id, lb.leave_type_id, lt.name_en, lt.name_ar, 
                   lb.total_days, lb.used_days, lb.remaining_days
            FROM leave_balance lb
            JOIN leave_types lt ON lt.id = lb.leave_type_id
            WHERE lb.year = YEAR(CURDATE()) AND lb.employee_id IN ($placeholders)
        ", $empIds);
    }
}
?>
<div class="page-header d-flex justify-content-between">
  <h1 class="page-title"><i class="fas fa-umbrella-beach me-2"></i><?= t('leaves') ?></h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal">
    <i class="fas fa-plus me-1"></i>New Request
  </button>
</div>
<!-- Filter tabs -->
<ul class="nav nav-pills mb-3">
  <?php foreach (['pending','approved','rejected',''] as $s): ?>
  <li class="nav-item">
    <a class="nav-link <?= $filter === $s ? 'active' : '' ?>" href="?page=leaves&status=<?= $s ?>">
      <?= $s ? ucfirst($s) : 'All' ?>
      <?php if ($s === 'pending'): ?>
        <span class="badge bg-danger ms-1"><?= DB::val("SELECT COUNT(*) FROM leave_requests WHERE status='pending'") ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>
<div class="card card-modern">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-dark"><tr>
          <th><?= t('employee_name') ?></th><th><?= t('leave_type') ?></th>
          <th><?= t('start_date') ?></th><th><?= t('end_date') ?></th>
          <th><?= t('days') ?></th><th><?= t('status') ?></th><th><?= t('actions') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
        <tr>
          <td><strong><?= h(lang()==='ar'?($r['name_ar']?:$r['name_en']):$r['name_en']) ?></strong></td>
          <td><?= h(lang()==='ar'?($r['lt_ar']?:$r['lt_en']):$r['lt_en']) ?> <?= $r['is_paid']?'<span class="badge bg-success">Paid</span>':'<span class="badge bg-warning">Unpaid</span>' ?></td>
          <td><?= fdate($r['start_date']) ?></td><td><?= fdate($r['end_date']) ?></td>
          <td><span class="badge bg-info"><?= $r['days'] ?> <?= t('days') ?></span></td>
          <td><?= statusBadge($r['status'] === 'approved' ? 'approved_status' : $r['status']) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <div class="btn-group btn-group-sm">
              <form method="POST" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="sub_action" value="approve"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
              </form>
              <form method="POST" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="sub_action" value="reject"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
              </form>
            </div>
            <?php else: echo '<span class="text-muted small">—</span>'; endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($requests)): ?><tr><td colspan="7" class="text-center py-4 text-muted"><?= t('no_records') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($totalLeavePages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Showing <?= $leaveOffset + 1 ?>–<?= min($leaveOffset + $leavePerPage, $totalLeaveCount) ?> of <?= $totalLeaveCount ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($pg = 1; $pg <= $totalLeavePages; $pg++): ?>
      <li class="page-item <?= $pg == $leavePage ? 'active' : '' ?>">
        <a class="page-link" href="?page=leaves&status=<?= $filter ?>&p=<?= $pg ?>"><?= $pg ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<!-- New Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">New Leave Request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="request">
      <div class="modal-body">
        <div class="mb-3"><label class="form-label"><?= t('employee_name') ?></label>
          <select class="form-select select2" name="employee_id" required>
            <option value="">-- <?= t('employees') ?> --</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>"><?= h(lang()==='ar'?($e['name_ar']?:$e['name_en']):$e['name_en']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3"><label class="form-label"><?= t('leave_type') ?></label>
          <select class="form-select" name="leave_type_id" id="leaveTypeSelect" required onchange="updateLeaveBalance()">
            <?php foreach ($leaveTypes as $lt): ?><option value="<?= $lt['id'] ?>" data-days="<?= $lt['days_per_year'] ?>"><?= h(lang()==='ar'?($lt['name_ar']?:$lt['name_en']):$lt['name_en']) ?> (<?= $lt['days_per_year'] ?> <?= t('days_per_year') ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col"><label class="form-label"><?= t('start_date') ?></label><input type="date" class="form-control" name="start_date" id="startDate" required onchange="calculateDays()"></div>
          <div class="col"><label class="form-label"><?= t('end_date') ?></label><input type="date" class="form-control" name="end_date" id="endDate" required onchange="calculateDays()"></div>
        </div>
        <div class="mb-3">
          <div class="alert alert-info d-flex justify-content-between align-items-center">
            <span><strong><?= t('leave_balance') ?>:</strong> <span id="balanceDisplay"><?= t('select_emp_leave_type') ?></span></span>
            <span class="badge bg-primary" id="daysBadge">0 <?= t('days') ?></span>
          </div>
        </div>
        <div class="mb-3"><label class="form-label"><?= t('reason') ?></label><textarea class="form-control" name="reason" rows="3"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= t('save') ?></button></div>
    </form>
  </div></div>
</div>

<script>
const leaveBalances = <?= j($leaveBalances) ?>;

function getBalance(empId, leaveTypeId) {
  const balance = leaveBalances.find(b => b.employee_id == empId && b.leave_type_id == leaveTypeId);
  if (balance) {
    return { total: parseFloat(balance.total_days), used: parseFloat(balance.used_days), remaining: parseFloat(balance.remaining_days) };
  }
  // Return default if no balance record exists
  const leaveType = document.querySelector(`#leaveTypeSelect option[value="${leaveTypeId}"]`);
  const totalDays = leaveType ? parseFloat(leaveType.dataset.days) : 0;
  return { total: totalDays, used: 0, remaining: totalDays };
}

function updateLeaveBalance() {
  const empId = document.querySelector('[name="employee_id"]').value;
  const leaveTypeId = document.querySelector('#leaveTypeSelect').value;

  if (empId && leaveTypeId) {
    const balance = getBalance(empId, leaveTypeId);
    document.getElementById('balanceDisplay').textContent =
      `${balance.remaining} <?= t('remaining') ?> <?= t('of') ?> ${balance.total} <?= t('days') ?>`;
    document.getElementById('daysBadge').textContent = `${balance.used} <?= t('used') ?>`;
  }
}

function calculateDays() {
  const start = document.getElementById('startDate').value;
  const end = document.getElementById('endDate').value;
  if (start && end) {
    const diff = Math.round((new Date(end) - new Date(start)) / 86400000) + 1;
    document.getElementById('daysBadge').textContent = `${diff} <?= t('days') ?>`;
  }
}

// Initialize when employee changes
document.querySelector('[name="employee_id"]').addEventListener('change', updateLeaveBalance);
</script>
