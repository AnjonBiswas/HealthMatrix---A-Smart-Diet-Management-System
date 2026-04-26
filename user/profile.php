<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

$alerts = [];
$comparison = null;
$activeTab = 'personal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_personal') {
            $activeTab = 'personal';
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');

            $errors = [];
            if (mb_strlen($fullName) < 3) {
                $errors[] = 'Full name must be at least 3 characters.';
            }
            if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
                $errors[] = 'Phone must be 7-15 digits (optional +).';
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
                if ($profilePic !== null) {
                    $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, profile_pic = :profile_pic, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':phone' => $phone,
                        ':profile_pic' => $profilePic,
                        ':id' => $userId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':phone' => $phone,
                        ':id' => $userId,
                    ]);
                }

                $_SESSION['user_name'] = $fullName;
                $_SESSION['full_name'] = $fullName;
                $alerts[] = ['type' => 'success', 'text' => 'Personal info updated successfully.'];
                logActivity($userId, 'user', 'Updated personal profile info');
            } else {
                foreach ($errors as $err) {
                    $alerts[] = ['type' => 'danger', 'text' => $err];
                }
            }
        }

        if ($action === 'update_health') {
            $activeTab = 'health';
            $age = (int) ($_POST['age'] ?? 0);
            $weight = (float) ($_POST['weight'] ?? 0);
            $height = (float) ($_POST['height'] ?? 0);
            $gender = strtolower((string) ($_POST['gender'] ?? ''));
            $activity = strtolower((string) ($_POST['activity_level'] ?? ''));
            $goal = strtolower((string) ($_POST['goal'] ?? ''));

            $allowedGenders = ['male', 'female', 'other'];
            $allowedActivities = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'];
            $allowedGoals = ['weight_loss', 'maintain', 'gain'];

            $errors = [];
            if ($age < 13 || $age > 100) { $errors[] = 'Age must be between 13 and 100.'; }
            if ($weight < 20 || $weight > 350) { $errors[] = 'Weight must be between 20 and 350 kg.'; }
            if ($height < 90 || $height > 250) { $errors[] = 'Height must be between 90 and 250 cm.'; }
            if (!in_array($gender, $allowedGenders, true)) { $errors[] = 'Invalid gender.'; }
            if (!in_array($activity, $allowedActivities, true)) { $errors[] = 'Invalid activity level.'; }
            if (!in_array($goal, $allowedGoals, true)) { $errors[] = 'Invalid goal.'; }

            if (empty($errors)) {
                $stmtOld = $pdo->prepare('SELECT bmi, daily_calorie_goal FROM users WHERE id = :id LIMIT 1');
                $stmtOld->execute([':id' => $userId]);
                $old = $stmtOld->fetch();
                $oldBmi = (float) ($old['bmi'] ?? 0);
                $oldCal = (int) ($old['daily_calorie_goal'] ?? 0);

                $newBmi = calculateBMI($weight, $height);
                $newCalorieGoal = calculateDailyCalories($age, $weight, $height, $gender, $activity, $goal);

                $stmt = $pdo->prepare(
                    'UPDATE users SET
                     age = :age, weight = :weight, height = :height, gender = :gender, activity_level = :activity_level,
                     goal = :goal, bmi = :bmi, daily_calorie_goal = :daily_calorie_goal, updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':age' => $age,
                    ':weight' => $weight,
                    ':height' => $height,
                    ':gender' => $gender,
                    ':activity_level' => $activity,
                    ':goal' => $goal,
                    ':bmi' => $newBmi,
                    ':daily_calorie_goal' => $newCalorieGoal,
                    ':id' => $userId,
                ]);

                $comparison = [
                    'old_bmi' => $oldBmi,
                    'new_bmi' => $newBmi,
                    'old_calorie' => $oldCal,
                    'new_calorie' => $newCalorieGoal,
                ];

                $alerts[] = ['type' => 'success', 'text' => 'Health details updated and goals recalculated.'];
                logActivity($userId, 'user', 'Updated health profile');
            } else {
                foreach ($errors as $err) {
                    $alerts[] = ['type' => 'danger', 'text' => $err];
                }
            }
        }

        if ($action === 'change_password') {
            $activeTab = 'security';
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $userPass = (string) ($stmt->fetch()['password'] ?? '');

            $isHash = str_starts_with($userPass, '$2y$') || str_starts_with($userPass, '$argon2');
            $currentValid = $isHash ? password_verify($currentPassword, $userPass) : hash_equals($userPass, $currentPassword);

            $errors = [];
            if (!$currentValid) {
                $errors[] = 'Current password is incorrect.';
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $newPassword)) {
                $errors[] = 'New password must be 8+ chars with uppercase, lowercase, number, and symbol.';
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirm password do not match.';
            }

            if (empty($errors)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
                $stmtUpdate->execute([':password' => $hash, ':id' => $userId]);
                $alerts[] = ['type' => 'success', 'text' => 'Password changed successfully.'];
                logActivity($userId, 'user', 'Changed account password');
            } else {
                foreach ($errors as $err) {
                    $alerts[] = ['type' => 'danger', 'text' => $err];
                }
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Update failed due to server error.'];
    }
}

$stmtUser = $pdo->prepare(
    'SELECT id, full_name, email, phone, profile_pic, age, weight, height, gender, activity_level, goal, bmi, daily_calorie_goal, created_at
     FROM users
     WHERE id = :id
     LIMIT 1'
);
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ' . SITE_URL . '/auth/logout.php');
    exit;
}

$_SESSION['user_name'] = (string) $user['full_name'];
$_SESSION['user_email'] = (string) $user['email'];
$_SESSION['full_name'] = (string) $user['full_name'];

