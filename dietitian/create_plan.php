<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/meal_template_library.php';
redirectIfNotLoggedIn(['dietitian']);

$pdo = Database::getInstance()->getConnection();
$dietitianId = (int) ($_SESSION['user_id'] ?? 0);
if ($dietitianId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }
ensureDietitianStarterTemplates($pdo, $dietitianId);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'template') {
    header('Content-Type: application/json; charset=utf-8');
    $templateId = (int) ($_GET['template_id'] ?? 0);
    if ($templateId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid template']); exit; }
    $q = $pdo->prepare('SELECT id,template_name,meal_data FROM meal_templates WHERE id=:id AND dietitian_id=:d LIMIT 1');
    $q->execute([':id' => $templateId, ':d' => $dietitianId]);
    $t = $q->fetch();
    if (!$t) { echo json_encode(['success' => false, 'message' => 'Template not found']); exit; }
    echo json_encode(['success' => true, 'template' => ['id' => (int)$t['id'], 'name' => $t['template_name'], 'meals' => json_decode((string)$t['meal_data'], true)]]);
    exit;
}

$alerts = [];
$templates = $pdo->prepare('SELECT id,template_name FROM meal_templates WHERE dietitian_id=:d ORDER BY created_at DESC');
$templates->execute([':d' => $dietitianId]);
$templateRows = $templates->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $goalType = (string) ($_POST['goal_type'] ?? 'maintain');
    $durationDays = (int) ($_POST['duration_days'] ?? 0);
    $totalCalories = (int) ($_POST['total_calories'] ?? 0);
    $statusInput = (string) ($_POST['status'] ?? 'active');
    $saveMode = (string) ($_POST['save_mode'] ?? 'publish');
    $templateName = trim((string) ($_POST['template_name'] ?? ''));
    $mealsJson = (string) ($_POST['meals_json'] ?? '[]');
    $meals = json_decode($mealsJson, true);

    $allowedGoals = ['weight_loss', 'gain', 'maintain'];
    $errors = [];
    if ($title === '' || mb_strlen($title) < 3) $errors[] = 'Title is required (min 3 chars).';
    if (!in_array($goalType, $allowedGoals, true)) $errors[] = 'Invalid goal type.';
    if ($durationDays < 1 || $durationDays > 120) $errors[] = 'Duration must be 1-120 days.';
    if ($totalCalories < 500 || $totalCalories > 8000) $errors[] = 'Total calories must be 500-8000.';
    if (!is_array($meals)) $errors[] = 'Invalid meal builder data.';

    if (empty($errors)) {
        $status = ($statusInput === 'draft' || $saveMode === 'save_draft') ? 'inactive' : 'active';
        try {
            $pdo->beginTransaction();
            $insPlan = $pdo->prepare(
                'INSERT INTO diet_plans (title,description,dietitian_id,goal_type,total_calories,duration_days,status,created_at)
                 VALUES (:t,:d,:di,:g,:c,:du,:s,NOW())'
            );
            $insPlan->execute([
                ':t' => $title, ':d' => $description, ':di' => $dietitianId, ':g' => $goalType,
                ':c' => $totalCalories, ':du' => $durationDays, ':s' => $status,
            ]);
            $planId = (int) $pdo->lastInsertId();

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
                    if ($mealName === '' || $cal <= 0 || !in_array($mealType, ['breakfast', 'lunch', 'dinner', 'snack'], true)) continue;
                    $insMeal->execute([
                        ':p' => $planId, ':mt' => $mealType, ':mn' => $mealName, ':ds' => (string) ($m['description'] ?? ''),
                        ':c' => $cal, ':pr' => (float) ($m['protein'] ?? 0), ':cb' => (float) ($m['carbs'] ?? 0),
                        ':f' => (float) ($m['fat'] ?? 0), ':day' => $dayN,
                    ]);
                }
            }

            if ($saveMode === 'save_template') {
                if ($templateName === '') $templateName = $title . ' Template';
                $insTpl = $pdo->prepare(
                    'INSERT INTO meal_templates (dietitian_id,template_name,meal_data,created_at)
                     VALUES (:d,:n,:m,NOW())'
                );
                $insTpl->execute([
                    ':d' => $dietitianId,
                    ':n' => $templateName,
                    ':m' => json_encode($meals, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $pdo->commit();
            logActivity($dietitianId, 'dietitian', 'Created diet plan');
            header('Location: ' . SITE_URL . '/dietitian/edit_plan.php?id=' . $planId . '&created=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Failed to create plan.';
        }
    }

    foreach ($errors as $err) $alerts[] = ['type' => 'danger', 'text' => $err];
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Plan | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li class="active"><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Create Diet Plan</h5><small class="text-muted">Build meals by day with live calories</small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<form method="post" id="planForm">
<input type="hidden" name="meals_json" id="mealsJson">
<input type="hidden" name="save_mode" id="saveMode" value="publish">
<div class="card mb-3"><div class="card-header">Section 1 - Plan Details</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
<div class="col-md-6"><label class="form-label">Goal Type</label><select class="form-control" name="goal_type" required><option value="weight_loss">Weight Loss</option><option value="gain">Gain</option><option value="maintain">Maintain</option></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
<div class="col-md-3"><label class="form-label">Duration (days)</label><input type="number" min="1" max="120" class="form-control" name="duration_days" id="durationDays" value="7" required></div>
<div class="col-md-3"><label class="form-label">Total Calories Target</label><input type="number" min="500" class="form-control" name="total_calories" id="totalCaloriesTarget" value="2000" required></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-control" name="status"><option value="active">Active</option><option value="draft">Draft</option></select></div>
<div class="col-md-3"><label class="form-label">Load Template (AJAX)</label><div class="d-flex gap-2"><select id="templateSelect" class="form-control"><option value="">None</option><?php foreach($templateRows as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e((string)$t['template_name']) ?></option><?php endforeach; ?></select><button type="button" class="btn btn-outline" id="loadTemplateBtn">Load</button></div></div>
</div></div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Section 2 - Meal Builder</span><span>Running Total: <strong id="runningTotal">0</strong> kcal</span></div><div class="card-body">
<ul class="nav nav-tabs mb-3" id="dayTabs"></ul>
<div class="tab-content" id="dayContent"></div>
</div></div>

<div class="card"><div class="card-header">Section 3 - Save Options</div><div class="card-body row g-2">
<div class="col-md-4"><button type="submit" class="btn btn-secondary w-100 save-btn" data-mode="save_draft">Save as Draft</button></div>
<div class="col-md-4"><button type="submit" class="btn btn-success w-100 save-btn" data-mode="publish">Publish Plan</button></div>
<div class="col-md-4">
<div class="input-group"><input type="text" class="form-control" name="template_name" placeholder="Template name (optional)"><button type="submit" class="btn btn-primary save-btn" data-mode="save_template">Save as Template</button></div>
</div>
</div></div>
</form>
</div></main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const durationInput=document.getElementById('durationDays'), dayTabs=document.getElementById('dayTabs'), dayContent=document.getElementById('dayContent'), runningTotal=document.getElementById('runningTotal'), mealsJson=document.getElementById('mealsJson'), form=document.getElementById('planForm');
let mealState={};
function mealRow(day,mealType,m={}){const id=crypto.randomUUID();return `<div class="border rounded p-2 mb-2 meal-row" data-id="${id}" data-day="${day}">
<div class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">Type</label><select class="form-control meal-type"><option value="breakfast" ${mealType==='breakfast'?'selected':''}>Breakfast</option><option value="lunch" ${mealType==='lunch'?'selected':''}>Lunch</option><option value="dinner" ${mealType==='dinner'?'selected':''}>Dinner</option><option value="snack" ${mealType==='snack'?'selected':''}>Snack</option></select></div>
<div class="col-md-3"><label class="form-label">Meal Name</label><input class="form-control meal-name" value="${m.meal_name||''}"></div>
<div class="col-md-3"><label class="form-label">Description</label><input class="form-control meal-description" value="${m.description||''}"></div>
<div class="col-md-1"><label class="form-label">Cal</label><input type="number" min="0" class="form-control meal-cal" value="${m.calories||0}"></div>
<div class="col-md-1"><label class="form-label">P</label><input type="number" step="0.1" class="form-control meal-protein" value="${m.protein||0}"></div>
<div class="col-md-1"><label class="form-label">C</label><input type="number" step="0.1" class="form-control meal-carbs" value="${m.carbs||0}"></div>
<div class="col-md-1"><label class="form-label">F</label><input type="number" step="0.1" class="form-control meal-fat" value="${m.fat||0}"></div>
<div class="col-md-12"><button type="button" class="btn btn-danger btn-sm remove-meal">Remove</button></div></div></div>`;}
function collectState(){mealState={};document.querySelectorAll('.meal-row').forEach(r=>{const day=r.dataset.day;if(!mealState[day])mealState[day]=[];mealState[day].push({meal_type:r.querySelector('.meal-type').value,meal_name:r.querySelector('.meal-name').value.trim(),description:r.querySelector('.meal-description').value.trim(),calories:parseInt(r.querySelector('.meal-cal').value||'0',10),protein:parseFloat(r.querySelector('.meal-protein').value||'0'),carbs:parseFloat(r.querySelector('.meal-carbs').value||'0'),fat:parseFloat(r.querySelector('.meal-fat').value||'0')});});
let t=0;Object.values(mealState).forEach(rows=>rows.forEach(m=>{t+=parseInt(m.calories||0,10)||0;}));runningTotal.textContent=t;mealsJson.value=JSON.stringify(mealState);}
function renderBuilder(seed=null){const dur=Math.max(1,parseInt(durationInput.value||'7',10));dayTabs.innerHTML='';dayContent.innerHTML='';for(let d=1;d<=dur;d++){dayTabs.insertAdjacentHTML('beforeend',`<li class="nav-item"><button class="nav-link ${d===1?'active':''}" data-bs-toggle="tab" data-bs-target="#day${d}" type="button">Day ${d}</button></li>`);
const defaults=['breakfast','lunch','dinner','snack'];let rows='';if(seed&&seed[d]){seed[d].forEach(m=>rows+=mealRow(d,m.meal_type||'snack',m));} else {defaults.forEach(mt=>rows+=mealRow(d,mt,{}));}
dayContent.insertAdjacentHTML('beforeend',`<div class="tab-pane fade ${d===1?'show active':''}" id="day${d}"><div class="mb-2"><button type="button" class="btn btn-outline btn-sm add-meal" data-day="${d}">Add Meal</button></div><div id="dayRows${d}">${rows}</div></div>`);}
bindMealEvents();collectState();}
function bindMealEvents(){document.querySelectorAll('.add-meal').forEach(b=>b.onclick=()=>{const d=b.dataset.day;document.getElementById('dayRows'+d).insertAdjacentHTML('beforeend',mealRow(d,'snack',{}));bindMealEvents();collectState();});
document.querySelectorAll('.remove-meal').forEach(b=>b.onclick=()=>{b.closest('.meal-row').remove();collectState();});
document.querySelectorAll('.meal-row input,.meal-row select').forEach(i=>i.oninput=collectState);}
durationInput.addEventListener('change',()=>renderBuilder(mealState));
document.querySelectorAll('.save-btn').forEach(b=>b.addEventListener('click',()=>document.getElementById('saveMode').value=b.dataset.mode));
form.addEventListener('submit',e=>{collectState();if(!Object.keys(mealState).length){e.preventDefault();alert('Please add meals before saving.');}});
document.getElementById('loadTemplateBtn').addEventListener('click',async()=>{const id=document.getElementById('templateSelect').value;if(!id)return;const r=await fetch(`<?= SITE_URL ?>/dietitian/create_plan.php?ajax=template&template_id=${id}`);const j=await r.json();if(!j.success){alert(j.message||'Template load failed');return;}const seed=j.template.meals||{};const maxDay=Math.max(...Object.keys(seed).map(Number),7);durationInput.value=maxDay;renderBuilder(seed);});
renderBuilder();
const qp=new URLSearchParams(window.location.search);const tid=qp.get('template_id');if(tid){document.getElementById('templateSelect').value=tid;document.getElementById('loadTemplateBtn').click();}
</script>
</body></html>
