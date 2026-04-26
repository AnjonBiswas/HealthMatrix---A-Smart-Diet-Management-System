<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$errors = [];
$old = [];

$allowedActivities = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'];
$allowedGoals = ['weight_loss', 'maintain', 'gain'];
$allowedGenders = ['male', 'female', 'other'];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_email') {
    header('Content-Type: application/json; charset=utf-8');
    $email = trim((string) ($_GET['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['available' => false, 'message' => 'Invalid email format']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $exists = (bool) $stmt->fetchColumn();

    echo json_encode([
        'available' => !$exists,
        'message' => $exists ? 'Email already in use' : 'Email is available',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $gender = strtolower((string) ($_POST['gender'] ?? ''));
    $weight = (float) ($_POST['weight'] ?? 0);
    $height = (float) ($_POST['height'] ?? 0);
    $activityLevel = strtolower((string) ($_POST['activity_level'] ?? ''));
    $goal = strtolower((string) ($_POST['goal'] ?? ''));
    $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === '1';

    $old = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'age' => (string) $age,
        'gender' => $gender,
        'weight' => (string) $weight,
        'height' => (string) $height,
        'activity_level' => $activityLevel,
        'goal' => $goal,
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

    if ($age < 13 || $age > 100) {
        $errors[] = 'Age must be between 13 and 100.';
    }

    if (!in_array($gender, $allowedGenders, true)) {
        $errors[] = 'Please select a valid gender.';
    }

    if ($weight < 20 || $weight > 350) {
        $errors[] = 'Weight must be between 20 and 350 kg.';
    }

    if ($height < 90 || $height > 250) {
        $errors[] = 'Height must be between 90 and 250 cm.';
    }

    if (!in_array($activityLevel, $allowedActivities, true)) {
        $errors[] = 'Please select a valid activity level.';
    }

    if (!in_array($goal, $allowedGoals, true)) {
        $errors[] = 'Please select a valid goal.';
    }

    if (!$termsAccepted) {
        $errors[] = 'You must agree to the terms and conditions.';
    }

    $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetchColumn()) {
        $errors[] = 'This email is already registered.';
    }

    $profilePic = null;
    if (!empty($_FILES['profile_pic']['name'])) {
        $upload = uploadImage($_FILES['profile_pic'], 'users');
        if (!$upload['success']) {
            $errors[] = $upload['error'];
        } else {
            $profilePic = (string) $upload['path'];
        }
    }

    if (empty($errors)) {
        $bmi = calculateBMI($weight, $height);
        $dailyCalories = calculateDailyCalories($age, $weight, $height, $gender, $activityLevel, $goal);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users
                (full_name, email, password, phone, profile_pic, age, weight, height, gender, activity_level, goal, bmi, daily_calorie_goal, status, created_at, updated_at)
                VALUES
                (:full_name, :email, :password, :phone, :profile_pic, :age, :weight, :height, :gender, :activity_level, :goal, :bmi, :daily_calorie_goal, :status, NOW(), NOW())'
            );

            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':password' => $passwordHash,
                ':phone' => $phone,
                ':profile_pic' => $profilePic,
                ':age' => $age,
                ':weight' => $weight,
                ':height' => $height,
                ':gender' => $gender,
                ':activity_level' => $activityLevel,
                ':goal' => $goal,
                ':bmi' => $bmi,
                ':daily_calorie_goal' => $dailyCalories,
                ':status' => 'active',
            ]);

            $newUserId = (int) $pdo->lastInsertId();
            $_SESSION['flash_success'] = 'Registration successful. Welcome to HealthMatrix.';

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_type'] = 'user';
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;

            logActivity($newUserId, 'user', 'Registered new account');

            header('Location: ' . SITE_URL . '/user/dashboard.php?registered=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | HealthMatrix</title>
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
            <h2>Create your healthy journey</h2>
            <p>Receive personalized nutrition and diet recommendations tailored to your goals.</p>
        </div>
        <p class="mb-0">Already have an account? <a class="text-light text-decoration-underline" href="<?= SITE_URL ?>/auth/login.php">Login now</a></p>
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

                    <div class="mb-3">
                        <div class="progress" role="progressbar" aria-label="Registration steps progress">
                            <div class="progress-bar" id="registerProgressBar" style="width: 33%"></div>
                        </div>
                        <small class="text-muted">Step <span id="currentStepLabel">1</span> of 3</small>
                    </div>

                    <form id="registerForm" method="post" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="current_step" id="current_step" value="1">

                        <div class="step-pane" data-step="1">
                            <h5 class="mb-3">Account Information</h5>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-user me-1"></i>Full Name</label>
                                <input class="form-control" type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                <small class="text-danger field-error" data-error-for="full_name"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-envelope me-1"></i>Email</label>
                                <input class="form-control" type="email" name="email" id="email" value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                <small id="emailCheckResult" class="d-block mt-1"></small>
                                <small class="text-danger field-error" data-error-for="email"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Password</label>
                                <input class="form-control" type="password" name="password" id="password" required>
                                <div class="progress mt-2" style="height: 8px;">
                                    <div id="passwordStrengthBar" class="progress-bar" style="width: 0%"></div>
                                </div>
                                <small id="passwordStrengthText" class="text-muted">Password strength: N/A</small>
                                <small class="text-danger field-error" data-error-for="password"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-lock me-1"></i>Confirm Password</label>
                                <input class="form-control" type="password" name="confirm_password" id="confirm_password" required>
                                <small class="text-danger field-error" data-error-for="confirm_password"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-phone me-1"></i>Phone</label>
                                <input class="form-control" type="text" name="phone" id="phone" value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                <small class="text-danger field-error" data-error-for="phone"></small>
                            </div>
                        </div>

                        <div class="step-pane d-none" data-step="2">
                            <h5 class="mb-3">Personal Details</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Age</label>
                                    <input class="form-control" type="number" name="age" id="age" min="13" max="100" value="<?= htmlspecialchars($old['age'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                    <small class="text-danger field-error" data-error-for="age"></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block"><i class="fa-solid fa-venus-mars me-1"></i>Gender</label>
                                    <div class="d-flex gap-3 mt-2">
                                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $key => $label): ?>
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input me-1" name="gender" value="<?= $key ?>" <?= (($old['gender'] ?? '') === $key) ? 'checked' : '' ?> required>
                                                <?= $label ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-danger field-error" data-error-for="gender"></small>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fa-solid fa-weight-scale me-1"></i>Weight (kg)</label>
                                    <input class="form-control" type="number" step="0.1" name="weight" id="weight" value="<?= htmlspecialchars($old['weight'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                    <small class="text-danger field-error" data-error-for="weight"></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fa-solid fa-ruler-vertical me-1"></i>Height (cm)</label>
                                    <input class="form-control" type="number" step="0.1" name="height" id="height" value="<?= htmlspecialchars($old['height'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                    <small class="text-danger field-error" data-error-for="height"></small>
                                </div>
                            </div>
                            <div class="form-group mt-3">
                                <label class="form-label"><i class="fa-solid fa-person-running me-1"></i>Activity Level</label>
                                <select class="form-control" name="activity_level" id="activity_level" required>
                                    <option value="">Select Activity</option>
                                    <option value="sedentary" <?= (($old['activity_level'] ?? '') === 'sedentary') ? 'selected' : '' ?>>Sedentary</option>
                                    <option value="lightly_active" <?= (($old['activity_level'] ?? '') === 'lightly_active') ? 'selected' : '' ?>>Lightly Active</option>
                                    <option value="moderately_active" <?= (($old['activity_level'] ?? '') === 'moderately_active') ? 'selected' : '' ?>>Moderately Active</option>
                                    <option value="very_active" <?= (($old['activity_level'] ?? '') === 'very_active') ? 'selected' : '' ?>>Very Active</option>
                                    <option value="extra_active" <?= (($old['activity_level'] ?? '') === 'extra_active') ? 'selected' : '' ?>>Extra Active</option>
                                </select>
                                <small class="text-danger field-error" data-error-for="activity_level"></small>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fa-solid fa-image me-1"></i>Profile Picture (Optional)</label>
                                <input class="form-control" type="file" name="profile_pic" id="profile_pic" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                        </div>

                        <div class="step-pane d-none" data-step="3">
                            <h5 class="mb-3">Set Your Goal</h5>
                            <div class="row g-3 mb-3">
                                <?php
                                $goals = [
                                    'weight_loss' => ['Weight Loss', 'fa-fire-flame-curved'],
                                    'maintain' => ['Maintain', 'fa-scale-balanced'],
                                    'gain' => ['Weight Gain', 'fa-dumbbell'],
                                ];
                                foreach ($goals as $goalKey => [$goalLabel, $icon]):
                                ?>
                                <div class="col-md-4">
                                    <label class="card p-3 h-100 goal-card">
                                        <input class="form-check-input d-none goal-input" type="radio" name="goal" value="<?= $goalKey ?>" <?= (($old['goal'] ?? '') === $goalKey) ? 'checked' : '' ?> required>
                                        <div class="text-center">
                                            <i class="fa-solid <?= $icon ?> fa-2x mb-2 text-success"></i>
                                            <h6 class="mb-0"><?= $goalLabel ?></h6>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-danger field-error d-block mb-2" data-error-for="goal"></small>

                            <div class="card">
                                <div class="card-header">Preview</div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>BMI:</strong> <span id="bmiPreview">--</span></p>
                                    <p class="mb-0"><strong>Daily Calorie Goal:</strong> <span id="caloriePreview">--</span> kcal</p>
                                </div>
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">I agree to the terms and conditions.</label>
                            </div>
                            <small class="text-danger field-error d-block" data-error-for="terms"></small>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline" id="prevStepBtn" disabled><i class="fa-solid fa-arrow-left me-1"></i>Previous</button>
                            <button type="button" class="btn btn-primary" id="nextStepBtn">Next<i class="fa-solid fa-arrow-right ms-1"></i></button>
                            <button type="submit" class="btn btn-success d-none" id="submitBtn"><i class="fa-solid fa-user-check me-1"></i>Create Account</button>
                        </div>
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
    HMValidation.initRegistrationForm({
        formSelector: '#registerForm',
        stepSelector: '.step-pane',
        nextBtnSelector: '#nextStepBtn',
        prevBtnSelector: '#prevStepBtn',
        submitBtnSelector: '#submitBtn',
        progressBarSelector: '#registerProgressBar',
        stepLabelSelector: '#currentStepLabel',
        emailInputSelector: '#email',
        emailResultSelector: '#emailCheckResult',
        emailCheckUrl: '<?= SITE_URL ?>/auth/register.php?ajax=check_email',
        passwordInputSelector: '#password',
        passwordStrengthBarSelector: '#passwordStrengthBar',
        passwordStrengthTextSelector: '#passwordStrengthText',
        bmiSelector: '#bmiPreview',
        calorieSelector: '#caloriePreview'
    });
});
</script>
</body>
</html>
