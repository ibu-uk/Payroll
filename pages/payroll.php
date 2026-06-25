<?php
$pageTitle = t('payroll');
$subAction = get('sub', 'list');

// ── Process payroll ────────────────────────────────────────────────────────────
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');

    if ($sub === 'create_period') {
        requireRole('admin', 'manager', 'hr');
        $year  = (int)post('year');
        $month = (int)post('month');
        $payDate = post('payment_date');
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate   = date('Y-m-t', strtotime($startDate));
        $label = monthName($month, lang()) . " $year";

        $existing = DB::row("SELECT id FROM payroll_periods WHERE period_year=? AND period_month=?", [$year, $month]);
        if ($existing) {
            setFlash('error', 'Payroll period already exists for this month.');
        } else {
            $id = DB::insert('payroll_periods', [
                'period_year'=>$year,'period_month'=>$month,'period_label'=>$label,
                'start_date'=>$startDate,'end_date'=>$endDate,'payment_date'=>$payDate,
                'status'=>'draft','created_by'=>currentUser()['id']
            ]);
            setFlash('success', "Payroll period '$label' created.");
        }
        redirect('index.php?page=payroll');
    }

    if ($sub === 'process') {
        requireRole('admin', 'manager');
        $periodId = (int)post('period_id');
        $period   = DB::row("SELECT * FROM payroll_periods WHERE id=?", [$periodId]);
        if (!$period || $period['status'] !== 'draft') {
            setFlash('error', 'Cannot process this payroll period.');
            redirect('index.php?page=payroll');
        }

        DB::q("UPDATE payroll_periods SET status='processing' WHERE id=?", [$periodId]);
        DB::q("DELETE FROM payroll_item_details WHERE payroll_item_id IN (SELECT id FROM payroll_items WHERE payroll_period_id=?)", [$periodId]);
        DB::q("DELETE FROM payroll_items WHERE payroll_period_id=?", [$periodId]);

        // Use batch processing for better performance with large datasets
        $employees = DB::rows("SELECT id FROM employees WHERE status IN ('active','probation')");
        $totalGross = 0; $totalNet = 0; $totalDeds = 0;
        $processedCount = 0;
        $errorCount = 0;
        $batchSize = 100; // Process 100 employees at a time
        $employeeIds = array_column($employees, 'id');
        $batches = array_chunk($employeeIds, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $empId) {
                try {
                    $calc = calculatePayrollItem($empId, $periodId, $period['period_year'], $period['period_month']);
                    if (empty($calc)) {
                        $errorCount++;
                        continue;
                    }

                    $details = $calc['allowance_details'];
                    $dedDetails = $calc['deduction_details'];
                    unset($calc['allowance_details'], $calc['deduction_details']);

                    // Insert payroll item
                    $itemId = DB::insert('payroll_items', $calc);
                    
                    // Insert allowance details
                    foreach ($details as $d) {
                        DB::insert('payroll_item_details', array_merge(['payroll_item_id' => $itemId], $d));
                    }
                    
                    // Insert deduction details
                    foreach ($dedDetails as $d) {
                        DB::insert('payroll_item_details', array_merge(['payroll_item_id' => $itemId], $d));
                    }
                    
                    $totalGross += $calc['gross_salary'];
                    $totalNet   += $calc['net_salary'];
                    $totalDeds  += $calc['total_deductions'];
                    $processedCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    error_log("Payroll processing error for employee $empId: " . $e->getMessage());
                }
            }
        }

        DB::update('payroll_periods', [
            'status'=>'processing','total_gross'=>round($totalGross,3),
            'total_net'=>round($totalNet,3),'total_deductions'=>round($totalDeds,3),
            'employee_count'=>$processedCount
        ], 'id=?', [$periodId]);

        $msg = "Payroll processed for $processedCount employees.";
        if ($errorCount > 0) {
            $msg .= " $errorCount employees had errors and were skipped.";
        }
        setFlash('success', $msg);
        redirect('index.php?page=payroll&sub=view&id=' . $periodId);
    }

    if ($sub === 'approve') {
        requireRole('admin', 'manager');
        $periodId = (int)post('period_id');
        DB::update('payroll_periods', [
            'status'=>'approved','approved_by'=>currentUser()['id'],'approved_at'=>date('Y-m-d H:i:s')
        ], 'id=?', [$periodId]);
        setFlash('success', t('approved') . ' – ' . t('payroll'));
        redirect('index.php?page=payroll');
    }

    if ($sub === 'mark_paid') {
        requireRole('admin', 'manager');
        $periodId = (int)post('period_id');
        DB::update('payroll_periods', ['status'=>'paid'], 'id=?', [$periodId]);
        DB::q("UPDATE payroll_items SET payment_status='paid', payment_date=CURDATE() WHERE payroll_period_id=?", [$periodId]);
        setFlash('success', t('paid') . '!');
        redirect('index.php?page=payroll');
    }
}

