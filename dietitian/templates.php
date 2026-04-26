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

$alerts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $id = (int) ($_POST['template_id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM meal_templates WHERE id=:id AND dietitian_id=:d');
                $stmt->execute([':id' => $id, ':d' => $dietitianId]);
                $alerts[] = ['type' => 'success', 'text' => 'Template deleted.'];
                logActivity($dietitianId, 'dietitian', 'Deleted meal template');
            }
        }

        if ($action === 'assign_template') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $userId = (int) ($_POST['user_id'] ?? 0);
            $startDate = trim((string) ($_POST['start_date'] ?? date('Y-m-d')));

            if ($templateId <= 0 || $userId <= 0) {
                throw new RuntimeException('Please select a valid template and user.');
            }

            $dt = DateTime::createFromFormat('Y-m-d', $startDate);
            if (!$dt || $dt->format('Y-m-d') !== $startDate) {
                throw new RuntimeException('Invalid start date.');
            }

            $tq = $pdo->prepare('SELECT id,template_name,meal_data FROM meal_templates WHERE id=:id AND dietitian_id=:d LIMIT 1');
            $tq->execute([':id' => $templateId, ':d' => $dietitianId]);
            $template = $tq->fetch();
            if (!$template) {
                throw new RuntimeException('Template not found.');
            }

            $uq = $pdo->prepare('SELECT id,full_name FROM users WHERE id=:id AND status="active" LIMIT 1');
            $uq->execute([':id' => $userId]);
            $user = $uq->fetch();
            if (!$user) {
                throw new RuntimeException('Selected user is not active.');
            }

            $mealsByDay = json_decode((string) $template['meal_data'], true);
            if (!is_array($mealsByDay) || empty($mealsByDay)) {
                throw new RuntimeException('Template has no meal data.');
            }

            $durationDays = 1;
            $dayTotals = [];
            foreach ($mealsByDay as $dayKey => $rows) {
                $dayN = max(1, (int) $dayKey);
                $durationDays = max($durationDays, $dayN);
                $dayCal = 0;
                if (is_array($rows)) {
                    foreach ($rows as $m) {
                        $dayCal += (int) ($m['calories'] ?? 0);
                    }
                }
                $dayTotals[] = $dayCal;
            }
            $avgDailyCalories = (int) max(500, round(array_sum($dayTotals) / max(1, count($dayTotals))));

            $nameLower = strtolower((string) $template['template_name']);
            $goalType = 'maintain';
            if (str_contains($nameLower, 'loss')) {
                $goalType = 'weight_loss';
            } elseif (str_contains($nameLower, 'gain') || str_contains($nameLower, 'bulk')) {
                $goalType = 'gain';
            }

            $planTitle = (string) $template['template_name'] . ' - ' . (string) $user['full_name'];
            if (mb_strlen($planTitle) > 190) {
                $planTitle = mb_substr($planTitle, 0, 190);
            }

            $endDate = date('Y-m-d', strtotime($startDate . ' +' . $durationDays . ' day'));

            $pdo->beginTransaction();

            $planIns = $pdo->prepare(
                'INSERT INTO diet_plans (title,description,dietitian_id,goal_type,total_calories,duration_days,status,created_at)
                 VALUES (:t,:d,:di,:g,:c,:du,"active",NOW())'
            );
            $planIns->execute([
                ':t' => $planTitle,
                ':d' => 'Auto-created from template "' . (string) $template['template_name'] . '"',
                ':di' => $dietitianId,
                ':g' => $goalType,
                ':c' => $avgDailyCalories,
                ':du' => $durationDays,
            ]);
            $planId = (int) $pdo->lastInsertId();

            $mealIns = $pdo->prepare(
                'INSERT INTO meals (diet_plan_id,meal_type,meal_name,description,calories,protein,carbs,fat,day_number,created_at)
                 VALUES (:p,:mt,:mn,:ds,:c,:pr,:cb,:f,:day,NOW())'
            );
            foreach ($mealsByDay as $dayKey => $rows) {
                $dayN = max(1, (int) $dayKey);
                if (!is_array($rows)) {
                    continue;
                }
                foreach ($rows as $m) {
                    $mealType = (string) ($m['meal_type'] ?? 'snack');
                    if (!in_array($mealType, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
                        $mealType = 'snack';
                    }
                    $mealName = trim((string) ($m['meal_name'] ?? 'Meal'));
                    $calories = max(0, (int) ($m['calories'] ?? 0));
                    $mealIns->execute([
                        ':p' => $planId,
                        ':mt' => $mealType,
                        ':mn' => $mealName,
                        ':ds' => (string) ($m['description'] ?? ''),
                        ':c' => $calories,
                        ':pr' => (float) ($m['protein'] ?? 0),
                        ':cb' => (float) ($m['carbs'] ?? 0),
                        ':f' => (float) ($m['fat'] ?? 0),
                        ':day' => $dayN,
                    ]);
                }
            }

            $assignIns = $pdo->prepare(
                'INSERT INTO user_diet_plans (user_id,diet_plan_id,dietitian_id,assigned_date,end_date,status,dietitian_notes)
                 VALUES (:u,:p,:d,:s,:e,"active",:n)'
            );
            $assignIns->execute([
                ':u' => $userId,
                ':p' => $planId,
                ':d' => $dietitianId,
                ':s' => $startDate,
                ':e' => $endDate,
                ':n' => 'Assigned directly from template "' . (string) $template['template_name'] . '"',
            ]);

            $pdo->commit();
            $alerts[] = ['type' => 'success', 'text' => 'Template assigned successfully to ' . (string) $user['full_name'] . '.'];
            logActivity($dietitianId, 'dietitian', 'Assigned template to user');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $alerts[] = ['type' => 'danger', 'text' => $e instanceof RuntimeException ? $e->getMessage() : 'Action failed.'];
    }
}

