<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$userQ = $pdo->prepare('SELECT full_name,email,goal,daily_calorie_goal FROM users WHERE id=:id LIMIT 1');
$userQ->execute([':id' => $userId]);
$user = $userQ->fetch();

$planQ = $pdo->prepare(
    'SELECT udp.assigned_date,udp.end_date,udp.dietitian_notes,dp.id plan_id,dp.title,dp.goal_type,dp.duration_days,dp.total_calories,
            d.full_name dietitian_name
     FROM user_diet_plans udp
     JOIN diet_plans dp ON dp.id=udp.diet_plan_id
     JOIN dietitians d ON d.id=udp.dietitian_id
     WHERE udp.user_id=:u AND udp.status="active"
     ORDER BY udp.assigned_date DESC LIMIT 1'
);
$planQ->execute([':u' => $userId]);
$plan = $planQ->fetch();

$days = [];
for ($i = 1; $i <= 7; $i++) $days[$i] = ['breakfast'=>[],'lunch'=>[],'dinner'=>[],'snack'=>[],'total'=>0];
if ($plan) {
    $m = $pdo->prepare('SELECT day_number,meal_type,meal_name,description,calories,protein,carbs,fat FROM meals WHERE diet_plan_id=:p AND day_number BETWEEN 1 AND 7 ORDER BY day_number ASC,FIELD(meal_type,"breakfast","lunch","dinner","snack"),id ASC');
    $m->execute([':p' => (int) $plan['plan_id']]);
    foreach ($m->fetchAll() as $row) {
        $d = (int) $row['day_number']; $t = (string) $row['meal_type'];
        if (!isset($days[$d][$t])) continue;
        $days[$d][$t][] = $row;
        $days[$d]['total'] += (int) $row['calories'];
    }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diet Plan Document</title>
    <style>
        body{font-family:Georgia,"Times New Roman",serif;background:#f7f7f7;margin:0;padding:18px;color:#222}
        .doc{max-width:980px;margin:0 auto;background:#fff;padding:24px;border:1px solid #ddd}
        .topbar{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:14px}
        .brand{display:flex;align-items:center}
        .print-logo{width:190px;max-width:100%;height:auto;display:block}
        h1,h2,h3{margin:.3rem 0}
        .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
        .box{border:1px solid #bdbdbd;padding:10px}
        .note{border-left:4px solid #444;padding:8px;background:#fafafa}
        .day{margin-top:18px;border:1px solid #c7c7c7}
        .day-header{padding:10px;border-bottom:1px solid #c7c7c7;background:repeating-linear-gradient(45deg,#f5f5f5,#f5f5f5 8px,#ffffff 8px,#ffffff 16px)}
        .meal{padding:10px;border-bottom:1px dashed #c7c7c7}
        .meal:last-child{border-bottom:none}
        table{width:100%;border-collapse:collapse;margin-top:6px}
        th,td{border:1px solid #ccc;padding:6px;font-size:.92rem}
        th{text-align:left;background:#f4f4f4}
        .footer{margin-top:22px;border-top:2px solid #333;padding-top:10px;font-size:.92rem}
        .tips{margin-top:10px;padding:8px;border:1px dashed #888}
        .controls{margin:12px auto;max-width:980px;display:flex;gap:8px}
        .btn{border:1px solid #333;background:#fff;padding:8px 12px;cursor:pointer}
        @media print{
            body{background:#fff;padding:0}
            .controls,.no-print{display:none!important}
            .doc{border:none;max-width:100%;padding:0}
            .day{page-break-inside:avoid;page-break-before:always}
            .day:first-of-type{page-break-before:auto}
            *{color:#000!important;background:none!important}
            th,td,.box,.day,.topbar,.footer{border-color:#000!important}
        }
    </style>
</head>
<body>
<div class="controls no-print">
    <button class="btn" onclick="window.print()">Print This Page</button>
    <button class="btn" onclick="alert('To download as PDF, choose Print and select Save as PDF.')">Download as PDF</button>
</div>
<div class="doc">
    <div class="topbar">
        <div class="brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="print-logo"></div>
        <div>Generated: <?= e(date('M d, Y')) ?></div>
    </div>

    <h2>Personalized Diet Plan</h2>
    <?php if (!$plan): ?>
        <p>No active diet plan found for this user.</p>
    <?php else: ?>
    <div class="meta-grid">
        <div class="box">
            <h3>User Information</h3>
            <div><strong>Name:</strong> <?= e((string) $user['full_name']) ?></div>
            <div><strong>Goal:</strong> <?= e(ucwords(str_replace('_',' ',(string) $user['goal']))) ?></div>
            <div><strong>Daily Target:</strong> <?= (int) ($user['daily_calorie_goal'] ?? 0) ?> kcal</div>
        </div>
        <div class="box">
            <h3>Dietitian Information</h3>
            <div><strong>Name:</strong> <?= e((string) $plan['dietitian_name']) ?></div>
            <div><strong>Plan:</strong> <?= e((string) $plan['title']) ?></div>
            <div><strong>Plan Goal:</strong> <?= e(ucwords(str_replace('_',' ',(string) $plan['goal_type']))) ?></div>
        </div>
    </div>
    <div class="note"><strong>Dietitian Notes:</strong> <?= e((string) ($plan['dietitian_notes'] ?: 'Follow the meal schedule, stay hydrated, and stay consistent.')) ?></div>

    <?php foreach ($days as $day => $groups): ?>
        <div class="day">
            <div class="day-header"><strong>Day <?= $day ?></strong> - Total Calories: <?= (int) $groups['total'] ?> kcal</div>
            <?php foreach (['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snack'] as $type => $label): ?>
                <div class="meal">
                    <h3><?= $label ?></h3>
                    <?php if (empty($groups[$type])): ?>
                        <p>No meal specified.</p>
                    <?php else: ?>
                        <?php foreach ($groups[$type] as $meal): ?>
                            <div><strong><?= e((string) $meal['meal_name']) ?></strong></div>
                            <div><?= e((string) $meal['description']) ?></div>
                            <table>
                                <tr><th>Calories</th><th>Protein</th><th>Carbs</th><th>Fat</th></tr>
                                <tr><td><?= (int) $meal['calories'] ?> kcal</td><td><?= (float) $meal['protein'] ?> g</td><td><?= (float) $meal['carbs'] ?> g</td><td><?= (float) $meal['fat'] ?> g</td></tr>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        Generated on <?= e(date('M d, Y h:i A')) ?>
        <div class="tips">
            <strong>Healthy Lifestyle Tips:</strong>
            Keep meal timing consistent, drink enough water, prioritize sleep, and track your progress weekly.
        </div>
    </div>
</div>
<script>
if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.addEventListener('load', () => window.print());
}
</script>
</body>
</html>
