<?php
$pageTitle = 'Bonuses & Commissions';
$subAction = get('sub', 'list');

// Handle POST
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    
    if ($sub === 'add') {
        requireRole('admin', 'manager', 'hr');
        $bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
        if (!$bonusesTableExists) {
            setFlash('error', 'Bonuses table does not exist. Please run the migration: migrations/add_advanced_features.sql');
            redirect('index.php?page=bonuses');
        }

        $id = DB::insert('bonuses', [
            'employee_id' => (int)post('employee_id'),
            'bonus_type' => post('bonus_type'),
            'amount' => (float)post('amount'),
            'period_year' => (int)post('period_year'),
            'period_month' => (int)post('period_month'),
            'description' => post('description'),
            'status' => 'pending'
        ]);
        setFlash('success', 'Bonus added successfully.');
        redirect('index.php?page=bonuses');
    }

    if ($sub === 'approve') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('bonuses', [
            'status' => 'approved',
            'approved_by' => currentUser()['id'],
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id=?', [$id]);
        setFlash('success', 'Bonus approved.');
        redirect('index.php?page=bonuses');
    }

    if ($sub === 'reject') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('bonuses', ['status' => 'rejected'], 'id=?', [$id]);
        setFlash('success', 'Bonus rejected.');
        redirect('index.php?page=bonuses');
    }

    if ($sub === 'mark_paid') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('bonuses', [
            'status' => 'paid',
            'payment_date' => post('payment_date') ?: date('Y-m-d')
        ], 'id=?', [$id]);
        setFlash('success', 'Bonus marked as paid.');
        redirect('index.php?page=bonuses');
    }
}

// Add/Edit Form
if ($subAction === 'add') {
    $employees = DB::rows("SELECT id, name_en, employee_no FROM employees WHERE status='active' ORDER BY name_en");
    ?>
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-gift me-2"></i>Add Bonus</h1>
    </div>
    <div class="card card-modern">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="add">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee</label>
              <select class="form-select select2" name="employee_id" required>
                <option value="">Select employee...</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= h($e['name_en']) ?> (<?= h($e['employee_no']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Bonus Type</label>
              <select class="form-select" name="bonus_type" required>
                <option value="performance">Performance Bonus</option>
                <option value="sales_commission">Sales Commission</option>
                <option value="project_bonus">Project Bonus</option>
                <option value="signing_bonus">Signing Bonus</option>
                <option value="referral_bonus">Referral Bonus</option>
                <option value="year_end_bonus">Year-End Bonus</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Amount</label>
              <input type="number" step="0.001" class="form-control" name="amount" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Year</label>
              <select class="form-select" name="period_year" required>
                <?php for($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Month</label>
              <select class="form-select" name="period_month" required>
                <?php for($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Bonus</button>
              <a href="index.php?page=bonuses" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

// List View
$bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
$bonuses = [];
if ($bonusesTableExists) {
    $bonuses = DB::rows("
        SELECT b.*, e.name_en, e.employee_no, u.name as approved_by_name
        FROM bonuses b
        LEFT JOIN employees e ON e.id = b.employee_id
        LEFT JOIN users u ON u.id = b.approved_by
        ORDER BY b.created_at DESC
    ");
}
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-gift me-2"></i>Bonuses & Commissions</h1>
  <a href="?page=bonuses&sub=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Bonus</a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Period</th>
          <th>Status</th>
          <th>Payment Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bonuses as $b): ?>
        <tr>
          <td><?= h($b['name_en']) ?> (<?= h($b['employee_no']) ?>)</td>
          <td><?= ucfirst(str_replace('_', ' ', $b['bonus_type'])) ?></td>
          <td class="fw-bold text-success"><?= money((float)$b['amount']) ?></td>
          <td><?= date('F Y', mktime(0,0,0,$b['period_month'],1,$b['period_year'])) ?></td>
          <td><?= statusBadge($b['status']) ?></td>
          <td><?= $b['payment_date'] ? fdate($b['payment_date']) : '-' ?></td>
          <td>
            <?php if ($b['status'] === 'pending'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="approve">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="reject">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
            </form>
            <?php elseif ($b['status'] === 'approved'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="mark_paid">
              <input type="hidden" name="id" value="<?= $b['id'] ?>">
              <button class="btn btn-sm btn-primary" title="Mark as Paid"><i class="fas fa-money-bill"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bonuses)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">No bonuses found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