$stmt = $pdo->prepare('SELECT id,template_name,meal_data,created_at FROM meal_templates WHERE dietitian_id=:d ORDER BY created_at DESC');
$stmt->execute([':d' => $dietitianId]);
$templates = $stmt->fetchAll();

$usersStmt = $pdo->prepare(
    'SELECT DISTINCT u.id, u.full_name, u.email
     FROM users u
     INNER JOIN user_diet_plans udp ON udp.user_id = u.id
     WHERE u.status = "active" AND udp.dietitian_id = :d
     ORDER BY u.full_name ASC'
);
$usersStmt->execute([':d' => $dietitianId]);
$users = $usersStmt->fetchAll();
$hasAssignableUsers = !empty($users);

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Templates | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li class="active"><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Meal Templates</h5><small class="text-muted">Reuse meal structures for faster plan creation</small></div><a class="btn btn-primary right" href="<?= SITE_URL ?>/dietitian/create_plan.php">Create Plan</a></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="card"><div class="card-body">
<div class="row g-3">
<?php foreach($templates as $t):
    $meals = json_decode((string)$t['meal_data'], true);
    $mealCount = 0;
    if (is_array($meals)) foreach ($meals as $dayRows) if (is_array($dayRows)) $mealCount += count($dayRows);
?>
<div class="col-md-6 col-lg-4">
<div class="border rounded p-3 h-100">
<h6 class="mb-1"><?= e((string)$t['template_name']) ?></h6>
<small class="text-muted d-block mb-2">Created: <?= e(date('M d, Y', strtotime((string)$t['created_at']))) ?></small>
<small class="badge mb-2"><?= $mealCount ?> meals</small>
<div class="d-flex gap-2 flex-wrap">
<a class="btn btn-success btn-sm template-action-btn" href="<?= SITE_URL ?>/dietitian/create_plan.php?template_id=<?= (int)$t['id'] ?>"><i class="fa-solid fa-bolt me-1"></i>Load</a>
<button class="btn btn-primary btn-sm template-action-btn" data-bs-toggle="modal" data-bs-target="#assignTpl<?= (int)$t['id'] ?>" <?= $hasAssignableUsers ? '' : 'disabled title="No assigned users available"' ?>><i class="fa-solid fa-user-plus me-1"></i>Assign</button>
<button class="btn btn-outline btn-sm template-action-btn preview-template-btn" data-template-id="<?= (int)$t['id'] ?>">Preview</button>
<form method="post" onsubmit="return confirm('Delete this template?');">
<input type="hidden" name="action" value="delete"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
<button class="btn btn-danger btn-sm template-action-btn">Delete</button></form>
</div>
</div>
</div>

<div class="modal fade" id="assignTpl<?= (int)$t['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Template To User</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="action" value="assign_template">
          <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
          <p class="mb-2"><strong>Template:</strong> <?= e((string)$t['template_name']) ?></p>
          <label class="form-label">Select User</label>
          <select class="form-control" name="user_id" required>
            <?php if ($hasAssignableUsers): ?>
              <option value="">Choose a user</option>
              <?php foreach($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= e((string)$u['full_name']) ?> (<?= e((string)$u['email']) ?>)</option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="">No users assigned to you yet</option>
            <?php endif; ?>
          </select>
          <label class="form-label mt-2">Start Date</label>
          <input class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required>
          <small class="text-muted d-block mt-2">This will auto-create a plan from this template and assign it immediately.</small>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Assign Now</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; if(empty($templates)): ?><p class="text-muted mb-0">No templates available yet. Save one while creating a plan.</p><?php endif; ?>
</div></div></div>

</div></main></div>

<div class="modal fade" id="tplPreviewModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Template Preview</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="tplPreviewBody"><p class="text-muted">Loading...</p></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('tplPreviewModal'));
const body = document.getElementById('tplPreviewBody');
document.querySelectorAll('.preview-template-btn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    body.innerHTML='<p class="text-muted">Loading...</p>'; modal.show();
    const id=btn.dataset.templateId;
    const res=await fetch(`<?= SITE_URL ?>/dietitian/create_plan.php?ajax=template&template_id=${id}`);
    const data=await res.json();
    if(!data.success){body.innerHTML='<p class="text-danger">Could not load template.</p>'; return;}
    const meals=data.template.meals||{};
    let html='';
    Object.keys(meals).sort((a,b)=>parseInt(a)-parseInt(b)).forEach(day=>{
      html+=`<div class="mb-3"><h6>Day ${day}</h6>`;
      (meals[day]||[]).forEach(m=>{
        html+=`<div class="border rounded p-2 mb-2"><strong>${m.meal_name||'-'}</strong> <small class="text-muted">(${m.meal_type||''})</small><br><small>${m.calories||0} kcal | P ${m.protein||0} C ${m.carbs||0} F ${m.fat||0}</small></div>`;
      });
      html+='</div>';
    });
    body.innerHTML=html||'<p class="text-muted">No meals in template.</p>';
  });
});
</script>
</body></html>
