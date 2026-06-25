<?php
// ─── PayrollPro Configuration ─────────────────────────────────────────────────
// Edit these settings before first use

define('APP_NAME',    'PayrollPro');
define('APP_VERSION', '1.0.0');
define('APP_DIR',     dirname(__DIR__));
define('UPLOAD_DIR',  APP_DIR . '/uploads/');
define('CACHE_DIR',   APP_DIR . '/cache/');

// ── Database (XAMPP defaults: host=localhost, user=root, pass='') ─────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'payroll_db');
define('DB_USER',    'root');
define('DB_PASS',    '');          // XAMPP default is empty — change for production
define('DB_CHARSET', 'utf8mb4');

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);  // 2 hours
define('SESSION_NAME',     'payroll_session');

// ── App settings ──────────────────────────────────────────────────────────────
define('PER_PAGE',       25);
define('MAX_FILE_SIZE',  5 * 1024 * 1024);   // 5 MB
define('ALLOWED_IMG',    ['jpg','jpeg','png','gif','webp']);

// ── Timezone (change to your timezone) ───────────────────────────────────────
date_default_timezone_set('Asia/Kuwait');

// ── Error reporting (set display_errors=0 in production) ─────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 0);      // Set to 0 on production server
ini_set('log_errors', 1);
ini_set('error_log', APP_DIR . '/cache/error.log');
