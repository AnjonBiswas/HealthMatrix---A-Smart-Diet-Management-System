<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['dietitian']);

$pdo = Database::getInstance()->getConnection();
$dietitianId = (int) ($_SESSION['user_id'] ?? 0);
if ($dietitianId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$planId = (int) ($_GET['id'] ?? 0);
$mode = (string) ($_GET['mode'] ?? 'edit');
$isView = $mode === 'view';
if ($planId <= 0) { header('Location: ' . SITE_URL . '/dietitian/diet_plans.php'); exit; }

$alerts = [];
if (isset($_GET['created']) && $_GET['created'] === '1') {
    $alerts[] = ['type' => 'success', 'text' => 'Plan created successfully.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isView) {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $goalType = (string) ($_POST['goal_type'] ?? 'maintain');
    $durationDays = (int) ($_POST['duration_days'] ?? 0);
    $totalCalories = (int) ($_POST['total_calories'] ?? 0);
    $statusInput = (string) ($_POST['status'] ?? 'active');
    $mealsJson = (string) ($_POST['meals_json'] ?? '[]');
    $meals = json_decode($mealsJson, true);

    $errors = [];
    if ($title === '' || mb_strlen($title) < 3) $errors[] = 'Title is required.';
    if (!in_array($goalType, ['weight_loss', 'gain', 'maintain'], true)) $errors[] = 'Invalid goal type.';
    if ($durationDays < 1 || $durationDays > 120) $errors[] = 'Duration must be 1-120 days.';
    if ($totalCalories < 500 || $totalCalories > 8000) $errors[] = 'Calories target out of range.';
    if (!is_array($meals)) $errors[] = 'Invalid meals payload.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $status = $statusInput === 'draft' ? 'inactive' : 'active';
            $up = $pdo->prepare(
                'UPDATE diet_plans
                 SET title=:t,description=:d,goal_type=:g,total_calories=:c,duration_days=:du,status=:s
                 WHERE id=:id AND dietitian_id=:di'
            );
            $up->execute([
                ':t' => $title, ':d' => $description, ':g' => $goalType, ':c' => $totalCalories,
                ':du' => $durationDays, ':s' => $status, ':id' => $planId, ':di' => $dietitianId,
            ]);

            $delMeals = $pdo->prepare('DELETE FROM meals WHERE diet_plan_id=:id');
            $delMeals->execute([':id' => $planId]);
            $insMeal = $pdo->prepare(
                'INSERT INTO meals (diet_plan_id,meal_type,meal_name,description,calories,protein,carbs,fat,day_number,created_at)
                 VALUES (:p,:mt,:mn,:ds,:c,:pr,:cb,:f,:day,NOW())'
            );
            foreach ($meals as $day => $rows) {
                $dayN = (int) $day;
                if ($dayN < 1 || $dayN > $durationDays || !is_array($rows)) continue;
                foreach ($rows as $m) {
                    $mealType = (string) ($m['meal_type'] ?? '');
                    $mealName = trim((string) ($m['meal_name'] ?? ''));
                    $cal = (int) ($m['calories'] ?? 0);
                    if ($mealName === '' || $cal <= 0 || !in_array($mealType, ['breakfast','lunch','dinner','snack'], true)) continue;
                    $insMeal->execute([
                        ':p' => $planId, ':mt' => $mealType, ':mn' => $mealName, ':ds' => (string) ($m['description'] ?? ''),
                        ':c' => $cal, ':pr' => (float) ($m['protein'] ?? 0), ':cb' => (float) ($m['carbs'] ?? 0),
                        ':f' => (float) ($m['fat'] ?? 0), ':day' => $dayN,
                    ]);
                }
            }
            $pdo->commit();
            $alerts[] = ['type' => 'success', 'text' => 'Plan updated successfully.'];
            logActivity($dietitianId, 'dietitian', 'Updated diet plan');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $alerts[] = ['type' => 'danger', 'text' => 'Update failed.'];
        }
    } else {
        foreach ($errors as $err) $alerts[] = ['type' => 'danger', 'text' => $err];
    }
}

$planQ = $pdo->prepare('SELECT * FROM diet_plans WHERE id=:id AND dietitian_id=:d LIMIT 1');
$planQ->execute([':id' => $planId, ':d' => $dietitianId]);
$plan = $planQ->fetch();
if (!$plan) { header('Location: ' . SITE_URL . '/dietitian/diet_plans.php'); exit; }

