<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? 'user');

if ($userId > 0) {
    logActivity($userId, $userType, 'Logged out');
}

setcookie('HM_REMEMBER', '', time() - 3600, '/');

if (isLoggedIn()) {
    logoutUser();
}

header('Location: ' . SITE_URL . '/auth/login.php?logged_out=1');
exit;
