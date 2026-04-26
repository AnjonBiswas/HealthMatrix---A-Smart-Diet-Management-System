<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['admin']);

$pdo = Database::getInstance()->getConnection();
$alerts = [];
$perPage = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'edit_user') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');
            $goal = (string) ($_POST['goal'] ?? 'maintain');
            if ($uid > 0 && in_array($status, ['active', 'inactive'], true) && in_array($goal, ['weight_loss', 'gain', 'maintain'], true)) {
                $s = $pdo->prepare('UPDATE users SET status=:s, goal=:g, updated_at=NOW() WHERE id=:id');
                $s->execute([':s' => $status, ':g' => $goal, ':id' => $uid]);
                $alerts[] = ['type' => 'success', 'text' => 'User updated.'];
            }
        }
        if ($action === 'assign_dietitian') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $did = (int) ($_POST['dietitian_id'] ?? 0);
            $pid = (int) ($_POST['diet_plan_id'] ?? 0);
            $start = (string) ($_POST['start_date'] ?? date('Y-m-d'));
            $end = (string) ($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 day')));
            if ($uid > 0 && $did > 0 && $pid > 0) {
                $ins = $pdo->prepare('INSERT INTO user_diet_plans (user_id,diet_plan_id,dietitian_id,assigned_date,end_date,status,dietitian_notes) VALUES (:u,:p,:d,:s,:e,"active","Assigned by admin users panel")');
                $ins->execute([':u' => $uid, ':p' => $pid, ':d' => $did, ':s' => $start, ':e' => $end]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian assigned.'];
            }
        }
        if ($action === 'toggle_status') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $newStatus = (string) ($_POST['new_status'] ?? 'active');
            if ($uid > 0 && in_array($newStatus, ['active', 'inactive'], true)) {
                $s = $pdo->prepare('UPDATE users SET status=:s, updated_at=NOW() WHERE id=:id');
                $s->execute([':s' => $newStatus, ':id' => $uid]);
                $alerts[] = ['type' => 'success', 'text' => 'User status updated.'];
            }
        }
        if ($action === 'delete_user') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            if ($uid > 0) {
                $d = $pdo->prepare('DELETE FROM users WHERE id=:id');
                $d->execute([':id' => $uid]);
                $alerts[] = ['type' => 'success', 'text' => 'User deleted.'];
            }
        }
        if ($action === 'bulk_action') {
            $bulk = (string) ($_POST['bulk_type'] ?? '');
            $ids = $_POST['selected_ids'] ?? [];
            if (is_array($ids) && !empty($ids)) {
                $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
                if ($cleanIds) {
                    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                    if ($bulk === 'activate' || $bulk === 'deactivate') {
                        $status = $bulk === 'activate' ? 'active' : 'inactive';
                        $stmt = $pdo->prepare("UPDATE users SET status='{$status}', updated_at=NOW() WHERE id IN ({$placeholders})");
                        $stmt->execute($cleanIds);
                        $alerts[] = ['type' => 'success', 'text' => 'Bulk status updated.'];
                    } elseif ($bulk === 'delete') {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})");
                        $stmt->execute($cleanIds);
                        $alerts[] = ['type' => 'success', 'text' => 'Bulk delete completed.'];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed.'];
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$goalFilter = trim((string) ($_GET['goal'] ?? ''));
$activityFilter = trim((string) ($_GET['activity_level'] ?? ''));
$planFilter = trim((string) ($_GET['plan_filter'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'created_at'));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
$page = max(1, (int) ($_GET['page'] ?? 1));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$sortMap = ['name' => 'u.full_name', 'email' => 'u.email', 'bmi' => 'u.bmi', 'goal' => 'u.goal', 'status' => 'u.status', 'joined' => 'u.created_at', 'created_at' => 'u.created_at'];
$sortSql = $sortMap[$sort] ?? 'u.created_at';

$baseSql = ' FROM users u
LEFT JOIN (
    SELECT udp.user_id, dp.title plan_name, d.full_name dietitian_name, udp.assigned_date, udp.status
    FROM user_diet_plans udp
    JOIN diet_plans dp ON dp.id=udp.diet_plan_id
    JOIN dietitians d ON d.id=udp.dietitian_id
    WHERE udp.id IN (SELECT MAX(id) FROM user_diet_plans GROUP BY user_id)
) latest ON latest.user_id=u.id';
$where = [];
$params = [];
if ($search !== '') { $where[] = '(u.full_name LIKE :q OR u.email LIKE :q)'; $params[':q'] = '%' . $search . '%'; }
if (in_array($statusFilter, ['active', 'inactive'], true)) { $where[] = 'u.status=:st'; $params[':st'] = $statusFilter; }
if (in_array($goalFilter, ['weight_loss', 'gain', 'maintain'], true)) { $where[] = 'u.goal=:g'; $params[':g'] = $goalFilter; }
if (in_array($activityFilter, ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'], true)) { $where[] = 'u.activity_level=:a'; $params[':a'] = $activityFilter; }
if ($planFilter === 'has_plan') $where[] = 'latest.plan_name IS NOT NULL';
if ($planFilter === 'no_plan') $where[] = 'latest.plan_name IS NULL';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($exportCsv) {
    $csvSql = 'SELECT u.id,u.full_name,u.email,u.phone,u.age,u.weight,u.height,u.gender,u.activity_level,u.goal,u.bmi,u.daily_calorie_goal,u.status,u.created_at,
               latest.plan_name,latest.dietitian_name,latest.assigned_date,latest.status assigned_status'
               . $baseSql . $whereSql . " ORDER BY {$sortSql} {$dir}";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $csvRows = $csvStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Age', 'Weight', 'Height', 'Gender', 'Activity Level', 'Goal', 'BMI', 'Daily Calorie Goal', 'Status', 'Joined', 'Plan', 'Dietitian', 'Assigned Date', 'Assignment Status']);
    foreach ($csvRows as $r) {
        fputcsv($out, [
            $r['id'], $r['full_name'], $r['email'], $r['phone'], $r['age'], $r['weight'], $r['height'], $r['gender'],
            $r['activity_level'], $r['goal'], $r['bmi'], $r['daily_calorie_goal'], $r['status'], $r['created_at'],
            $r['plan_name'], $r['dietitian_name'], $r['assigned_date'], $r['assigned_status'],
        ]);
    }
    fclose($out);
    exit;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) c' . $baseSql . $whereSql);
$countStmt->execute($params);
$total = (int) ($countStmt->fetch()['c'] ?? 0);

$offset = ($page - 1) * $perPage;
$listSql = 'SELECT u.id,u.full_name,u.email,u.phone,u.age,u.weight,u.height,u.gender,u.activity_level,u.goal,u.bmi,u.daily_calorie_goal,u.status,u.created_at,
            latest.plan_name,latest.dietitian_name,latest.assigned_date,latest.status assigned_status'
            . $baseSql . $whereSql . " ORDER BY {$sortSql} {$dir} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$dietitianRows = $pdo->query('SELECT id,full_name FROM dietitians WHERE status="active" ORDER BY full_name')->fetchAll();
$planRows = $pdo->query('SELECT id,title,dietitian_id FROM diet_plans WHERE status="active" ORDER BY title')->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function sortUrl(string $col): string {
    $q = $_GET;
    $current = $q['sort'] ?? 'created_at';
    $dir = strtolower((string) ($q['dir'] ?? 'desc'));
    $q['sort'] = $col;
    $q['dir'] = ($current === $col && $dir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($q);
}
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li class="active"><a href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-doctor"></i>Dietitians</a></li>
<li><a href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Manage Users</h5><small class="text-muted">Search, update, assign, and moderate users</small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="card mb-3"><div class="card-body">
<form method="get" class="row g-2">
<div class="col-md-3"><input name="q" class="form-control" placeholder="Search name/email" value="<?= e($search) ?>"></div>
<div class="col-md-2"><select name="status" class="form-control"><option value="">Status</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option></select></div>
<div class="col-md-2"><select name="goal" class="form-control"><option value="">Goal</option><option value="weight_loss" <?= $goalFilter==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $goalFilter==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $goalFilter==='maintain'?'selected':'' ?>>Maintain</option></select></div>
<div class="col-md-2"><select name="activity_level" class="form-control"><option value="">Activity</option><?php foreach(['sedentary','lightly_active','moderately_active','very_active','extra_active'] as $a): ?><option value="<?= $a ?>" <?= $activityFilter===$a?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$a))) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><select name="plan_filter" class="form-control"><option value="">Plan Filter</option><option value="has_plan" <?= $planFilter==='has_plan'?'selected':'' ?>>Has Plan</option><option value="no_plan" <?= $planFilter==='no_plan'?'selected':'' ?>>No Plan</option></select></div>
<div class="col-md-1"><button class="btn btn-outline w-100">Apply</button></div>
<div class="col-md-2"><a class="btn btn-success w-100" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</a></div>
</form></div></div>

<form method="post" onsubmit="return confirm('Apply bulk action?');">
<input type="hidden" name="action" value="bulk_action">
<div class="card"><div class="card-body table-responsive">
<div class="d-flex gap-2 mb-2"><select name="bulk_type" class="form-control" style="max-width:220px"><option value="activate">Activate Selected</option><option value="deactivate">Deactivate Selected</option><option value="delete">Delete Selected</option></select><button class="btn btn-danger">Apply</button></div>
<table class="table table-striped"><thead><tr>
<th><input type="checkbox" id="checkAll"></th>
<th><a href="<?= sortUrl('name') ?>">Name</a></th><th><a href="<?= sortUrl('email') ?>">Email</a></th><th><a href="<?= sortUrl('bmi') ?>">BMI</a></th><th><a href="<?= sortUrl('goal') ?>">Goal</a></th><th>Plan</th><th>Dietitian</th><th><a href="<?= sortUrl('status') ?>">Status</a></th><th><a href="<?= sortUrl('joined') ?>">Joined</a></th><th>Actions</th>
</tr></thead><tbody>
<?php foreach($users as $u): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= (int)$u['id'] ?>"></td>
<td><?= e((string)$u['full_name']) ?></td><td><?= e((string)$u['email']) ?></td><td><?= number_format((float)$u['bmi'],2) ?></td><td><?= e(ucwords(str_replace('_',' ',(string)$u['goal']))) ?></td>
<td><?= e((string)($u['plan_name'] ?? 'N/A')) ?></td><td><?= e((string)($u['dietitian_name'] ?? 'N/A')) ?></td><td><span class="badge"><?= e((string)$u['status']) ?></span></td><td><?= e(date('M d, Y',strtotime((string)$u['created_at']))) ?></td>
<td class="d-flex gap-1 flex-wrap">
<button type="button" class="btn btn-sm btn-outline" data-bs-toggle="modal" data-bs-target="#view<?= (int)$u['id'] ?>">View</button>
<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$u['id'] ?>">Edit</button>
<button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#assign<?= (int)$u['id'] ?>">Assign</button>
<button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#plan<?= (int)$u['id'] ?>">Plan</button>
<form method="post"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="new_status" value="<?= $u['status']==='active'?'inactive':'active' ?>"><button class="btn btn-sm btn-warning"><?= $u['status']==='active'?'Deactivate':'Activate' ?></button></form>
<form method="post" onsubmit="return confirm('Delete user?');"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
</td>
</tr>
<?php endforeach; if(empty($users)): ?><tr><td colspan="10" class="text-center text-muted">No users found.</td></tr><?php endif; ?>
</tbody></table>
<?= generatePagination($total,$page,$perPage,'?'.http_build_query(array_merge($_GET,['page'=>'' ]))) ?>
</div></div>
</form>

<?php foreach($users as $u):
$uid=(int)$u['id'];
$bmiH=$pdo->prepare('SELECT log_date,weight FROM weight_log WHERE user_id=:u ORDER BY log_date DESC LIMIT 10');$bmiH->execute([':u'=>$uid]);$weightRows=$bmiH->fetchAll();
$foodQ=$pdo->prepare('SELECT meal_type,food_name,calories FROM food_log WHERE user_id=:u AND log_date=CURDATE() ORDER BY id DESC LIMIT 10');$foodQ->execute([':u'=>$uid]);$foodRows=$foodQ->fetchAll();
$msgCount=(int)($pdo->query("SELECT COUNT(*) c FROM messages WHERE (sender_type='user' AND sender_id={$uid}) OR (receiver_type='user' AND receiver_id={$uid})")->fetch()['c']??0);
?>
<div class="modal fade" id="view<?= $uid ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">User Profile - <?= e((string)$u['full_name']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="row g-2 mb-3">
<div class="col-md-4"><div class="border rounded p-2"><small>Email</small><div><?= e((string)$u['email']) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Phone</small><div><?= e((string)($u['phone']??'N/A')) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>BMI</small><div><?= number_format((float)$u['bmi'],2) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Current Plan</small><div><?= e((string)($u['plan_name']??'N/A')) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Dietitian</small><div><?= e((string)($u['dietitian_name']??'N/A')) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Message History Count</small><div><?= $msgCount ?></div></div></div>
</div>
<h6>BMI/Weight History</h6><ul><?php foreach($weightRows as $wr): ?><li><?= e((string)$wr['log_date']) ?> - <?= number_format((float)$wr['weight'],1) ?> kg</li><?php endforeach; if(empty($weightRows)): ?><li class="text-muted">No history.</li><?php endif; ?></ul>
<h6>Recent Food Logs (Today)</h6><ul><?php foreach($foodRows as $fr): ?><li><?= e((string)$fr['meal_type']) ?> - <?= e((string)$fr['food_name']) ?> (<?= (int)$fr['calories'] ?> kcal)</li><?php endforeach; if(empty($foodRows)): ?><li class="text-muted">No logs today.</li><?php endif; ?></ul>
</div></div></div></div>

<div class="modal fade" id="edit<?= $uid ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
<input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" value="<?= $uid ?>">
<label class="form-label">Status</label><select class="form-control" name="status"><option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $u['status']==='inactive'?'selected':'' ?>>Inactive</option></select>
<label class="form-label mt-2">Goal</label><select class="form-control" name="goal"><option value="weight_loss" <?= $u['goal']==='weight_loss'?'selected':'' ?>>Weight Loss</option><option value="gain" <?= $u['goal']==='gain'?'selected':'' ?>>Gain</option><option value="maintain" <?= $u['goal']==='maintain'?'selected':'' ?>>Maintain</option></select>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>

<div class="modal fade" id="assign<?= $uid ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Assign Dietitian</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
<input type="hidden" name="action" value="assign_dietitian"><input type="hidden" name="user_id" value="<?= $uid ?>">
<label class="form-label">Dietitian</label><select class="form-control did-sel" name="dietitian_id" data-target="planSel<?= $uid ?>" required><option value="">Select</option><?php foreach($dietitianRows as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e((string)$d['full_name']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Diet Plan</label><select class="form-control" id="planSel<?= $uid ?>" name="diet_plan_id" required><option value="">Select plan</option><?php foreach($planRows as $p): ?><option value="<?= (int)$p['id'] ?>" data-dietitian="<?= (int)$p['dietitian_id'] ?>"><?= e((string)$p['title']) ?></option><?php endforeach; ?></select>
<div class="row g-2 mt-2"><div class="col"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date" value="<?= date('Y-m-d') ?>"></div><div class="col"><label class="form-label">End</label><input type="date" class="form-control" name="end_date" value="<?= date('Y-m-d',strtotime('+30 day')) ?>"></div></div>
</div><div class="modal-footer"><button class="btn btn-success">Assign</button></div></form></div></div></div>

<div class="modal fade" id="plan<?= $uid ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">View/Change Plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><p class="mb-1"><strong>Current Plan:</strong> <?= e((string)($u['plan_name']??'N/A')) ?></p><p class="mb-0"><strong>Dietitian:</strong> <?= e((string)($u['dietitian_name']??'N/A')) ?></p><small class="text-muted">Use Assign Dietitian action to change plan.</small></div>
</div></div></div>
<?php endforeach; ?>

</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
document.getElementById('checkAll')?.addEventListener('change',e=>document.querySelectorAll('input[name="selected_ids[]"]').forEach(c=>c.checked=e.target.checked));
document.querySelectorAll('.did-sel').forEach(sel=>{sel.addEventListener('change',()=>{const target=document.getElementById(sel.dataset.target); const did=sel.value; [...target.options].forEach(o=>{if(!o.value)return; o.hidden=(o.dataset.dietitian!==did);}); target.value='';});});
</script>
</body></html>
