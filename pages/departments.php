<?php
$pageTitle = t('departments');
if (isPost()) {
    checkCsrf();
    $sub = post('sub_action');
    if ($sub === 'save') {
        requireRole('admin', 'manager', 'hr');
        $id = (int)post('id');
        $data = ['code'=>post('code'),'name_en'=>post('name_en'),'name_ar'=>post('name_ar'),'is_active'=>(int)post('is_active',1)];
        $id > 0 ? DB::update('departments',$data,'id=?',[$id]) : DB::insert('departments',$data);
        setFlash('success',t('saved_success'));
    } elseif ($sub === 'delete') {
        requireRole('admin', 'manager');
        DB::update('departments',['is_active'=>0],'id=?',[(int)post('id')]);
        setFlash('success',t('deleted_success'));
    }
    redirect('index.php?page=departments');
}
$depts = DB::rows("SELECT d.*,COUNT(e.id) as emp_count FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active' GROUP BY d.id ORDER BY d.name_en");
$editId = (int)get('edit');
$editDept = $editId ? DB::row("SELECT * FROM departments WHERE id=?",[$editId]) : null;
?>
<div class="page-header d-flex justify-content-between">
  <h1 class="page-title"><i class="fas fa-sitemap me-2"></i><?= t('departments') ?></h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#deptModal"><i class="fas fa-plus me-1"></i><?= t('add') ?></button>
</div>
<div class="row g-3">
  <?php foreach ($depts as $d): ?>
  <div class="col-md-4 col-lg-3">
    <div class="card card-modern text-center h-100">
      <div class="card-body">
        <div class="dept-icon-lg mb-3"><i class="fas fa-building fa-2x text-primary"></i></div>
        <h5 class="fw-700"><?= h(lang()==='ar'?($d['name_ar']?:$d['name_en']):$d['name_en']) ?></h5>
        <span class="badge bg-secondary mb-2"><?= h($d['code']) ?></span>
        <p class="text-muted mb-3"><strong class="text-dark"><?= $d['emp_count'] ?></strong> <?= t('employees') ?></p>
        <?= statusBadge($d['is_active'] ? 'active' : 'inactive') ?>
      </div>
      <div class="card-footer d-flex gap-2 justify-content-center">
        <button class="btn btn-sm btn-outline-primary" onclick="editDept(<?= htmlspecialchars(json_encode($d)) ?>)"><i class="fas fa-edit"></i></button>
        <form method="POST" class="d-inline"><input type="hidden" name="_csrf" value="<?= csrf() ?>">
          <input type="hidden" name="sub_action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="deptModalTitle"><?= t('add') ?> <?= t('departments') ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><input type="hidden" name="_csrf" value="<?= csrf() ?>"><input type="hidden" name="sub_action" value="save">
      <input type="hidden" name="id" id="deptId" value="0">
      <div class="modal-body">
        <div class="mb-3"><label class="form-label"><?= t('name_en') ?> *</label><input type="text" class="form-control" name="name_en" id="deptNameEn" required></div>
        <div class="mb-3"><label class="form-label"><?= t('name_ar') ?></label><input type="text" class="form-control" name="name_ar" id="deptNameAr" dir="rtl"></div>
        <div class="mb-3"><label class="form-label">Code</label><input type="text" class="form-control" name="code" id="deptCode" placeholder="e.g. HR"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="deptActive" value="1" checked><label class="form-check-label" for="deptActive"><?= t('active') ?></label></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= t('save') ?></button></div>
    </form>
  </div></div>
</div>
<script>
function editDept(d) {
  document.getElementById('deptId').value = d.id;
  document.getElementById('deptNameEn').value = d.name_en || '';
  document.getElementById('deptNameAr').value = d.name_ar || '';
  document.getElementById('deptCode').value = d.code || '';
  document.getElementById('deptActive').checked = d.is_active == 1;
  document.getElementById('deptModalTitle').textContent = '<?= t("edit") ?> <?= t("departments") ?>';
  new bootstrap.Modal(document.getElementById('deptModal')).show();
}
</script>
