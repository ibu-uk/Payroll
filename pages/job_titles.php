<?php
$pageTitle = t('job_titles');
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    if ($sub === 'save') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = [
            'title_en' => post('title_en'),
            'title_ar' => post('title_ar'),
            'grade' => post('grade'),
            'min_salary' => (float)post('min_salary'),
            'max_salary' => (float)post('max_salary'),
            'working_hours' => (int)post('working_hours'),
            'shift_type' => post('shift_type'),
            'is_active' => (int)post('is_active', 1)
        ];
        $id > 0 ? DB::update('job_titles', $data, 'id=?', [$id]) : DB::insert('job_titles', $data);
        setFlash('success', t('saved_success'));
    } elseif ($sub === 'delete') {
        requireRole('admin', 'manager');
        DB::update('job_titles', ['is_active' => 0], 'id=?', [(int)post('id')]);
        setFlash('success', t('deleted_success'));
    }
    redirect('index.php?page=job_titles');
}
$jobTitles = DB::rows("SELECT jt.*, COUNT(e.id) as emp_count FROM job_titles jt LEFT JOIN employees e ON e.job_title_id=jt.id AND e.status='active' GROUP BY jt.id ORDER BY jt.title_en");
$editId = (int)get('edit');
$editJob = $editId ? DB::row("SELECT * FROM job_titles WHERE id=?", [$editId]) : null;
?>
<div class="page-header d-flex justify-content-between">
  <h1 class="page-title"><i class="fas fa-briefcase me-2"></i><?= t('job_titles') ?></h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#jobModal"><i class="fas fa-plus me-1"></i><?= t('add') ?></button>
</div>
<div class="card card-modern">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-dark">
          <tr>
            <th><?= t('title_en') ?></th>
            <th><?= t('title_ar') ?></th>
            <th>Grade</th>
            <th>Working Hours</th>
            <th>Shift</th>
            <th>Salary Range</th>
            <th>Employees</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobTitles as $jt): ?>
          <tr>
            <td><?= h($jt['title_en']) ?></td>
            <td dir="rtl"><?= h($jt['title_ar'] ?? '') ?></td>
            <td><span class="badge bg-secondary"><?= h($jt['grade']) ?></span></td>
            <td><strong><?= $jt['working_hours'] ?></strong> hrs/day</td>
            <td><span class="badge bg-info"><?= ucfirst($jt['shift_type']) ?></span></td>
            <td><?= money($jt['min_salary']) ?> - <?= money($jt['max_salary']) ?></td>
            <td><?= $jt['emp_count'] ?></td>
            <td><?= statusBadge($jt['is_active'] ? 'active' : 'inactive') ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="editJob(<?= htmlspecialchars(json_encode($jt)) ?>)"><i class="fas fa-edit"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="modal fade" id="jobModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jobModalTitle"><?= t('add') ?> <?= t('job_titles') ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="sub_action" value="save">
        <input type="hidden" name="id" id="jobId" value="0">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= t('title_en') ?> *</label>
              <input type="text" class="form-control" name="title_en" id="jobTitleEn" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= t('title_ar') ?></label>
              <input type="text" class="form-control" name="title_ar" id="jobTitleAr" dir="rtl">
            </div>
            <div class="col-md-6">
              <label class="form-label">Grade</label>
              <input type="text" class="form-control" name="grade" id="jobGrade" placeholder="e.g. A1, B2">
            </div>
            <div class="col-md-6">
              <label class="form-label">Working Hours/Day *</label>
              <input type="number" class="form-control" name="working_hours" id="jobWorkingHours" min="1" max="12" value="8" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Shift Type *</label>
              <select class="form-select" name="shift_type" id="jobShiftType" required>
                <option value="morning">Morning (8AM-4PM)</option>
                <option value="evening">Evening (4PM-12AM)</option>
                <option value="night">Night (12AM-8AM)</option>
                <option value="flexible">Flexible</option>
              </select>
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-6">
              <label class="form-label">Min Salary</label>
              <input type="number" class="form-control" name="min_salary" id="jobMinSalary" step="0.001" min="0" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Max Salary</label>
              <input type="number" class="form-control" name="max_salary" id="jobMaxSalary" step="0.001" min="0" value="0">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="jobActive" value="1" checked>
                <label class="form-check-label" for="jobActive"><?= t('active') ?></label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= t('save') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function editJob(j) {
  document.getElementById('jobId').value = j.id;
  document.getElementById('jobTitleEn').value = j.title_en || '';
  document.getElementById('jobTitleAr').value = j.title_ar || '';
  document.getElementById('jobGrade').value = j.grade || '';
  document.getElementById('jobWorkingHours').value = j.working_hours || 8;
  document.getElementById('jobShiftType').value = j.shift_type || 'morning';
  document.getElementById('jobMinSalary').value = j.min_salary || 0;
  document.getElementById('jobMaxSalary').value = j.max_salary || 0;
  document.getElementById('jobActive').checked = j.is_active == 1;
  document.getElementById('jobModalTitle').textContent = '<?= t("edit") ?> <?= t("job_titles") ?>';
  new bootstrap.Modal(document.getElementById('jobModal')).show();
}
</script>
