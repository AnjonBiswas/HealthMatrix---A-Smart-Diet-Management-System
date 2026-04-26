<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['dietitian']);

$pdo = Database::getInstance()->getConnection();
$dietitianId = (int) ($_SESSION['user_id'] ?? 0);
if ($dietitianId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$alerts = [];
$goalFilter = (string) ($_GET['goal'] ?? '');
$allowedGoals = ['weight_loss', 'gain', 'maintain'];
if (!in_array($goalFilter, $allowedGoals, true)) $goalFilter = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            if ($planId > 0) {
                $stmt = $pdo->prepare('DELETE FROM diet_plans WHERE id=:id AND dietitian_id=:d');
                $stmt->execute([':id' => $planId, ':d' => $dietitianId]);
                $alerts[] = ['type' => 'success', 'text' => 'Plan deleted.'];
                logActivity($dietitianId, 'dietitian', 'Deleted diet plan');
            }
        }
        if ($action === 'duplicate') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            if ($planId > 0) {
                $stmtPlan = $pdo->prepare('SELECT * FROM diet_plans WHERE id=:id AND dietitian_id=:d LIMIT 1');
                $stmtPlan->execute([':id' => $planId, ':d' => $dietitianId]);
                $plan = $stmtPlan->fetch();
                if ($plan) {
                    $ins = $pdo->prepare(
                        'INSERT INTO diet_plans (title,description,dietitian_id,goal_type,total_calories,duration_days,status,created_at)
                         VALUES (:t,:desc,:d,:g,:c,:dur,:s,NOW())'
                    );
                    $ins->execute([
                        ':t' => (string) $plan['title'] . ' (Copy)',
                        ':desc' => $plan['description'],
                        ':d' => $dietitianId,
                        ':g' => $plan['goal_type'],
                        ':c' => $plan['total_calories'],
                        ':dur' => $plan['duration_days'],
                        ':s' => $plan['status'],
                    ]);
                    $newId = (int) $pdo->lastInsertId();

                    $meals = $pdo->prepare('SELECT meal_type,meal_name,description,calories,protein,carbs,fat,day_number FROM meals WHERE diet_plan_id=:id');
                    $meals->execute([':id' => $planId]);
                    $allMeals = $meals->fetchAll();
                    $insMeal = $pdo->prepare(
                        'INSERT INTO meals (diet_plan_id,meal_type,meal_name,description,calories,protein,carbs,fat,day_number,created_at)
                         VALUES (:p,:mt,:mn,:d,:c,:pr,:cb,:f,:day,NOW())'
                    );
                    foreach ($allMeals as $m) {
                        $insMeal->execute([
                            ':p' => $newId, ':mt' => $m['meal_type'], ':mn' => $m['meal_name'], ':d' => $m['description'],
                            ':c' => $m['calories'], ':pr' => $m['protein'], ':cb' => $m['carbs'], ':f' => $m['fat'], ':day' => $m['day_number'],
                        ]);
                    }
                    $alerts[] = ['type' => 'success', 'text' => 'Plan duplicated successfully.'];
                    logActivity($dietitianId, 'dietitian', 'Duplicated diet plan');
                }
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed.'];
    }
}

$sql = 'SELECT dp.id,dp.title,dp.goal_type,dp.duration_days,dp.total_calories,dp.status,
        COUNT(udp.id) assigned_users
        FROM diet_plans dp
        LEFT JOIN user_diet_plans udp ON udp.diet_plan_id=dp.id
        WHERE dp.dietitian_id=:d';
$params = [':d' => $dietitianId];
if ($goalFilter !== '') {
    $sql .= ' AND dp.goal_type=:g';
    $params[':g'] = $goalFilter;
}
$sql .= ' GROUP BY dp.id,dp.title,dp.goal_type,dp.duration_days,dp.total_calories,dp.status ORDER BY dp.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diet Plans | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li class="active"><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">My Diet Plans</h5><small class="text-muted">Manage and reuse your plans</small></div><a href="<?= SITE_URL ?>/dietitian/create_plan.php" class="btn btn-primary right">Create New Plan</a></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="card mb-3"><div class="card-body">
<form class="row g-2">
<div class="col-md-3"><label class="form-label">Filter by Goal</label>
<select name="goal" class="form-control"><option value="">All</option><option value="weight_loss" <?= $goalFilter==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $goalFilter==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $goalFilter==='maintain'?'selected':'' ?>>Maintain</option></select></div>
<div class="col-md-2 align-self-end"><button class="btn btn-outline w-100">Apply</button></div>
</form>
</div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>Title</th><th>Goal</th><th>Duration</th><th>Calories</th><th>Assigned Users</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($plans as $p): ?>
<tr>
<td><?= e((string)$p['title']) ?></td>
<td><?= e(ucwords(str_replace('_',' ',(string)$p['goal_type']))) ?></td>
<td><?= (int)$p['duration_days'] ?> days</td>
<td><?= (int)$p['total_calories'] ?> kcal</td>
<td><?= (int)$p['assigned_users'] ?></td>
<td><span class="badge"><?= e(ucfirst((string)$p['status'])) ?></span></td>
<td class="d-flex gap-1 flex-wrap">
<a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/dietitian/edit_plan.php?id=<?= (int)$p['id'] ?>&mode=view">View</a>
<a class="btn btn-sm btn-primary" href="<?= SITE_URL ?>/dietitian/edit_plan.php?id=<?= (int)$p['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this plan?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
<form method="post"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-secondary">Duplicate</button></form>
</td>
</tr>
<?php endforeach; if(empty($plans)): ?><tr><td colspan="7" class="text-center text-muted">No plans found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

