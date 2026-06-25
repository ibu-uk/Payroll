<?php
$pageTitle = 'Gratuity / End of Service';
$subAction = get('sub', 'list');

// Handle POST
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    
    if ($sub === 'calculate') {
        requireRole('admin', 'manager', 'hr');
        $gratuityTableExists = DB::row("SHOW TABLES LIKE 'gratuity'") !== null;
        if (!$gratuityTableExists) {
            setFlash('error', 'Gratuity table does not exist. Please run the migration: migrations/add_advanced_features.sql');
            redirect('index.php?page=gratuity');
        }
        
        $empId = (int)post('employee_id');
        $emp = DB::row("SELECT * FROM employees WHERE id=?", [$empId]);
        if (!$emp) {
            setFlash('error', 'Employee not found.');
            redirect('index.php?page=gratuity');
        }
        
        $hireDate = $emp['hire_date'];
        $terminationDate = post('termination_date') ?: date('Y-m-d');
        $lastSalary = (float)($emp['basic_salary'] + DB::val("SELECT COALESCE(SUM(amount),0) FROM employee_allowances WHERE employee_id=? AND is_active=1", [$empId]));
        
        // Calculate years of service
        $hire = new DateTime($hireDate);
        $term = new DateTime($terminationDate);
        $diff = $hire->diff($term);
        $yearsOfService = $diff->y + ($diff->m / 12) + ($diff->d / 365);
        
        // Kuwait Labor Law calculation
        // First 5 years: 15 days per year
        // After 5 years: 30 days per year
        if ($yearsOfService <= 5) {
            $gratuityDays = $yearsOfService * 15;
        } else {
            $gratuityDays = (5 * 15) + (($yearsOfService - 5) * 30);
        }
        
        $dailyRate = $lastSalary / 30; // Assuming 30 days per month
        $gratuityAmount = $gratuityDays * $dailyRate;
        
        $id = DB::insert('gratuity', [
            'employee_id' => $empId,
            'calculation_date' => date('Y-m-d'),
            'hire_date' => $hireDate,
            'termination_date' => $terminationDate,
            'years_of_service' => $yearsOfService,
            'last_salary' => $lastSalary,
            'gratuity_amount' => $gratuityAmount,
            'calculation_method' => 'kuwait_labor_law',
            'status' => 'calculated'
        ]);
        
        setFlash('success', 'Gratuity calculated successfully.');
        redirect('index.php?page=gratuity&sub=view&id=' . $id);
    }
    
    if ($sub === 'approve') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('gratuity', [
            'status' => 'approved',
            'approved_by' => currentUser()['id'],
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id=?', [$id]);
        setFlash('success', 'Gratuity approved.');
        redirect('index.php?page=gratuity');
    }
    
    if ($sub === 'mark_paid') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('gratuity', ['status' => 'paid'], 'id=?', [$id]);
        setFlash('success', 'Gratuity marked as paid.');
        redirect('index.php?page=gratuity');
    }
}

