<?php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    if (!isset($_SESSION['user_data'])) {
        $_SESSION['user_data'] = DB::row("SELECT id,name,email,role,lang FROM users WHERE id=? AND is_active=1", [$_SESSION['user_id']]);
    }
    return $_SESSION['user_data'];
}

function hasRole(string ...$roles): bool {
    $user = currentUser();
    return $user && in_array($user['role'], $roles);
}

function requireRole(string ...$roles): void {
    if (!hasRole(...$roles)) {
        setFlash('error', 'You do not have permission to perform this action.');
        header('Location: index.php?page=dashboard');
        exit;
    }
}

function lang(): string {
    return $_SESSION['lang'] ?? 'en';
}

function t(string $key): string {
    static $translations = [];
    $l = lang();
    if (empty($translations[$l])) {
        $file = APP_DIR . "/config/$l.php";
        $translations[$l] = file_exists($file) ? require $file : [];
    }
    return $translations[$l][$key] ?? $key;
}

function formatT(string $key, array $params = []): string {
    $text = t($key);
    foreach ($params as $k => $v) {
        $text = str_replace('{' . $k . '}', $v, $text);
    }
    return $text;
}

function isRtl(): bool { return lang() === 'ar'; }

function toggleLang(): void {
    $_SESSION['lang'] = lang() === 'en' ? 'ar' : 'en';
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = compact('type', 'msg');
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
