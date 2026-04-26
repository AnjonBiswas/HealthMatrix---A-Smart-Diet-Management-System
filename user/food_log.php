<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }
ensureNutritionSearchTables($pdo);

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

$prefill = [
 'food_name'=>(string)($_GET['prefill_name']??''),'meal_type'=>(string)($_GET['prefill_meal_type']??'snack'),
 'quantity'=>(string)($_GET['prefill_quantity']??'1'),'unit'=>(string)($_GET['prefill_unit']??'piece'),
 'calories'=>(string)($_GET['prefill_calories']??''),'protein'=>(string)($_GET['prefill_protein']??''),
 'carbs'=>(string)($_GET['prefill_carbs']??''),'fat'=>(string)($_GET['prefill_fat']??'')
];
$ingredientQuery = trim((string)($_GET['ingredient_q'] ?? ''));

function normalizeIngredientToken(string $v): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9\s]/', '', $v) ?? '';
    $v = preg_replace('/\s+/', ' ', $v) ?? '';
    return trim($v);
}

function ingredientMatchSimple(string $a, string $b): bool {
    if ($a === '' || $b === '') return false;
    return strpos($a, $b) !== false || strpos($b, $a) !== false;
}

$ingredientSuggestionRows = [];
if ($ingredientQuery !== '') {
    $userIngredients = array_values(array_filter(array_map('normalizeIngredientToken', explode(',', $ingredientQuery))));

    $sql = 'SELECT meal_name, ingredient_list, short_description, calories_est FROM ingredient_meal_suggestions';
    $params = [];
    if (!empty($userIngredients)) {
        $likeParts = [];
        foreach ($userIngredients as $idx => $token) {
            $key = ':t' . $idx;
            $likeParts[] = 'ingredient_list LIKE ' . $key;
            $params[$key] = '%' . $token . '%';
        }
        $sql .= ' WHERE ' . implode(' OR ', $likeParts);
    }
    $sql .= ' ORDER BY meal_name ASC LIMIT 60';

    $stmtMealSuggest = $pdo->prepare($sql);
    $stmtMealSuggest->execute($params);
    $candidateMeals = $stmtMealSuggest->fetchAll();

    foreach ($candidateMeals as $meal) {
        $mealIngredients = array_values(array_filter(array_map('normalizeIngredientToken', explode(',', (string)$meal['ingredient_list']))));
        $matched = [];
        $missing = [];

        foreach ($mealIngredients as $mIng) {
            $isMatch = false;
            foreach ($userIngredients as $uIng) {
                if (ingredientMatchSimple($uIng, $mIng)) {
                    $matched[] = $mIng;
                    $isMatch = true;
                    break;
                }
            }
            if (!$isMatch) $missing[] = $mIng;
        }

        if (!empty($matched)) {
            $ingredientSuggestionRows[] = [
                'meal_name' => (string)$meal['meal_name'],
                'short_description' => (string)$meal['short_description'],
                'calories_est' => $meal['calories_est'] !== null ? (int)$meal['calories_est'] : null,
                'matched' => array_values(array_unique($matched)),
                'missing' => array_values(array_unique($missing)),
                'match_count' => count(array_unique($matched)),
                'missing_count' => count(array_unique($missing)),
            ];
        }
    }

    usort($ingredientSuggestionRows, function (array $a, array $b): int {
        if ($a['match_count'] !== $b['match_count']) return $b['match_count'] <=> $a['match_count'];
        if ($a['missing_count'] !== $b['missing_count']) return $a['missing_count'] <=> $b['missing_count'];
        return strcmp($a['meal_name'], $b['meal_name']);
    });
}

$u = $pdo->prepare('SELECT daily_calorie_goal FROM users WHERE id=:id'); $u->execute([':id'=>$userId]);
$dailyGoal = (int)($u->fetch()['daily_calorie_goal'] ?? 0);

$planTarget = 0;
$a = $pdo->prepare('SELECT udp.assigned_date,dp.id pid,dp.total_calories,dp.duration_days FROM user_diet_plans udp JOIN diet_plans dp ON dp.id=udp.diet_plan_id WHERE udp.user_id=:u AND udp.status="active" ORDER BY udp.assigned_date DESC LIMIT 1');
$a->execute([':u'=>$userId]); $active = $a->fetch();
if ($active) {
  $planTarget = (int)$active['total_calories']; $dur = max(1,(int)$active['duration_days']);
  $day=((int)(new DateTime((string)$active['assigned_date']))->diff(new DateTime($date))->format('%a')%$dur)+1;
  $d=$pdo->prepare('SELECT COALESCE(SUM(calories),0) t FROM meals WHERE diet_plan_id=:p AND day_number=:d');
  $d->execute([':p'=>(int)$active['pid'],':d'=>$day]); $planTarget=(int)($d->fetch()['t']??$planTarget);
}

