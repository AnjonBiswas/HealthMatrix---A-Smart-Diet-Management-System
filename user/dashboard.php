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

ensureNutritionSearchTables($pdo);

$today = date('Y-m-d');
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'toggle_meal') {
            $mealId = (int) ($_POST['meal_id'] ?? 0);
            $markEaten = isset($_POST['mark_eaten']) && $_POST['mark_eaten'] === '1';

            $stmtMeal = $pdo->prepare('SELECT id, meal_name, meal_type, calories, protein, carbs, fat FROM meals WHERE id = :id LIMIT 1');
            $stmtMeal->execute([':id' => $mealId]);
            $meal = $stmtMeal->fetch();

            if ($meal) {
                if ($markEaten) {
                    $stmtExists = $pdo->prepare(
                        'SELECT id FROM food_log
                         WHERE user_id = :user_id AND food_name = :food_name AND meal_type = :meal_type
                         AND calories = :calories AND unit = :unit AND log_date = :log_date LIMIT 1'
                    );
                    $stmtExists->execute([
                        ':user_id' => $userId,
                        ':food_name' => $meal['meal_name'],
                        ':meal_type' => $meal['meal_type'],
                        ':calories' => $meal['calories'],
                        ':unit' => 'plan_meal',
                        ':log_date' => $today,
                    ]);

                    if (!$stmtExists->fetch()) {
                        $stmtInsert = $pdo->prepare(
                            'INSERT INTO food_log
                            (user_id, food_name, meal_type, calories, protein, carbs, fat, quantity, unit, log_date, created_at)
                            VALUES
                            (:user_id, :food_name, :meal_type, :calories, :protein, :carbs, :fat, :quantity, :unit, :log_date, NOW())'
                        );
                        $stmtInsert->execute([
                            ':user_id' => $userId,
                            ':food_name' => $meal['meal_name'],
                            ':meal_type' => $meal['meal_type'],
                            ':calories' => $meal['calories'],
                            ':protein' => $meal['protein'],
                            ':carbs' => $meal['carbs'],
                            ':fat' => $meal['fat'],
                            ':quantity' => 1,
                            ':unit' => 'plan_meal',
                            ':log_date' => $today,
                        ]);
                    }
                } else {
                    $stmtDelete = $pdo->prepare(
                        'DELETE FROM food_log
                         WHERE user_id = :user_id AND food_name = :food_name AND meal_type = :meal_type
                         AND calories = :calories AND unit = :unit AND log_date = :log_date'
                    );
                    $stmtDelete->execute([
                        ':user_id' => $userId,
                        ':food_name' => $meal['meal_name'],
                        ':meal_type' => $meal['meal_type'],
                        ':calories' => $meal['calories'],
                        ':unit' => 'plan_meal',
                        ':log_date' => $today,
                    ]);
                }
                logActivity($userId, 'user', 'Updated meal completion status');
            }
        }

        $_SESSION['flash_success'] = 'Dashboard updated successfully.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not process request.';
    }

    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit;
}

$stmtUser = $pdo->prepare(
    'SELECT id, full_name, email, bmi, goal, daily_calorie_goal, activity_level, created_at
     FROM users WHERE id = :id LIMIT 1'
);
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch();

if (!$user) {
    header('Location: ' . SITE_URL . '/auth/logout.php');
    exit;
}

$foodSearchQuery = trim((string) ($_GET['food_q'] ?? ''));
$foodSearchRows = [];
$foodSearchMessage = '';
if ($foodSearchQuery !== '') {
    $stmtFoodSearch = $pdo->prepare(
        'SELECT food_name, calories_est, serving_unit
         FROM food_calorie_reference
         WHERE food_name LIKE :q
         ORDER BY food_name ASC
         LIMIT 20'
    );
    $stmtFoodSearch->execute([':q' => '%' . $foodSearchQuery . '%']);
    $foodSearchRows = $stmtFoodSearch->fetchAll();
    if (empty($foodSearchRows)) {
        $foodSearchMessage = 'No matching food found. Try another food name like apple, rice, egg, banana, or bread.';
    }
}

