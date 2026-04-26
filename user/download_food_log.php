<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-6 day')));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-6 day'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

$userQ = $pdo->prepare('SELECT full_name,daily_calorie_goal FROM users WHERE id=:id LIMIT 1');
$userQ->execute([':id' => $userId]);
$user = $userQ->fetch();

$q = $pdo->prepare(
    'SELECT log_date,meal_type,food_name,quantity,unit,calories,protein,carbs,fat
     FROM food_log
     WHERE user_id=:u AND log_date BETWEEN :f AND :t
     ORDER BY log_date ASC, FIELD(meal_type,"breakfast","lunch","dinner","snack"), id ASC'
);
$q->execute([':u' => $userId, ':f' => $from, ':t' => $to]);
$rows = $q->fetchAll();

$grouped = [];
$grand = ['calories' => 0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
foreach ($rows as $r) {
    $d = (string) $r['log_date'];
    if (!isset($grouped[$d])) $grouped[$d] = ['rows' => [], 'totals' => ['calories' => 0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0]];
    $grouped[$d]['rows'][] = $r;
    $grouped[$d]['totals']['calories'] += (int) $r['calories'];
    $grouped[$d]['totals']['protein'] += (float) $r['protein'];
    $grouped[$d]['totals']['carbs'] += (float) $r['carbs'];
    $grouped[$d]['totals']['fat'] += (float) $r['fat'];
    $grand['calories'] += (int) $r['calories'];
    $grand['protein'] += (float) $r['protein'];
    $grand['carbs'] += (float) $r['carbs'];
    $grand['fat'] += (float) $r['fat'];
}

$daysCount = max(1, count($grouped));
$avg = [
    'calories' => (int) round($grand['calories'] / $daysCount),
    'protein' => round($grand['protein'] / $daysCount, 1),
    'carbs' => round($grand['carbs'] / $daysCount, 1),
    'fat' => round($grand['fat'] / $daysCount, 1),
];

$summaryText = 'In this period, your average intake was ' . $avg['calories'] . ' kcal/day with macros around P ' . $avg['protein'] . 'g, C ' . $avg['carbs'] . 'g, F ' . $avg['fat'] . 'g.';

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Log Report</title>
    <style>
        body{font-family:Georgia,"Times New Roman",serif;background:#f7f7f7;margin:0;padding:18px;color:#222}
        .controls{margin:12px auto;max-width:1100px;display:flex;gap:8px;flex-wrap:wrap}
        .btn{border:1px solid #333;background:#fff;padding:8px 12px;cursor:pointer}
        .doc{max-width:1100px;margin:0 auto;background:#fff;padding:24px;border:1px solid #ddd}
        .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #333;padding-bottom:10px}
        .print-logo{width:190px;max-width:100%;height:auto;display:block}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{border:1px solid #ccc;padding:6px;font-size:.92rem}
        th{background:#f4f4f4;text-align:left}
        .date-row{background:repeating-linear-gradient(45deg,#f5f5f5,#f5f5f5 8px,#ffffff 8px,#ffffff 16px);font-weight:700}
        .totals{background:#f9f9f9;font-weight:700}
        .summary{margin-top:12px;border:1px dashed #888;padding:10px}
        .footer{margin-top:18px;border-top:2px solid #333;padding-top:10px}
        @media print{
            body{background:#fff;padding:0}
            .controls,.no-print{display:none!important}
            .doc{max-width:100%;border:none;padding:0}
            *{color:#000!important;background:none!important}
            th,td,.doc,.summary,.footer{border-color:#000!important}
        }
    </style>
</head>
<body>
<div class="controls no-print">
    <form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <div><label>From</label><br><input type="date" name="from" value="<?= e($from) ?>"></div>
        <div><label>To</label><br><input type="date" name="to" value="<?= e($to) ?>"></div>
        <button class="btn">Generate</button>
    </form>
    <button class="btn" onclick="window.print()">Print/Download</button>
    <button class="btn" onclick="alert('Use Print and select Save as PDF in your browser.')">Download as PDF</button>
</div>

<div class="doc">
    <div class="head">
        <div><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="print-logo"></div>
        <div>Generated: <?= e(date('M d, Y')) ?></div>
    </div>
    <h2>Food Log Report</h2>
    <p><strong>User:</strong> <?= e((string) ($user['full_name'] ?? 'User')) ?> | <strong>Range:</strong> <?= e($from) ?> to <?= e($to) ?></p>
    <table>
        <thead>
        <tr><th>Date</th><th>Meal Type</th><th>Food Name</th><th>Qty</th><th>Calories</th><th>P/C/F</th></tr>
        </thead>
        <tbody>
        <?php foreach ($grouped as $date => $g): ?>
            <tr class="date-row"><td colspan="6"><?= e(date('M d, Y', strtotime($date))) ?></td></tr>
            <?php foreach ($g['rows'] as $r): ?>
                <tr>
                    <td><?= e((string) $r['log_date']) ?></td>
                    <td><?= e(ucfirst((string) $r['meal_type'])) ?></td>
                    <td><?= e((string) $r['food_name']) ?></td>
                    <td><?= (float) $r['quantity'] . ' ' . e((string) $r['unit']) ?></td>
                    <td><?= (int) $r['calories'] ?></td>
                    <td><?= (float) $r['protein'] ?>/<?= (float) $r['carbs'] ?>/<?= (float) $r['fat'] ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="totals">
                <td colspan="4">Daily Total</td>
                <td><?= (int) $g['totals']['calories'] ?></td>
                <td><?= round((float) $g['totals']['protein'],1) ?>/<?= round((float) $g['totals']['carbs'],1) ?>/<?= round((float) $g['totals']['fat'],1) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($grouped)): ?>
            <tr><td colspan="6" style="text-align:center;">No logs found for selected range.</td></tr>
        <?php endif; ?>
        <tr class="totals">
            <td colspan="4">Grand Total</td>
            <td><?= (int) $grand['calories'] ?></td>
            <td><?= round((float) $grand['protein'],1) ?>/<?= round((float) $grand['carbs'],1) ?>/<?= round((float) $grand['fat'],1) ?></td>
        </tr>
        <tr class="totals">
            <td colspan="4">Daily Average</td>
            <td><?= (int) $avg['calories'] ?></td>
            <td><?= $avg['protein'] ?>/<?= $avg['carbs'] ?>/<?= $avg['fat'] ?></td>
        </tr>
        </tbody>
    </table>
    <div class="summary">
        <strong>Chart Summary:</strong>
        <?= e($summaryText) ?>
    </div>
    <div class="footer">
        Generated on <?= e(date('M d, Y h:i A')) ?>.
    </div>
</div>
</body>
</html>
