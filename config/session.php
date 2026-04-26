<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function normalizeSessionPath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    // session.save_path can include the format "N;/path/to/sessions".
    if (str_contains($path, ';')) {
        $parts = explode(';', $path);
        return trim((string) end($parts));
    }

    return $path;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    $savePath = normalizeSessionPath((string) ini_get('session.save_path'));
    if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
        $fallbackPath = BASE_PATH . '/tmp/sessions';
        if (!is_dir($fallbackPath)) {
            mkdir($fallbackPath, 0775, true);
        }
        if (is_dir($fallbackPath) && is_writable($fallbackPath)) {
            session_save_path($fallbackPath);
        }
    }

    session_start();

    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = time();
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_type']);
}

function getUserType(): ?string
{
    return $_SESSION['user_type'] ?? null;
}

function getUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function loginUser(int $userId, string $userType, string $fullName = ''): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_type'] = $userType;
    $_SESSION['full_name'] = $fullName;
    $_SESSION['last_activity'] = time();
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function hasRole(array|string $roles): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $roles = is_array($roles) ? $roles : [$roles];
    return in_array((string) getUserType(), $roles, true);
}

function requireRole(array|string $roles): void
{
    if (!hasRole($roles)) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

function redirectIfNotLoggedIn(array $allowedRoles = []): void
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }

    if (!empty($allowedRoles) && !hasRole($allowedRoles)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
        exit;
    }
}
