<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$alerts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_weight') {
            $weight = (float) ($_POST['weight'] ?? 0);
            $logDate = (string) ($_POST['log_date'] ?? date('Y-m-d'));
            if ($weight <= 0 || $weight > 500) {
                $alerts[] = ['type' => 'danger', 'text' => 'Please enter a valid weight value.'];
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
                $alerts[] = ['type' => 'danger', 'text' => 'Invalid date format.'];
            } else {
                $stmtCheck = $pdo->prepare('SELECT id FROM weight_log WHERE user_id = :u AND log_date = :d LIMIT 1');
                $stmtCheck->execute([':u' => $userId, ':d' => $logDate]);
                $existing = $stmtCheck->fetchColumn();
                if ($existing) {
                    $stmtUpdate = $pdo->prepare('UPDATE weight_log SET weight = :w, created_at = NOW() WHERE id = :id AND user_id = :u');
                    $stmtUpdate->execute([':w' => $weight, ':id' => (int) $existing, ':u' => $userId]);
                } else {
                    $stmtIns = $pdo->prepare('INSERT INTO weight_log (user_id, weight, log_date, created_at) VALUES (:u,:w,:d,NOW())');
                    $stmtIns->execute([':u' => $userId, ':w' => $weight, ':d' => $logDate]);
                }
                $stmtUser = $pdo->prepare('SELECT height FROM users WHERE id = :id');
                $stmtUser->execute([':id' => $userId]);
                $height = (float) ($stmtUser->fetch()['height'] ?? 0);
                if ($height > 0) {
                    $bmi = calculateBMI($weight, $height);
                    $stmtBmi = $pdo->prepare('UPDATE users SET weight=:w,bmi=:b,updated_at=NOW() WHERE id=:id');
                    $stmtBmi->execute([':w' => $weight, ':b' => $bmi, ':id' => $userId]);
                }
                $alerts[] = ['type' => 'success', 'text' => 'Weight log saved successfully.'];
                logActivity($userId, 'user', 'Added/updated weight log');
            }
        }
        if ($action === 'delete_weight') {
            $logId = (int) ($_POST['log_id'] ?? 0);
            if ($logId > 0) {
                $stmtDel = $pdo->prepare('DELETE FROM weight_log WHERE id = :id AND user_id = :u');
                $stmtDel->execute([':id' => $logId, ':u' => $userId]);
                $alerts[] = ['type' => 'success', 'text' => 'Weight entry deleted.'];
                logActivity($userId, 'user', 'Deleted weight log');
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Could not complete request.'];
    }
}

$userStmt = $pdo->prepare('SELECT full_name,weight,height,bmi,daily_calorie_goal,goal FROM users WHERE id=:id');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();
$height = (float) ($user['height'] ?? 0);
$goalType = (string) ($user['goal'] ?? 'maintain');

$wStmt = $pdo->prepare('SELECT id,weight,log_date FROM weight_log WHERE user_id=:u ORDER BY log_date ASC');
$wStmt->execute([':u' => $userId]);
$weightRows = $wStmt->fetchAll();

$weightLabels = [];
$weightData = [];
$bmiData = [];
foreach ($weightRows as $row) {
    $weightLabels[] = date('M d', strtotime((string) $row['log_date']));
    $w = (float) $row['weight'];
    $weightData[] = $w;
    $bmiData[] = $height > 0 ? round(calculateBMI($w, $height), 2) : 0;
}

$startingWeight = !empty($weightRows) ? (float) $weightRows[0]['weight'] : (float) ($user['weight'] ?? 0);
$currentWeight = !empty($weightRows) ? (float) $weightRows[count($weightRows)-1]['weight'] : (float) ($user['weight'] ?? 0);
$change = $currentWeight - $startingWeight;
$goalWeight = $goalType === 'weight_loss' ? max(35, $startingWeight - 5) : ($goalType === 'gain' ? $startingWeight + 5 : $startingWeight);

$goalLine = array_fill(0, max(1, count($weightData)), $goalWeight);

$bmiNow = (float) ($user['bmi'] ?? 0);
$bmiCategory = getBMICategory($bmiNow);
$bmiPct = min(100, max(0, (int) round(($bmiNow / 40) * 100)));

