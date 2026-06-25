<?php
// PayrollPro – Browser Installer
// Visit: http://localhost/payroll/install.php
// DELETE this file after installation!

define('INSTALLER', true);
session_start();

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = false;

function testDB(string $host, string $user, string $pass, string $name): array {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return ['pdo'=>$pdo,'db'=>$name,'error'=>null];
    } catch (Exception $e) {
        return ['pdo'=>null,'db'=>null,'error'=>$e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $host    = trim($_POST['db_host']    ?? 'localhost');
    $user    = trim($_POST['db_user']    ?? 'root');
    $pass    = $_POST['db_pass']         ?? '';
    $name    = trim($_POST['db_name']    ?? 'payroll_db');
    $company = trim($_POST['company']    ?? 'PayrollPro');
    $admin_e = trim($_POST['admin_email']?? 'admin@payroll.local');
    $admin_p = $_POST['admin_pass']      ?? '';

    if (!$admin_p) $errors[] = 'Admin password is required.';
    if (strlen($admin_p) < 6) $errors[] = 'Admin password must be at least 6 characters.';

    if (empty($errors)) {
        $res = testDB($host, $user, $pass, $name);
        if ($res['error']) {
            $errors[] = 'Database connection failed: ' . $res['error'];
        } else {
            $pdo = $res['pdo'];
            $pdo->exec("USE `$name`");

            // Run schema
            $sql = file_get_contents(__DIR__ . '/install.sql');
            foreach (explode(";\n", $sql) as $q) {
                $q = trim($q);
                if ($q) try { $pdo->exec($q); } catch (Exception $e) { /* ignore duplicate errors */ }
            }

            // Set company name
            $pdo->prepare("UPDATE settings SET company_name_en=? WHERE id=1")->execute([$company]);

            // Create admin
            $hash = password_hash($admin_p, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (id,name,email,password,role) VALUES (1,'Admin',?,?,'super_admin') ON DUPLICATE KEY UPDATE email=VALUES(email),password=VALUES(password)")->execute([$admin_e,$hash]);

            // Write config
            $cfg = file_get_contents(__DIR__ . '/config/config.php');
            $cfg = preg_replace("/'localhost'\s*;.*?DB_HOST/", "'$host';     // DB_HOST", $cfg);
            $cfg = str_replace("define('DB_HOST',    'localhost')", "define('DB_HOST',    '$host')", $cfg);
            $cfg = str_replace("define('DB_NAME',    'payroll_db')", "define('DB_NAME',    '$name')", $cfg);
            $cfg = str_replace("define('DB_USER',    'root')", "define('DB_USER',    '$user')", $cfg);
            $cfg = str_replace("define('DB_PASS',    '')", "define('DB_PASS',    '$pass')", $cfg);
            $cfg = str_replace("ini_set('display_errors', 1)", "ini_set('display_errors', 0)", $cfg);
            file_put_contents(__DIR__ . '/config/config.php', $cfg);

            $success = true;
            $_SESSION['install_done'] = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PayrollPro Installer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#0f172a,#1e3a5f);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif}
.install-card{background:#fff;border-radius:20px;overflow:hidden;max-width:520px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.4)}
.install-header{background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;padding:28px 32px}
.install-logo{width:52px;height:52px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:13px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:24px}
.install-body{padding:28px 32px}
.step-bar{display:flex;gap:4px;margin-bottom:24px}
.step-dot{flex:1;height:4px;border-radius:2px;background:#e2e8f0}
.step-dot.done{background:linear-gradient(90deg,#3b82f6,#6366f1)}
.btn-install{background:linear-gradient(135deg,#3b82f6,#6366f1);border:none;border-radius:10px;padding:12px;font-weight:600;font-size:15px}
.check-item{display:flex;align-items:center;gap:8px;padding:6px 0;font-size:14px}
.check-ok{color:#10b981}.check-fail{color:#ef4444}
</style>
</head>
<body>
<div class="install-card">
  <div class="install-header">
    <div class="install-logo"><i class="fas fa-briefcase"></i></div>
    <h2 class="mb-0">PayrollPro Installer</h2>
    <p class="mb-0 opacity-75 small">Bilingual Payroll Management System v1.0</p>
  </div>
  <div class="install-body">
    <div class="step-bar">
      <div class="step-dot done"></div>
      <div class="step-dot <?= $step>=2?'done':'' ?>"></div>
      <div class="step-dot <?= $success?'done':'' ?>"></div>
    </div>

    <?php if ($success): ?>
    <div class="text-center py-3">
      <div style="width:70px;height:70px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px">✅</div>
      <h4 class="text-success fw-700">Installation Complete!</h4>
      <p class="text-muted">PayrollPro is ready to use.</p>
      <a href="index.php" class="btn btn-install btn-primary text-white w-100 mt-2">
        <i class="fas fa-right-to-bracket me-2"></i>Go to Login
      </a>
      <p class="text-muted small mt-3"><i class="fas fa-triangle-exclamation text-warning me-1"></i>Delete <code>install.php</code> from your server for security.</p>
    </div>
    <?php elseif ($step === 1): ?>
    <h5 class="fw-700 mb-3">System Requirements Check</h5>
    <?php
    $checks = [
        'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'ZipArchive (Excel export)' => class_exists('ZipArchive'),
        'GD Image'  => extension_loaded('gd'),
        'mbstring'  => extension_loaded('mbstring'),
        'Writable: uploads/' => is_writable(__DIR__.'/uploads'),
        'Writable: cache/'  => is_writable(__DIR__.'/cache'),
    ];
    $allOk = true;
    foreach ($checks as $label => $ok):
        if (!$ok) $allOk = false;
    ?>
    <div class="check-item">
      <i class="fas <?= $ok ? 'fa-circle-check check-ok' : 'fa-circle-xmark check-fail' ?>"></i>
      <span><?= $label ?></span>
      <span class="ms-auto <?= $ok ? 'text-success' : 'text-danger' ?> fw-600 small"><?= $ok ? 'OK' : 'MISSING' ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (!is_writable(__DIR__.'/uploads')): ?>
    <div class="alert alert-warning mt-3 small p-2">Run: <code>chmod -R 775 uploads cache</code></div>
    <?php endif; ?>
    <a href="?step=2" class="btn btn-install btn-primary text-white w-100 mt-4">
      <i class="fas fa-arrow-right me-2"></i>Continue to Setup
    </a>

    <?php elseif ($step === 2): ?>
    <h5 class="fw-700 mb-3">Database & Admin Setup</h5>
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <div class="mb-3"><label class="form-label fw-500">Company Name</label><input type="text" class="form-control" name="company" value="PayrollPro" required></div>
      <hr class="my-3">
      <p class="fw-600 small text-muted text-uppercase mb-2">Database Settings</p>
      <div class="row g-2 mb-2">
        <div class="col-8"><label class="form-label small">Host</label><input type="text" class="form-control form-control-sm" name="db_host" value="localhost"></div>
        <div class="col-4"><label class="form-label small">Database</label><input type="text" class="form-control form-control-sm" name="db_name" value="payroll_db"></div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6"><label class="form-label small">Username</label><input type="text" class="form-control form-control-sm" name="db_user" value="root"></div>
        <div class="col-6"><label class="form-label small">Password</label><input type="password" class="form-control form-control-sm" name="db_pass" placeholder="Leave blank for XAMPP"></div>
      </div>
      <hr class="my-3">
      <p class="fw-600 small text-muted text-uppercase mb-2">Admin Account</p>
      <div class="mb-2"><label class="form-label small">Admin Email</label><input type="email" class="form-control form-control-sm" name="admin_email" value="admin@payroll.local" required></div>
      <div class="mb-3"><label class="form-label small">Admin Password</label><input type="password" class="form-control form-control-sm" name="admin_pass" placeholder="Min 6 characters" required></div>
      <button type="submit" class="btn btn-install btn-primary text-white w-100">
        <i class="fas fa-database me-2"></i>Install PayrollPro
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