$stmtPlan = $pdo->prepare(
    'SELECT udp.id AS user_plan_id, udp.assigned_date, udp.status AS assign_status,
            dp.id AS diet_plan_id, dp.title, dp.duration_days, dp.total_calories
     FROM user_diet_plans udp
     JOIN diet_plans dp ON dp.id = udp.diet_plan_id
     WHERE udp.user_id = :user_id AND udp.status = "active"
     ORDER BY udp.assigned_date DESC
     LIMIT 1'
);
$stmtPlan->execute([':user_id' => $userId]);
$activePlan = $stmtPlan->fetch();

$todayDayNumber = 1;
if ($activePlan) {
    $assignedDate = new DateTime((string) $activePlan['assigned_date']);
    $now = new DateTime($today);
    $daysDiff = (int) $assignedDate->diff($now)->format('%a');
    $duration = max(1, (int) $activePlan['duration_days']);
    $todayDayNumber = ($daysDiff % $duration) + 1;
}

$todayMeals = [];
$eatenMeals = [];
if ($activePlan) {
    $stmtMeals = $pdo->prepare(
        'SELECT id, meal_type, meal_name, description, calories, protein, carbs, fat
         FROM meals
         WHERE diet_plan_id = :diet_plan_id AND day_number = :day_number
         ORDER BY FIELD(meal_type, "breakfast","lunch","dinner","snack"), id ASC'
    );
    $stmtMeals->execute([
        ':diet_plan_id' => (int) $activePlan['diet_plan_id'],
        ':day_number' => $todayDayNumber,
    ]);
    $todayMeals = $stmtMeals->fetchAll();

    $stmtEaten = $pdo->prepare('SELECT food_name, meal_type, calories FROM food_log WHERE user_id = :user_id AND log_date = :log_date');
    $stmtEaten->execute([':user_id' => $userId, ':log_date' => $today]);
    foreach ($stmtEaten->fetchAll() as $item) {
        $eatenMeals[$item['food_name'] . '|' . $item['meal_type'] . '|' . $item['calories']] = true;
    }
}

$stmtTodayTotals = $pdo->prepare(
    'SELECT
        COALESCE(SUM(calories), 0) AS calories,
        COALESCE(SUM(protein), 0) AS protein,
        COALESCE(SUM(carbs), 0) AS carbs,
        COALESCE(SUM(fat), 0) AS fat
     FROM food_log
     WHERE user_id = :user_id AND log_date = :log_date'
);
$stmtTodayTotals->execute([':user_id' => $userId, ':log_date' => $today]);
$todayTotals = $stmtTodayTotals->fetch();

$calorieGoal = (int) ($user['daily_calorie_goal'] ?? 0);
$todayCalories = (int) ($todayTotals['calories'] ?? 0);
$todayProtein = (float) ($todayTotals['protein'] ?? 0);
$todayCarbs = (float) ($todayTotals['carbs'] ?? 0);
$todayFat = (float) ($todayTotals['fat'] ?? 0);

$remainingCalories = max(0, $calorieGoal - $todayCalories);
$caloriePercent = $calorieGoal > 0 ? min(100, round(($todayCalories / $calorieGoal) * 100)) : 0;

$stmtMealBreakdown = $pdo->prepare(
    'SELECT meal_type, COALESCE(SUM(calories), 0) AS total
     FROM food_log
     WHERE user_id = :user_id AND log_date = :log_date
     GROUP BY meal_type'
);
$stmtMealBreakdown->execute([':user_id' => $userId, ':log_date' => $today]);
$mealBreakdownRaw = $stmtMealBreakdown->fetchAll();
$mealBreakdown = ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0, 'snack' => 0];
foreach ($mealBreakdownRaw as $row) {
    $mealBreakdown[(string) $row['meal_type']] = (int) $row['total'];
}

