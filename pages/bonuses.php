<?php
$pageTitle = t('bonuses');
$subAction = get('sub', 'list');

// Handle POST
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    
    if ($sub === 'add') {
        requireRole('admin', 'manager', 'hr');
        $bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
        if (!$bonusesTableExists) {
            setFlash('error', t('table_missing') . ' bonuses. ' . t('run_migration'));
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
        setFlash('success', t('bonus_added'));
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
        setFlash('success', t('bonus_approved'));
        redirect('index.php?page=bonuses');
    }

    if ($sub === 'reject') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('bonuses', ['status' => 'rejected'], 'id=?', [$id]);
        setFlash('success', t('bonus_rejected'));
        redirect('index.php?page=bonuses');
    }

    if ($sub === 'mark_paid') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('bonuses', [
            'status' => 'paid',
            'payment_date' => post('payment_date') ?: date('Y-m-d')
        ], 'id=?', [$id]);
        setFlash('success', t('bonus_paid'));
        redirect('index.php?page=bonuses');
    }
}

// Add/Edit Form
if ($subAction === 'add') {
    ?>
    <div class="page-header">
      <h1 class="page-title"><i class="fas fa-gift me-2"></i><?= t('add_bonus') ?></h1>
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
              <label class="form-label"><?= t('bonus_type') ?></label>
              <select class="form-select" name="bonus_type" required>
                <option value="performance"><?= t('performance_bonus') ?></option>
                <option value="sales_commission"><?= t('sales_commission') ?></option>
                <option value="project_bonus"><?= t('project_bonus') ?></option>
                <option value="signing_bonus"><?= t('signing_bonus') ?></option>
                <option value="referral_bonus"><?= t('referral_bonus') ?></option>
                <option value="year_end_bonus"><?= t('year_end_bonus') ?></option>
                <option value="other"><?= t('other') ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('amount') ?></label>
              <input type="number" step="0.001" class="form-control" name="amount" required>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('year') ?></label>
              <select class="form-select" name="period_year" required>
                <?php for($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('month') ?></label>
              <select class="form-select" name="period_month" required>
                <?php for($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= monthName($m, lang()) ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label"><?= t('description') ?></label>
              <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= t('save') ?></button>
              <a href="index.php?page=bonuses" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

// List View
$bonusPage = max(1, (int)get('p', 1));
$bonusesTableExists = DB::row("SHOW TABLES LIKE 'bonuses'") !== null;
$bonuses = ['data'=>[], 'last_page'=>0, 'page'=>1, 'from'=>0, 'to'=>0, 'total'=>0];
if ($bonusesTableExists) {
    $bonuses = DB::paginate(
        "SELECT b.*, e.name_en, e.employee_no, u.name as approved_by_name
         FROM bonuses b
         LEFT JOIN employees e ON e.id = b.employee_id
         LEFT JOIN users u ON u.id = b.approved_by
         ORDER BY b.created_at DESC",
        [], $bonusPage, 50
    );
}
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-gift me-2"></i><?= t('bonuses') ?></h1>
  <a href="?page=bonuses&sub=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i><?= t('add_bonus') ?></a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th><?= t('employee_name') ?></th>
          <th><?= t('bonus_type') ?></th>
          <th><?= t('amount') ?></th>
          <th><?= t('period') ?></th>
          <th><?= t('status') ?></th>
          <th><?= t('payment_date') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bonuses['data'] as $b): ?>
        <tr>
          <td><?= h($b['name_en']) ?> (<?= h($b['employee_no']) ?>)</td>
          <td><?= t($b['bonus_type']) ?></td>
          <td class="fw-bold text-success"><?= money((float)$b['amount']) ?></td>
          <td><?= monthName((int)$b['period_month'], lang()) . ' ' . $b['period_year'] ?></td>
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
              <button class="btn btn-sm btn-primary" title="<?= t('mark_paid') ?>"><i class="fas fa-money-bill"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bonuses['data'])): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted"><?= t('no_bonuses_found') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($bonuses['last_page'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= formatT('showing_x_of_y', ['from' => $bonuses['from'], 'to' => $bonuses['to'], 'total' => $bonuses['total']]) ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($pg = 1; $pg <= $bonuses['last_page']; $pg++): ?>
      <li class="page-item <?= $pg == $bonuses['page'] ? 'active' : '' ?>">
        <a class="page-link" href="?page=bonuses&p=<?= $pg ?>"><?= $pg ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
