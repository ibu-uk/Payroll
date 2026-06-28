<?php
$pageTitle = t('leave_types_management');
$subAction = get('sub', 'list');

// Handle POST
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    
    if ($sub === 'save') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = [
            'name_en' => post('name_en'),
            'name_ar' => post('name_ar'),
            'days_per_year' => (float)post('days_per_year'),
            'is_paid' => post('is_paid') ? 1 : 0,
            'is_active' => post('is_active') ? 1 : 0
        ];
        
        if ($id > 0) {
            DB::update('leave_types', $data, 'id=?', [$id]);
            setFlash('success', t('leave_type_updated'));
        } else {
            DB::insert('leave_types', $data);
            setFlash('success', t('leave_type_created'));
        }
        redirect('index.php?page=leave_types');
    }
    
    if ($sub === 'delete') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('leave_types', ['is_active' => 0], 'id=?', [$id]);
        setFlash('success', t('leave_type_deactivated'));
        redirect('index.php?page=leave_types');
    }
}

// Add/Edit Form
if ($subAction === 'edit') {
    $id = (int)get('id');
    $lt = $id > 0 ? DB::row("SELECT * FROM leave_types WHERE id=?", [$id]) : null;
    ?>
    <div class="page-header d-flex justify-content-between align-items-start">
      <h1 class="page-title"><i class="fas fa-umbrella-beach me-2"></i><?= $id > 0 ? t('edit_leave_type') : t('add_leave_type') ?></h1>
      <a href="index.php?page=leave_types" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="card card-modern">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="save">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('name_en') ?></label>
              <input type="text" class="form-control" name="name_en" value="<?= h($lt['name_en'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('name_ar') ?></label>
              <input type="text" class="form-control" name="name_ar" value="<?= h($lt['name_ar'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('days_per_year_label') ?></label>
              <input type="number" step="0.1" class="form-control" name="days_per_year" value="<?= $lt['days_per_year'] ?? 15 ?>" required>
              <small class="text-muted"><?= t('days_per_year') ?> <?= t('days_per_year_label') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('paid_leave') ?></label>
              <select class="form-select" name="is_paid">
                <option value="1" <?= ($lt['is_paid'] ?? 1) == 1 ? 'selected' : '' ?>><?= t('yes') ?> (<?= t('paid') ?>)</option>
                <option value="0" <?= ($lt['is_paid'] ?? 1) == 0 ? 'selected' : '' ?>><?= t('no') ?> (<?= t('unpaid') ?>)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('status') ?></label>
              <select class="form-select" name="is_active">
                <option value="1" <?= ($lt['is_active'] ?? 1) == 1 ? 'selected' : '' ?>><?= t('active') ?></option>
                <option value="0" <?= ($lt['is_active'] ?? 1) == 0 ? 'selected' : '' ?>><?= t('inactive') ?></option>
              </select>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= t('save') ?></button>
              <a href="index.php?page=leave_types" class="btn btn-outline-secondary"><?= t('cancel') ?></a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

// List View
$leaveTypes = DB::rows("SELECT * FROM leave_types ORDER BY id");
?>
<div class="page-header d-flex justify-content-between align-items-start">
  <h1 class="page-title"><i class="fas fa-umbrella-beach me-2"></i><?= t('leave_types_management') ?></h1>
  <a href="?page=leave_types&sub=edit" class="btn btn-primary"><i class="fas fa-plus me-2"></i><?= t('add_leave_type') ?></a>
</div>

<div class="card card-modern">
  <div class="card-body">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th><?= t('name_en') ?></th>
          <th><?= t('name_ar') ?></th>
          <th><?= t('days_per_year_label') ?></th>
          <th><?= t('paid_leave') ?></th>
          <th><?= t('status') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leaveTypes as $lt): ?>
        <tr>
          <td><?= $lt['id'] ?></td>
          <td><?= h($lt['name_en']) ?></td>
          <td><?= h($lt['name_ar']) ?></td>
          <td class="fw-bold"><?= $lt['days_per_year'] ?></td>
          <td><?= $lt['is_paid'] ? '<span class="badge bg-success">' . t('paid') . '</span>' : '<span class="badge bg-warning">' . t('unpaid') . '</span>' ?></td>
          <td><?= $lt['is_active'] ? '<span class="badge bg-primary">' . t('active') . '</span>' : '<span class="badge bg-secondary">' . t('inactive') . '</span>' ?></td>
          <td>
            <a href="?page=leave_types&sub=edit&id=<?= $lt['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
            <?php if ($lt['is_active']): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= csrf() ?>">
              <input type="hidden" name="sub_action" value="delete">
              <input type="hidden" name="id" value="<?= $lt['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('deactivate_leave_type') ?>')"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
