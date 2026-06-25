<?php
$pageTitle = 'Loans Management';
$subAction = get('sub', 'list');

// Handle POST
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    
    if ($sub === 'add') {
        requireRole('admin', 'manager', 'hr');
        $id = DB::insert('loans', [
            'employee_id' => (int)post('employee_id'),
            'loan_amount' => (float)post('loan_amount'),
            'interest_rate' => (float)post('interest_rate', 0),
            'total_amount' => (float)post('total_amount'),
            'installment_amount' => (float)post('installment_amount'),
            'number_of_installments' => (int)post('number_of_installments'),
            'start_date' => post('start_date'),
            'reason' => post('reason'),
            'status' => 'active'
        ]);
        setFlash('success', 'Loan added successfully.');
        redirect('index.php?page=loans');
    }
    
    if ($sub === 'repay') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        $amount = (float)post('amount');
        $loan = DB::row("SELECT * FROM loans WHERE id=?", [$id]);
        if ($loan) {
            $newPaid = $loan['amount_paid'] + $amount;
            $newStatus = $newPaid >= $loan['total_amount'] ? 'paid' : 'active';
            DB::update('loans', [
                'amount_paid' => $newPaid,
                'status' => $newStatus,
                'last_payment_date' => date('Y-m-d')
            ], 'id=?', [$id]);
            setFlash('success', 'Payment recorded.');
        }
        redirect('index.php?page=loans');
    }
    
    if ($sub === 'close') {
        requireRole('admin');
        $id = (int)post('id');
        DB::update('loans', ['status' => 'closed'], 'id=?', [$id]);
        setFlash('success', 'Loan closed.');
        redirect('index.php?page=loans');
    }
}

// Add Loan Form
if ($subAction === 'add') {
    $employees = DB::rows("SELECT id, name_en, employee_no FROM employees WHERE status='active' ORDER BY name_en");
    ?>
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i>Add Loan</h1>
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
              <label class="form-label">Loan Amount</label>
              <input type="number" step="0.001" class="form-control" name="loan_amount" id="loanAmount" required oninput="calculateLoan()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Interest Rate (%)</label>
              <input type="number" step="0.1" class="form-control" name="interest_rate" id="interestRate" value="0" oninput="calculateLoan()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Number of Installments</label>
              <input type="number" class="form-control" name="number_of_installments" id="numInstallments" required oninput="calculateLoan()">
            </div>
            <div class="col-md-4">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="start_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Total Amount (with interest)</label>
              <input type="number" step="0.001" class="form-control" name="total_amount" id="totalAmount" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Installment Amount</label>
              <input type="number" step="0.001" class="form-control" name="installment_amount" id="installmentAmount" readonly>
            </div>
            <div class="col-12">
              <label class="form-label">Reason</label>
              <textarea class="form-control" name="reason" rows="3"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Loan</button>
              <a href="index.php?page=loans" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <script>
    function calculateLoan() {
      const amount = parseFloat(document.getElementById('loanAmount').value) || 0;
      const rate = parseFloat(document.getElementById('interestRate').value) || 0;
      const installments = parseInt(document.getElementById('numInstallments').value) || 1;
      
      const total = amount + (amount * rate / 100);
      const installment = total / installments;
      
      document.getElementById('totalAmount').value = total.toFixed(3);
      document.getElementById('installmentAmount').value = installment.toFixed(3);
    }
    </script>
    <?php
    return;
}

// Repayment Form
if ($subAction === 'repay') {
    $id = (int)get('id');
    $loan = DB::row("SELECT l.*, e.name_en, e.employee_no FROM loans l LEFT JOIN employees e ON e.id = l.employee_id WHERE l.id=?", [$id]);
    if (!$loan) redirect('index.php?page=loans');
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <div>
        <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i>Repay Loan</h1>
        <p class="text-muted mb-0"><?= h($loan['name_en']) ?> - Loan #<?= $loan['id'] ?></p>
      </div>
      <a href="index.php?page=loans" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern">Loan Details</h5></div>
          <div class="card-body">
            <div class="mb-2"><strong>Loan Amount:</strong> <?= money((float)$loan['loan_amount']) ?></div>
            <div class="mb-2"><strong>Total Amount:</strong> <?= money((float)$loan['total_amount']) ?></div>
            <div class="mb-2"><strong>Installment:</strong> <?= money((float)$loan['installment_amount']) ?></div>
            <div class="mb-2"><strong>Paid:</strong> <?= money((float)$loan['amount_paid']) ?></div>
            <div class="mb-2"><strong>Remaining:</strong> <?= money((float)($loan['total_amount'] - $loan['amount_paid'])) ?></div>
            <div class="mb-2"><strong>Status:</strong> <?= statusBadge($loan['status']) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern">Record Payment</h5></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="repay">
              <input type="hidden" name="id" value="<?= $loan['id'] ?>">
              <div class="mb-3">
                <label class="form-label">Payment Amount</label>
                <input type="number" step="0.001" class="form-control" name="amount" value="<?= min((float)$loan['installment_amount'], (float)($loan['total_amount'] - $loan['amount_paid'])) ?>" required>
              </div>
              <button type="submit" class="btn btn-success w-100"><i class="fas fa-money-bill me-2"></i>Record Payment</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php
    return;
}

// List View
$loans = DB::rows("
    SELECT l.*, e.name_en, e.employee_no
    FROM loans l
    LEFT JOIN employees e ON e.id = l.employee_id
    ORDER BY l.status ASC, l.created_at DESC
");
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i>Loans Management</h1>
  <a href="?page=loans&sub=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Loan</a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Loan Amount</th>
          <th>Total Amount</th>
          <th>Installment</th>
          <th>Paid</th>
          <th>Remaining</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loans as $l): ?>
        <tr>
          <td><?= h($l['name_en']) ?> (<?= h($l['employee_no']) ?>)</td>
          <td><?= money((float)$l['loan_amount']) ?></td>
          <td><?= money((float)$l['total_amount']) ?></td>
          <td><?= money((float)$l['installment_amount']) ?></td>
          <td><?= money((float)$l['amount_paid']) ?></td>
          <td class="fw-bold"><?= money((float)($l['total_amount'] - $l['amount_paid'])) ?></td>
          <td><?= statusBadge($l['status']) ?></td>
          <td>
            <?php if ($l['status'] === 'active'): ?>
            <a href="?page=loans&sub=repay&id=<?= $l['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-money-bill"></i></a>
            <?php endif; ?>
            <?php if ($l['status'] !== 'closed'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="close">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Close this loan?')"><i class="fas fa-times"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($loans)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">No loans found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
