<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/meal_template_library.php';

if (isLoggedIn()) {
    $type = $_SESSION['user_type'] ?? 'user';
    $redirect = $type === 'admin'
        ? '/admin/dashboard.php'
        : ($type === 'dietitian' ? '/dietitian/dashboard.php' : '/user/dashboard.php');
    header('Location: ' . SITE_URL . $redirect);
    exit;
}

$pdo = Database::getInstance()->getConnection();

$roleToTable = [
    'user' => 'users',
    'dietitian' => 'dietitians',
    'admin' => 'admins',
];

$activeTab = 'user';
$prefillEmail = '';
$prefillRemember = false;
$flashMessage = $_SESSION['flash_success'] ?? '';
if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $flashMessage = 'You have been logged out successfully.';
}
if (isset($_GET['pending']) && $_GET['pending'] === '1') {
    $flashMessage = 'Dietitian registration submitted. Please wait for admin approval.';
    $activeTab = 'dietitian';
}
unset($_SESSION['flash_success']);

if (!empty($_COOKIE['HM_REMEMBER'])) {
    $decoded = json_decode((string) $_COOKIE['HM_REMEMBER'], true);
    if (is_array($decoded)) {
        $activeTab = in_array(($decoded['role'] ?? ''), ['user', 'dietitian', 'admin'], true) ? $decoded['role'] : 'user';
        $prefillEmail = (string) ($decoded['email'] ?? '');
        $prefillRemember = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $role = strtolower(trim((string) ($_POST['role'] ?? 'user')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    if (!isset($roleToTable[$role])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid login role selected.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email.']);
        exit;
    }

    if ($password === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }

    $attemptKey = $role . '|' . $email;
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $attempts = (int) ($_SESSION['login_attempts'][$attemptKey] ?? 0);

    if ($attempts >= 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please try later.',
            'remaining_attempts' => 0,
        ]);
        exit;
    }

    $table = $roleToTable[$role];
    $query = $role === 'admin'
        ? "SELECT id, full_name, email, password FROM {$table} WHERE email = :email LIMIT 1"
        : "SELECT id, full_name, email, password, status FROM {$table} WHERE email = :email LIMIT 1";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        $isValidPassword = false;
        if ($user) {
            $storedPassword = (string) $user['password'];
            $isHash = str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2');
            $isValidPassword = $isHash ? password_verify($password, $storedPassword) : hash_equals($storedPassword, $password);
        }

        if ($user && $isValidPassword) {
            if ($role !== 'admin') {
                $status = strtolower((string) ($user['status'] ?? 'inactive'));
                if ($status !== 'active') {
                    if ($role === 'dietitian' && $status === 'pending') {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your dietitian account is pending admin approval.',
                            'remaining_attempts' => 5 - $attempts,
                        ]);
                        exit;
                    }
                    if ($role === 'dietitian' && $status === 'inactive') {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your dietitian account is inactive. Please contact admin.',
                            'remaining_attempts' => 5 - $attempts,
                        ]);
                        exit;
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'Your account is not active yet.',
                        'remaining_attempts' => 5 - $attempts,
                    ]);
                    exit;
                }
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_type'] = $role;
            $_SESSION['user_name'] = (string) $user['full_name'];
            $_SESSION['user_email'] = (string) $user['email'];
            $_SESSION['full_name'] = (string) $user['full_name'];

            if ($role === 'dietitian') {
                ensureDietitianStarterTemplates($pdo, (int) $user['id']);
            }

            unset($_SESSION['login_attempts'][$attemptKey]);

            if ($remember) {
                $cookieData = json_encode(['role' => $role, 'email' => $email], JSON_UNESCAPED_SLASHES);
                setcookie('HM_REMEMBER', (string) $cookieData, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('HM_REMEMBER', '', time() - 3600, '/');
            }

            logActivity((int) $user['id'], $role, 'Logged in');

            $redirect = $role === 'admin'
                ? SITE_URL . '/admin/dashboard.php'
                : ($role === 'dietitian' ? SITE_URL . '/dietitian/dashboard.php' : SITE_URL . '/user/dashboard.php');

            echo json_encode(['success' => true, 'redirect' => $redirect]);
            exit;
        }

        $attempts++;
        $_SESSION['login_attempts'][$attemptKey] = $attempts;
        $remaining = max(0, 5 - $attempts);

        echo json_encode([
            'success' => false,
            'message' => $remaining > 0 ? "Invalid credentials. Remaining attempts: {$remaining}" : 'Maximum attempts reached.',
            'remaining_attempts' => $remaining,
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Login request failed. Please try again.',
        ]);
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HealthMatrix</title>
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
            <h2>Welcome back</h2>
            <p>Track your nutrition, talk to your dietitian, and reach your goals with confidence.</p>
        </div>
        <div class="auth-quick-links">
            <p class="mb-0">New here? <a class="text-light text-decoration-underline" href="<?= SITE_URL ?>/auth/register.php">Create your free account now</a></p>
            <p class="mb-0">Dietitian? <a class="text-light text-decoration-underline" href="<?= SITE_URL ?>/auth/register_dietitian.php">Register As Dietitian</a></p>
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
                    <?php if ($flashMessage): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <div id="loginAlertArea"></div>

                    <ul class="nav nav-pills mb-3" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $activeTab === 'user' ? 'active' : '' ?>" data-role="user" type="button">User Login</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $activeTab === 'dietitian' ? 'active' : '' ?>" data-role="dietitian" type="button">Dietitian Login</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $activeTab === 'admin' ? 'active' : '' ?>" data-role="admin" type="button">Admin Login</button>
                        </li>
                    </ul>

                    <form id="loginForm" novalidate>
                        <input type="hidden" name="role" id="role" value="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email</label>
                            <input type="email" class="form-control" name="email" id="login_email" value="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8') ?>" required>
                            <small class="text-danger field-error" data-error-for="login_email"></small>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="login_password" required>
                                <button type="button" class="btn btn-outline-secondary" id="toggleLoginPassword">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-danger field-error" data-error-for="login_password"></small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember" value="1" <?= $prefillRemember ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remember_me">Remember me</label>
                            </div>
                            <a href="<?= SITE_URL ?>/auth/forgot_password.php">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="loginSubmitBtn">
                            <span id="loginBtnText"><i class="fa-solid fa-right-to-bracket me-1"></i>Login</span>
                            <span id="loginBtnLoading" class="d-none"><span class="spinner-border spinner-border-sm me-1"></span>Logging in...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/validation.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('#loginTabs .nav-link');
    const roleInput = document.getElementById('role');
    const loginForm = document.getElementById('loginForm');
    const alertArea = document.getElementById('loginAlertArea');
    const submitBtn = document.getElementById('loginSubmitBtn');
    const btnText = document.getElementById('loginBtnText');
    const btnLoading = document.getElementById('loginBtnLoading');
    const togglePasswordBtn = document.getElementById('toggleLoginPassword');
    const passwordInput = document.getElementById('login_password');

    tabs.forEach(btn => {
        btn.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            roleInput.value = this.dataset.role;
        });
    });

    togglePasswordBtn.addEventListener('click', function () {
        const icon = this.querySelector('i');
        const visible = passwordInput.type === 'text';
        passwordInput.type = visible ? 'password' : 'text';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        alertArea.innerHTML = '';

        const email = document.getElementById('login_email').value.trim();
        const password = passwordInput.value;

        if (!HMValidation.validateEmail(email)) {
            alertArea.innerHTML = '<div class="alert alert-danger">Please enter a valid email.</div>';
            return;
        }
        if (!password) {
            alertArea.innerHTML = '<div class="alert alert-danger">Password is required.</div>';
            return;
        }

        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');

        const formData = new FormData(loginForm);
        try {
            const res = await fetch('<?= SITE_URL ?>/auth/login.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = data.redirect;
                return;
            }

            alertArea.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Login failed') + '</div>';
        } catch (err) {
            alertArea.innerHTML = '<div class="alert alert-danger">Could not connect to server.</div>';
        } finally {
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
        }
    });
});
</script>
</body>
</html>
