<?php
$rtl = isRtl();
$dir = $rtl ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login – PayrollPro</title>
<?php if($rtl): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.login-page { min-height:100vh; display:flex; align-items:center; justify-content:center;
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%); }
.login-card { background:#fff; border-radius:20px; padding:2.5rem; width:100%; max-width:420px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.4); }
.login-logo { width:64px; height:64px; background:linear-gradient(135deg,#3b82f6,#6366f1);
  border-radius:16px; display:flex; align-items:center; justify-content:center;
  margin:0 auto 1rem; box-shadow:0 8px 20px rgba(99,102,241,0.4); }
.login-logo i { font-size:28px; color:#fff; }
.login-title { font-size:1.6rem; font-weight:700; color:#0f172a; text-align:center; margin-bottom:.25rem; }
.login-sub { text-align:center; color:#64748b; font-size:.9rem; margin-bottom:1.75rem; }
.form-floating label { color:#64748b; }
.btn-login { background:linear-gradient(135deg,#3b82f6,#6366f1); border:none; border-radius:10px;
  padding:.75rem; font-weight:600; font-size:1rem; letter-spacing:.3px;
  transition:all .3s; box-shadow:0 4px 15px rgba(99,102,241,0.3); }
.btn-login:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(99,102,241,0.4); }
.lang-toggle-login { position:absolute; top:1.5rem; right:1.5rem; }
[dir=rtl] .lang-toggle-login { right:auto; left:1.5rem; }
.demo-info { background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px;
  padding:.75rem 1rem; font-size:.82rem; color:#0369a1; margin-top:1rem; }
</style>
</head>
<body style="font-family: <?= $rtl ? "'Cairo'" : "'Inter'" ?>, sans-serif;">
<div class="login-page position-relative">
  <a href="index.php?action=toggle_lang&page=login" class="btn btn-sm btn-outline-light lang-toggle-login">
    <?= lang() === 'en' ? '🇸🇦 عربي' : '🇬🇧 English' ?>
  </a>
  <div class="login-card">
    <div class="login-logo"><i class="fas fa-briefcase"></i></div>
    <h1 class="login-title">PayrollPro</h1>
    <p class="login-sub"><?= $rtl ? 'نظام إدارة الرواتب المتكامل' : 'Complete Payroll Management System' ?></p>

    <?php if (!empty($loginError)): ?>
    <div class="alert alert-danger alert-sm mb-3">
      <i class="fas fa-circle-exclamation me-2"></i><?= h($loginError) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login">
      <div class="form-floating mb-3">
        <input type="email" class="form-control" id="email" name="email" placeholder="email"
               value="<?= h(post('email')) ?>" required>
        <label for="email"><i class="fas fa-envelope me-2"></i><?= $rtl ? 'البريد الإلكتروني' : 'Email Address' ?></label>
      </div>
      <div class="form-floating mb-3">
        <input type="password" class="form-control" id="password" name="password" placeholder="password" required>
        <label for="password"><i class="fas fa-lock me-2"></i><?= $rtl ? 'كلمة المرور' : 'Password' ?></label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="remember">
        <label class="form-check-label text-muted small" for="remember">
          <?= $rtl ? 'تذكرني' : 'Remember me' ?>
        </label>
      </div>
      <button type="submit" class="btn btn-login btn-primary w-100 text-white">
        <i class="fas fa-right-to-bracket me-2"></i>
        <?= $rtl ? 'تسجيل الدخول' : 'Sign In' ?>
      </button>
    </form>
    <div class="demo-info">
      <i class="fas fa-info-circle me-1"></i>
      <?= $rtl ? 'البريد: admin@payroll.local | كلمة المرور: admin123' : 'Email: admin@payroll.local | Password: admin123' ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
