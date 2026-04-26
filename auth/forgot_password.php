<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = Database::getInstance()->getConnection();
$message = '';
$error = '';

$roleToTable = [
    'user' => 'users',
    'dietitian' => 'dietitians',
    'admin' => 'admins',
];

if (!isset($_SESSION['password_reset'])) {
    $_SESSION['password_reset'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'request_token') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = strtolower(trim((string) ($_POST['role'] ?? 'user')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email.';
        } elseif (!isset($roleToTable[$role])) {
            $error = 'Invalid account type selected.';
        } else {
            $table = $roleToTable[$role];
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM {$table} WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $account = $stmt->fetch();

            if (!$account) {
                $error = 'No account found with this email.';
            } else {
                $token = (string) random_int(100000, 999999);
                $_SESSION['password_reset'] = [
                    'table' => $table,
                    'role' => $role,
                    'user_id' => (int) $account['id'],
                    'email' => (string) $account['email'],
                    'token' => $token,
                    'verified' => false,
                    'expires_at' => time() + 900,
                ];
                $message = 'Demo token generated: <strong>' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</strong> (valid for 15 minutes)';
            }
        }
    }

    if ($action === 'verify_token') {
        $tokenInput = trim((string) ($_POST['token'] ?? ''));
        $sessionReset = $_SESSION['password_reset'] ?? [];

        if (empty($sessionReset['token'])) {
            $error = 'Please generate a reset token first.';
        } elseif (time() > (int) $sessionReset['expires_at']) {
            $_SESSION['password_reset'] = [];
            $error = 'Token has expired. Please request a new one.';
        } elseif (!hash_equals((string) $sessionReset['token'], $tokenInput)) {
            $error = 'Invalid token. Please check and try again.';
        } else {
            $_SESSION['password_reset']['verified'] = true;
            $message = 'Token verified. You can now set a new password.';
        }
    }

    if ($action === 'reset_password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $sessionReset = $_SESSION['password_reset'] ?? [];

        if (empty($sessionReset) || empty($sessionReset['verified'])) {
            $error = 'Please verify the token before resetting password.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $newPassword)) {
            $error = 'Password must be 8+ chars with uppercase, lowercase, number and symbol.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $table = (string) $sessionReset['table'];
            $userId = (int) $sessionReset['user_id'];
            $role = (string) $sessionReset['role'];

            try {
                $stmt = $pdo->prepare("UPDATE {$table} SET password = :password WHERE id = :id");
                $stmt->execute([
                    ':password' => $passwordHash,
                    ':id' => $userId,
                ]);

                logActivity($userId, $role, 'Password reset');
                $_SESSION['flash_success'] = 'Password has been reset successfully. Please login.';
                $_SESSION['password_reset'] = [];

                header('Location: ' . SITE_URL . '/auth/login.php?reset=1');
                exit;
            } catch (Throwable $e) {
                $error = 'Could not reset password. Please try again.';
            }
        }
    }
}

$reset = $_SESSION['password_reset'] ?? [];
$tokenGenerated = !empty($reset['token']);
$tokenVerified = !empty($reset['verified']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="auth-container">
    <aside class="auth-visual">
        <div class="auth-fall-layer" aria-hidden="true">
            <i class="fa-solid fa-carrot food-item"></i>
            <i class="fa-solid fa-burger food-item"></i>
            <i class="fa-solid fa-pizza-slice food-item"></i>
            <i class="fa-solid fa-mug-hot food-item"></i>
            <i class="fa-solid fa-apple-whole food-item"></i>
            <i class="fa-solid fa-seedling food-item"></i>
            <i class="fa-solid fa-lemon food-item"></i>
            <i class="fa-solid fa-ice-cream food-item"></i>
            <i class="fa-solid fa-pepper-hot food-item"></i>
            <i class="fa-solid fa-cookie food-item"></i>
        </div>
        <div>
            <a href="<?= SITE_URL ?>" class="logo-home-link" aria-label="Go to HealthMatrix landing page">
                <img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="logo mb-3">
            </a>
            <h2>Recover your account</h2>
            <p>Generate a demo reset token, verify it, and set a fresh password securely.</p>
        </div>
    </aside>
    <section class="auth-panel">
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-logo-area">
                    <a href="<?= SITE_URL ?>" class="logo-home-link" aria-label="Go to HealthMatrix landing page">
                        <img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="auth-brand-logo">
                    </a>
                </div>
                <div class="auth-card-body">
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    <?php if ($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>

                    <div class="card mb-3">
                        <div class="card-header">1. Request Token</div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="request_token">
                                <div class="mb-3">
                                    <label class="form-label">Account Type</label>
                                    <select class="form-control" name="role" required>
                                        <option value="user">User</option>
                                        <option value="dietitian">Dietitian</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane me-1"></i>Generate Token</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">2. Verify Token</div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="verify_token">
                                <div class="mb-3">
                                    <label class="form-label">Token</label>
                                    <input type="text" class="form-control" name="token" maxlength="6" required <?= $tokenGenerated ? '' : 'disabled' ?>>
                                </div>
                                <button class="btn btn-secondary" type="submit" <?= $tokenGenerated ? '' : 'disabled' ?>><i class="fa-solid fa-shield-halved me-1"></i>Verify</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">3. Set New Password</div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="reset_password">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required <?= $tokenVerified ? '' : 'disabled' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required <?= $tokenVerified ? '' : 'disabled' ?>>
                                </div>
                                <button class="btn btn-success" type="submit" <?= $tokenVerified ? '' : 'disabled' ?>><i class="fa-solid fa-check me-1"></i>Reset Password</button>
                            </form>
                        </div>
                    </div>

                    <p class="mt-3 mb-0"><a href="<?= SITE_URL ?>/auth/login.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to login</a></p>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
