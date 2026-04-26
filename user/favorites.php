<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$alerts = [];

if ($userId <= 0) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $mealName = trim((string) ($_POST['meal_name'] ?? ''));
            $calories = (int) ($_POST['calories'] ?? 0);
            $protein = (float) ($_POST['protein'] ?? 0);
            $carbs = (float) ($_POST['carbs'] ?? 0);
            $fat = (float) ($_POST['fat'] ?? 0);

            if ($mealName === '' || mb_strlen($mealName) < 2 || $calories <= 0) {
                $alerts[] = ['type' => 'danger', 'text' => 'Meal name and calories are required.'];
            } else {
                $check = $pdo->prepare('SELECT id FROM user_favorite_meals WHERE user_id=:u AND meal_name=:m LIMIT 1');
                $check->execute([':u' => $userId, ':m' => $mealName]);
                if ($check->fetchColumn()) {
                    $alerts[] = ['type' => 'warning', 'text' => 'This meal is already in favorites.'];
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO user_favorite_meals
                         (user_id, meal_name, calories, protein, carbs, fat, created_at)
                         VALUES (:u,:m,:c,:p,:cb,:f,NOW())'
                    );
                    $ins->execute([
                        ':u' => $userId,
                        ':m' => $mealName,
                        ':c' => $calories,
                        ':p' => $protein,
                        ':cb' => $carbs,
                        ':f' => $fat,
                    ]);
                    $alerts[] = ['type' => 'success', 'text' => 'Favorite meal added.'];
                    logActivity($userId, 'user', 'Added favorite meal');
                }
            }
        }

        if ($action === 'delete') {
            $favoriteId = (int) ($_POST['favorite_id'] ?? 0);
            if ($favoriteId > 0) {
                $del = $pdo->prepare('DELETE FROM user_favorite_meals WHERE id=:id AND user_id=:u');
                $del->execute([':id' => $favoriteId, ':u' => $userId]);
                $alerts[] = ['type' => 'success', 'text' => 'Favorite removed.'];
                logActivity($userId, 'user', 'Removed favorite meal');
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed. Please try again.'];
    }
}

$stmt = $pdo->prepare(
    'SELECT id, meal_name, calories, protein, carbs, fat, created_at
     FROM user_favorite_meals
     WHERE user_id = :u
     ORDER BY created_at DESC'
);
$stmt->execute([':u' => $userId]);
$favorites = $stmt->fetchAll();

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
    <title>Favorite Meals | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <nav class="navbar">
                <button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div>
                    <h5 class="mb-0">Favorite Meals</h5>
                    <small class="text-muted">Save meals you reuse often</small>
                </div>
            </nav>

            <?php foreach ($alerts as $a): ?>
                <div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div>
            <?php endforeach; ?>

            <div class="card mb-3">
                <div class="card-header">Add New Favorite</div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <input type="hidden" name="action" value="add">
                        <div class="col-md-4">
                            <label class="form-label">Meal Name</label>
                            <input class="form-control" name="meal_name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Calories</label>
                            <input type="number" min="1" class="form-control" name="calories" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Protein</label>
                            <input type="number" step="0.1" class="form-control" name="protein">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Carbs</label>
                            <input type="number" step="0.1" class="form-control" name="carbs">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fat</label>
                            <input type="number" step="0.1" class="form-control" name="fat">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Favorite</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">My Favorite Meals</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($favorites as $f): ?>
                            <?php
                            $redirect = SITE_URL . '/user/food_log.php?' . http_build_query([
                                'prefill_name' => $f['meal_name'],
                                'prefill_meal_type' => 'snack',
                                'prefill_quantity' => 1,
                                'prefill_unit' => 'piece',
                                'prefill_calories' => $f['calories'],
                                'prefill_protein' => $f['protein'],
                                'prefill_carbs' => $f['carbs'],
                                'prefill_fat' => $f['fat'],
                                'date' => date('Y-m-d'),
                            ]);
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-1"><?= e((string) $f['meal_name']) ?></h6>
                                    <small class="text-muted d-block mb-2"><?= (int) $f['calories'] ?> kcal | P <?= (float) $f['protein'] ?> | C <?= (float) $f['carbs'] ?> | F <?= (float) $f['fat'] ?></small>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-success btn-sm" href="<?= e($redirect) ?>"><i class="fa-solid fa-plus me-1"></i>Add to Today's Log</a>
                                        <form method="post" onsubmit="return confirm('Delete this favorite?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="favorite_id" value="<?= (int) $f['id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($favorites)): ?>
                            <p class="text-muted mb-0">No favorites yet. Add your most used meals above.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => document.body.classList.toggle('sidebar-collapsed'));
</script>
</body>
</html>

