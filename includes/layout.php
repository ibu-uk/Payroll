<?php
$flash = getFlash();
$user = currentUser();
$settings = DB::row("SELECT * FROM settings WHERE id=1") ?? [];
$rtl = isRtl();
$bsDir = $rtl ? 'rtl/' : '';
$htmlDir = $rtl ? 'rtl' : 'ltr';
$companyName = lang() === 'ar' ? ($settings['company_name_ar'] ?? 'باي رول برو') : ($settings['company_name_en'] ?? 'PayrollPro');
$currentPage = get('page', 'dashboard');
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= $htmlDir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? t('dashboard')) ?> – <?= h($companyName) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💼</text></svg>">
<?php if($rtl): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css') ?>">
<style>
/* Inline SweetAlert2 Styles - Override defaults */
.swal2-popup {
  border-radius: 16px !important;
  box-shadow: 0 8px 32px rgba(0,0,0,.12), 0 4px 16px rgba(0,0,0,.08) !important;
  font-family: 'Inter', 'Cairo', sans-serif !important;
}
.swal2-icon {
  border: none !important;
  box-shadow: none !important;
}
.swal2-icon.swal2-warning {
  color: #f39c12 !important;
  border-color: #f39c12 !important;
}
.swal2-icon.swal2-error {
  color: #e74c3c !important;
  border-color: #e74c3c !important;
}
.swal2-icon.swal2-success {
  color: #27ae60 !important;
  border-color: #27ae60 !important;
}
.swal2-confirm {
  background: #4a90e2 !important;
  border-radius: 8px !important;
  padding: 10px 24px !important;
  font-weight: 500 !important;
  border: none !important;
}
.swal2-confirm:hover {
  background: #6366f1 !important;
}
.swal2-cancel {
  border-radius: 8px !important;
  padding: 10px 24px !important;
  font-weight: 500 !important;
  border: 1px solid #e8e8e8 !important;
  background: #fff !important;
  color: #666666 !important;
}
.swal2-cancel:hover {
  background: #f5f5f0 !important;
}
/* Bootstrap Modal Styles */
.modal-content {
  border-radius: 16px !important;
  box-shadow: 0 8px 32px rgba(0,0,0,.12), 0 4px 16px rgba(0,0,0,.08) !important;
  border: 1px solid #e8e8e8 !important;
}
.modal-header {
  border-bottom: 1px solid #e8e8e8 !important;
  padding: 16px 20px !important;
  background: #f5f5f0 !important;
  border-radius: 16px 16px 0 0 !important;
}
.modal-footer {
  border-top: 1px solid #e8e8e8 !important;
  padding: 16px 20px !important;
  background: #f5f5f0 !important;
  border-radius: 0 0 16px 16px !important;
}
</style>
</head>
<body class="<?= $rtl ? 'rtl' : 'ltr' ?>">

<!-- Sidebar -->
<nav id="sidebar" class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-briefcase"></i></div>
    <div class="brand-text">
      <span class="brand-name"><?= h($companyName) ?></span>
      <span class="brand-sub">Payroll System</span>
    </div>
  </div>
  <div class="sidebar-divider"></div>
  <ul class="sidebar-nav">
    <?php
    $nav = [
      ['page'=>'dashboard',   'icon'=>'fa-gauge-high',      'key'=>'dashboard'],
      ['page'=>'employees',   'icon'=>'fa-users',           'key'=>'employees'],
      ['page'=>'job_titles',  'icon'=>'fa-briefcase',       'key'=>'job_titles'],
      ['page'=>'departments', 'icon'=>'fa-sitemap',         'key'=>'departments'],
      ['page'=>'payroll',     'icon'=>'fa-money-bill-wave', 'key'=>'payroll'],
      ['page'=>'attendance',  'icon'=>'fa-calendar-check',  'key'=>'attendance'],
      ['page'=>'leaves',      'icon'=>'fa-umbrella-beach',  'key'=>'leaves'],
      ['page'=>'leave_types', 'icon'=>'fa-list',            'key'=>'leave_types'],
      ['page'=>'loans',       'icon'=>'fa-hand-holding-usd', 'key'=>'loans'],
      ['page'=>'bonuses',     'icon'=>'fa-gift',            'key'=>'bonuses'],
      ['page'=>'gratuity',    'icon'=>'fa-calculator',      'key'=>'gratuity'],
      ['page'=>'reports',     'icon'=>'fa-chart-bar',       'key'=>'reports'],
      ['page'=>'settings',    'icon'=>'fa-gear',            'key'=>'settings'],
    ];
    foreach ($nav as $item):
      $active = $currentPage === $item['page'] ? 'active' : '';
    ?>
    <li class="sidebar-item">
      <a href="index.php?page=<?= $item['page'] ?>" class="sidebar-link <?= $active ?>">
        <i class="fas <?= $item['icon'] ?> sidebar-icon"></i>
        <span><?= t($item['key']) ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></div>
      <div class="user-details">
        <span class="user-name"><?= h($user['name'] ?? '') ?></span>
        <span class="user-role"><?= ucfirst($user['role'] ?? '') ?></span>
      </div>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div id="main-content" class="main-content">
  <!-- Top Navbar -->
  <header class="top-header">
    <div class="header-left">
      <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <nav aria-label="breadcrumb" class="ms-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php"><?= t('dashboard') ?></a></li>
          <?php if ($currentPage !== 'dashboard'): ?>
          <li class="breadcrumb-item active"><?= t($currentPage) ?></li>
          <?php endif; ?>
        </ol>
      </nav>
    </div>
    <div class="header-right">
      <!-- Language Toggle -->
      <a href="index.php?action=toggle_lang&page=<?= $currentPage ?>" class="btn btn-sm btn-lang" title="Toggle Language">
        <?= lang() === 'en' ? '🇰🇼 عربي' : '🇬🇧 English' ?>
      </a>
      <!-- User Menu -->
      <div class="dropdown">
        <button class="btn btn-sm user-btn dropdown-toggle" data-bs-toggle="dropdown">
          <div class="user-avatar-sm"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></div>
          <span><?= h(explode(' ', $user['name'] ?? 'Admin')[0]) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header"><?= h($user['name'] ?? '') ?></h6></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="index.php?page=settings"><i class="fas fa-gear me-2"></i><?= t('settings') ?></a></li>
          <li><a class="dropdown-item text-danger" href="index.php?action=logout"><i class="fas fa-sign-out-alt me-2"></i><?= t('logout') ?></a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Flash messages -->
  <?php if ($flash): ?>
  <div class="px-4 pt-3">
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
      <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> me-2"></i>
      <?= h($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <div class="page-content">
    <?= $content ?? '' ?>
  </div>

  <footer class="page-footer">
    <span><?= h($companyName) ?> &copy; <?= date('Y') ?> &nbsp;|&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?></span>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if ($currentPage === 'dashboard'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php endif; ?>
</body>
</html>
