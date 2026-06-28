<?php
$pageTitle = t('loans_management');
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
            'total_amount' => (float)post('loan_amount'),
            'installment_amount' => (float)post('installment_amount'),
            'number_of_installments' => (int)post('number_of_installments'),
            'start_date' => post('start_date'),
            'reason' => post('reason'),
            'status' => 'active'
        ]);
        setFlash('success', t('loan_added'));
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
            setFlash('success', t('payment_recorded'));
        }
        redirect('index.php?page=loans');
    }
    
    if ($sub === 'close') {
        requireRole('admin');
        $id = (int)post('id');
        DB::update('loans', ['status' => 'closed'], 'id=?', [$id]);
        setFlash('success', t('loan_closed'));
        redirect('index.php?page=loans');
    }
}

// Add Loan Form
if ($subAction === 'add') {
    ?>
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i><?= t('add_loan') ?></h1>
    </div>
    <div class="card card-modern">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="add">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('employee_name') ?></label>
              <select class="form-select select2-ajax" name="employee_id" required data-placeholder="<?= t('search_employee') ?>">
                <option value=""></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('loan_amount') ?></label>
              <input type="number" step="0.001" class="form-control" name="loan_amount" id="loanAmount" required oninput="calculateLoan()">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('number_of_installments') ?></label>
              <input type="number" class="form-control" name="number_of_installments" id="numInstallments" required oninput="calculateLoan()">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('start_date') ?></label>
              <input type="date" class="form-control" name="start_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('installment_amount') ?></label>
              <input type="number" step="0.001" class="form-control" name="installment_amount" id="installmentAmount" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('total_amount') ?></label>
              <input type="number" step="0.001" class="form-control" id="totalAmount" readonly value="0.000">
              <small class="text-muted"><?= t('no_interest_kuwait') ?></small>
            </div>
            <div class="col-12">
              <label class="form-label"><?= t('reason') ?></label>
              <textarea class="form-control" name="reason" rows="3"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= t('save') ?></button>
              <a href="index.php?page=loans" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <script>
    function calculateLoan() {
      const amount = parseFloat(document.getElementById('loanAmount').value) || 0;
      const installments = parseInt(document.getElementById('numInstallments').value) || 1;

      const installment = amount / installments;

      document.getElementById('totalAmount').value = amount.toFixed(3);
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
        <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i><?= t('repay_loan') ?></h1>
        <p class="text-muted mb-0"><?= h($loan['name_en']) ?> - <?= t('loan_number') ?><?= $loan['id'] ?></p>
      </div>
      <a href="index.php?page=loans" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern"><?= t('loan_details') ?></h5></div>
          <div class="card-body">
            <div class="mb-2"><strong><?= t('loan_amount') ?>:</strong> <?= money((float)$loan['loan_amount']) ?></div>
            <div class="mb-2"><strong><?= t('total_amount') ?>:</strong> <?= money((float)$loan['total_amount']) ?> <small class="text-muted">(<?= t('no_interest') ?>)</small></div>
            <div class="mb-2"><strong><?= t('installment_amount') ?>:</strong> <?= money((float)$loan['installment_amount']) ?></div>
            <div class="mb-2"><strong><?= t('paid') ?>:</strong> <?= money((float)$loan['amount_paid']) ?></div>
            <div class="mb-2"><strong><?= t('remaining') ?>:</strong> <?= money((float)($loan['total_amount'] - $loan['amount_paid'])) ?></div>
            <div class="mb-2"><strong><?= t('status') ?>:</strong> <?= statusBadge($loan['status']) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-modern">
          <div class="card-header-modern"><h5 class="card-title-modern"><?= t('record_payment') ?></h5></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="repay">
              <input type="hidden" name="id" value="<?= $loan['id'] ?>">
              <div class="mb-3">
                <label class="form-label"><?= t('payment_amount') ?></label>
                <input type="number" step="0.001" class="form-control" name="amount" value="<?= min((float)$loan['installment_amount'], (float)($loan['total_amount'] - $loan['amount_paid'])) ?>" required>
              </div>
              <button type="submit" class="btn btn-success w-100"><i class="fas fa-money-bill me-2"></i><?= t('record_payment') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php
    return;
}

// List View
$loanPage = max(1, (int)get('p', 1));
$loans = DB::paginate(
    "SELECT l.*, e.name_en, e.employee_no
     FROM loans l
     LEFT JOIN employees e ON e.id = l.employee_id
     ORDER BY l.status ASC, l.created_at DESC",
    [], $loanPage, 50
);
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-hand-holding-usd me-2"></i><?= t('loans_management') ?></h1>
  <a href="?page=loans&sub=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i><?= t('add_loan') ?></a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th><?= t('employee_name') ?></th>
          <th><?= t('loan_amount') ?></th>
          <th><?= t('total_amount') ?></th>
          <th><?= t('installment_amount') ?></th>
          <th><?= t('paid') ?></th>
          <th><?= t('remaining') ?></th>
          <th><?= t('status') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loans['data'] as $l): ?>
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
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('close_loan') ?>')"><i class="fas fa-times"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($loans['data'])): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted"><?= t('no_loans_found') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($loans['last_page'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= formatT('showing_x_of_y', ['from' => $loans['from'], 'to' => $loans['to'], 'total' => $loans['total']]) ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($pg = 1; $pg <= $loans['last_page']; $pg++): ?>
      <li class="page-item <?= $pg == $loans['page'] ? 'active' : '' ?>">
        <a class="page-link" href="?page=loans&p=<?= $pg ?>"><?= $pg ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