// View Calculation
if ($subAction === 'view') {
    $id = (int)get('id');
    $g = DB::row("
        SELECT g.*, e.name_en, e.employee_no, u.name as approved_by_name
        FROM gratuity g
        LEFT JOIN employees e ON e.id = g.employee_id
        LEFT JOIN users u ON u.id = g.approved_by
        WHERE g.id = ?
    ", [$id]);
    if (!$g) redirect('index.php?page=gratuity');
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div>
        <h1 class="page-title"><i class="fas fa-calculator me-2"></i>Gratuity Calculation</h1>
        <p class="text-muted mb-0"><?= h($g['name_en']) ?> - <?= fdate($g['calculation_date']) ?></p>
      </div>
      <a href="index.php?page=gratuity" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern">Employee Details</h5></div>
          <div class="card-body">
            <div class="mb-2"><strong>Name:</strong> <?= h($g['name_en']) ?></div>
            <div class="mb-2"><strong>Employee No:</strong> <?= h($g['employee_no']) ?></div>
            <div class="mb-2"><strong>Hire Date:</strong> <?= fdate($g['hire_date']) ?></div>
            <div class="mb-2"><strong>Termination Date:</strong> <?= fdate($g['termination_date']) ?></div>
            <div class="mb-2"><strong>Years of Service:</strong> <?= number_format($g['years_of_service'], 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern">Calculation Details</h5></div>
          <div class="card-body">
            <div class="mb-2"><strong>Last Salary:</strong> <?= money((float)$g['last_salary']) ?></div>
            <div class="mb-2"><strong>Daily Rate:</strong> <?= money((float)$g['last_salary'] / 30) ?></div>
            <div class="mb-2"><strong>Method:</strong> <?= ucfirst(str_replace('_', ' ', $g['calculation_method'])) ?></div>
            <div class="mb-2"><strong>Status:</strong> <?= statusBadge($g['status']) ?></div>
            <?php if ($g['approved_by_name']): ?>
            <div class="mb-2"><strong>Approved By:</strong> <?= h($g['approved_by_name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card card-modern bg-success bg-opacity-10">
          <div class="card-body text-center">
            <h3 class="text-success">Total Gratuity Amount</h3>
            <h1 class="display-4 text-success"><?= money((float)$g['gratuity_amount']) ?></h1>
            <?php if ($g['status'] === 'calculated'): ?>
            <div class="mt-3">
              <form method="POST" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="sub_action" value="approve">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <button class="btn btn-success"><i class="fas fa-check me-2"></i>Approve</button>
              </form>
            </div>
            <?php elseif ($g['status'] === 'approved'): ?>
            <div class="mt-3">
              <form method="POST" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="sub_action" value="mark_paid">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <button class="btn btn-primary"><i class="fas fa-money-bill me-2"></i>Mark as Paid</button>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    return;
}

// Calculate Form
if ($subAction === 'calculate') {
    $employees = DB::rows("SELECT id, name_en, employee_no, hire_date, basic_salary FROM employees WHERE status IN ('active','terminated') ORDER BY name_en");
    ?>
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-calculator me-2"></i>Calculate Gratuity</h1>
    </div>
    <div class="card card-modern">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="calculate">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee</label>
              <select class="form-select select2" name="employee_id" required>
                <option value="">Select employee...</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" data-hire="<?= $e['hire_date'] ?>">
                  <?= h($e['name_en']) ?> (<?= h($e['employee_no']) ?>) - Hired: <?= fdate($e['hire_date']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Termination Date</label>
              <input type="date" class="form-control" name="termination_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-calculator me-2"></i>Calculate Gratuity</button>
              <a href="index.php?page=gratuity" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

// List View
$gratuityTableExists = DB::row("SHOW TABLES LIKE 'gratuity'") !== null;
$gratuities = [];
if ($gratuityTableExists) {
    $gratuities = DB::rows("
        SELECT g.*, e.name_en, e.employee_no, u.name as approved_by_name
        FROM gratuity g
        LEFT JOIN employees e ON e.id = g.employee_id
        LEFT JOIN users u ON u.id = g.approved_by
        ORDER BY g.created_at DESC
    ");
}
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-calculator me-2"></i>Gratuity / End of Service</h1>
  <a href="?page=gratuity&sub=calculate" class="btn btn-primary"><i class="fas fa-calculator me-2"></i>Calculate Gratuity</a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Hire Date</th>
          <th>Termination Date</th>
          <th>Years of Service</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gratuities as $g): ?>
        <tr>
          <td><?= h($g['name_en']) ?> (<?= h($g['employee_no']) ?>)</td>
          <td><?= fdate($g['hire_date']) ?></td>
          <td><?= fdate($g['termination_date']) ?></td>
          <td><?= number_format($g['years_of_service'], 2) ?></td>
          <td class="fw-bold text-success"><?= money((float)$g['gratuity_amount']) ?></td>
          <td><?= statusBadge($g['status']) ?></td>
          <td>
            <a href="?page=gratuity&sub=view&id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($gratuities)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">No gratuity calculations found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
