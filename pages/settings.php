<?php
$pageTitle = t('settings');
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action','company');
    if ($sub === 'company') {
        requireRole('admin');
        $data = [
            'company_name_en'    => post('company_name_en'),
            'company_name_ar'    => post('company_name_ar'),
            'company_address_en' => post('company_address_en'),
            'company_address_ar' => post('company_address_ar'),
            'company_phone'      => post('company_phone'),
            'company_email'      => post('company_email'),
            'currency'           => post('currency'),
            'currency_ar'        => post('currency_ar'),
            'country'            => post('country'),
            'tax_rate'           => (float)post('tax_rate'),
            'social_insurance_rate' => (float)post('social_insurance_rate'),
            'overtime_rate'      => (float)post('overtime_rate'),
            'work_days_per_month'=> (int)post('work_days_per_month'),
            'work_hours_per_day' => (int)post('work_hours_per_day'),
            'payroll_day'        => (int)post('payroll_day'),
            'holiday_overtime_rate' => (float)post('holiday_overtime_rate'),
        ];
        if (!empty($_FILES['logo']['name'])) {
            $uploaded = uploadFile($_FILES['logo'], 'logos', 'logo_');
            if ($uploaded) $data['logo'] = $uploaded;
        }
        DB::update('settings', $data, 'id=1');
        cacheClear('settings_currency'); // Clear cache when settings change
        setFlash('success', t('saved_success'));
    } elseif ($sub === 'allow_type') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = ['name_en'=>post('name_en'),'name_ar'=>post('name_ar'),'calc_type'=>post('calc_type'),'is_active'=>1];
        $id > 0 ? DB::update('allowance_types',$data,'id=?',[$id]) : DB::insert('allowance_types',$data);
        setFlash('success', t('saved_success'));
    } elseif ($sub === 'ded_type') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = ['name_en'=>post('name_en'),'name_ar'=>post('name_ar'),'calc_type'=>post('calc_type'),'is_active'=>1];
        $id > 0 ? DB::update('deduction_types',$data,'id=?',[$id]) : DB::insert('deduction_types',$data);
        setFlash('success', t('saved_success'));
    } elseif ($sub === 'emp_deduction') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = [
            'employee_id' => (int)post('employee_id'),
            'deduction_type_id' => (int)post('deduction_type_id'),
            'amount' => (float)post('amount'),
            'effective_date' => post('effective_date') ?: date('Y-m-d'),
            'end_date' => post('end_date') ?: null,
            'is_active' => 1
        ];
        if ($id > 0) {
            DB::update('employee_deductions', $data, 'id=?', [$id]);
            setFlash('success', 'Employee deduction updated successfully');
        } else {
            DB::insert('employee_deductions', $data);
            setFlash('success', 'Employee deduction added successfully');
        }
        redirect('index.php?page=settings&tab=employee_deductions');
    } elseif ($sub === 'delete_emp_deduction') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('employee_deductions', ['is_active' => 0], 'id=?', [$id]);
        setFlash('success', 'Employee deduction removed');
        redirect('index.php?page=settings&tab=employee_deductions');
    } elseif ($sub === 'add_user') {
        requireRole('admin');
        $email = trim(post('email'));
        if (DB::row("SELECT id FROM users WHERE email=?", [$email])) {
            setFlash('error', t('email_already_exists'));
        } elseif (strlen(post('password')) < 6) {
            setFlash('error', t('password_min_length'));
        } else {
            DB::insert('users', ['name'=>post('name'),'email'=>$email,'password'=>password_hash(post('password'),PASSWORD_DEFAULT),'role'=>post('role'),'lang'=>post('lang','en')]);
            setFlash('success', t('saved_success'));
        }
    } elseif ($sub === 'edit_user') {
        requireRole('admin');
        $id = (int)post('id');
        $email = trim(post('email'));
        if (DB::row("SELECT id FROM users WHERE email=? AND id!=?", [$email, $id])) {
            setFlash('error', t('email_already_exists'));
        } else {
            $data = [
                'name' => post('name'),
                'email' => $email,
                'role' => post('role'),
                'lang' => post('lang','en')
            ];
            if (!empty(post('password'))) {
                if (strlen(post('password')) < 6) {
                    setFlash('error', t('password_min_length'));
                } else {
                    $data['password'] = password_hash(post('password'), PASSWORD_DEFAULT);
                }
            }
            if (empty($_SESSION['flash']) || ($_SESSION['flash']['type'] ?? '') !== 'error') {
                DB::update('users', $data, 'id=?', [$id]);
                setFlash('success', t('saved_success'));
            }
        }
    } elseif ($sub === 'toggle_user_status') {
        requireRole('admin');
        $id = (int)post('id');
        if ($id === (int)(currentUser()['id'] ?? 0)) {
            setFlash('error', t('cannot_deactivate_self'));
        } else {
            $user = DB::row("SELECT is_active FROM users WHERE id=?", [$id]);
            $newStatus = $user['is_active'] ? 0 : 1;
            DB::update('users', ['is_active' => $newStatus], 'id=?', [$id]);
            setFlash('success', $newStatus ? t('user_activated') : t('user_deactivated'));
        }
    } elseif ($sub === 'reset_password') {
        requireRole('admin');
        $id = (int)post('id');
        $newPassword = post('new_password');
        if (strlen($newPassword) < 6) {
            setFlash('error', t('password_min_length'));
        } elseif ($id === (int)(currentUser()['id'] ?? 0)) {
            setFlash('error', t('cannot_reset_own_password'));
        } else {
            DB::update('users', ['password' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id=?', [$id]);
            setFlash('success', t('password_reset_success'));
        }
    } elseif ($sub === 'add_holiday') {
        requireRole('admin', 'manager', 'hr');
        $isRecurring = post('is_recurring') ? 1 : 0;
        $data = [
            'name_en' => post('holiday_name_en'),
            'name_ar' => post('holiday_name_ar'),
            'holiday_date' => post('holiday_date'),
            'holiday_type' => post('holiday_type'),
            'is_recurring' => $isRecurring,
            'recurring_month' => $isRecurring ? (int)post('recurring_month') : null,
            'recurring_day' => $isRecurring ? (int)post('recurring_day') : null,
            'notes' => post('holiday_notes'),
            'is_active' => 1
        ];
        DB::insert('holidays', $data);
        setFlash('success', 'Holiday added successfully');
        redirect('index.php?page=settings&tab=holidays');
    } elseif ($sub === 'change_password') {
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $confirmPassword = post('confirm_password');
        
        $user = currentUser();
        $dbUser = DB::row("SELECT * FROM users WHERE id=?", [$user['id']]);
        
        if (!password_verify($currentPassword, $dbUser['password'])) {
            setFlash('error', t('password_incorrect'));
        } elseif (strlen($newPassword) < 6) {
            setFlash('error', t('password_min_length'));
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('error', t('password_mismatch'));
        } else {
            DB::update('users', ['password' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id=?', [$user['id']]);
            setFlash('success', t('password_changed'));
        }
        redirect('index.php?page=settings&tab=account');
    }
    redirect('index.php?page=settings');
}

// Handle holiday deletion
if (isPost() && post('sub_action') === 'delete_holiday') {
    requireRole('admin', 'manager', 'hr');
    checkCsrf();
    DB::q("DELETE FROM holidays WHERE id=?", [(int)post('holiday_id')]);
    setFlash('success', t('deleted_success'));
    redirect('index.php?page=settings&tab=holidays');
}
$s = DB::row("SELECT * FROM settings WHERE id=1") ?? [];
$allowTypes = DB::rows("SELECT * FROM allowance_types ORDER BY sort_order");
$dedTypes   = DB::rows("SELECT * FROM deduction_types ORDER BY sort_order");
$users      = DB::rows("SELECT id,name,email,role,is_active,last_login,lang FROM users ORDER BY id");
// Check if holidays table exists before querying
$holidaysTableExists = DB::row("SHOW TABLES LIKE 'holidays'") !== null;
$holidays = $holidaysTableExists ? DB::rows("SELECT * FROM holidays ORDER BY holiday_date DESC") : [];
// Employee deductions for dedicated tab
$employeeDeductions = DB::rows("
    SELECT ed.*, e.name_en, e.employee_no, dt.name_en as dt_name, dt.calc_type
    FROM employee_deductions ed
    JOIN employees e ON e.id = ed.employee_id
    JOIN deduction_types dt ON dt.id = ed.deduction_type_id
    WHERE ed.is_active = 1
    ORDER BY ed.effective_date DESC, e.name_en
");
$dedTypesActive = DB::rows("SELECT * FROM deduction_types WHERE is_active=1 AND is_system=0 ORDER BY name_en");
$tab = get('tab','company');
?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-gear me-2"></i><?= t('settings') ?></h1></div>
<ul class="nav nav-tabs mb-4">
  <?php foreach (['company'=>t('company'),'holidays'=>t('holidays'),'allowances'=>t('allowance_types'),'deductions'=>t('deduction_types'),'employee_deductions'=>t('employee_deductions'),'users'=>t('users'),'account'=>t('account')] as $k=>$v): ?>
  <li class="nav-item"><a class="nav-link <?= $tab===$k?'active':'' ?>" href="?page=settings&tab=<?= $k ?>"><?= $v ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'company'): ?>
<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="sub_action" value="company">
  <div class="row g-3">
    <div class="col-md-8">
      <div class="card card-modern mb-3">
        <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-building me-2 text-primary"></i><?= t('company_settings') ?></h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label"><?= t('company_name') ?> (EN)</label><input type="text" class="form-control" name="company_name_en" value="<?= h($s['company_name_en']??'') ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= t('company_name') ?> (AR)</label><input type="text" class="form-control" name="company_name_ar" dir="rtl" value="<?= h($s['company_name_ar']??'') ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= t('company_address') ?> (EN)</label><textarea class="form-control" name="company_address_en" rows="2"><?= h($s['company_address_en']??'') ?></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= t('company_address') ?> (AR)</label><textarea class="form-control" name="company_address_ar" dir="rtl" rows="2"><?= h($s['company_address_ar']??'') ?></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= t('phone') ?></label><input type="text" class="form-control" name="company_phone" value="<?= h($s['company_phone']??'') ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= t('email') ?></label><input type="email" class="form-control" name="company_email" value="<?= h($s['company_email']??'') ?>"></div>
            <div class="col-md-4"><label class="form-label"><?= t('currency_en') ?></label><input type="text" class="form-control" name="currency" value="<?= h($s['currency']??'KWD') ?>"></div>
            <div class="col-md-4"><label class="form-label"><?= t('currency_ar') ?></label><input type="text" class="form-control" name="currency_ar" dir="rtl" value="<?= h($s['currency_ar']??'د.ك') ?>"></div>
            <div class="col-md-4"><label class="form-label"><?= t('country') ?></label><input type="text" class="form-control" name="country" value="<?= h($s['country']??'Kuwait') ?>"></div>
          </div>
        </div>
      </div>
      <div class="card card-modern">
        <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-percent me-2 text-warning"></i><?= t('payroll_configuration') ?></h5></div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle me-2"></i>
            <?= t('work_hours_note') ?>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label"><?= t('tax_rate') ?></label>
              <div class="input-group">
                <input type="number" class="form-control" name="tax_rate" step="0.01" min="0" max="100" value="<?= $s['tax_rate']??0 ?>">
                <span class="input-group-text">%</span>
              </div>
              <small class="text-muted"><?= t('tax_applied_gross') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('social_ins_rate') ?></label>
              <div class="input-group">
                <input type="number" class="form-control" name="social_insurance_rate" step="0.01" min="0" max="100" value="<?= $s['social_insurance_rate']??11 ?>">
                <span class="input-group-text">%</span>
              </div>
              <small class="text-muted"><?= t('social_ins_basic') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('overtime_rate') ?></label>
              <input type="number" class="form-control" name="overtime_rate" step="0.01" min="1" value="<?= $s['overtime_rate']??1.25 ?>">
              <small class="text-muted"><?= t('overtime_example') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('holiday_overtime_rate') ?></label>
              <input type="number" class="form-control" name="holiday_overtime_rate" step="0.01" min="1" value="<?= $s['holiday_overtime_rate']??2.0 ?>">
              <small class="text-muted"><?= t('holiday_ot_example') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('work_days_per_month') ?></label>
              <input type="number" class="form-control" name="work_days_per_month" min="20" max="31" value="<?= $s['work_days_per_month']??22 ?>">
              <small class="text-muted"><?= t('work_days_note') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('work_hours_per_day') ?></label>
              <input type="number" class="form-control" name="work_hours_per_day" min="4" max="12" value="<?= $s['work_hours_per_day']??8 ?>">
              <small class="text-muted"><?= t('work_hours_fallback') ?></small>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= t('payroll_day') ?></label>
              <input type="number" class="form-control" name="payroll_day" min="1" max="31" value="<?= $s['payroll_day']??25 ?>">
              <small class="text-muted"><?= t('payroll_day_note') ?></small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-modern">
        <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-image me-2"></i><?= t('company_logo') ?></h5></div>
        <div class="card-body text-center">
          <?php $logo = $s['logo'] ?? ''; ?>
          <?php if ($logo && file_exists(UPLOAD_DIR . 'logos/' . $logo)): ?>
          <img src="uploads/logos/<?= h($logo) ?>" class="img-fluid mb-3 rounded" style="max-height:80px">
          <?php else: ?><div class="text-muted mb-3 py-3"><i class="fas fa-image fa-3x opacity-25"></i></div><?php endif; ?>
          <input type="file" class="form-control" name="logo" accept="image/*">
        </div>
      </div>
    </div>
  </div>
  <div class="mt-3"><button class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i><?= t('save_settings') ?></button></div>
</form>

<?php elseif ($tab === 'holidays'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-1"><?= t('manage_holidays') ?></h5>
    <small class="text-muted"><?= t('holidays_desc') ?></small>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#holidayModal"><i class="fas fa-plus me-1"></i><?= t('add_holiday') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-dark">
          <tr>
            <th><?= t('date') ?></th>
            <th><?= t('holiday_name_en') ?></th>
            <th><?= t('holiday_name_ar') ?></th>
            <th><?= t('holiday_type') ?></th>
            <th><?= t('recurring') ?></th>
            <th><?= t('status') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($holidays as $h): ?>
          <tr>
            <td><strong><?= fdate($h['holiday_date']) ?></strong></td>
            <td><?= h($h['name_en']) ?></td>
            <td dir="rtl"><?= h($h['name_ar'] ?? '') ?></td>
            <td><span class="badge bg-info"><?= ucfirst($h['holiday_type']) ?></span></td>
            <td>
              <?php if ($h['is_recurring']): ?>
                <span class="badge bg-success"><?= t('every') ?> <?= monthName((int)$h['recurring_month'], lang()) ?> <?= $h['recurring_day'] ?></span>
              <?php else: ?>
                <span class="text-muted"><?= t('no') ?></span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($h['is_active'] ? 'active' : 'inactive') ?></td>
            <td>
              <form method="POST" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                <input type="hidden" name="sub_action" value="delete_holiday">
                <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('delete_holiday_confirm') ?>')"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($holidays)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3"><?= t('no_holidays_found') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="modal fade" id="holidayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= t('add_holiday') ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="sub_action" value="add_holiday">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('holiday_name_en') ?> *</label>
              <input type="text" class="form-control" name="holiday_name_en" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('holiday_name_ar') ?></label>
              <input type="text" class="form-control" name="holiday_name_ar" dir="rtl">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('holiday_date') ?> *</label>
              <input type="date" class="form-control" name="holiday_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('holiday_type') ?> *</label>
              <select class="form-select" name="holiday_type" required>
                <option value="government"><?= t('government_holiday') ?></option>
                <option value="religious"><?= t('religious_holiday') ?></option>
                <option value="company"><?= t('company_holiday') ?></option>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_recurring" id="isRecurring" value="1" onchange="toggleRecurring()">
                <label class="form-check-label" for="isRecurring"><?= t('recurring_holiday') ?></label>
              </div>
            </div>
            <div class="col-md-6" id="recurringFields" style="display:none;">
              <label class="form-label"><?= t('month') ?></label>
              <select class="form-select" name="recurring_month">
                <?php for($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>"><?= monthName($m, lang()) ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-6" id="recurringDay" style="display:none;">
              <label class="form-label"><?= t('day') ?></label>
              <input type="number" class="form-control" name="recurring_day" min="1" max="31" value="1">
            </div>
            <div class="col-12">
              <label class="form-label"><?= t('holiday_notes') ?></label>
              <textarea class="form-control" name="holiday_notes" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= t('save_holiday') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function toggleRecurring() {
  const isRecurring = document.getElementById('isRecurring').checked;
  document.getElementById('recurringFields').style.display = isRecurring ? 'block' : 'none';
  document.getElementById('recurringDay').style.display = isRecurring ? 'block' : 'none';
}
</script>

<?php elseif ($tab === 'allowances'): ?>
<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#allowModal"><i class="fas fa-plus me-1"></i><?= t('add') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0"><table class="table table-hover mb-0">
    <thead class="table-dark"><tr><th>Name (EN)</th><th>Name (AR)</th><th>Type</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($allowTypes as $a): ?>
    <tr><td><?= h($a['name_en']) ?></td><td dir="rtl"><?= h($a['name_ar']??'') ?></td>
      <td><span class="badge bg-info"><?= $a['calc_type'] ?></span></td>
      <td><?= statusBadge($a['is_active']?'active':'inactive') ?></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="editAllow(<?= htmlspecialchars(json_encode($a)) ?>)"><i class="fas fa-edit"></i></button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="modal fade" id="allowModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('allowance_type') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="allow_type"><input type="hidden" name="id" id="allowId" value="0">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('name_en') ?></label><input type="text" class="form-control" name="name_en" id="allowNameEn" required></div>
      <div class="mb-3"><label class="form-label"><?= t('name_ar') ?></label><input type="text" class="form-control" name="name_ar" id="allowNameAr" dir="rtl"></div>
      <div class="mb-3"><label class="form-label"><?= t('calculation_type') ?></label>
        <select class="form-select" name="calc_type" id="allowCalc">
          <option value="fixed"><?= t('fixed_amount') ?></option>
          <option value="percentage_basic"><?= t('percentage_of_basic') ?></option>
          <option value="percentage_gross"><?= t('percentage_of_gross') ?></option>
        </select>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('save') ?></button></div>
  </form>
</div></div></div>
<script>function editAllow(a){document.getElementById('allowId').value=a.id;document.getElementById('allowNameEn').value=a.name_en;document.getElementById('allowNameAr').value=a.name_ar||'';document.getElementById('allowCalc').value=a.calc_type;new bootstrap.Modal(document.getElementById('allowModal')).show();}</script>

<?php elseif ($tab === 'deductions'): ?>
<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dedModal"><i class="fas fa-plus me-1"></i><?= t('add') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0"><table class="table table-hover mb-0">
    <thead class="table-dark"><tr><th><?= t('name_en') ?></th><th><?= t('name_ar') ?></th><th><?= t('calculation_type') ?></th><th><?= t('status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dedTypes as $d): ?>
    <tr><td><?= h($d['name_en']) ?></td><td dir="rtl"><?= h($d['name_ar']??'') ?></td>
      <td><span class="badge bg-info"><?= $d['calc_type'] ?></span></td>
      <td><?= statusBadge($d['is_active']?'active':'inactive') ?></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="editDed(<?= htmlspecialchars(json_encode($d)) ?>)"><i class="fas fa-edit"></i></button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="modal fade" id="dedModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('deduction_type') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="ded_type"><input type="hidden" name="id" id="dedId" value="0">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('name_en') ?></label><input type="text" class="form-control" name="name_en" id="dedNameEn" required></div>
      <div class="mb-3"><label class="form-label"><?= t('name_ar') ?></label><input type="text" class="form-control" name="name_ar" id="dedNameAr" dir="rtl"></div>
      <div class="mb-3"><label class="form-label"><?= t('calculation_type') ?></label>
        <select class="form-select" name="calc_type" id="dedCalc">
          <option value="fixed"><?= t('fixed_amount') ?></option>
          <option value="percentage_basic"><?= t('percentage_of_basic') ?></option>
          <option value="percentage_gross"><?= t('percentage_of_gross') ?></option>
        </select>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('save') ?></button></div>
  </form>
</div></div></div>
<script>function editDed(d){document.getElementById('dedId').value=d.id;document.getElementById('dedNameEn').value=d.name_en;document.getElementById('dedNameAr').value=d.name_ar||'';document.getElementById('dedCalc').value=d.calc_type;new bootstrap.Modal(document.getElementById('dedModal')).show();}</script>

<?php elseif ($tab === 'employee_deductions'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><?= t('employee_deductions') ?></h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#empDedModal"><i class="fas fa-plus me-1"></i><?= t('add') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0"><table class="table table-hover mb-0">
    <thead class="table-dark"><tr><th><?= t('employee_name') ?></th><th><?= t('deduction_type') ?></th><th><?= t('amount') ?></th><th><?= t('calculation_type') ?></th><th><?= t('effective_date') ?></th><th><?= t('end_date') ?></th><th><?= t('actions') ?></th></tr></thead>
    <tbody>
    <?php foreach ($employeeDeductions as $ed): ?>
    <tr>
      <td><?= h($ed['name_en']) ?> <span class="badge bg-secondary"><?= h($ed['employee_no']) ?></span></td>
      <td><?= h($ed['dt_name']) ?></td>
      <td><?= money((float)$ed['amount']) ?></td>
      <td><span class="badge bg-info"><?= $ed['calc_type'] ?></span></td>
      <td><?= fdate($ed['effective_date']) ?></td>
      <td><?= $ed['end_date'] ? fdate($ed['end_date']) : '-' ?></td>
      <td>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" onclick="editEmpDed(<?= htmlspecialchars(json_encode($ed)) ?>)"><i class="fas fa-edit"></i></button>
          <form method="POST" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="sub_action" value="delete_emp_deduction">
            <input type="hidden" name="id" value="<?= $ed['id'] ?>">
            <button class="btn btn-outline-danger" onclick="return confirm('<?= t('remove_deduction') ?>')"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($employeeDeductions)): ?>
    <tr><td colspan="7" class="text-center text-muted py-3"><?= t('no_employee_deductions_configured') ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>
<div class="modal fade" id="empDedModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('employee_deduction') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="emp_deduction"><input type="hidden" name="id" id="empDedId" value="0">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('employee_name') ?></label>
        <select class="form-select select2-ajax" name="employee_id" id="empDedEmployee" required data-placeholder="<?= t('search_employee') ?>">
          <option value=""></option>
        </select>
      </div>
      <div class="mb-3"><label class="form-label"><?= t('deduction_type') ?></label>
        <select class="form-select" name="deduction_type_id" id="empDedType" required>
          <option value=""><?= t('select_type') ?></option>
          <?php foreach ($dedTypesActive as $dt): ?>
          <option value="<?= $dt['id'] ?>"><?= h($dt['name_en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3"><label class="form-label"><?= t('amount_percentage') ?></label><input type="number" step="0.001" class="form-control" name="amount" id="empDedAmount" required></div>
      <div class="mb-3"><label class="form-label"><?= t('effective_date') ?></label><input type="date" class="form-control" name="effective_date" id="empDedEffective" required></div>
      <div class="mb-3"><label class="form-label"><?= t('end_date_optional') ?></label><input type="date" class="form-control" name="end_date" id="empDedEnd"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('save') ?></button></div>
  </form>
</div></div></div>
<script>function editEmpDed(d){document.getElementById('empDedId').value=d.id;var sel=document.getElementById('empDedEmployee');sel.innerHTML='<option value="'+d.employee_id+'" selected>'+d.name_en+' ('+d.employee_no+')</option>';try{$(sel).trigger('change');}catch(e){}document.getElementById('empDedType').value=d.deduction_type_id;document.getElementById('empDedAmount').value=d.amount;document.getElementById('empDedEffective').value=d.effective_date;document.getElementById('empDedEnd').value=d.end_date||'';new bootstrap.Modal(document.getElementById('empDedModal')).show();}</script>

<?php elseif ($tab === 'account'): ?>
<div class="card card-modern">
  <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-user-shield me-2 text-primary"></i><?= t('change_password') ?></h5></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="sub_action" value="change_password">
      <div class="row g-3">
        <div class="col-md-12">
          <label class="form-label"><?= t('current_password') ?></label>
          <input type="password" class="form-control" name="current_password" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('new_password') ?></label>
          <input type="password" class="form-control" name="new_password" required minlength="6">
          <small class="text-muted"><?= t('password_min_length') ?></small>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= t('confirm_password') ?></label>
          <input type="password" class="form-control" name="confirm_password" required minlength="6">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary"><i class="fas fa-key me-2"></i><?= t('change_password') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php elseif ($tab === 'users'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><?= t('user_management') ?></h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal"><i class="fas fa-plus me-1"></i><?= t('add_user') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0"><table class="table table-hover mb-0">
    <thead class="table-dark"><tr><th><?= t('user_name') ?></th><th><?= t('email') ?></th><th><?= t('role') ?></th><th><?= t('language') ?></th><th><?= t('last_login') ?></th><th><?= t('status') ?></th><th><?= t('actions') ?></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= h($u['name']) ?></td>
      <td><?= h($u['email']) ?></td>
      <td><span class="badge bg-primary"><?= $u['role'] ?></span></td>
      <td><?= strtoupper($u['lang'] ?? 'en') ?></td>
      <td><?= $u['last_login'] ? fdate($u['last_login'],'d/m/Y H:i') : '-' ?></td>
      <td><?= statusBadge($u['is_active']?'active':'inactive') ?></td>
      <td>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)"><i class="fas fa-edit"></i></button>
          <form method="POST" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= csrf() ?>">
            <input type="hidden" name="sub_action" value="toggle_user_status">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>" title="<?= $u['is_active'] ? t('deactivate') : t('activate') ?>">
              <i class="fas fa-<?= $u['is_active'] ? 'user-slash' : 'user-check' ?>"></i>
            </button>
          </form>
          <button class="btn btn-outline-secondary" onclick="resetPassword(<?= $u['id'] ?>)" title="<?= t('reset_password') ?>"><i class="fas fa-key"></i></button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('add_user') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="add_user">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('full_name') ?></label><input type="text" class="form-control" name="name" required></div>
      <div class="mb-3"><label class="form-label"><?= t('email') ?></label><input type="email" class="form-control" name="email" required></div>
      <div class="mb-3"><label class="form-label"><?= t('password') ?></label><input type="password" class="form-control" name="password" required></div>
      <div class="mb-3"><label class="form-label"><?= t('role') ?></label>
        <select class="form-select" name="role">
          <option value="hr"><?= t('hr') ?></option><option value="admin"><?= t('admin') ?></option><option value="manager"><?= t('manager') ?></option><option value="viewer"><?= t('viewer') ?></option>
        </select>
      </div>
      <div class="mb-3"><label class="form-label"><?= t('language') ?></label>
        <select class="form-select" name="lang">
          <option value="en"><?= t('english') ?></option><option value="ar"><?= t('arabic') ?></option>
        </select>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('save') ?></button></div>
  </form>
</div></div></div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('edit_user') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="edit_user">
    <input type="hidden" name="id" id="editUserId">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('full_name') ?></label><input type="text" class="form-control" name="name" id="editUserName" required></div>
      <div class="mb-3"><label class="form-label"><?= t('email') ?></label><input type="email" class="form-control" name="email" id="editUserEmail" required></div>
      <div class="mb-3"><label class="form-label"><?= t('new_password_keep_current') ?></label><input type="password" class="form-control" name="password"></div>
      <div class="mb-3"><label class="form-label"><?= t('role') ?></label>
        <select class="form-select" name="role" id="editUserRole">
          <option value="hr"><?= t('hr') ?></option><option value="admin"><?= t('admin') ?></option><option value="manager"><?= t('manager') ?></option><option value="viewer"><?= t('viewer') ?></option>
        </select>
      </div>
      <div class="mb-3"><label class="form-label"><?= t('language') ?></label>
        <select class="form-select" name="lang" id="editUserLang">
          <option value="en"><?= t('english') ?></option><option value="ar"><?= t('arabic') ?></option>
        </select>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('save') ?></button></div>
  </form>
</div></div></div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><?= t('reset_password') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="reset_password">
    <input type="hidden" name="id" id="resetUserId">
    <div class="modal-body">
      <div class="mb-3"><label class="form-label"><?= t('new_password') ?></label><input type="password" class="form-control" name="new_password" required minlength="6"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary w-100"><?= t('reset_password') ?></button></div>
  </form>
</div></div></div>

<script>
function editUser(u) {
  document.getElementById('editUserId').value = u.id;
  document.getElementById('editUserName').value = u.name;
  document.getElementById('editUserEmail').value = u.email;
  document.getElementById('editUserRole').value = u.role;
  document.getElementById('editUserLang').value = u.lang || 'en';
  new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
function resetPassword(id) {
  document.getElementById('resetUserId').value = id;
  new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>
<?php endif; ?>