$q=$pdo->prepare('SELECT id,meal_type,food_name,quantity,unit,calories,protein,carbs,fat FROM food_log WHERE user_id=:u AND log_date=:d ORDER BY FIELD(meal_type,"breakfast","lunch","dinner","snack"),id');
$q->execute([':u'=>$userId,':d'=>$date]); $logs=$q->fetchAll();
$groups=['breakfast'=>[],'lunch'=>[],'dinner'=>[],'snack'=>[]]; $sub=['breakfast'=>0,'lunch'=>0,'dinner'=>0,'snack'=>0];
$totCal=0;$totP=0.0;$totC=0.0;$totF=0.0;
foreach($logs as $r){$t=(string)$r['meal_type']; if(!isset($groups[$t]))continue; $groups[$t][]=$r; $sub[$t]+=(int)$r['calories']; $totCal+=(int)$r['calories']; $totP+=(float)$r['protein']; $totC+=(float)$r['carbs']; $totF+=(float)$r['fat'];}

$f=$pdo->prepare('SELECT id,meal_name,calories,protein,carbs,fat FROM user_favorite_meals WHERE user_id=:u ORDER BY created_at DESC'); $f->execute([':u'=>$userId]); $favorites=$f->fetchAll();
$r=$pdo->prepare('SELECT fl.* FROM food_log fl JOIN (SELECT MAX(id) mx FROM food_log WHERE user_id=:u GROUP BY food_name ORDER BY MAX(created_at) DESC LIMIT 10) t ON t.mx=fl.id ORDER BY fl.created_at DESC');
$r->execute([':u'=>$userId]); $recent=$r->fetchAll();

