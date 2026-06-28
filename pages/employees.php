<?php
$pageTitle = t('employees');
$subAction = get('sub', 'list');

// Check if civil_id column exists for backward compatibility
$useCivilId = DB::row("SHOW COLUMNS FROM employees LIKE 'civil_id'") !== null;
$idColumn = $useCivilId ? 'civil_id' : 'national_id';

// Function to log employee history
function logEmployeeChange($empId, $changeType, $fieldName = null, $oldValue = null, $newValue = null, $notes = null) {
    try {
        $historyTableExists = DB::row("SHOW TABLES LIKE 'employee_history'") !== null;
        if (!$historyTableExists) return;
        
        DB::insert('employee_history', [
            'employee_id' => $empId,
            'changed_by' => currentUser()['id'],
            'change_type' => $changeType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes
        ]);
    } catch (Exception $e) {
        // Silently fail if history logging fails
        error_log("Employee history logging failed: " . $e->getMessage());
    }
}

// ── Handle POST actions ────────────────────────────────────────────────────────
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');

    if ($sub === 'save') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        
        // Get old employee data for history tracking
        $oldEmp = null;
        if ($id > 0) {
            $oldEmp = DB::row("SELECT * FROM employees WHERE id=?", [$id]);
        }
        
        $data = [
            'name_en'         => post('name_en'),
            'name_ar'         => post('name_ar'),
            'email'           => post('email'),
            'phone'           => post('phone'),
            'nationality'     => post('nationality'),
            $idColumn         => post('civil_id'),
            'passport_no'     => post('passport_no'),
            'gender'          => post('gender'),
            'date_of_birth'   => post('date_of_birth') ?: null,
            'hire_date'       => post('hire_date'),
            'department_id'   => (int)post('department_id') ?: null,
            'job_title_id'    => (int)post('job_title_id') ?: null,
            'employment_type' => post('employment_type'),
            'status'          => post('status'),
            'bank_name'       => post('bank_name'),
            'bank_account'    => post('bank_account'),
            'iban'            => post('iban'),
            'basic_salary'    => (float)post('basic_salary'),
            'notes'           => post('notes'),
        ];

        // Handle photo upload
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadFile($_FILES['photo'], 'avatars', 'emp_');
            if ($uploaded) $data['photo'] = $uploaded;
        }

        // Handle photo deletion
        if (post('delete_photo') == '1' && $id > 0) {
            $emp = DB::row("SELECT photo FROM employees WHERE id=?", [$id]);
            if ($emp && $emp['photo'] && file_exists(UPLOAD_DIR . 'avatars/' . $emp['photo'])) {
                unlink(UPLOAD_DIR . 'avatars/' . $emp['photo']);
            }
            $data['photo'] = '';
        }

        // Validate employee_no
        $employeeNo = trim(post('employee_no'));
        if (empty($employeeNo)) {
            setFlash('error', t('employee_number_required'));
            redirect('index.php?page=employees&sub=' . ($id > 0 ? 'edit&id=' . $id : 'add'));
        }

        // Check if employee_no already exists (for new employees or when changing it)
        if ($id > 0) {
            $existing = DB::row("SELECT id FROM employees WHERE employee_no=? AND id!=?", [$employeeNo, $id]);
        } else {
            $existing = DB::row("SELECT id FROM employees WHERE employee_no=?", [$employeeNo]);
        }
        if ($existing) {
            setFlash('error', t('employee_number_exists'));
            redirect('index.php?page=employees&sub=' . ($id > 0 ? 'edit&id=' . $id : 'add'));
        }

        $data['employee_no'] = $employeeNo;

        // Validate email uniqueness
        $email = trim(post('email'));
        if (!empty($email)) {
            if ($id > 0) {
                $existingEmail = DB::row("SELECT id FROM employees WHERE email=? AND id!=?", [$email, $id]);
            } else {
                $existingEmail = DB::row("SELECT id FROM employees WHERE email=?", [$email]);
            }
            if ($existingEmail) {
                setFlash('error', t('employee_email_exists'));
                redirect('index.php?page=employees&sub=' . ($id > 0 ? 'edit&id=' . $id : 'add'));
            }
        }

        if ($id > 0) {
            DB::update('employees', $data, 'id=?', [$id]);
            
            // Log changes to employee history
            if ($oldEmp) {
                if ($oldEmp['basic_salary'] != $data['basic_salary']) {
                    logEmployeeChange($id, 'salary_change', 'basic_salary', $oldEmp['basic_salary'], $data['basic_salary']);
                }
                if ($oldEmp['status'] != $data['status']) {
                    logEmployeeChange($id, 'status_change', 'status', $oldEmp['status'], $data['status']);
                }
                if ($oldEmp['department_id'] != $data['department_id']) {
                    logEmployeeChange($id, 'department_change', 'department_id', $oldEmp['department_id'], $data['department_id']);
                }
                if ($oldEmp['job_title_id'] != $data['job_title_id']) {
                    logEmployeeChange($id, 'job_change', 'job_title_id', $oldEmp['job_title_id'], $data['job_title_id']);
                }
            }
            
            // Update allowances
            DB::q("DELETE FROM employee_allowances WHERE employee_id=?", [$id]);
        } else {
            $id = DB::insert('employees', $data);
            logEmployeeChange($id, 'created', null, null, null, t('employee_created'));
        }

        // Save allowances
        $allowTypeIds = post('allowance_type_id', []);
        $allowAmounts = post('allowance_amount', []);
        foreach ($allowTypeIds as $i => $typeId) {
            if ($typeId && isset($allowAmounts[$i]) && $allowAmounts[$i] > 0) {
                DB::insert('employee_allowances', [
                    'employee_id' => $id, 'allowance_type_id' => $typeId,
                    'amount' => (float)$allowAmounts[$i], 'is_active' => 1
                ]);
            }
        }

        setFlash('success', t('saved_success'));
        redirect('index.php?page=employees');
    }

    if ($sub === 'delete') {
        requireRole('admin', 'manager');
        $id = (int)post('id');
        DB::update('employees', ['status' => 'terminated'], 'id=?', [$id]);
        setFlash('success', t('deleted_success'));
        redirect('index.php?page=employees');
    }
}