$bmiCategory = getBMICategory((float) $user['bmi']);
$goalLabel = ucwords(str_replace('_', ' ', (string) $user['goal']));
$avatarUrl = !empty($user['profile_pic'])
    ? (SITE_URL . '/uploads/' . ltrim((string) $user['profile_pic'], '/'))
    : (SITE_URL . '/assets/images/default_avatar.png');

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <nav class="navbar">
                <button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div>
                    <h5 class="mb-0">My Profile</h5>
                    <small class="text-muted">Manage your account and health details</small>
                </div>
            </nav>

            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= e($alert['type']) ?>"><?= e($alert['text']) ?></div>
            <?php endforeach; ?>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">Profile Overview</div>
                        <div class="card-body text-center">
                            <img src="<?= e($avatarUrl) ?>" id="avatarPreview" alt="Avatar" class="rounded-circle mx-auto mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                            <h5><?= e((string) $user['full_name']) ?></h5>
                            <p class="mb-1"><i class="fa-solid fa-envelope me-1"></i><?= e((string) $user['email']) ?></p>
                            <p class="mb-1"><i class="fa-solid fa-phone me-1"></i><?= e((string) ($user['phone'] ?? 'N/A')) ?></p>
                            <p class="mb-2"><i class="fa-solid fa-calendar me-1"></i>Member since <?= e(date('M d, Y', strtotime((string) $user['created_at']))) ?></p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <span class="badge">BMI: <?= number_format((float) $user['bmi'], 2) ?> (<?= e($bmiCategory) ?>)</span>
                                <span class="badge"><?= e($goalLabel) ?></span>
                                <span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) $user['activity_level']))) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">Edit Profile</div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <button class="nav-link <?= $activeTab === 'personal' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPersonal" type="button">Personal Info</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link <?= $activeTab === 'health' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabHealth" type="button">Health Details</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabSecurity" type="button">Account Security</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade <?= $activeTab === 'personal' ? 'show active' : '' ?>" id="tabPersonal">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="update_personal">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control" name="full_name" value="<?= e((string) $user['full_name']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone</label>
                                                <input type="text" class="form-control" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Profile Picture</label>
                                                <input type="file" class="form-control" name="profile_pic" id="profilePicInput" accept=".jpg,.jpeg,.png,.gif,.webp">
                                            </div>
                                        </div>
                                        <button class="btn btn-primary mt-3" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save Personal Info</button>
                                    </form>
                                </div>

                                <div class="tab-pane fade <?= $activeTab === 'health' ? 'show active' : '' ?>" id="tabHealth">
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_health">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Age</label>
                                                <input type="number" class="form-control" name="age" value="<?= (int) $user['age'] ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Weight (kg)</label>
                                                <input type="number" step="0.1" class="form-control" name="weight" value="<?= e((string) $user['weight']) ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Height (cm)</label>
                                                <input type="number" step="0.1" class="form-control" name="height" value="<?= e((string) $user['height']) ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Gender</label>
                                                <select class="form-control" name="gender" required>
                                                    <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                                    <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                                    <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Activity Level</label>
                                                <select class="form-control" name="activity_level" required>
                                                    <?php foreach (['sedentary','lightly_active','moderately_active','very_active','extra_active'] as $activity): ?>
                                                        <option value="<?= $activity ?>" <?= $user['activity_level'] === $activity ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $activity))) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Goal</label>
                                                <select class="form-control" name="goal" required>
                                                    <?php foreach (['weight_loss','maintain','gain'] as $g): ?>
                                                        <option value="<?= $g ?>" <?= $user['goal'] === $g ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $g))) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <button class="btn btn-success mt-3" type="submit"><i class="fa-solid fa-calculator me-1"></i>Save & Recalculate</button>
                                    </form>

                                    <?php if ($comparison): ?>
                                        <div class="card mt-3">
                                            <div class="card-header">Before vs After</div>
                                            <div class="card-body">
                                                <p class="mb-1"><strong>BMI:</strong> <?= number_format((float) $comparison['old_bmi'], 2) ?> → <?= number_format((float) $comparison['new_bmi'], 2) ?></p>
                                                <p class="mb-0"><strong>Daily Calorie Goal:</strong> <?= (int) $comparison['old_calorie'] ?> kcal → <?= (int) $comparison['new_calorie'] ?> kcal</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="tab-pane fade <?= $activeTab === 'security' ? 'show active' : '' ?>" id="tabSecurity">
                                    <form method="post" id="securityForm">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Current Password</label>
                                                <input type="password" class="form-control" name="current_password" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">New Password</label>
                                                <input type="password" class="form-control" name="new_password" id="newPasswordInput" required>
                                                <div class="progress mt-2" style="height:8px;"><div id="securityPasswordBar" class="progress-bar" style="width:0%"></div></div>
                                                <small id="securityPasswordText" class="text-muted">Password strength: N/A</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Confirm Password</label>
                                                <input type="password" class="form-control" name="confirm_password" required>
                                            </div>
                                        </div>
                                        <button class="btn btn-danger mt-3" type="submit"><i class="fa-solid fa-key me-1"></i>Change Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/validation.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.body.classList.toggle('sidebar-collapsed');
});

document.getElementById('profilePicInput')?.addEventListener('change', function (e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (ev) {
        const avatar = document.getElementById('avatarPreview');
        if (avatar) avatar.src = ev.target.result;
    };
    reader.readAsDataURL(file);
});

const newPasswordInput = document.getElementById('newPasswordInput');
newPasswordInput?.addEventListener('input', function () {
    HMValidation.updatePasswordStrength('#newPasswordInput', '#securityPasswordBar', '#securityPasswordText');
});
</script>
</body>
</html>

