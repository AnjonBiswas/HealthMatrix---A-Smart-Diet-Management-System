<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['admin']);

$pdo = Database::getInstance()->getConnection();
$alerts = [];

$featureColumnExists = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM diet_plans LIKE 'is_featured'")->fetch();
    $featureColumnExists = (bool) $col;
} catch (Throwable) {
    $featureColumnExists = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create' || $action === 'edit') {
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $dietitianId = (int) ($_POST['dietitian_id'] ?? 0);
            $goal = (string) ($_POST['goal_type'] ?? 'maintain');
            $duration = (int) ($_POST['duration_days'] ?? 0);
            $calories = (int) ($_POST['total_calories'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');
            $mealsJson = (string) ($_POST['meals_json'] ?? '[]');
            $meals = json_decode($mealsJson, true);

            $errs = [];
            if ($title === '' || mb_strlen($title) < 3) $errs[] = 'Title is required.';
            if ($dietitianId <= 0) $errs[] = 'Dietitian is required.';
            if (!in_array($goal, ['weight_loss', 'gain', 'maintain'], true)) $errs[] = 'Invalid goal.';
            if ($duration < 1 || $duration > 120) $errs[] = 'Invalid duration.';
            if ($calories < 500 || $calories > 8000) $errs[] = 'Invalid calories.';
            if (!in_array($status, ['active', 'inactive'], true)) $errs[] = 'Invalid status.';
            if (!is_array($meals)) $errs[] = 'Invalid meals payload.';

            if (empty($errs)) {
                $pdo->beginTransaction();
                if ($action === 'create') {
                    $ins = $pdo->prepare(
                        'INSERT INTO diet_plans (title,description,dietitian_id,goal_type,total_calories,duration_days,status,created_at)
                         VALUES (:t,:d,:di,:g,:c,:du,:s,NOW())'
                    );
                    $ins->execute([
                        ':t' => $title, ':d' => $description, ':di' => $dietitianId, ':g' => $goal,
                        ':c' => $calories, ':du' => $duration, ':s' => $status,
                    ]);
                    $planId = (int) $pdo->lastInsertId();
                } else {
                    $up = $pdo->prepare(
                        'UPDATE diet_plans
                         SET title=:t,description=:d,dietitian_id=:di,goal_type=:g,total_calories=:c,duration_days=:du,status=:s
                         WHERE id=:id'
                    );
                    $up->execute([
                        ':t' => $title, ':d' => $description, ':di' => $dietitianId, ':g' => $goal,
                        ':c' => $calories, ':du' => $duration, ':s' => $status, ':id' => $planId,
                    ]);
                    $pdo->prepare('DELETE FROM meals WHERE diet_plan_id=:id')->execute([':id' => $planId]);
                }

                $insMeal = $pdo->prepare(
                    'INSERT INTO meals (diet_plan_id,meal_type,meal_name,description,calories,protein,carbs,fat,day_number,created_at)
                     VALUES (:p,:mt,:mn,:ds,:c,:pr,:cb,:f,:day,NOW())'
                );
                foreach ($meals as $day => $rows) {
                    $dayN = (int) $day;
                    if ($dayN < 1 || $dayN > $duration || !is_array($rows)) continue;
                    foreach ($rows as $m) {
                        $mt = (string) ($m['meal_type'] ?? '');
                        $mn = trim((string) ($m['meal_name'] ?? ''));
                        $cal = (int) ($m['calories'] ?? 0);
                        if ($mn === '' || $cal <= 0 || !in_array($mt, ['breakfast','lunch','dinner','snack'], true)) continue;
                        $insMeal->execute([
                            ':p' => $planId, ':mt' => $mt, ':mn' => $mn, ':ds' => (string) ($m['description'] ?? ''),
                            ':c' => $cal, ':pr' => (float) ($m['protein'] ?? 0), ':cb' => (float) ($m['carbs'] ?? 0),
                            ':f' => (float) ($m['fat'] ?? 0), ':day' => $dayN,
                        ]);
                    }
                }
                $pdo->commit();
                $alerts[] = ['type' => 'success', 'text' => $action === 'create' ? 'Plan created.' : 'Plan updated.'];
            } else {
                foreach ($errs as $e) $alerts[] = ['type' => 'danger', 'text' => $e];
            }
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['plan_id'] ?? 0);
            $newStatus = (string) ($_POST['new_status'] ?? 'active');
            if ($id > 0 && in_array($newStatus, ['active', 'inactive'], true)) {
                $pdo->prepare('UPDATE diet_plans SET status=:s WHERE id=:id')->execute([':s' => $newStatus, ':id' => $id]);
                $alerts[] = ['type' => 'success', 'text' => 'Plan status updated.'];
            }
        }

        if ($action === 'feature' && $featureColumnExists) {
            $id = (int) ($_POST['plan_id'] ?? 0);
            $newVal = (int) ($_POST['new_val'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('UPDATE diet_plans SET is_featured=:f WHERE id=:id')->execute([':f' => $newVal, ':id' => $id]);
                $alerts[] = ['type' => 'success', 'text' => 'Feature state updated.'];
            }
        }

        if ($action === 'clone') {
            $id = (int) ($_POST['plan_id'] ?? 0);
            if ($id > 0) {
                $p = $pdo->prepare('SELECT * FROM diet_plans WHERE id=:id'); $p->execute([':id'=>$id]); $plan = $p->fetch();
                if ($plan) {
                    $ins = $pdo->prepare('INSERT INTO diet_plans (title,description,dietitian_id,goal_type,total_calories,duration_days,status,created_at) VALUES (:t,:d,:di,:g,:c,:du,:s,NOW())');
                    $ins->execute([':t'=>(string)$plan['title'].' (Clone)',':d'=>$plan['description'],':di'=>$plan['dietitian_id'],':g'=>$plan['goal_type'],':c'=>$plan['total_calories'],':du'=>$plan['duration_days'],':s'=>$plan['status']]);
                    $newId = (int) $pdo->lastInsertId();
                    $m = $pdo->prepare('SELECT meal_type,meal_name,description,calories,protein,carbs,fat,day_number FROM meals WHERE diet_plan_id=:id');
                    $m->execute([':id'=>$id]);
                    $insM = $pdo->prepare('INSERT INTO meals (diet_plan_id,meal_type,meal_name,description,calories,protein,carbs,fat,day_number,created_at) VALUES (:p,:mt,:mn,:d,:c,:pr,:cb,:f,:day,NOW())');
                    foreach($m->fetchAll() as $x){$insM->execute([':p'=>$newId,':mt'=>$x['meal_type'],':mn'=>$x['meal_name'],':d'=>$x['description'],':c'=>$x['calories'],':pr'=>$x['protein'],':cb'=>$x['carbs'],':f'=>$x['fat'],':day'=>$x['day_number']]);}
                    $alerts[] = ['type' => 'success', 'text' => 'Plan cloned.'];
                }
            }
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['plan_id'] ?? 0);
            if ($id > 0) {
                $c = $pdo->prepare('SELECT COUNT(*) c FROM user_diet_plans WHERE diet_plan_id=:id'); $c->execute([':id'=>$id]); $assigned = (int)($c->fetch()['c']??0);
                if ($assigned > 0) {
                    $alerts[] = ['type' => 'warning', 'text' => "Cannot delete plan: {$assigned} users are assigned."];
                } else {
                    $pdo->prepare('DELETE FROM diet_plans WHERE id=:id')->execute([':id'=>$id]);
                    $alerts[] = ['type' => 'success', 'text' => 'Plan deleted.'];
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed.'];
    }
}

$goalCounts = ['weight_loss' => 0, 'gain' => 0, 'maintain' => 0];
$q = $pdo->query('SELECT goal_type, COUNT(*) c FROM diet_plans GROUP BY goal_type');
foreach ($q->fetchAll() as $r) $goalCounts[(string) $r['goal_type']] = (int) $r['c'];
$popular = $pdo->query(
    'SELECT dp.title, COUNT(udp.id) assigned
     FROM diet_plans dp LEFT JOIN user_diet_plans udp ON udp.diet_plan_id=dp.id
     GROUP BY dp.id,dp.title ORDER BY assigned DESC LIMIT 1'
)->fetch();
$avgDur = (float) ($pdo->query('SELECT AVG(duration_days) avg_d FROM diet_plans')->fetch()['avg_d'] ?? 0);

$search = trim((string) ($_GET['q'] ?? ''));
$goal = trim((string) ($_GET['goal'] ?? ''));
$dietitianFilter = (int) ($_GET['dietitian_id'] ?? 0);
$status = trim((string) ($_GET['status'] ?? ''));

$sql = 'SELECT dp.id,dp.title,dp.description,dp.goal_type,dp.duration_days,dp.total_calories,dp.status,dp.created_at,
        d.full_name dietitian_name,d.id dietitian_id,
        COUNT(udp.id) assigned_count
        FROM diet_plans dp
        JOIN dietitians d ON d.id=dp.dietitian_id
        LEFT JOIN user_diet_plans udp ON udp.diet_plan_id=dp.id';
$where=[];$params=[];
if($search!==''){ $where[]='dp.title LIKE :q'; $params[':q']='%'.$search.'%'; }
if(in_array($goal,['weight_loss','gain','maintain'],true)){ $where[]='dp.goal_type=:g'; $params[':g']=$goal; }
if($dietitianFilter>0){ $where[]='dp.dietitian_id=:di'; $params[':di']=$dietitianFilter; }
if(in_array($status,['active','inactive'],true)){ $where[]='dp.status=:st'; $params[':st']=$status; }
if($where) $sql .= ' WHERE '.implode(' AND ',$where);
$sql .= ' GROUP BY dp.id,dp.title,dp.description,dp.goal_type,dp.duration_days,dp.total_calories,dp.status,dp.created_at,d.full_name,d.id ORDER BY dp.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();

$dietitians = $pdo->query('SELECT id,full_name FROM dietitians ORDER BY full_name')->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Diet Plans | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-doctor"></i>Dietitians</a></li>
<li class="active"><a href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Diet Plans Management</h5><small class="text-muted">Admin control for all diet plans</small></div><button class="btn btn-primary right" data-bs-toggle="modal" data-bs-target="#createPlanModal">Create Plan</button></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="row g-2 mb-3">
<div class="col-md-3"><div class="stat-card"><h3>Weight Loss Plans</h3><div class="value"><?= $goalCounts['weight_loss'] ?></div></div></div>
<div class="col-md-3"><div class="stat-card"><h3>Gain Plans</h3><div class="value"><?= $goalCounts['gain'] ?></div></div></div>
<div class="col-md-3"><div class="stat-card"><h3>Maintain Plans</h3><div class="value"><?= $goalCounts['maintain'] ?></div></div></div>
<div class="col-md-3"><div class="stat-card"><h3>Most Popular</h3><div class="value" style="font-size:1rem;"><?= e((string)($popular['title']??'N/A')) ?></div><small><?= (int)($popular['assigned']??0) ?> assigned</small><small class="d-block">Avg Duration: <?= number_format($avgDur,1) ?> days</small></div></div>
</div>

<div class="card mb-3"><div class="card-body">
<form class="row g-2">
<div class="col-md-3"><input name="q" class="form-control" placeholder="Search by name" value="<?= e($search) ?>"></div>
<div class="col-md-2"><select name="goal" class="form-control"><option value="">Goal</option><option value="weight_loss" <?= $goal==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $goal==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $goal==='maintain'?'selected':'' ?>>Maintain</option></select></div>
<div class="col-md-3"><select name="dietitian_id" class="form-control"><option value="0">Dietitian</option><?php foreach($dietitians as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $dietitianFilter===(int)$d['id']?'selected':'' ?>><?= e((string)$d['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><select name="status" class="form-control"><option value="">Status</option><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option></select></div>
<div class="col-md-2"><button class="btn btn-outline w-100">Apply</button></div>
</form>
</div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>Title</th><th>Dietitian</th><th>Goal</th><th>Duration</th><th>Calories</th><th>Assigned</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($plans as $p): ?>
<tr>
<td><?= e((string)$p['title']) ?></td><td><?= e((string)$p['dietitian_name']) ?></td><td><?= e(ucwords(str_replace('_',' ',(string)$p['goal_type']))) ?></td><td><?= (int)$p['duration_days'] ?> days</td><td><?= (int)$p['total_calories'] ?> kcal</td><td><?= (int)$p['assigned_count'] ?></td><td><span class="badge"><?= e((string)$p['status']) ?></span></td>
<td class="d-flex gap-1 flex-wrap">
<button class="btn btn-sm btn-outline" data-bs-toggle="modal" data-bs-target="#detail<?= (int)$p['id'] ?>">View</button>
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$p['id'] ?>">Edit</button>
<form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="new_status" value="<?= $p['status']==='active'?'inactive':'active' ?>"><button class="btn btn-sm btn-warning"><?= $p['status']==='active'?'Disable':'Enable' ?></button></form>
<form method="post"><input type="hidden" name="action" value="clone"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-secondary">Clone</button></form>
<?php if($featureColumnExists): ?><form method="post"><input type="hidden" name="action" value="feature"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="new_val" value="1"><button class="btn btn-sm btn-success">Feature</button></form><?php endif; ?>
<form method="post" onsubmit="return confirm('Delete plan?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
</td>
</tr>
<?php endforeach; if(empty($plans)): ?><tr><td colspan="8" class="text-center text-muted">No plans found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<?php foreach($plans as $p):
$meals = $pdo->prepare('SELECT * FROM meals WHERE diet_plan_id=:id ORDER BY day_number ASC, FIELD(meal_type,"breakfast","lunch","dinner","snack"),id ASC'); $meals->execute([':id'=>(int)$p['id']]); $mealRows=$meals->fetchAll();
$users = $pdo->prepare('SELECT u.full_name,u.email,udp.status FROM user_diet_plans udp JOIN users u ON u.id=udp.user_id WHERE udp.diet_plan_id=:id'); $users->execute([':id'=>(int)$p['id']]); $uRows=$users->fetchAll();
$seed=[]; foreach($mealRows as $m){$d=(int)$m['day_number']; if(!isset($seed[$d]))$seed[$d]=[]; $seed[$d][]=['meal_type'=>$m['meal_type'],'meal_name'=>$m['meal_name'],'description'=>$m['description'],'calories'=>(int)$m['calories'],'protein'=>(float)$m['protein'],'carbs'=>(float)$m['carbs'],'fat'=>(float)$m['fat']];}
?>
<div class="modal fade" id="detail<?= (int)$p['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Plan Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<p class="mb-1"><strong>Title:</strong> <?= e((string)$p['title']) ?></p><p class="mb-1"><strong>Dietitian:</strong> <?= e((string)$p['dietitian_name']) ?></p><p class="mb-1"><strong>Goal:</strong> <?= e((string)$p['goal_type']) ?></p><p class="mb-1"><strong>Status:</strong> <?= e((string)$p['status']) ?></p><p class="mb-2"><strong>Created:</strong> <?= e(date('M d, Y',strtotime((string)$p['created_at']))) ?></p>
<h6>Meals by Day</h6><?php foreach($seed as $day=>$rows): ?><div class="border rounded p-2 mb-2"><strong>Day <?= (int)$day ?></strong><?php foreach($rows as $m): ?><div class="small"><?= e((string)$m['meal_type']) ?> - <?= e((string)$m['meal_name']) ?> (<?= (int)$m['calories'] ?> kcal)</div><?php endforeach; ?></div><?php endforeach; if(empty($seed)): ?><p class="text-muted">No meals.</p><?php endif; ?>
<h6>Assigned Users</h6><ul><?php foreach($uRows as $u): ?><li><?= e((string)$u['full_name']) ?> (<?= e((string)$u['email']) ?>) - <?= e((string)$u['status']) ?></li><?php endforeach; if(empty($uRows)): ?><li class="text-muted">No assigned users</li><?php endif; ?></ul>
</div></div></div></div>

<div class="modal fade" id="edit<?= (int)$p['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post" class="admin-plan-form"><div class="modal-body">
<input type="hidden" name="action" value="edit"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="meals_json" class="meals-json">
<div class="row g-2">
<div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="<?= e((string)$p['title']) ?>"></div>
<div class="col-md-6"><label class="form-label">Dietitian</label><select class="form-control" name="dietitian_id"><?php foreach($dietitians as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $p['dietitian_id']===(int)$d['id']?'selected':'' ?>><?= e((string)$d['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description"><?= e((string)$p['description']) ?></textarea></div>
<div class="col-md-3"><label class="form-label">Goal</label><select class="form-control" name="goal_type"><option value="weight_loss" <?= $p['goal_type']==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $p['goal_type']==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $p['goal_type']==='maintain'?'selected':'' ?>>Maintain</option></select></div>
<div class="col-md-3"><label class="form-label">Duration</label><input type="number" class="form-control duration-days" name="duration_days" value="<?= (int)$p['duration_days'] ?>"></div>
<div class="col-md-3"><label class="form-label">Calories</label><input type="number" class="form-control" name="total_calories" value="<?= (int)$p['total_calories'] ?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-control" name="status"><option value="active" <?= $p['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $p['status']==='inactive'?'selected':'' ?>>Inactive</option></select></div>
</div>
<div class="mt-3"><small class="text-muted d-block mb-1">Meals JSON editable (admin quick-edit):</small><textarea class="form-control meals-json-editor" rows="8"><?= e(json_encode($seed, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></textarea></div>
</div><div class="modal-footer"><button class="btn btn-primary">Save Changes</button></div></form></div></div></div>
<?php endforeach; ?>

<div class="modal fade" id="createPlanModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Create Plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post" class="admin-plan-form"><div class="modal-body">
<input type="hidden" name="action" value="create"><input type="hidden" name="meals_json" class="meals-json">
<div class="row g-2">
<div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
<div class="col-md-6"><label class="form-label">Dietitian</label><select class="form-control" name="dietitian_id" required><option value="">Select</option><?php foreach($dietitians as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e((string)$d['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div>
<div class="col-md-3"><label class="form-label">Goal</label><select class="form-control" name="goal_type"><option value="weight_loss">Weight Loss</option><option value="gain">Gain</option><option value="maintain">Maintain</option></select></div>
<div class="col-md-3"><label class="form-label">Duration</label><input type="number" class="form-control duration-days" name="duration_days" value="7"></div>
<div class="col-md-3"><label class="form-label">Calories</label><input type="number" class="form-control" name="total_calories" value="2000"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-control" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
</div>
<div class="mt-3"><small class="text-muted d-block mb-1">Meals JSON (day => rows):</small><textarea class="form-control meals-json-editor" rows="8">{}</textarea></div>
</div><div class="modal-footer"><button class="btn btn-success">Create Plan</button></div></form></div></div></div>

</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
document.querySelectorAll('.admin-plan-form').forEach(form=>{
  form.addEventListener('submit',e=>{
    const editor=form.querySelector('.meals-json-editor'); const hidden=form.querySelector('.meals-json');
    try{const parsed=JSON.parse(editor.value||'{}'); hidden.value=JSON.stringify(parsed);}catch(_){e.preventDefault(); alert('Meals JSON is invalid.');}
  });
});
</script>
</body></html>