$calStmt = $pdo->prepare(
    'SELECT log_date, COALESCE(SUM(calories), 0) AS total
     FROM food_log
     WHERE user_id = :u AND log_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY log_date
     ORDER BY log_date ASC'
);
$calStmt->execute([':u' => $userId]);
$calRows = $calStmt->fetchAll();
$calMap = [];
foreach ($calRows as $r) { $calMap[(string) $r['log_date']] = (int) $r['total']; }
$calLabels = [];
$calData = [];
$onTargetCount = 0;
$goalCal = (int) ($user['daily_calorie_goal'] ?? 0);
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $cal = $calMap[$d] ?? 0;
    $calLabels[] = date('M d', strtotime($d));
    $calData[] = $cal;
    if ($goalCal > 0 && $cal > 0 && abs($cal - $goalCal) <= 150) { $onTargetCount++; }
}
$avgCal = count($calData) ? (int) round(array_sum($calData) / max(1, count(array_filter($calData, fn($v)=>$v>0)))) : 0;

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracker | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
    <style>
        .bmi-scale{position:relative;height:16px;border-radius:999px;background:linear-gradient(90deg,#3498db 0%,#2ECC71 35%,#F39C12 70%,#E74C3C 100%);}
        .bmi-pointer{position:absolute;top:-5px;width:14px;height:26px;background:#2C3E50;border-radius:6px;transform:translateX(-50%);transition:left .45s ease}
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content"><div class="container-fluid">
        <nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Progress Tracker</h5><small class="text-muted">Weight, BMI, and calorie trends</small></div></nav>
        <?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

        <div class="card mb-3"><div class="card-header">Measurements Summary</div><div class="card-body">
            <div class="row g-2">
                <div class="col-md-4"><div class="stat-card"><h3>Current Weight</h3><div class="value"><?= number_format($currentWeight,1) ?> kg</div></div></div>
                <div class="col-md-4"><div class="stat-card"><h3>Current BMI</h3><div class="value"><?= number_format($bmiNow,2) ?></div><small><?= e($bmiCategory) ?></small></div></div>
                <div class="col-md-4"><div class="stat-card"><h3>Daily Calorie Goal</h3><div class="value"><?= (int)$goalCal ?> kcal</div><small class="badge"><?= e(ucwords(str_replace('_',' ',$goalType))) ?></small></div></div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header">Weight Tracker</div><div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8"><canvas id="weightChart" height="120"></canvas></div>
                <div class="col-lg-4">
                    <form method="post" class="row g-2">
                        <input type="hidden" name="action" value="add_weight">
                        <div class="col-12"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" min="1" class="form-control" name="weight" required></div>
                        <div class="col-12"><label class="form-label">Date</label><input type="date" class="form-control" name="log_date" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-12"><button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Log Weight</button></div>
                    </form>
                    <hr>
                    <small class="d-block">Starting: <strong><?= number_format($startingWeight,1) ?> kg</strong></small>
                    <small class="d-block">Current: <strong><?= number_format($currentWeight,1) ?> kg</strong></small>
                    <small class="d-block">Change: <strong><?= ($change>=0?'+':'').number_format($change,1) ?> kg</strong></small>
                    <small class="d-block">Goal Weight: <strong><?= number_format($goalWeight,1) ?> kg</strong></small>
                </div>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-striped"><thead><tr><th>Date</th><th>Weight</th><th>Action</th></tr></thead><tbody>
                <?php foreach(array_reverse($weightRows) as $wr): ?>
                    <tr><td><?= e(date('M d, Y', strtotime((string)$wr['log_date']))) ?></td><td><?= number_format((float)$wr['weight'],1) ?> kg</td><td>
                        <form method="post" onsubmit="return confirm('Delete this weight entry?');">
                            <input type="hidden" name="action" value="delete_weight"><input type="hidden" name="log_id" value="<?= (int)$wr['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td></tr>
                <?php endforeach; if(empty($weightRows)): ?><tr><td colspan="3" class="text-center text-muted">No weight logs yet.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div></div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6"><div class="card h-100"><div class="card-header">BMI History</div><div class="card-body">
                <canvas id="bmiChart" height="130"></canvas>
                <div class="mt-3"><div class="d-flex justify-content-between"><small>Underweight</small><small>Normal</small><small>Overweight</small><small>Obese</small></div><div class="bmi-scale"><div class="bmi-pointer" style="left:<?= $bmiPct ?>%"></div></div></div>
                <div class="mt-2"><span class="badge"><?= e($bmiCategory) ?></span></div>
            </div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header">Calorie Trend (30 Days)</div><div class="card-body">
                <canvas id="calorieChart" height="130"></canvas>
                <div class="mt-3">
                    <small class="d-block">Average daily intake: <strong><?= $avgCal ?> kcal</strong></small>
                    <small class="d-block">Days on target: <strong><?= $onTargetCount ?></strong></small>
                </div>
            </div></div></div>
        </div>
    </div></main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-collapsed'));
new Chart(document.getElementById('weightChart'),{type:'line',data:{labels:<?= json_encode($weightLabels) ?>,datasets:[{label:'Weight',data:<?= json_encode($weightData) ?>,borderColor:'#3498DB',backgroundColor:'rgba(52,152,219,.15)',fill:true,tension:.3},{label:'Goal',data:<?= json_encode($goalLine) ?>,borderColor:'#E74C3C',borderDash:[6,6],pointRadius:0}]},options:{responsive:true}});
new Chart(document.getElementById('bmiChart'),{type:'line',data:{labels:<?= json_encode($weightLabels) ?>,datasets:[{label:'BMI',data:<?= json_encode($bmiData) ?>,borderColor:'#2ECC71',backgroundColor:'rgba(46,204,113,.15)',fill:true,tension:.3}]},options:{responsive:true}});
new Chart(document.getElementById('calorieChart'),{type:'bar',data:{labels:<?= json_encode($calLabels) ?>,datasets:[{label:'Calories',data:<?= json_encode($calData) ?>,backgroundColor:'#27AE60'}]},options:{plugins:{legend:{display:false}},responsive:true}});
</script>
</body>
</html>