$macro=max(0.01,$totP+$totC+$totF); $pp=(int)round($totP/$macro*100); $cp=(int)round($totC/$macro*100); $fp=max(0,100-$pp-$cp);
$diff=$totCal-$dailyGoal; $goalClass=$diff>150?'danger':($diff<-250?'warning':'success'); $goalText=$diff>0?'+'.$diff.' over goal':abs($diff).' below goal';
function e(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Log | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout">
<aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
<li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
<li class="active"><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
<li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
<li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
<li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Food Log</h5><small class="text-muted">Daily entries and quick add</small></div>
<form class="d-flex gap-2" method="get" id="ingredientSuggestForm">
<input type="hidden" name="date" value="<?= e($date) ?>">
<input class="form-control form-control-sm" style="min-width:260px;" name="ingredient_q" id="ingredientQueryInput" value="<?= e($ingredientQuery) ?>" placeholder="Ingredients (e.g. potato, oil, lentils)">
<button class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
</form></nav>
<div id="alertArea"></div>

<div class="card mb-3<?= $ingredientQuery==='' ? ' d-none' : '' ?>" id="ingredientSuggestCard"><div class="card-header">Ingredient-based Meal Suggestions
<span class="badge ms-2" id="ingredientSourceBadge"></span>
</div><div class="card-body" id="ingredientSuggestBody">
<p class="text-muted mb-2">Search input: <strong><?= e($ingredientQuery) ?></strong></p>
<?php if(!empty($ingredientSuggestionRows)): ?>
<div class="row g-2">
<?php foreach($ingredientSuggestionRows as $s): ?>
<div class="col-md-6 col-lg-4"><div class="border rounded p-3 h-100">
<h6 class="mb-1"><?= e($s['meal_name']) ?></h6>
<small class="text-muted d-block mb-2"><?= e($s['short_description']) ?></small>
<?php if($s['calories_est']!==null): ?><small class="badge d-inline-block mb-2"><?= (int)$s['calories_est'] ?> kcal (est.)</small><?php endif; ?>
<div class="mb-1"><strong>Matched:</strong> <?= !empty($s['matched']) ? e(implode(', ', $s['matched'])) : 'None' ?></div>
<div><strong>Missing:</strong> <?= !empty($s['missing']) ? e(implode(', ', $s['missing'])) : 'No missing ingredients' ?></div>
</div></div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-muted mb-0">No meal suggestion found yet. Try broader ingredients like rice, egg, lentils, potato, onion, or oil.</p>
<?php endif; ?>
</div></div>

<div class="card mb-3"><div class="card-body">
<form class="row g-2 align-items-end" method="get">
<div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="<?= e($date) ?>"></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Load</button></div>
<div class="col-md-7"><div class="d-flex gap-2 flex-wrap justify-content-md-end">
<span class="badge">Goal <?= $dailyGoal ?> kcal</span><span class="badge">Consumed <?= $totCal ?> kcal</span><span class="badge">P <?= number_format($totP,1) ?>g</span><span class="badge">C <?= number_format($totC,1) ?>g</span><span class="badge">F <?= number_format($totF,1) ?>g</span>
</div></div></form></div></div>

<div class="card mb-3"><div class="card-header">Quick Add</div><div class="card-body">
<ul class="nav nav-tabs mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t1" type="button">Manual Entry</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t2" type="button">Favorites</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t3" type="button">Recent</button></li></ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="t1">
<form id="addForm" class="row g-2"><input type="hidden" name="action" value="add"><input type="hidden" name="log_date" value="<?= e($date) ?>">
<div class="col-md-4"><label class="form-label">Food</label><input class="form-control" name="food_name" required value="<?= e($prefill['food_name']) ?>"></div>
<div class="col-md-2"><label class="form-label">Meal</label><select class="form-control" name="meal_type"><?php foreach(['breakfast','lunch','dinner','snack'] as $m):?><option value="<?= $m ?>" <?= $prefill['meal_type']===$m?'selected':'' ?>><?= ucfirst($m) ?></option><?php endforeach;?></select></div>
<div class="col-md-1"><label class="form-label">Qty</label><input type="number" step="0.01" min="0.01" class="form-control" name="quantity" value="<?= e($prefill['quantity']) ?>"></div>
<div class="col-md-1"><label class="form-label">Unit</label><select class="form-control" name="unit"><?php foreach(['g','ml','piece','cup'] as $u):?><option value="<?= $u ?>" <?= $prefill['unit']===$u?'selected':'' ?>><?= strtoupper($u) ?></option><?php endforeach;?></select></div>
<div class="col-md-1"><label class="form-label">Cal</label><input type="number" min="1" class="form-control" name="calories" required value="<?= e($prefill['calories']) ?>"></div>
<div class="col-md-1"><label class="form-label">P</label><input type="number" step="0.1" class="form-control" name="protein" value="<?= e($prefill['protein']) ?>"></div>
<div class="col-md-1"><label class="form-label">C</label><input type="number" step="0.1" class="form-control" name="carbs" value="<?= e($prefill['carbs']) ?>"></div>
<div class="col-md-1"><label class="form-label">F</label><input type="number" step="0.1" class="form-control" name="fat" value="<?= e($prefill['fat']) ?>"></div>
<div class="col-md-12"><button class="btn btn-success"><i class="fa-solid fa-plus me-1"></i>Add to Log</button></div></form></div>
<div class="tab-pane fade" id="t2"><div class="row g-2"><?php foreach($favorites as $fv):?><div class="col-md-4"><div class="border rounded p-2"><h6 class="mb-1"><?= e((string)$fv['meal_name']) ?></h6><small class="d-block text-muted mb-2"><?= (int)$fv['calories'] ?> kcal | P <?= (float)$fv['protein'] ?> C <?= (float)$fv['carbs'] ?> F <?= (float)$fv['fat'] ?></small><div class="d-flex gap-2"><button class="btn btn-primary btn-sm quick-add" data-food="<?= e((string)$fv['meal_name']) ?>" data-meal-type="snack" data-quantity="1" data-unit="piece" data-calories="<?= (int)$fv['calories'] ?>" data-protein="<?= (float)$fv['protein'] ?>" data-carbs="<?= (float)$fv['carbs'] ?>" data-fat="<?= (float)$fv['fat'] ?>">Add Today</button><button class="btn btn-outline-danger btn-sm rem-fav" data-favorite-id="<?= (int)$fv['id'] ?>"><i class="fa-solid fa-heart-crack"></i></button></div></div></div><?php endforeach; if(empty($favorites)):?><p class="text-muted">No favorites yet.</p><?php endif;?></div></div>
<div class="tab-pane fade" id="t3"><div class="row g-2"><?php foreach($recent as $rc):?><div class="col-md-4"><div class="border rounded p-2"><h6 class="mb-1"><?= e((string)$rc['food_name']) ?></h6><small class="d-block text-muted mb-2"><?= ucfirst((string)$rc['meal_type']) ?> | <?= (float)$rc['quantity'] ?> <?= e((string)$rc['unit']) ?> | <?= (int)$rc['calories'] ?> kcal</small><button class="btn btn-secondary btn-sm quick-add" data-food="<?= e((string)$rc['food_name']) ?>" data-meal-type="<?= e((string)$rc['meal_type']) ?>" data-quantity="<?= (float)$rc['quantity'] ?>" data-unit="<?= e((string)$rc['unit']) ?>" data-calories="<?= (int)$rc['calories'] ?>" data-protein="<?= (float)$rc['protein'] ?>" data-carbs="<?= (float)$rc['carbs'] ?>" data-fat="<?= (float)$rc['fat'] ?>">Add Again</button></div></div><?php endforeach; if(empty($recent)):?><p class="text-muted">No recent food entries.</p><?php endif;?></div></div>
</div></div></div>

<div class="card mb-3"><div class="card-header">Today's Food Log</div><div class="card-body table-responsive">
<table class="table table-striped table-hover"><thead><tr><th>Meal</th><th>Food</th><th>Qty</th><th>Calories</th><th>P/C/F</th><th>Actions</th></tr></thead><tbody>
<?php foreach(['breakfast','lunch','dinner','snack'] as $t): if(!empty($groups[$t])): foreach($groups[$t] as $row): ?>
<tr><td><?= ucfirst((string)$row['meal_type']) ?></td><td><?= e((string)$row['food_name']) ?></td><td><?= (float)$row['quantity'].' '.e((string)$row['unit']) ?></td><td><?= (int)$row['calories'] ?></td><td><?= (float)$row['protein'] ?>/<?= (float)$row['carbs'] ?>/<?= (float)$row['fat'] ?> g</td><td>
<button class="btn btn-sm btn-outline edit-btn" data-log='<?= json_encode(['id'=>(int)$row['id'],'food_name'=>(string)$row['food_name'],'meal_type'=>(string)$row['meal_type'],'quantity'=>(float)$row['quantity'],'unit'=>(string)$row['unit'],'calories'=>(int)$row['calories'],'protein'=>(float)$row['protein'],'carbs'=>(float)$row['carbs'],'fat'=>(float)$row['fat']], JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>Edit</button>
<button class="btn btn-sm btn-danger del-btn" data-log-id="<?= (int)$row['id'] ?>">Delete</button>
<button class="btn btn-sm btn-outline-danger fav-btn" data-log-id="<?= (int)$row['id'] ?>"><i class="fa-solid fa-heart"></i></button>
</td></tr>
<?php endforeach; ?><tr class="table-light"><td colspan="3"><strong><?= ucfirst($t) ?> Subtotal</strong></td><td colspan="3"><strong><?= (int)$sub[$t] ?> kcal</strong></td></tr><?php endif; endforeach; if(empty($logs)): ?><tr><td colspan="6" class="text-center text-muted">No logs for this date.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="card"><div class="card-header">Daily Summary</div><div class="card-body"><div class="row g-3">
<div class="col-md-4"><div class="border rounded p-3"><h6>Total vs Goal</h6><p class="mb-1"><strong><?= $totCal ?> / <?= $dailyGoal ?> kcal</strong></p><span class="badge bg-<?= $goalClass ?>-subtle text-<?= $goalClass ?>-emphasis"><?= e($goalText) ?></span></div></div>
<div class="col-md-4"><div class="border rounded p-3"><h6>Macros</h6><div class="progress mb-1"><div class="progress-bar bg-success" style="width:<?= $pp ?>%"></div><div class="progress-bar bg-warning" style="width:<?= $cp ?>%"></div><div class="progress-bar bg-info" style="width:<?= $fp ?>%"></div></div><small>P <?= $pp ?>% | C <?= $cp ?>% | F <?= $fp ?>%</small></div></div>
<div class="col-md-4"><div class="border rounded p-3"><h6>Plan Target</h6><p class="mb-1"><strong><?= $totCal ?> / <?= $planTarget ?> kcal</strong></p><small class="text-muted"><?= $planTarget>0?(($totCal-$planTarget)>=0?'+':'').($totCal-$planTarget).' kcal difference':'No active plan target found.' ?></small></div></div>
</div></div></div>
</div></main></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Edit Entry</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><form id="editForm" class="row g-2">
<input type="hidden" name="action" value="edit"><input type="hidden" name="log_id" id="eid">
<div class="col-12"><label class="form-label">Food</label><input class="form-control" name="food_name" id="efn" required></div>
<div class="col-6"><label class="form-label">Meal</label><select class="form-control" name="meal_type" id="emt"><option value="breakfast">Breakfast</option><option value="lunch">Lunch</option><option value="dinner">Dinner</option><option value="snack">Snack</option></select></div>
<div class="col-6"><label class="form-label">Calories</label><input type="number" min="1" class="form-control" name="calories" id="ecal" required></div>
<div class="col-6"><label class="form-label">Qty</label><input type="number" step="0.01" class="form-control" name="quantity" id="eqty"></div>
<div class="col-6"><label class="form-label">Unit</label><select class="form-control" name="unit" id="eunit"><option value="g">G</option><option value="ml">ML</option><option value="piece">Piece</option><option value="cup">Cup</option></select></div>
<div class="col-4"><label class="form-label">P</label><input type="number" step="0.1" class="form-control" name="protein" id="ep"></div>
<div class="col-4"><label class="form-label">C</label><input type="number" step="0.1" class="form-control" name="carbs" id="ec"></div>
<div class="col-4"><label class="form-label">F</label><input type="number" step="0.1" class="form-control" name="fat" id="ef"></div>
</form></div><div class="modal-footer"><button class="btn btn-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="saveEdit">Save</button></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-collapsed'));
const api='<?= SITE_URL ?>/api/log_food.php', date='<?= e($date) ?>', alertArea=document.getElementById('alertArea');
const show=(m,t='success')=>alertArea.innerHTML=`<div class="alert alert-${t}">${m}</div>`;
const post=async(d)=>{const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)});return await r.json();};
document.getElementById('addForm')?.addEventListener('submit',async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.currentTarget).entries());const rs=await post(data);if(rs.success){show(rs.message);setTimeout(()=>location.reload(),300);}else show(rs.message||'Failed','danger');});
document.querySelectorAll('.quick-add').forEach(b=>b.addEventListener('click',async()=>{const rs=await post({action:'add',log_date:date,food_name:b.dataset.food,meal_type:b.dataset.mealType,quantity:b.dataset.quantity||1,unit:b.dataset.unit||'piece',calories:b.dataset.calories,protein:b.dataset.protein||0,carbs:b.dataset.carbs||0,fat:b.dataset.fat||0}); if(rs.success){show(rs.message);setTimeout(()=>location.reload(),300);} else show(rs.message||'Failed','danger');}));
document.querySelectorAll('.del-btn').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Delete this entry?'))return;const rs=await post({action:'delete',log_id:b.dataset.logId}); if(rs.success){show(rs.message);setTimeout(()=>location.reload(),200);} else show(rs.message||'Delete failed','danger');}));
document.querySelectorAll('.fav-btn').forEach(b=>b.addEventListener('click',async()=>{const rs=await post({action:'add_favorite',log_id:b.dataset.logId});show(rs.message,rs.success?'success':'warning');}));
document.querySelectorAll('.rem-fav').forEach(b=>b.addEventListener('click',async()=>{const rs=await post({action:'remove_favorite',favorite_id:b.dataset.favoriteId}); if(rs.success){show(rs.message);setTimeout(()=>location.reload(),200);} else show(rs.message||'Failed','danger');}));
const modal=new bootstrap.Modal(document.getElementById('editModal'));
const eid=document.getElementById('eid'), efn=document.getElementById('efn'), emt=document.getElementById('emt'), eqty=document.getElementById('eqty'), eunit=document.getElementById('eunit'), ecal=document.getElementById('ecal'), ep=document.getElementById('ep'), ec=document.getElementById('ec'), ef=document.getElementById('ef');
document.querySelectorAll('.edit-btn').forEach(b=>b.addEventListener('click',()=>{const d=JSON.parse(b.dataset.log); eid.value=d.id; efn.value=d.food_name; emt.value=d.meal_type; eqty.value=d.quantity; eunit.value=d.unit; ecal.value=d.calories; ep.value=d.protein; ec.value=d.carbs; ef.value=d.fat; modal.show();}));
document.getElementById('saveEdit')?.addEventListener('click',async()=>{const rs=await post(Object.fromEntries(new FormData(document.getElementById('editForm')).entries())); if(rs.success){modal.hide();show(rs.message);setTimeout(()=>location.reload(),250);} else show(rs.message||'Update failed','danger');});