// ── Sub-views ──────────────────────────────────────────────────────────────────
if ($subAction === 'add' || $subAction === 'edit') {
    $emp = $subAction === 'edit' ? DB::row("SELECT * FROM employees WHERE id=?", [(int)get('id')]) : [];
    // For backward compatibility, map civil_id to national_id if column doesn't exist
    if ($emp && !$useCivilId && isset($emp['national_id'])) {
        $emp['civil_id'] = $emp['national_id'];
    }
    $departments = DB::rows("SELECT * FROM departments WHERE is_active=1 ORDER BY name_en");
    $jobTitles   = DB::rows("SELECT * FROM job_titles WHERE is_active=1 ORDER BY title_en");
    $allowTypes  = DB::rows("SELECT * FROM allowance_types WHERE is_active=1 ORDER BY sort_order");
    $empAllows   = $emp ? DB::rows("SELECT * FROM employee_allowances WHERE employee_id=? AND is_active=1", [$emp['id']]) : [];
    $empAllowMap = array_column($empAllows, 'amount', 'allowance_type_id');
    ?>
    <div class="page-header">
      <div class="d-flex align-items-center gap-3">
        <a href="index.php?page=employees" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
        <h1 class="page-title"><?= $subAction === 'add' ? t('add_employee') : t('edit_employee') ?></h1>
      </div>
      <p class="page-sub text-muted"><?= $subAction === 'add' ? t('add_new_employee') : t('edit_employee_details') ?></p>
    </div>
    <?php if ($subAction === 'add' && hasRole('viewer')): ?>
    <div class="alert alert-warning"><?= t('viewers_cannot_add') ?></div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">
      <input type="hidden" name="sub_action" value="save">
      <input type="hidden" name="id" value="<?= (int)($emp['id'] ?? 0) ?>">
      <div class="row g-3">
        <!-- Personal Info -->
        <div class="col-lg-8">
          <div class="card card-modern mb-3">
            <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-user me-2 text-primary"></i><?= t('personal_details') ?></h5></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('employee_number') ?> *</label>
                  <input type="text" class="form-control" name="employee_no" value="<?= h($emp['employee_no'] ?? ($subAction === 'add' ? generateEmpNo() : '')) ?>" required <?= $subAction === 'edit' ? 'readonly' : '' ?>>
                  <small class="text-muted"><?= $subAction === 'edit' ? t('cannot_change_after_creation') : t('auto_suggested') ?></small>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('name_en') ?> *</label>
                  <input type="text" class="form-control" name="name_en" value="<?= h($emp['name_en'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('name_ar') ?></label>
                  <input type="text" class="form-control" name="name_ar" dir="rtl" value="<?= h($emp['name_ar'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('email') ?></label>
                  <input type="email" class="form-control" name="email" value="<?= h($emp['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('phone') ?></label>
                  <input type="text" class="form-control" name="phone" value="<?= h($emp['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('nationality') ?></label>
                  <select class="form-select" name="nationality">
                    <option value=""><?= t('select_country') ?></option>
                    <?php
                    $countries = [
                        'Kuwait' => 'Kuwait',
                        'Afghanistan' => 'Afghanistan',
                        'Albania' => 'Albania',
                        'Algeria' => 'Algeria',
                        'Andorra' => 'Andorra',
                        'Angola' => 'Angola',
                        'Argentina' => 'Argentina',
                        'Armenia' => 'Armenia',
                        'Australia' => 'Australia',
                        'Austria' => 'Austria',
                        'Azerbaijan' => 'Azerbaijan',
                        'Bahrain' => 'Bahrain',
                        'Bangladesh' => 'Bangladesh',
                        'Belarus' => 'Belarus',
                        'Belgium' => 'Belgium',
                        'Belize' => 'Belize',
                        'Benin' => 'Benin',
                        'Bhutan' => 'Bhutan',
                        'Bolivia' => 'Bolivia',
                        'Bosnia and Herzegovina' => 'Bosnia and Herzegovina',
                        'Brazil' => 'Brazil',
                        'Brunei' => 'Brunei',
                        'Bulgaria' => 'Bulgaria',
                        'Burkina Faso' => 'Burkina Faso',
                        'Burundi' => 'Burundi',
                        'Cambodia' => 'Cambodia',
                        'Cameroon' => 'Cameroon',
                        'Canada' => 'Canada',
                        'Chad' => 'Chad',
                        'Chile' => 'Chile',
                        'China' => 'China',
                        'Colombia' => 'Colombia',
                        'Comoros' => 'Comoros',
                        'Congo' => 'Congo',
                        'Costa Rica' => 'Costa Rica',
                        'Croatia' => 'Croatia',
                        'Cuba' => 'Cuba',
                        'Cyprus' => 'Cyprus',
                        'Czech Republic' => 'Czech Republic',
                        'Denmark' => 'Denmark',
                        'Djibouti' => 'Djibouti',
                        'Dominican Republic' => 'Dominican Republic',
                        'Ecuador' => 'Ecuador',
                        'Egypt' => 'Egypt',
                        'El Salvador' => 'El Salvador',
                        'Estonia' => 'Estonia',
                        'Ethiopia' => 'Ethiopia',
                        'Fiji' => 'Fiji',
                        'Finland' => 'Finland',
                        'France' => 'France',
                        'Gabon' => 'Gabon',
                        'Gambia' => 'Gambia',
                        'Georgia' => 'Georgia',
                        'Germany' => 'Germany',
                        'Ghana' => 'Ghana',
                        'Greece' => 'Greece',
                        'Guatemala' => 'Guatemala',
                        'Guinea' => 'Guinea',
                        'Haiti' => 'Haiti',
                        'Honduras' => 'Honduras',
                        'Hong Kong' => 'Hong Kong',
                        'Hungary' => 'Hungary',
                        'Iceland' => 'Iceland',
                        'India' => 'India',
                        'Indonesia' => 'Indonesia',
                        'Iran' => 'Iran',
                        'Iraq' => 'Iraq',
                        'Ireland' => 'Ireland',
                        'Israel' => 'Israel',
                        'Italy' => 'Italy',
                        'Jamaica' => 'Jamaica',
                        'Japan' => 'Japan',
                        'Jordan' => 'Jordan',
                        'Kazakhstan' => 'Kazakhstan',
                        'Kenya' => 'Kenya',
                        'Kyrgyzstan' => 'Kyrgyzstan',
                        'Laos' => 'Laos',
                        'Latvia' => 'Latvia',
                        'Lebanon' => 'Lebanon',
                        'Libya' => 'Libya',
                        'Lithuania' => 'Lithuania',
                        'Luxembourg' => 'Luxembourg',
                        'Macedonia' => 'Macedonia',
                        'Madagascar' => 'Madagascar',
                        'Malaysia' => 'Malaysia',
                        'Maldives' => 'Maldives',
                        'Mali' => 'Mali',
                        'Malta' => 'Malta',
                        'Mauritania' => 'Mauritania',
                        'Mexico' => 'Mexico',
                        'Moldova' => 'Moldova',
                        'Monaco' => 'Monaco',
                        'Mongolia' => 'Mongolia',
                        'Montenegro' => 'Montenegro',
                        'Morocco' => 'Morocco',
                        'Myanmar' => 'Myanmar',
                        'Nepal' => 'Nepal',
                        'Netherlands' => 'Netherlands',
                        'New Zealand' => 'New Zealand',
                        'Nicaragua' => 'Nicaragua',
                        'Nigeria' => 'Nigeria',
                        'North Korea' => 'North Korea',
                        'Norway' => 'Norway',
                        'Oman' => 'Oman',
                        'Pakistan' => 'Pakistan',
                        'Panama' => 'Panama',
                        'Paraguay' => 'Paraguay',
                        'Peru' => 'Peru',
                        'Philippines' => 'Philippines',
                        'Poland' => 'Poland',
                        'Portugal' => 'Portugal',
                        'Qatar' => 'Qatar',
                        'Romania' => 'Romania',
                        'Russia' => 'Russia',
                        'Saudi Arabia' => 'Saudi Arabia',
                        'Senegal' => 'Senegal',
                        'Serbia' => 'Serbia',
                        'Singapore' => 'Singapore',
                        'Slovakia' => 'Slovakia',
                        'Slovenia' => 'Slovenia',
                        'South Africa' => 'South Africa',
                        'South Korea' => 'South Korea',
                        'Spain' => 'Spain',
                        'Sri Lanka' => 'Sri Lanka',
                        'Sweden' => 'Sweden',
                        'Switzerland' => 'Switzerland',
                        'Syria' => 'Syria',
                        'Taiwan' => 'Taiwan',
                        'Tajikistan' => 'Tajikistan',
                        'Tanzania' => 'Tanzania',
                        'Thailand' => 'Thailand',
                        'Tunisia' => 'Tunisia',
                        'Turkey' => 'Turkey',
                        'Uganda' => 'Uganda',
                        'Ukraine' => 'Ukraine',
                        'United Arab Emirates' => 'United Arab Emirates',
                        'United Kingdom' => 'United Kingdom',
                        'United States' => 'United States',
                        'Uruguay' => 'Uruguay',
                        'Uzbekistan' => 'Uzbekistan',
                        'Venezuela' => 'Venezuela',
                        'Vietnam' => 'Vietnam',
                        'Yemen' => 'Yemen',
                        'Zambia' => 'Zambia',
                        'Zimbabwe' => 'Zimbabwe'
                    ];
                    foreach ($countries as $code => $name):
                    ?>
                    <option value="<?= $code ?>" <?= ($emp['nationality'] ?? '') === $code ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('civil_id') ?></label>
                  <input type="text" class="form-control" name="civil_id" value="<?= h($emp['civil_id'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('passport_no') ?></label>
                  <input type="text" class="form-control" name="passport_no" value="<?= h($emp['passport_no'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('gender') ?></label>
                  <select class="form-select" name="gender">
                    <option value="male" <?= ($emp['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= t('male') ?></option>
                    <option value="female" <?= ($emp['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= t('female') ?></option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('date_of_birth') ?? 'Date of Birth' ?></label>
                  <input type="date" class="form-control" name="date_of_birth" value="<?= $emp['date_of_birth'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('hire_date') ?> *</label>
                  <input type="date" class="form-control" name="hire_date" value="<?= $emp['hire_date'] ?? '' ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-500"><?= t('status') ?></label>
                  <select class="form-select" name="status">
                    <?php foreach (['active','probation','on_leave','terminated','suspended'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($emp['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= t($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Job Info -->
          <div class="card card-modern mb-3">
            <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-briefcase me-2 text-success"></i><?= t('job_details') ?></h5></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('department') ?></label>
                  <select class="form-select select2" name="department_id">
                    <option value="">-- <?= t('department') ?> --</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($emp['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                      <?= h(lang() === 'ar' ? ($d['name_ar'] ?? $d['name_en']) : $d['name_en']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('job_title') ?></label>
                  <select class="form-select select2" name="job_title_id">
                    <option value="">-- <?= t('job_title') ?> --</option>
                    <?php foreach ($jobTitles as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= ($emp['job_title_id'] ?? '') == $j['id'] ? 'selected' : '' ?>>
                      <?= h(lang() === 'ar' ? ($j['title_ar'] ?? $j['title_en']) : $j['title_en']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('employment_type') ?></label>
                  <select class="form-select" name="employment_type">
                    <?php foreach (['full_time','part_time','contractor','intern'] as $et): ?>
                    <option value="<?= $et ?>" <?= ($emp['employment_type'] ?? 'full_time') === $et ? 'selected' : '' ?>><?= t($et) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-500"><?= t('basic_salary') ?> *</label>
                  <div class="input-group">
                    <input type="number" class="form-control" name="basic_salary" step="0.001" min="0"
                           value="<?= number_format((float)($emp['basic_salary'] ?? 0), 3, '.', '') ?>" required>
                    <span class="input-group-text"><?= t('currency') ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Allowances -->
          <div class="card card-modern mb-3">
            <div class="card-header-modern d-flex justify-content-between">
              <h5 class="card-title-modern"><i class="fas fa-plus-circle me-2 text-success"></i><?= t('allowances') ?></h5>
              <button type="button" class="btn btn-sm btn-success" onclick="addAllowanceRow()">
                <i class="fas fa-plus"></i>
              </button>
            </div>
            <div class="card-body">
              <div id="allowanceRows">
              <?php foreach ($allowTypes as $at): ?>
              <?php $amt = $empAllowMap[$at['id']] ?? ''; if(!$amt && $subAction === 'add') continue; ?>
              <div class="row g-2 mb-2 allowance-row">
                <div class="col-6">
                  <select class="form-select form-select-sm" name="allowance_type_id[]">
                    <?php foreach ($allowTypes as $at2): ?>
                    <option value="<?= $at2['id'] ?>" <?= $at2['id'] == $at['id'] ? 'selected' : '' ?>>
                      <?= h(lang() === 'ar' ? ($at2['name_ar'] ?? $at2['name_en']) : $at2['name_en']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-5">
                  <input type="number" class="form-control form-control-sm" name="allowance_amount[]" step="0.001" min="0" value="<?= h($amt) ?>" placeholder="<?= t('allowance_amount') ?>">
                </div>
                <div class="col-1">
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.allowance-row').remove()"><i class="fas fa-times"></i></button>
                </div>
              </div>
              <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Bank Info -->
          <div class="card card-modern mb-3">
            <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-building-columns me-2 text-info"></i><?= t('bank_information') ?></h5></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4"><label class="form-label"><?= t('bank_name') ?></label><input type="text" class="form-control" name="bank_name" value="<?= h($emp['bank_name'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label"><?= t('bank_account') ?></label><input type="text" class="form-control" name="bank_account" value="<?= h($emp['bank_account'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label"><?= t('iban') ?></label><input type="text" class="form-control" name="iban" value="<?= h($emp['iban'] ?? '') ?>"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
          <div class="card card-modern mb-3">
            <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-image me-2"></i><?= t('photo') ?></h5></div>
            <div class="card-body text-center">
              <?php $photo = $emp['photo'] ?? ''; ?>
              <div class="emp-avatar-upload" id="avatarPreview">
                <?php if ($photo && file_exists(UPLOAD_DIR . 'avatars/' . $photo)): ?>
                <img src="uploads/avatars/<?= h($photo) ?>" class="rounded-circle" width="100" height="100" style="object-fit:cover">
                <?php else: ?>
                <div class="avatar-placeholder"><i class="fas fa-user fa-3x text-muted"></i></div>
                <?php endif; ?>
              </div>
              <input type="file" class="form-control mt-2" name="photo" accept="image/*" onchange="previewAvatar(this)">
              <?php if ($photo && file_exists(UPLOAD_DIR . 'avatars/' . $photo)): ?>
              <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="deletePhotoBtn">
                <i class="fas fa-trash me-1"></i><?= t('delete_photo') ?>
              </button>
              <input type="hidden" name="delete_photo" id="deletePhoto" value="0">
              <div id="deletePhotoMsg" class="text-danger small mt-1" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i> <?= t('click_save_confirm_deletion') ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="card card-modern mb-3">
            <div class="card-header-modern"><h5 class="card-title-modern"><i class="fas fa-note-sticky me-2"></i><?= t('notes') ?></h5></div>
            <div class="card-body">
              <textarea class="form-control" name="notes" rows="4"><?= h($emp['notes'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i><?= t('save_employee') ?></button>
            <a href="index.php?page=employees" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i><?= t('cancel') ?></a>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <template id="allowanceRowTemplate">
      <div class="row g-2 mb-2 allowance-row">
        <div class="col-6">
          <select class="form-select form-select-sm" name="allowance_type_id[]">
            <?php foreach ($allowTypes as $at2): ?>
            <option value="<?= $at2['id'] ?>"><?= h(lang() === 'ar' ? ($at2['name_ar'] ?? $at2['name_en']) : $at2['name_en']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-5"><input type="number" class="form-control form-control-sm" name="allowance_amount[]" step="0.001" min="0" value="" placeholder="<?= t('allowance_amount') ?>"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.allowance-row').remove()"><i class="fas fa-times"></i></button></div>
      </div>
    </template>
    <script>
    function addAllowanceRow() {
      const t = document.getElementById('allowanceRowTemplate');
      document.getElementById('allowanceRows').appendChild(t.content.cloneNode(true));
    }
    function previewAvatar(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
          document.getElementById('avatarPreview').innerHTML = `<img src="${e.target.result}" class="rounded-circle" width="100" height="100" style="object-fit:cover">`;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
    function deletePhoto() {
      if (confirm('<?= t('delete_photo_confirm') ?>')) {
        document.getElementById('deletePhoto').value = '1';
        document.getElementById('avatarPreview').innerHTML = '<div class="avatar-placeholder"><i class="fas fa-user fa-3x text-muted"></i></div>';
        document.getElementById('deletePhotoMsg').style.display = 'block';
      }
    }
    // Attach event listener to delete button
    document.addEventListener('DOMContentLoaded', function() {
      const deleteBtn = document.getElementById('deletePhotoBtn');
      if (deleteBtn) {
        deleteBtn.addEventListener('click', deletePhoto);
      }
    });
    </script>
    <?php
    return;
}

// ── Print view ──────────────────────────────────────────────────────────────────
if ($subAction === 'print') {
    $id = (int)get('id');
    $emp = DB::row("SELECT e.*, d.name_en as dept_en, d.name_ar as dept_ar, j.title_en, j.title_ar 
                    FROM employees e 
                    LEFT JOIN departments d ON d.id=e.department_id 
                    LEFT JOIN job_titles j ON j.id=e.job_title_id 
                    WHERE e.id=?", [$id]);
    if (!$emp) { redirect('index.php?page=employees'); }
    
    $allowances = DB::rows("SELECT ea.*, at.name_en, at.name_ar FROM employee_allowances ea 
                             JOIN allowance_types at ON at.id=ea.allowance_type_id 
                             WHERE ea.employee_id=? AND ea.is_active=1", [$id]);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?= t('employee_profile') ?> - <?= h($emp['name_en']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .section { margin-bottom: 20px; }
            .section-title { font-weight: bold; font-size: 14px; background: #f0f0f0; padding: 8px; margin-bottom: 10px; }
            .row { display: flex; margin-bottom: 8px; }
            .label { width: 150px; font-weight: bold; }
            .value { flex: 1; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f0f0f0; }
            @media print { body { padding: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= t('employee_profile') ?></h1>
            <p><?= t('generated_at') ?>: <?= date('Y-m-d H:i:s') ?></p>
        </div>
        
        <div class="section">
            <div class="section-title"><?= t('personal_information') ?></div>
            <div class="row"><div class="label"><?= t('employee_no') ?>:</div><div class="value"><?= h($emp['employee_no']) ?></div></div>
            <div class="row"><div class="label"><?= t('name_en') ?>:</div><div class="value"><?= h($emp['name_en']) ?></div></div>
            <div class="row"><div class="label"><?= t('name_ar') ?>:</div><div class="value" dir="rtl"><?= h($emp['name_ar']) ?></div></div>
            <div class="row"><div class="label"><?= t('email') ?>:</div><div class="value"><?= h($emp['email']) ?></div></div>
            <div class="row"><div class="label"><?= t('phone') ?>:</div><div class="value"><?= h($emp['phone']) ?></div></div>
            <div class="row"><div class="label"><?= t('nationality') ?>:</div><div class="value"><?= h($emp['nationality']) ?></div></div>
            <div class="row"><div class="label"><?= t('civil_id') ?>:</div><div class="value"><?= h($emp['civil_id']) ?></div></div>
            <div class="row"><div class="label"><?= t('passport_no') ?>:</div><div class="value"><?= h($emp['passport_no']) ?></div></div>
            <div class="row"><div class="label"><?= t('gender') ?>:</div><div class="value"><?= t($emp['gender']) ?></div></div>
            <div class="row"><div class="label"><?= t('date_of_birth') ?>:</div><div class="value"><?= fdate($emp['date_of_birth']) ?></div></div>
        </div>
        
        <div class="section">
            <div class="section-title"><?= t('employment_information') ?></div>
            <div class="row"><div class="label"><?= t('department') ?>:</div><div class="value"><?= h($emp['dept_en']) ?></div></div>
            <div class="row"><div class="label"><?= t('job_title') ?>:</div><div class="value"><?= h($emp['title_en']) ?></div></div>
            <div class="row"><div class="label"><?= t('employment_type') ?>:</div><div class="value"><?= t($emp['employment_type']) ?></div></div>
            <div class="row"><div class="label"><?= t('hire_date') ?>:</div><div class="value"><?= fdate($emp['hire_date']) ?></div></div>
            <div class="row"><div class="label"><?= t('status') ?>:</div><div class="value"><?= t($emp['status']) ?></div></div>
            <div class="row"><div class="label"><?= t('basic_salary') ?>:</div><div class="value"><?= money((float)$emp['basic_salary']) ?></div></div>
        </div>
        
        <?php if ($allowances): ?>
        <div class="section">
            <div class="section-title"><?= t('allowances') ?></div>
            <table>
                <tr><th><?= t('type') ?></th><th><?= t('amount') ?></th></tr>
                <?php foreach ($allowances as $a): ?>
                <tr>
                    <td><?= h(lang() === 'ar' ? ($a['name_ar'] ?? $a['name_en']) : $a['name_en']) ?></td>
                    <td><?= money((float)$a['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <div class="section-title"><?= t('bank_information') ?></div>
            <div class="row"><div class="label"><?= t('bank_name') ?>:</div><div class="value"><?= h($emp['bank_name']) ?></div></div>
            <div class="row"><div class="label"><?= t('bank_account') ?>:</div><div class="value"><?= h($emp['bank_account']) ?></div></div>
            <div class="row"><div class="label"><?= t('iban') ?>:</div><div class="value"><?= h($emp['iban']) ?></div></div>
        </div>
        
        <div class="section">
            <div class="section-title"><?= t('notes') ?></div>
            <div><?= nl2br(h($emp['notes'])) ?></div>
        </div>
        
        <script>
        window.onload = function() { window.print(); }
        </script>
    </body>
    </html>
    <?php
    return;
}

// ── List view ──────────────────────────────────────────────────────────────────
$departments = DB::rows("SELECT * FROM departments WHERE is_active=1 ORDER BY name_en");
$deptFilter  = get('dept');
$statusFilter = get('status', 'active');

$where = "WHERE 1=1";
$params = [];
if ($deptFilter) { $where .= " AND e.department_id=?"; $params[] = $deptFilter; }
if ($statusFilter) { $where .= " AND e.status=?"; $params[] = $statusFilter; }

$totalCount = DB::val("SELECT COUNT(*) FROM employees e $where", $params);
$page2 = max(1, (int)get('p', 1));
$emps = DB::paginate(
    "SELECT e.*, d.name_en as dept_en, d.name_ar as dept_ar, j.title_en, j.title_ar FROM employees e
     LEFT JOIN departments d ON d.id=e.department_id
     LEFT JOIN job_titles j ON j.id=e.job_title_id
     $where ORDER BY e.id DESC",
    $params, $page2
);
?>

<div class="page-header d-flex justify-content-between align-items-start">
  <div>
    <h1 class="page-title"><i class="fas fa-users me-2"></i><?= t('employees') ?></h1>
    <p class="page-sub text-muted"><?= number_format($totalCount) ?> <?= t('total_employees') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php?page=export&type=employees_excel" class="btn btn-outline-success btn-sm">
      <i class="fas fa-file-excel me-1"></i><?= t('export_excel') ?>
    </a>
    <a href="index.php?page=employees&sub=add" class="btn btn-primary btn-sm">
      <i class="fas fa-plus me-1"></i><?= t('add_employee') ?>
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card card-modern mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end">
      <input type="hidden" name="page" value="employees">
      <div class="col-md-4">
        <select class="form-select form-select-sm" name="dept" onchange="this.form.submit()">
          <option value=""><?= t('filter_by_department') ?></option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
            <?= h(lang() === 'ar' ? ($d['name_ar'] ?? $d['name_en']) : $d['name_en']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
          <option value=""><?= t('filter_by_status') ?></option>
          <?php foreach (['active','probation','on_leave','terminated','suspended'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= t($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <input type="text" class="form-control form-control-sm" id="empSearch" placeholder="<?= t('search') ?>...">
      </div>
      <div class="col-md-2">
        <a href="index.php?page=employees" class="btn btn-outline-secondary btn-sm w-100"><?= t('clear') ?></a>
      </div>
    </form>
  </div>
</div>

<div class="card card-modern">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="empTable">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th><?= t('employee_no') ?></th>
            <th><?= t('employee_name') ?></th>
            <th><?= t('department') ?></th>
            <th><?= t('job_title') ?></th>
            <th><?= t('hire_date') ?></th>
            <th><?= t('basic_salary') ?></th>
            <th><?= t('status') ?></th>
            <th class="text-center"><?= t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($emps['data'] as $i => $e): ?>
        <tr>
          <td class="text-muted small"><?= $emps['from'] + $i ?></td>
          <td><span class="badge bg-secondary"><?= h($e['employee_no']) ?></span></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="emp-avatar-mini">
                <?php if ($e['photo'] && file_exists(UPLOAD_DIR . 'avatars/' . $e['photo'])): ?>
                <img src="uploads/avatars/<?= h($e['photo']) ?>" width="36" height="36" class="rounded-circle" style="object-fit:cover">
                <?php else: ?>
                <div class="avatar-mini-placeholder"><?= strtoupper(substr($e['name_en'], 0, 1)) ?></div>
                <?php endif; ?>
              </div>
              <div>
                <div class="fw-500"><?= h(lang() === 'ar' ? ($e['name_ar'] ?: $e['name_en']) : $e['name_en']) ?></div>
                <small class="text-muted"><?= h($e['email'] ?? '') ?></small>
              </div>
            </div>
          </td>
          <td><?= h(lang() === 'ar' ? ($e['dept_ar'] ?? $e['dept_en'] ?? '-') : ($e['dept_en'] ?? '-')) ?></td>
          <td><?= h(lang() === 'ar' ? ($e['title_ar'] ?? $e['title_en'] ?? '-') : ($e['title_en'] ?? '-')) ?></td>
          <td><?= fdate($e['hire_date']) ?></td>
          <td class="fw-500 text-success"><?= money((float)$e['basic_salary']) ?></td>
          <td><?= statusBadge($e['status']) ?></td>
          <td class="text-center">
            <div class="btn-group btn-group-sm">
              <a href="index.php?page=employees&sub=edit&id=<?= $e['id'] ?>" class="btn btn-outline-primary" title="<?= t('edit') ?>"><i class="fas fa-edit"></i></a>
              <button class="btn btn-outline-info" onclick="showPayslipModal(<?= $e['id'] ?>, '<?= h($e['name_en']) ?>')" title="<?= t('payslip') ?>"><i class="fas fa-file-pdf"></i></button>
              <button class="btn btn-outline-secondary" onclick="printEmployeeProfile(<?= $e['id'] ?>)" title="<?= t('print') ?>"><i class="fas fa-print"></i></button>
              <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $e['id'] ?>)" title="<?= t('delete') ?>"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($emps['data'])): ?>
        <tr><td colspan="9" class="text-center py-4 text-muted"><?= t('no_records') ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- Pagination -->
  <?php if ($emps['last_page'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= formatT('showing_x_of_y', ['from' => $emps['from'], 'to' => $emps['to'], 'total' => $emps['total']]) ?></small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($pg = 1; $pg <= $emps['last_page']; $pg++): ?>
        <li class="page-item <?= $pg == $emps['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=employees&dept=<?= $deptFilter ?>&status=<?= $statusFilter ?>&p=<?= $pg ?>"><?= $pg ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- Delete modal -->
<form method="POST" id="deleteForm">
  <input type="hidden" name="_csrf" value="<?= csrf() ?>">
  <input type="hidden" name="sub_action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<!-- Payslip modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= t('generate_payslip') ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payslipEmpId">
        <div class="mb-3">
          <label class="form-label"><?= t('year') ?></label>
          <select class="form-select" id="payslipYear">
            <?php for($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= t('month') ?></label>
          <select class="form-select" id="payslipMonth">
            <?php for($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= monthName($m, lang()) ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary w-100" onclick="generatePayslip()">
          <i class="fas fa-file-pdf me-1"></i><?= t('generate_payslip') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function showPayslipModal(empId, empName) {
  document.getElementById('payslipEmpId').value = empId;
  new bootstrap.Modal(document.getElementById('payslipModal')).show();
}

function generatePayslip() {
  const empId = document.getElementById('payslipEmpId').value;
  const year = document.getElementById('payslipYear').value;
  const month = document.getElementById('payslipMonth').value;
  bootstrap.Modal.getInstance(document.getElementById('payslipModal')).hide();
  window.open(`index.php?page=export&type=payslip_pdf&emp_id=${empId}&year=${year}&month=${month}`, '_blank');
}

function printEmployeeProfile(empId) {
  window.open(`index.php?page=employees&sub=print&id=${empId}`, '_blank');
}

function confirmDelete(id) {
  Swal.fire({
    title: '<?= t("confirm_delete") ?>',
    icon: 'warning', showCancelButton: true,
    confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
    confirmButtonText: '<?= t("delete") ?>', cancelButtonText: '<?= t("cancel") ?>'
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('deleteId').value = id;
      document.getElementById('deleteForm').submit();
    }
  });
}
// Live search
document.getElementById('empSearch').addEventListener('input', function() {
  const v = this.value.toLowerCase();
  document.querySelectorAll('#empTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(v) ? '' : 'none';
  });
});
</script>
