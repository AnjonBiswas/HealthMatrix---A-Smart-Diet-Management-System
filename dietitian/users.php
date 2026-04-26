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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_note') {
            $udpId = (int) ($_POST['udp_id'] ?? 0);
            $note = trim((string) ($_POST['dietitian_note'] ?? ''));
            if ($udpId > 0) {
                $stmt = $pdo->prepare('UPDATE user_diet_plans SET dietitian_notes=:n WHERE id=:id AND dietitian_id=:d');
                $stmt->execute([':n' => $note, ':id' => $udpId, ':d' => $dietitianId]);
                $alerts[] = ['type' => 'success', 'text' => 'Note updated.'];
                logActivity($dietitianId, 'dietitian', 'Updated user note');
            }
        }
        if ($action === 'send_message') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $message = trim((string) ($_POST['message'] ?? ''));
            if ($userId <= 0 || $message === '') {
                $alerts[] = ['type' => 'danger', 'text' => 'Message cannot be empty.'];
            } elseif (mb_strlen($message) > 1000) {
                $alerts[] = ['type' => 'danger', 'text' => 'Message too long (max 1000).'];
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO messages (sender_id,sender_type,receiver_id,receiver_type,message,is_read,created_at)
                     VALUES (:sid,"dietitian",:rid,"user",:m,0,NOW())'
                );
                $stmt->execute([':sid' => $dietitianId, ':rid' => $userId, ':m' => $message]);
                $alerts[] = ['type' => 'success', 'text' => 'Message sent to user.'];
                logActivity($dietitianId, 'dietitian', 'Sent message to user');
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed.'];
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$planFilter = (int) ($_GET['plan_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$plansQ = $pdo->prepare('SELECT id,title FROM diet_plans WHERE dietitian_id=:d ORDER BY title ASC');
$plansQ->execute([':d' => $dietitianId]);
$plans = $plansQ->fetchAll();

$sql = 'SELECT udp.id udp_id, udp.user_id, udp.status assign_status, udp.assigned_date, udp.end_date, udp.dietitian_notes,
        u.full_name,u.email,u.bmi,u.goal,u.age,u.weight,u.height,u.gender,u.activity_level,u.daily_calorie_goal,
        dp.title plan_name, dp.id plan_id
        FROM user_diet_plans udp
        JOIN users u ON u.id=udp.user_id
        JOIN diet_plans dp ON dp.id=udp.diet_plan_id
        WHERE udp.dietitian_id=:d';
$params = [':d' => $dietitianId];
if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE :q OR u.email LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($planFilter > 0) {
    $sql .= ' AND dp.id=:pid';
    $params[':pid'] = $planFilter;
}
if (in_array($statusFilter, ['active', 'completed', 'pending'], true)) {
    $sql .= ' AND udp.status=:st';
    $params[':st'] = $statusFilter;
}
$sql .= ' ORDER BY udp.assigned_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function pct(string $s, ?string $e): int {
    if (!$e) return 0; $st=strtotime($s); $en=strtotime($e); if($en<=$st)return 0;
    return max(0,min(100,(int)round(((strtotime(date('Y-m-d'))-$st)/($en-$st))*100)));
}
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assigned Users | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
<li class="active"><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Assigned Users</h5><small class="text-muted">Track users and engagement</small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="card mb-3"><div class="card-body">
<form class="row g-2">
<div class="col-md-4"><input class="form-control" name="q" placeholder="Search name/email" value="<?= e($search) ?>"></div>
<div class="col-md-3"><select class="form-control" name="plan_id"><option value="0">All plans</option><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $planFilter===(int)$p['id']?'selected':'' ?>><?= e((string)$p['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><select class="form-control" name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="completed" <?= $statusFilter==='completed'?'selected':'' ?>>Completed</option><option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option></select></div>
<div class="col-md-2"><button class="btn btn-outline w-100">Filter</button></div>
</form></div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>Name</th><th>Email</th><th>BMI</th><th>Goal</th><th>Plan</th><th>Start Date</th><th>Progress</th></tr></thead><tbody>
<?php foreach($rows as $r): $pr=pct((string)$r['assigned_date'], (string)($r['end_date']??'')); ?>
<tr data-bs-toggle="modal" data-bs-target="#u<?= (int)$r['udp_id'] ?>" style="cursor:pointer;">
<td><?= e((string)$r['full_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= number_format((float)$r['bmi'],2) ?></td><td><?= e(ucwords(str_replace('_',' ',(string)$r['goal']))) ?></td><td><?= e((string)$r['plan_name']) ?></td><td><?= e((string)$r['assigned_date']) ?></td><td><?= $pr ?>%</td>
</tr>
<?php endforeach; if(empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">No users found.</td></tr><?php endif; ?>
</tbody></table></div></div>

<?php foreach($rows as $r):
$wq=$pdo->prepare('SELECT log_date,weight FROM weight_log WHERE user_id=:u ORDER BY log_date ASC LIMIT 20'); $wq->execute([':u'=>(int)$r['user_id']]); $wr=$wq->fetchAll();
$wLabels=[];$wData=[]; foreach($wr as $x){$wLabels[]=date('M d',strtotime((string)$x['log_date']));$wData[]=(float)$x['weight'];}
$fq=$pdo->prepare('SELECT COALESCE(SUM(calories),0) c,COALESCE(SUM(protein),0) p,COALESCE(SUM(carbs),0) cb,COALESCE(SUM(fat),0) f FROM food_log WHERE user_id=:u AND log_date=CURDATE()');
$fq->execute([':u'=>(int)$r['user_id']]); $foodToday=$fq->fetch();
?>
<div class="modal fade" id="u<?= (int)$r['udp_id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><?= e((string)$r['full_name']) ?> - User Detail</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-3 mb-3">
<div class="col-md-4"><div class="border rounded p-2"><small>Age</small><div><?= (int)$r['age'] ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Weight</small><div><?= number_format((float)$r['weight'],1) ?> kg</div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Height</small><div><?= number_format((float)$r['height'],1) ?> cm</div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>BMI</small><div><?= number_format((float)$r['bmi'],2) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Activity</small><div><?= e(ucwords(str_replace('_',' ',(string)$r['activity_level']))) ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-2"><small>Calorie Goal</small><div><?= (int)$r['daily_calorie_goal'] ?> kcal</div></div></div>
</div>
<div class="card mb-3"><div class="card-header">Weight Trend</div><div class="card-body"><canvas id="wc<?= (int)$r['udp_id'] ?>" height="100"></canvas></div></div>
<div class="card mb-3"><div class="card-header">Today's Food Summary</div><div class="card-body"><small>Calories: <strong><?= (int)$foodToday['c'] ?></strong> | Protein: <strong><?= number_format((float)$foodToday['p'],1) ?>g</strong> | Carbs: <strong><?= number_format((float)$foodToday['cb'],1) ?>g</strong> | Fat: <strong><?= number_format((float)$foodToday['f'],1) ?>g</strong></small></div></div>
<form method="post" class="mb-3"><input type="hidden" name="action" value="add_note"><input type="hidden" name="udp_id" value="<?= (int)$r['udp_id'] ?>"><label class="form-label">Add Note</label><textarea class="form-control" name="dietitian_note" rows="2"><?= e((string)$r['dietitian_notes']) ?></textarea><button class="btn btn-primary mt-2">Save Note</button></form>
<form method="post"><input type="hidden" name="action" value="send_message"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><label class="form-label">Send Message</label><textarea class="form-control" name="message" rows="2" placeholder="Write a message..." required></textarea><button class="btn btn-success mt-2">Send Message</button></form>
</div>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const c=document.getElementById('wc<?= (int)$r['udp_id'] ?>');if(!c)return;new Chart(c,{type:'line',data:{labels:<?= json_encode($wLabels) ?>,datasets:[{label:'Weight',data:<?= json_encode($wData) ?>,borderColor:'#3498DB',backgroundColor:'rgba(52,152,219,.15)',fill:true,tension:.3}]},options:{plugins:{legend:{display:false}},responsive:true}});});
</script>
<?php endforeach; ?>

</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