const ingredientForm = document.getElementById('ingredientSuggestForm');
const ingredientInput = document.getElementById('ingredientQueryInput');
const ingredientCard = document.getElementById('ingredientSuggestCard');
const ingredientBody = document.getElementById('ingredientSuggestBody');
const ingredientSourceBadge = document.getElementById('ingredientSourceBadge');

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function renderIngredientSuggestions(payload, q) {
    if (!ingredientBody) return;
    const rows = Array.isArray(payload.data) ? payload.data : [];
    let html = `<p class="text-muted mb-2">Search input: <strong>${esc(q)}</strong></p>`;
    if (!rows.length) {
        html += `<p class="text-muted mb-0">${esc(payload.message || 'No meal suggestions found.')}</p>`;
        ingredientBody.innerHTML = html;
        return;
    }
    html += '<div class="row g-2">';
    rows.forEach((row) => {
        const mealName = esc(row.meal_name || '');
        const desc = esc(row.description || '');
        const calories = row.calories !== null && row.calories !== undefined ? `${Number(row.calories)} kcal (est.)` : '';
        const matched = Array.isArray(row.matched_ingredients) && row.matched_ingredients.length ? esc(row.matched_ingredients.join(', ')) : 'None';
        const missing = Array.isArray(row.missing_ingredients) && row.missing_ingredients.length ? esc(row.missing_ingredients.join(', ')) : 'No missing ingredients';
        html += `<div class="col-md-6 col-lg-4"><div class="border rounded p-3 h-100"><h6 class="mb-1">${mealName}</h6><small class="text-muted d-block mb-2">${desc}</small>${calories ? `<small class="badge d-inline-block mb-2">${esc(calories)}</small>` : ''}<div class="mb-1"><strong>Matched:</strong> ${matched}</div><div><strong>Missing:</strong> ${missing}</div></div></div>`;
    });
    html += '</div>';
    ingredientBody.innerHTML = html;
}