$stmtWater = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) AS water_total FROM water_log WHERE user_id = :user_id AND log_date = :log_date');
$stmtWater->execute([':user_id' => $userId, ':log_date' => $today]);
$todayWater = (int) ($stmtWater->fetch()['water_total'] ?? 0);
$waterGoal = 2000;
$waterPercent = min(100, (int) round(($todayWater / $waterGoal) * 100));
$waterCups = min(8, (int) floor($todayWater / 250));

$stmtWeight = $pdo->prepare(
    'SELECT log_date, weight
     FROM weight_log
     WHERE user_id = :user_id AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY log_date ASC'
);
$stmtWeight->execute([':user_id' => $userId]);
$weightRows = $stmtWeight->fetchAll();

$weightLabels = [];
$weightData = [];
foreach ($weightRows as $row) {
    $weightLabels[] = date('M d', strtotime((string) $row['log_date']));
    $weightData[] = (float) $row['weight'];
}

$stmtMessages = $pdo->prepare(
    'SELECT sender_id, sender_type, message, created_at
     FROM messages
     WHERE receiver_id = :user_id AND receiver_type = "user"
     ORDER BY created_at DESC
     LIMIT 5'
);
$stmtMessages->execute([':user_id' => $userId]);
$recentMessages = $stmtMessages->fetchAll();

$stmtCalHistory = $pdo->prepare(
    'SELECT log_date, COALESCE(SUM(calories), 0) AS total
     FROM food_log
     WHERE user_id = :user_id AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY log_date
     ORDER BY log_date ASC'
);
$stmtCalHistory->execute([':user_id' => $userId]);
$calHistoryRows = $stmtCalHistory->fetchAll();

$calHistoryMap = [];
foreach ($calHistoryRows as $row) {
    $calHistoryMap[(string) $row['log_date']] = (int) $row['total'];
}

$historyLabels = [];
$historyValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $historyLabels[] = date('M d', strtotime($d));
    $historyValues[] = $calHistoryMap[$d] ?? 0;
}

$bmi = (float) ($user['bmi'] ?? 0);
$bmiCategory = getBMICategory($bmi);