$mealQ = $pdo->prepare('SELECT day_number,meal_type,meal_name,description,calories,protein,carbs,fat FROM meals WHERE diet_plan_id=:id ORDER BY day_number ASC, id ASC');
$mealQ->execute([':id' => $planId]);
$meals = $mealQ->fetchAll();
$mealSeed = [];
foreach ($meals as $m) {
    $day = (int) $m['day_number'];
    if (!isset($mealSeed[$day])) $mealSeed[$day] = [];
    $mealSeed[$day][] = [
        'meal_type' => (string) $m['meal_type'],
        'meal_name' => (string) $m['meal_name'],
        'description' => (string) $m['description'],
        'calories' => (int) $m['calories'],
        'protein' => (float) $m['protein'],
        'carbs' => (float) $m['carbs'],
        'fat' => (float) $m['fat'],
    ];
}

$usersQ = $pdo->prepare(
    'SELECT u.full_name, u.email, udp.status, udp.assigned_date, udp.end_date
     FROM user_diet_plans udp
     JOIN users u ON u.id=udp.user_id
     WHERE udp.diet_plan_id=:p
     ORDER BY udp.assigned_date DESC'
);
$usersQ->execute([':p' => $planId]);
$planUsers = $usersQ->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isView ? 'View' : 'Edit' ?> Plan | HealthMatrix</title>
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
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0"><?= $isView ? 'View' : 'Edit' ?> Plan</h5><small class="text-muted"><?= e((string)$plan['title']) ?></small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<form method="post" id="planForm">
<input type="hidden" name="meals_json" id="mealsJson">
<div class="card mb-3"><div class="card-header">Plan Details</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="<?= e((string)$plan['title']) ?>" <?= $isView?'readonly':'' ?>></div>
<div class="col-md-6"><label class="form-label">Goal Type</label><select class="form-control" name="goal_type" <?= $isView?'disabled':'' ?>><option value="weight_loss" <?= $plan['goal_type']==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $plan['goal_type']==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $plan['goal_type']==='maintain'?'selected':'' ?>>Maintain</option></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" <?= $isView?'readonly':'' ?>><?= e((string)$plan['description']) ?></textarea></div>
<div class="col-md-3"><label class="form-label">Duration</label><input type="number" min="1" max="120" class="form-control" name="duration_days" id="durationDays" value="<?= (int)$plan['duration_days'] ?>" <?= $isView?'readonly':'' ?>></div>
<div class="col-md-3"><label class="form-label">Total Calories</label><input type="number" class="form-control" name="total_calories" id="totalCaloriesTarget" value="<?= (int)$plan['total_calories'] ?>" <?= $isView?'readonly':'' ?>></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-control" name="status" <?= $isView?'disabled':'' ?>><option value="active" <?= $plan['status']==='active'?'selected':'' ?>>Active</option><option value="draft" <?= $plan['status']==='inactive'?'selected':'' ?>>Draft</option></select></div>
</div></div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Meal Builder</span><span>Running Total: <strong id="runningTotal">0</strong> kcal</span></div><div class="card-body">
<ul class="nav nav-tabs mb-3" id="dayTabs"></ul><div class="tab-content" id="dayContent"></div>
</div></div>

<?php if(!$isView): ?><div class="card mb-3"><div class="card-body"><button class="btn btn-success">Update Plan</button></div></div><?php endif; ?>
</form>