if (ingredientForm && ingredientInput) {
    ingredientForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = ingredientInput.value.trim();
        if (!q) {
            if (ingredientCard) ingredientCard.classList.add('d-none');
            return;
        }
        if (ingredientCard) ingredientCard.classList.remove('d-none');
        if (ingredientBody) ingredientBody.innerHTML = '<p class="text-muted mb-0">Finding suggestions...</p>';
        if (ingredientSourceBadge) {
            ingredientSourceBadge.className = 'badge ms-2 bg-light text-dark';
            ingredientSourceBadge.textContent = 'Loading';
        }
        try {
            const res = await fetch(`<?= SITE_URL ?>/api/ingredient_meal_suggestions.php?ingredients=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                renderIngredientSuggestions({ data: [], message: data.message || 'Suggestion search failed.' }, q);
                if (ingredientSourceBadge) {
                    ingredientSourceBadge.className = 'badge ms-2 bg-danger-subtle text-danger-emphasis';
                    ingredientSourceBadge.textContent = 'Error';
                }
                return;
            }
            renderIngredientSuggestions(data, q);
            if (ingredientSourceBadge) {
                const s = String(data.source || 'db').toLowerCase();
                if (s === 'ai') {
                    ingredientSourceBadge.className = 'badge ms-2 bg-warning-subtle text-warning-emphasis';
                    ingredientSourceBadge.textContent = 'AI Result';
                } else if (s === 'db') {
                    ingredientSourceBadge.className = 'badge ms-2 bg-success-subtle text-success-emphasis';
                    ingredientSourceBadge.textContent = 'DB Result';
                } else {
                    ingredientSourceBadge.className = 'badge ms-2 bg-secondary-subtle text-secondary-emphasis';
                    ingredientSourceBadge.textContent = 'No Result';
                }
            }
        } catch (err) {
            renderIngredientSuggestions({ data: [], message: 'Network error while getting suggestions.' }, q);
            if (ingredientSourceBadge) {
                ingredientSourceBadge.className = 'badge ms-2 bg-danger-subtle text-danger-emphasis';
                ingredientSourceBadge.textContent = 'Error';
            }
        }
    });
}
</script>
</body></html>