$goal = (string) ($user['goal'] ?? 'maintain');
$reminder = 'Stay consistent. Every healthy choice counts.';
if ($goal === 'weight_loss') {
    $reminder = 'Small calorie deficits plus consistency lead to sustainable fat loss.';
} elseif ($goal === 'gain') {
    $reminder = 'Prioritize protein, enough calories, and progressive training today.';
} elseif ($goal === 'maintain') {
    $reminder = 'Balance meals and hydration to maintain your current progress.';
}

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
    <title>User Dashboard | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li class="active"><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
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
                    <h5 class="mb-0 d-flex align-items-center gap-2 flex-wrap">
                        <span>Hello, <?= e((string) ($_SESSION['user_name'] ?? $user['full_name'])) ?></span>
                        <span class="badge bg-success-subtle text-success-emphasis"><?= e(ucfirst(str_replace('_', ' ', $goal))) ?></span>
                    </h5>
                    <small class="text-muted">Here is your daily nutrition summary</small>
                </div>
                <div class="right text-end">
                    <form method="get" class="d-flex gap-2 mb-1" id="foodCalorieSearchForm">
                        <input
                            type="text"
                            name="food_q"
                            id="foodCalorieQuery"
                            class="form-control form-control-sm"
                            placeholder="Search food calories..."
                            value="<?= e($foodSearchQuery) ?>"
                            style="min-width: 210px;"
                        >
                        <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                </div>
            </nav>

            <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
            <?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

                <div class="card mb-3<?= $foodSearchQuery === '' ? ' d-none' : '' ?>" id="foodCalorieSearchCard">
                    <div class="card-header">
                        Food Calorie Search Results for: <strong id="foodCalorieSearchTitle"><?= e($foodSearchQuery) ?></strong>
                        <span class="badge ms-2" id="foodCalorieSourceBadge"></span>
                    </div>
                    <div class="card-body" id="foodCalorieSearchResults">
                        <?php if (!empty($foodSearchRows)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                    <tr>
                                        <th>Food Name</th>
                                        <th>Estimated Calories</th>
                                        <th>Protein</th>
                                        <th>Carbs</th>
                                        <th>Fat</th>
                                        <th>Serving Unit</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($foodSearchRows as $item): ?>
                                        <tr>
                                            <td><?= e((string) $item['food_name']) ?></td>
                                            <td><?= (int) $item['calories_est'] ?> kcal</td>
                                            <td>--</td>
                                            <td>--</td>
                                            <td>--</td>
                                            <td><?= e((string) $item['serving_unit']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0"><?= e($foodSearchMessage) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

            <div class="dashboard-stats mb-3">
                <div class="stat-card">
                    <h3>Today's Calories</h3>
                    <div class="value"><?= $todayCalories ?> / <?= $calorieGoal ?> kcal</div>
                    <div class="progress mt-2"><div class="progress-bar" style="width: <?= $caloriePercent ?>%"></div></div>
                </div>
                <div class="stat-card info">
                    <h3>Water Intake</h3>
                    <div class="value"><?= $todayWater ?> / <?= $waterGoal ?> ml</div>
                    <div class="progress mt-2"><div class="progress-bar" style="width: <?= $waterPercent ?>%"></div></div>
                </div>
                <div class="stat-card warning">
                    <h3>Current BMI</h3>
                    <div class="value"><?= number_format($bmi, 2) ?></div>
                    <span class="badge"><?= e($bmiCategory) ?></span>
                </div>
                <div class="stat-card success">
                    <h3>Active Diet Plan</h3>
                    <div class="value" style="font-size:1.2rem;"><?= e($activePlan['title'] ?? 'No active plan') ?></div>
                    <small class="meta">Day <?= (int) $todayDayNumber ?></small>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <span>Calorie Summary</span>
                            <span class="badge">Remaining <?= $remainingCalories ?> kcal</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-5 text-center">
                                    <canvas id="calorieDonut" height="220"></canvas>
                                </div>
                                <div class="col-md-7">
                                    <div class="goal-row"><div class="label"><span>Breakfast</span><strong><?= $mealBreakdown['breakfast'] ?> kcal</strong></div></div>
                                    <div class="goal-row"><div class="label"><span>Lunch</span><strong><?= $mealBreakdown['lunch'] ?> kcal</strong></div></div>
                                    <div class="goal-row"><div class="label"><span>Dinner</span><strong><?= $mealBreakdown['dinner'] ?> kcal</strong></div></div>
                                    <div class="goal-row"><div class="label"><span>Snack</span><strong><?= $mealBreakdown['snack'] ?> kcal</strong></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <span>Today's Meal Plan</span>
                            <div>
                                <a href="<?= SITE_URL ?>/user/food_log.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus me-1"></i>Log Custom Food</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($todayMeals)): ?>
                                <p class="text-muted mb-0">No meals found for today. Assign a diet plan to see meals.</p>
                            <?php else: ?>
                                <?php foreach ($todayMeals as $meal): ?>
                                    <?php
                                    $key = $meal['meal_name'] . '|' . $meal['meal_type'] . '|' . $meal['calories'];
                                    $checked = isset($eatenMeals[$key]);
                                    ?>
                                    <form method="post" class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start">
                                        <input type="hidden" name="action" value="toggle_meal">
                                        <input type="hidden" name="meal_id" value="<?= (int) $meal['id'] ?>">
                                        <input type="hidden" name="mark_eaten" value="<?= $checked ? '0' : '1' ?>">
                                        <div>
                                            <div class="fw-semibold"><?= e(ucfirst((string) $meal['meal_type'])) ?>: <?= e((string) $meal['meal_name']) ?></div>
                                            <small class="text-muted"><?= e((string) ($meal['description'] ?? '')) ?></small><br>
                                            <small class="text-muted"><?= (int) $meal['calories'] ?> kcal | P <?= (float) $meal['protein'] ?>g | C <?= (float) $meal['carbs'] ?>g | F <?= (float) $meal['fat'] ?>g</small>
                                        </div>
                                        <button type="submit" class="btn btn-sm <?= $checked ? 'btn-success' : 'btn-outline' ?>">
                                            <i class="fa-solid <?= $checked ? 'fa-check-circle' : 'fa-circle' ?>"></i> <?= $checked ? 'Eaten' : 'Mark Eaten' ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header">Weight Progress (30 days)</div>
                        <div class="card-body">
                            <canvas id="weightChart" height="180"></canvas>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">Water Intake Tracker</div>
                        <div class="card-body">
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <div class="text-center">
                                        <div class="rounded-3 border <?= $i <= $waterCups ? 'bg-info-subtle border-info' : '' ?>" style="width:34px;height:50px;"></div>
                                        <small><?= $i ?></small>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?= $todayWater ?> ml logged today</small>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">Quick Add</div>
                        <div class="card-body d-grid gap-2">
                            <a href="<?= SITE_URL ?>/user/food_log.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Log Food</a>
                            <a href="<?= SITE_URL ?>/user/water_tracker.php" class="btn btn-info text-white"><i class="fa-solid fa-plus me-1"></i>Log Water</a>
                            <a href="<?= SITE_URL ?>/user/progress.php" class="btn btn-secondary"><i class="fa-solid fa-plus me-1"></i>Log Weight</a>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">Daily Reminder</div>
                        <div class="card-body">
                            <p class="mb-0"><?= e($reminder) ?></p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Recent Messages</div>
                        <div class="card-body">
                            <?php if (empty($recentMessages)): ?>
                                <p class="text-muted mb-0">No recent messages.</p>
                            <?php else: ?>
                                <div class="activity-feed">
                                    <?php foreach ($recentMessages as $msg): ?>
                                        <div class="activity-item">
                                            <span class="dot"></span>
                                            <div class="content">
                                                <div class="small fw-semibold"><?= e(ucfirst((string) $msg['sender_type'])) ?></div>
                                                <div><?= e((string) $msg['message']) ?></div>
                                                <span class="time"><?= e(date('M d, h:i A', strtotime((string) $msg['created_at']))) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">Macronutrient Breakdown</div>
                        <div class="card-body">
                            <canvas id="macroChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">7-Day Calorie History</div>
                        <div class="card-body">
                            <canvas id="calorieHistoryChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const sidebarToggle = document.getElementById('sidebarToggle');
sidebarToggle?.addEventListener('click', function () {
    document.body.classList.toggle('sidebar-collapsed');
});

new Chart(document.getElementById('calorieDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Consumed', 'Remaining'],
        datasets: [{
            data: [<?= (int) $todayCalories ?>, <?= max(0, (int) $calorieGoal - (int) $todayCalories) ?>],
            backgroundColor: ['#2ECC71', '#ECF0F1']
        }]
    },
    options: { responsive: true, cutout: '70%' }
});