// ── View period ────────────────────────────────────────────────────────────────
if ($subAction === 'view') {
    $periodId = (int)get('id');
    $period   = DB::row("SELECT * FROM payroll_periods WHERE id=?", [$periodId]);
    if (!$period) { redirect('index.php?page=payroll'); }

    // Pagination for payroll items
    $page = max(1, (int)get('p', 1));
    $perPage = 50;
    $totalCount = DB::val("SELECT COUNT(*) FROM payroll_items WHERE payroll_period_id=?", [$periodId]);
    $totalPages = ceil($totalCount / $perPage);
    $offset = ($page - 1) * $perPage;

    $items = DB::rows("
        SELECT pi.*, e.name_en, e.name_ar, e.employee_no,
               d.name_en as dept_en, d.name_ar as dept_ar,
               (SELECT COALESCE(SUM(amount), 0) FROM bonuses 
                WHERE employee_id = pi.employee_id 
                AND status = 'approved' 
                AND period_year = ? 
                AND period_month = ?) as bonus_amount
        FROM payroll_items pi
        JOIN employees e ON e.id=pi.employee_id
        LEFT JOIN departments d ON d.id=e.department_id
        WHERE pi.payroll_period_id=?
        ORDER BY e.name_en LIMIT $perPage OFFSET $offset", [$period['period_year'], $period['period_month'], $periodId]);
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div class="d-flex align-items-center gap-3">
        <a href="index.php?page=payroll" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
        <div>
          <h1 class="page-title"><?= h($period['period_label']) ?> – <?= t('payroll') ?></h1>
          <p class="page-sub text-muted"><?= statusBadge($period['status']) ?> &nbsp; <?= $period['employee_count'] ?> <?= t('employees') ?></p>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=export&type=payroll_excel&period_id=<?= $periodId ?>" class="btn btn-outline-success btn-sm">
          <i class="fas fa-file-excel me-1"></i><?= t('export_excel') ?>
        </a>
        <?php if ($period['status'] === 'processing'): ?>
        <form method="POST" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="approve">
          <input type="hidden" name="period_id" value="<?= $periodId ?>">
          <button class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i><?= t('approve_payroll') ?></button>
        </form>
        <?php elseif ($period['status'] === 'approved'): ?>
        <form method="POST" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="mark_paid">
          <input type="hidden" name="period_id" value="<?= $periodId ?>">
          <button class="btn btn-primary btn-sm"><i class="fas fa-money-bill me-1"></i><?= t('paid') ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-body"><div class="stat-value"><?= $period['employee_count'] ?></div><div class="stat-label"><?= t('employees') ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="stat-card stat-green"><div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
          <div class="stat-body"><div class="stat-value stat-value-sm"><?= money((float)$period['total_gross']) ?></div><div class="stat-label"><?= t('gross_salary') ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="stat-card stat-yellow"><div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
          <div class="stat-body"><div class="stat-value stat-value-sm"><?= money((float)$period['total_deductions']) ?></div><div class="stat-label"><?= t('total_deductions') ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="stat-card stat-purple"><div class="stat-icon"><i class="fas fa-wallet"></i></div>
          <div class="stat-body"><div class="stat-value stat-value-sm"><?= money((float)$period['total_net']) ?></div><div class="stat-label"><?= t('net_salary') ?></div></div></div>
      </div>
    </div>

    <div class="card card-modern">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="payrollItemsTable">
            <thead class="table-dark">
              <tr>
                <th><?= t('employee_no') ?></th>
                <th><?= t('employee_name') ?></th>
                <th><?= t('department') ?></th>
                <th><?= t('basic_salary') ?></th>
                <th><?= t('total_allowances') ?></th>
                <th>Bonus</th>
                <th><?= t('gross_salary') ?></th>
                <th><?= t('overtime') ?></th>
                <th><?= t('total_deductions') ?></th>
                <th class="text-success fw-bold"><?= t('net_salary') ?></th>
                <th><?= t('status') ?></th>
                <th><?= t('payslip') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= h($item['employee_no']) ?></span></td>
              <td class="fw-500"><?= h(lang() === 'ar' ? ($item['name_ar'] ?: $item['name_en']) : $item['name_en']) ?></td>
              <td><?= h(lang() === 'ar' ? ($item['dept_ar'] ?? $item['dept_en'] ?? '-') : ($item['dept_en'] ?? '-')) ?></td>
              <td><?= money((float)$item['basic_salary']) ?></td>
              <td class="text-success"><?= money((float)$item['total_allowances']) ?></td>
              <td><?= $item['bonus_amount'] > 0 ? '<span class="text-warning fw-bold">' . money((float)$item['bonus_amount']) . '</span>' : '-' ?></td>
              <td><?= money((float)$item['gross_salary']) ?></td>
              <td><?= $item['overtime_hours'] > 0 ? '<span class="text-info">' . money((float)$item['overtime_amount']) . '</span>' : '-' ?></td>
              <td class="text-danger"><?= money((float)$item['total_deductions']) ?></td>
              <td class="text-success fw-bold"><?= money((float)$item['net_salary']) ?></td>
              <td><?= statusBadge($item['payment_status']) ?></td>
              <td>
                <a href="index.php?page=export&type=payslip_pdf&item_id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" target="_blank">
                  <i class="fas fa-file-pdf"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div>
          <small class="text-muted">Showing <?= ($offset + 1) ?>-<?= min($offset + $perPage, $totalCount) ?> of <?= $totalCount ?> employees</small>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=payroll&sub=view&id=<?= $periodId ?>&p=<?= $page - 1 ?>">«</a>
            </li>
            <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=payroll&sub=view&id=<?= $periodId ?>&p=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=payroll&sub=view&id=<?= $periodId ?>&p=<?= $page + 1 ?>">»</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      </div>
    </div>
    <script>
    $(document).ready(function() {
      $('#payrollItemsTable').DataTable({
        pageLength: 25, lengthChange: false,
        dom: 'Bfrtip', buttons: ['copy','print'],
        language: { search: '<?= t("search") ?>' }
      });
    });
    </script>
    <?php
    return;
}

// ── List view ──────────────────────────────────────────────────────────────────
$periods = DB::rows("SELECT * FROM payroll_periods ORDER BY period_year DESC, period_month DESC");
$curYear = (int)date('Y');
$months  = range(1, 12);
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <div><h1 class="page-title"><i class="fas fa-money-bill-wave me-2"></i><?= t('payroll') ?></h1>
    <p class="text-muted mb-0"><?= t('payroll_period') ?></p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
    <i class="fas fa-plus me-1"></i><?= t('process_payroll') ?>
  </button>
</div>

<div class="row g-3">
  <?php foreach ($periods as $p):
    $statusColors = ['draft'=>'secondary','processing'=>'warning','approved'=>'success','paid'=>'primary','cancelled'=>'danger'];
    $color = $statusColors[$p['status']] ?? 'secondary';
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card card-modern payroll-period-card border-start border-4 border-<?= $color ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="mb-1 fw-700"><?= h($p['period_label']) ?></h5>
            <small class="text-muted"><?= fdate($p['start_date']) ?> – <?= fdate($p['end_date']) ?></small>
          </div>
          <?= statusBadge($p['status']) ?>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="payroll-mini-stat">
              <span class="mini-label"><?= t('employees') ?></span>
              <span class="mini-value"><?= $p['employee_count'] ?></span>
            </div>
          </div>
          <div class="col-6">
            <div class="payroll-mini-stat">
              <span class="mini-label"><?= t('net_salary') ?></span>
              <span class="mini-value text-success"><?= money((float)$p['total_net']) ?></span>
            </div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <?php if ($p['status'] === 'draft'): ?>
          <form method="POST" class="flex-fill"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="sub_action" value="process">
            <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
            <button class="btn btn-warning btn-sm w-100"><i class="fas fa-cog me-1"></i><?= t('process_payroll') ?></button>
          </form>
          <?php else: ?>
          <a href="index.php?page=payroll&sub=view&id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
            <i class="fas fa-eye me-1"></i><?= t('view') ?>
          </a>
          <?php endif; ?>
          <a href="index.php?page=export&type=payroll_excel&period_id=<?= $p['id'] ?>" class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-excel"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($periods)): ?>
  <div class="col-12 text-center py-5 text-muted">
    <i class="fas fa-file-invoice-dollar fa-3x mb-3 opacity-25"></i>
    <p><?= t('no_records') ?></p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
      <?= t('process_payroll') ?>
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- Create Period Modal -->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><?= t('process_payroll') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="sub_action" value="create_period">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><?= t('year') ?></label>
            <select class="form-select" name="year">
              <?php for ($y = $curYear + 1; $y >= $curYear - 2; $y--): ?>
              <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('month') ?></label>
            <select class="form-select" name="month">
              <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m == (int)date('m') ? 'selected' : '' ?>><?= monthName($m, lang()) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= t('payment_date') ?></label>
            <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-25') ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary w-100"><i class="fas fa-cog me-1"></i><?= t('process_payroll') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
