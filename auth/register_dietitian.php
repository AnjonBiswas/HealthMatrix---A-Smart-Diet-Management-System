<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $type = $_SESSION['user_type'] ?? 'user';
    $redirect = $type === 'admin'
        ? '/admin/dashboard.php'
        : ($type === 'dietitian' ? '/dietitian/dashboard.php' : '/user/dashboard.php');
    header('Location: ' . SITE_URL . $redirect);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $specialization = sanitizeInput($_POST['specialization'] ?? '');
    $experienceYears = (int) ($_POST['experience_years'] ?? 0);
    $registrationNumber = strtoupper(trim((string) ($_POST['registration_number'] ?? '')));
    $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === '1';

    $old = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'specialization' => $specialization,
        'experience_years' => (string) $experienceYears,
        'registration_number' => $registrationNumber,
    ];

    if (mb_strlen($fullName) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
        $errors[] = 'Password must be 8+ chars with uppercase, lowercase, number and symbol.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Phone number must be 7-15 digits (optional + allowed).';
    }

    if (mb_strlen($specialization) < 2) {
        $errors[] = 'Please enter your specialization.';
    }

    if ($experienceYears < 0 || $experienceYears > 60) {
        $errors[] = 'Experience must be between 0 and 60 years.';
    }

    if ($registrationNumber === '' || mb_strlen($registrationNumber) < 3 || mb_strlen($registrationNumber) > 60) {
        $errors[] = 'Registration number is required (3-60 characters).';
    }

    if (!$termsAccepted) {
        $errors[] = 'You must agree to the terms and approval process.';
    }

    $stmtDietitian = $pdo->prepare('SELECT id FROM dietitians WHERE email = :email LIMIT 1');
    $stmtDietitian->execute([':email' => $email]);
    if ($stmtDietitian->fetchColumn()) {
        $errors[] = 'This email is already registered as a dietitian.';
    }

    $stmtUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtUser->execute([':email' => $email]);
    if ($stmtUser->fetchColumn()) {
        $errors[] = 'This email is already registered as a user.';
    }

    $stmtAdmin = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
    $stmtAdmin->execute([':email' => $email]);
    if ($stmtAdmin->fetchColumn()) {
        $errors[] = 'This email is reserved for admin account use.';
    }

    if (empty($errors)) {
        try {
            $hasRegistrationColumn = false;
            $colStmt = $pdo->query("SHOW COLUMNS FROM dietitians LIKE 'registration_number'");
            if ($colStmt && $colStmt->fetch()) {
                $hasRegistrationColumn = true;
            }

            if ($hasRegistrationColumn) {
                $stmt = $pdo->prepare(
                    'INSERT INTO dietitians
                    (full_name, email, password, phone, specialization, experience_years, registration_number, bio, status, created_at)
                    VALUES
                    (:full_name, :email, :password, :phone, :specialization, :experience_years, :registration_number, "", "pending", NOW())'
                );

                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':phone' => $phone,
                    ':specialization' => $specialization,
                    ':experience_years' => $experienceYears,
                    ':registration_number' => $registrationNumber,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO dietitians
                    (full_name, email, password, phone, specialization, experience_years, bio, status, created_at)
                    VALUES
                    (:full_name, :email, :password, :phone, :specialization, :experience_years, :bio, "pending", NOW())'
                );

                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':phone' => $phone,
                    ':specialization' => $specialization,
                    ':experience_years' => $experienceYears,
                    ':bio' => 'Registration No: ' . $registrationNumber,
                ]);
            }

            $dietitianId = (int) $pdo->lastInsertId();
            logActivity($dietitianId, 'dietitian', 'Submitted dietitian registration request');

            $_SESSION['flash_success'] = 'Dietitian registration submitted successfully. Please wait for admin approval.';
            header('Location: ' . SITE_URL . '/auth/login.php?role=dietitian&pending=1');
            exit;
        } catch (Throwable) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dietitian Registration | HealthMatrix</title>
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
            <h2>Join as a Dietitian</h2>
            <p>Submit your professional profile. Admin will review and approve your account before access is enabled.</p>
        </div>
        <p class="mb-0">Already registered? <a class="text-light text-decoration-underline" href="<?= SITE_URL ?>/auth/login.php">Login here</a></p>
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
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-user me-1"></i>Full Name</label>
                            <input class="form-control" type="text" name="full_name" value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email</label>
                            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="field-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Password</label>
                                <input class="form-control" type="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Confirm Password</label>
                                <input class="form-control" type="password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-phone me-1"></i>Phone</label>
                                <input class="form-control" type="text" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-stethoscope me-1"></i>Specialization</label>
                                <input class="form-control" type="text" name="specialization" value="<?= htmlspecialchars($old['specialization'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-award me-1"></i>Experience (Years)</label>
                            <input class="form-control" type="number" min="0" max="60" name="experience_years" value="<?= htmlspecialchars($old['experience_years'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-id-card me-1"></i>Registration Number*</label>
                            <input class="form-control" type="text" name="registration_number" value="<?= htmlspecialchars($old['registration_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">I understand my account will remain pending until approved by admin.</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fa-solid fa-user-check me-1"></i>Submit Dietitian Registration
                        </button>
                    </form>

                    <p class="mt-3 mb-0 text-center">
                        <a href="<?= SITE_URL ?>/auth/login.php">Back to login</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>
</body>
</html>
