<?php
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSession();

// ── Special actions ───────────────────────────────────────────────────────────
$action = get('action');

if ($action === 'logout') {
    session_destroy();
    redirect('index.php?page=login');
}

if ($action === 'toggle_lang') {
    toggleLang();
    redirect('index.php?page=' . get('page', 'dashboard'));
}

// ── Login page ────────────────────────────────────────────────────────────────
$page = get('page', 'dashboard');

if ($page === 'login') {
    if (isPost()) {
        $email = post('email');
        $pass  = post('password');
        $user  = DB::row("SELECT * FROM users WHERE email=? AND is_active=1", [$email]);
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['lang']      = $user['lang'] ?? 'en';
            $_SESSION['user_data'] = null;
            DB::q("UPDATE users SET last_login=NOW() WHERE id=?", [$user['id']]);
            redirect('index.php?page=dashboard');
        }
        $loginError = 'Invalid email or password.';
    }
    require 'pages/login.php';
    exit;
}

requireLogin();

// ── Export actions (no layout) ────────────────────────────────────────────────
if ($page === 'export') {
    $type = get('type');
    if ($type === 'payslip_pdf') { require 'exports/payslip_pdf.php'; exit; }
    if ($type === 'payroll_excel') { require 'exports/payroll_excel.php'; exit; }
    if ($type === 'employees_excel') { require 'exports/employees_excel.php'; exit; }
    exit('Unknown export type');
}

// ── API / AJAX ────────────────────────────────────────────────────────────────
if ($page === 'api') {
    header('Content-Type: application/json; charset=utf-8');
    $endpoint = get('endpoint');
    require 'pages/api.php';
    exit;
}

// ── Allowed pages ─────────────────────────────────────────────────────────────
$allowed = ['dashboard','employees','job_titles','departments','payroll','attendance','leaves','leave_types','loans','bonuses','gratuity','reports','settings'];
if (!in_array($page, $allowed)) {
    $page = 'dashboard';
}

$pageFile = "pages/{$page}.php";
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = 'pages/dashboard.php';
}

ob_start();
require $pageFile;
$content = ob_get_clean();

require 'includes/layout.php';
