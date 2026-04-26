<?php
declare(strict_types=1);

/**
 * Global application configuration for HealthMatrix.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
}

date_default_timezone_set('Asia/Dhaka');

/**
 * Read an environment variable and return fallback when missing/empty.
 */
function envValue(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    if ($value === false) {
        return $fallback;
    }

    $value = trim((string) $value);
    return $value === '' ? $fallback : $value;
}

function detectSiteUrl(): string
{
    $configured = envValue('SITE_URL');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'http://localhost/diet_system';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $basePathReal = realpath(dirname(__DIR__));

    $path = '/diet_system';
    if ($documentRoot && $basePathReal && str_starts_with($basePathReal, $documentRoot)) {
        $relative = trim(str_replace('\\', '/', substr($basePathReal, strlen($documentRoot))), '/');
        $path = $relative === '' ? '' : '/' . $relative;
    }

    return rtrim(sprintf('%s://%s%s', $scheme, $host, $path), '/');
}

// ---------------------------------------------------------------------
// App
// ---------------------------------------------------------------------
define('SITE_NAME', 'HealthMatrix');
define('SITE_URL', detectSiteUrl());
define('BASE_PATH', dirname(__DIR__));

// ---------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------
define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_PORT', (int) envValue('DB_PORT', '3306'));
define('DB_NAME', envValue('DB_NAME', 'diet_system'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]);

// ---------------------------------------------------------------------
// Harris-Benedict constants
// BMR (male)   = 88.362 + (13.397 * weight_kg) + (4.799 * height_cm) - (5.677 * age)
// BMR (female) = 447.593 + (9.247 * weight_kg) + (3.098 * height_cm) - (4.330 * age)
// ---------------------------------------------------------------------
define('HB_MALE_BASE', 88.362);
define('HB_MALE_WEIGHT', 13.397);
define('HB_MALE_HEIGHT', 4.799);
define('HB_MALE_AGE', 5.677);

define('HB_FEMALE_BASE', 447.593);
define('HB_FEMALE_WEIGHT', 9.247);
define('HB_FEMALE_HEIGHT', 3.098);
define('HB_FEMALE_AGE', 4.330);

define('ACTIVITY_MULTIPLIERS', [
    'sedentary' => 1.2,
    'lightly_active' => 1.375,
    'moderately_active' => 1.55,
    'very_active' => 1.725,
    'extra_active' => 1.9,
]);

define('GOAL_CALORIE_ADJUSTMENT', [
    'weight_loss' => -500,
    'maintain' => 0,
    'gain' => 400,
]);

// ---------------------------------------------------------------------
// Security/session defaults
// ---------------------------------------------------------------------
define('SESSION_NAME', 'healthmatrix_session');
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 hours
define('CSRF_TOKEN_KEY', '_csrf_token');