new Chart(document.getElementById('weightChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($weightLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Weight (kg)',
            data: <?= json_encode($weightData, JSON_UNESCAPED_UNICODE) ?>,
            borderColor: '#3498DB',
            backgroundColor: 'rgba(52,152,219,0.15)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('macroChart'), {
    type: 'pie',
    data: {
        labels: ['Protein', 'Carbs', 'Fat'],
        datasets: [{
            data: [<?= (float) $todayProtein ?>, <?= (float) $todayCarbs ?>, <?= (float) $todayFat ?>],
            backgroundColor: ['#2ECC71', '#F39C12', '#3498DB']
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById('calorieHistoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($historyLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Calories',
            data: <?= json_encode($historyValues, JSON_UNESCAPED_UNICODE) ?>,
            backgroundColor: '#27AE60'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

const foodSearchForm = document.getElementById('foodCalorieSearchForm');
const foodSearchInput = document.getElementById('foodCalorieQuery');
const foodSearchCard = document.getElementById('foodCalorieSearchCard');
const foodSearchResults = document.getElementById('foodCalorieSearchResults');
const foodSearchTitle = document.getElementById('foodCalorieSearchTitle');
const foodSearchBadge = document.getElementById('foodCalorieSourceBadge');
const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function renderFoodSearchRows(payload) {
    if (!foodSearchResults) return;
    const rows = Array.isArray(payload.data) ? payload.data : [];
    if (!rows.length) {
        foodSearchResults.innerHTML = `<p class="text-muted mb-0">${payload.message || 'No food found.'}</p>`;
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th>Food Name</th><th>Estimated Calories</th><th>Protein</th><th>Carbs</th><th>Fat</th><th>Serving Unit</th></tr></thead><tbody>';
    rows.forEach((item) => {
        const name = escHtml((item.food_name || '').toString());
        const calories = Number(item.calories || 0);
        const protein = item.protein !== null && item.protein !== undefined ? `${Number(item.protein).toFixed(1)} g` : '--';
        const carbs = item.carbs !== null && item.carbs !== undefined ? `${Number(item.carbs).toFixed(1)} g` : '--';
        const fat = item.fat !== null && item.fat !== undefined ? `${Number(item.fat).toFixed(1)} g` : '--';
        const unit = escHtml((item.serving_unit || '').toString());
        html += `<tr><td>${name}</td><td>${calories} kcal</td><td>${protein}</td><td>${carbs}</td><td>${fat}</td><td>${unit}</td></tr>`;
    });
    html += '</tbody></table></div>';
    foodSearchResults.innerHTML = html;
}

if (foodSearchForm && foodSearchInput) {
    foodSearchForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const q = foodSearchInput.value.trim();
        if (q.length < 2) {
            if (foodSearchResults) foodSearchResults.innerHTML = '<p class="text-muted mb-0">Please type at least 2 characters.</p>';
            if (foodSearchCard) foodSearchCard.classList.remove('d-none');
            if (foodSearchBadge) {
                foodSearchBadge.className = 'badge ms-2 bg-secondary-subtle text-secondary-emphasis';
                foodSearchBadge.textContent = 'No Result';
            }
            return;
        }

        if (foodSearchCard) foodSearchCard.classList.remove('d-none');
        if (foodSearchTitle) foodSearchTitle.textContent = q;
        if (foodSearchResults) foodSearchResults.innerHTML = '<p class="text-muted mb-0">Searching...</p>';
        if (foodSearchBadge) {
            foodSearchBadge.className = 'badge ms-2 bg-light text-dark';
            foodSearchBadge.textContent = 'Loading';
        }

        try {
            const res = await fetch(`<?= SITE_URL ?>/api/food_calorie_search.php?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                renderFoodSearchRows({ data: [], message: data.message || 'Search failed.' });
                if (foodSearchBadge) {
                    foodSearchBadge.className = 'badge ms-2 bg-danger-subtle text-danger-emphasis';
                    foodSearchBadge.textContent = 'Error';
                }
                return;
            }
            renderFoodSearchRows(data);
            if (foodSearchBadge) {
                const s = (data.source || 'db').toString().toLowerCase();
                if (s === 'ai') {
                    foodSearchBadge.className = 'badge ms-2 bg-warning-subtle text-warning-emphasis';
                    foodSearchBadge.textContent = 'AI Result';
                } else if (s === 'db_ai') {
                    foodSearchBadge.className = 'badge ms-2 bg-info-subtle text-info-emphasis';
                    foodSearchBadge.textContent = 'AI + DB Result';
                } else if (s === 'db') {
                    foodSearchBadge.className = 'badge ms-2 bg-success-subtle text-success-emphasis';
                    foodSearchBadge.textContent = 'DB Result';
                } else {
                    foodSearchBadge.className = 'badge ms-2 bg-secondary-subtle text-secondary-emphasis';
                    foodSearchBadge.textContent = 'No Result';
                }
            }
        } catch (err) {
            renderFoodSearchRows({ data: [], message: 'Network error while searching food.' });
            if (foodSearchBadge) {
                foodSearchBadge.className = 'badge ms-2 bg-danger-subtle text-danger-emphasis';
                foodSearchBadge.textContent = 'Error';
            }
        }
    });
}
</script>
</body>
</html>