<div class="card"><div class="card-header">Users On This Plan</div><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Start</th><th>End</th></tr></thead><tbody>
<?php foreach($planUsers as $u): ?><tr><td><?= e((string)$u['full_name']) ?></td><td><?= e((string)$u['email']) ?></td><td><?= e((string)$u['status']) ?></td><td><?= e((string)$u['assigned_date']) ?></td><td><?= e((string)$u['end_date']) ?></td></tr><?php endforeach; if(empty($planUsers)): ?><tr><td colspan="5" class="text-center text-muted">No assigned users yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div></main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const READONLY=<?= $isView?'true':'false' ?>;
const durationInput=document.getElementById('durationDays'), dayTabs=document.getElementById('dayTabs'), dayContent=document.getElementById('dayContent'), runningTotal=document.getElementById('runningTotal'), mealsJson=document.getElementById('mealsJson');
let mealState=<?= json_encode($mealSeed, JSON_UNESCAPED_UNICODE) ?>;
function mealRow(day,mealType,m={}){return `<div class="border rounded p-2 mb-2 meal-row" data-day="${day}"><div class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">Type</label><select class="form-control meal-type" ${READONLY?'disabled':''}><option value="breakfast" ${mealType==='breakfast'?'selected':''}>Breakfast</option><option value="lunch" ${mealType==='lunch'?'selected':''}>Lunch</option><option value="dinner" ${mealType==='dinner'?'selected':''}>Dinner</option><option value="snack" ${mealType==='snack'?'selected':''}>Snack</option></select></div><div class="col-md-3"><label class="form-label">Meal Name</label><input class="form-control meal-name" value="${m.meal_name||''}" ${READONLY?'readonly':''}></div><div class="col-md-3"><label class="form-label">Description</label><input class="form-control meal-description" value="${m.description||''}" ${READONLY?'readonly':''}></div><div class="col-md-1"><label class="form-label">Cal</label><input type="number" class="form-control meal-cal" value="${m.calories||0}" ${READONLY?'readonly':''}></div><div class="col-md-1"><label class="form-label">P</label><input type="number" step="0.1" class="form-control meal-protein" value="${m.protein||0}" ${READONLY?'readonly':''}></div><div class="col-md-1"><label class="form-label">C</label><input type="number" step="0.1" class="form-control meal-carbs" value="${m.carbs||0}" ${READONLY?'readonly':''}></div><div class="col-md-1"><label class="form-label">F</label><input type="number" step="0.1" class="form-control meal-fat" value="${m.fat||0}" ${READONLY?'readonly':''}></div>${READONLY?'':`<div class="col-md-12"><button type="button" class="btn btn-danger btn-sm remove-meal">Remove</button></div>`}</div></div>`;}
function collectState(){mealState={};document.querySelectorAll('.meal-row').forEach(r=>{const day=r.dataset.day;if(!mealState[day])mealState[day]=[];mealState[day].push({meal_type:r.querySelector('.meal-type').value,meal_name:r.querySelector('.meal-name').value.trim(),description:r.querySelector('.meal-description').value.trim(),calories:parseInt(r.querySelector('.meal-cal').value||'0',10),protein:parseFloat(r.querySelector('.meal-protein').value||'0'),carbs:parseFloat(r.querySelector('.meal-carbs').value||'0'),fat:parseFloat(r.querySelector('.meal-fat').value||'0')});});let t=0;Object.values(mealState).forEach(rows=>rows.forEach(m=>t+=parseInt(m.calories||0,10)||0));runningTotal.textContent=t;mealsJson.value=JSON.stringify(mealState);}
function renderBuilder(seed){const dur=Math.max(1,parseInt(durationInput.value||'7',10));dayTabs.innerHTML='';dayContent.innerHTML='';for(let d=1;d<=dur;d++){dayTabs.insertAdjacentHTML('beforeend',`<li class="nav-item"><button class="nav-link ${d===1?'active':''}" data-bs-toggle="tab" data-bs-target="#day${d}" type="button">Day ${d}</button></li>`);let rows='';if(seed&&seed[d])seed[d].forEach(m=>rows+=mealRow(d,m.meal_type||'snack',m));else ['breakfast','lunch','dinner','snack'].forEach(mt=>rows+=mealRow(d,mt,{}));dayContent.insertAdjacentHTML('beforeend',`<div class="tab-pane fade ${d===1?'show active':''}" id="day${d}">${READONLY?'':`<div class="mb-2"><button type="button" class="btn btn-outline btn-sm add-meal" data-day="${d}">Add Meal</button></div>`}<div id="dayRows${d}">${rows}</div></div>`);}bindEvents();collectState();}
function bindEvents(){if(!READONLY){document.querySelectorAll('.add-meal').forEach(b=>b.onclick=()=>{const d=b.dataset.day;document.getElementById('dayRows'+d).insertAdjacentHTML('beforeend',mealRow(d,'snack',{}));bindEvents();collectState();});document.querySelectorAll('.remove-meal').forEach(b=>b.onclick=()=>{b.closest('.meal-row').remove();collectState();});}
document.querySelectorAll('.meal-row input,.meal-row select').forEach(i=>i.oninput=collectState);}
renderBuilder(mealState);
</script>
</body></html>

