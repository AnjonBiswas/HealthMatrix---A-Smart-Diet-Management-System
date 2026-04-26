<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['admin']);

$pdo = Database::getInstance()->getConnection();

$stats = ['users' => 0, 'new_users_week' => 0, 'dietitians' => 0, 'active_plans' => 0, 'pending_requests' => 0, 'today_logins' => 0, 'messages_today' => 0];
$stats['users'] = (int) ($pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] ?? 0);
$stats['new_users_week'] = (int) ($pdo->query('SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetch()['c'] ?? 0);
$stats['dietitians'] = (int) ($pdo->query('SELECT COUNT(*) c FROM dietitians')->fetch()['c'] ?? 0);
$stats['active_plans'] = (int) ($pdo->query('SELECT COUNT(*) c FROM diet_plans WHERE status="active"')->fetch()['c'] ?? 0);
$stats['pending_requests'] = (int) ($pdo->query('SELECT COUNT(*) c FROM dietitian_requests WHERE status="pending"')->fetch()['c'] ?? 0);
$q = $pdo->prepare('SELECT COUNT(*) c FROM activity_logs WHERE DATE(created_at)=CURDATE() AND action LIKE :a');
$q->execute([':a' => '%login%']); $stats['today_logins'] = (int) ($q->fetch()['c'] ?? 0);
$stats['messages_today'] = (int) ($pdo->query('SELECT COUNT(*) c FROM messages WHERE DATE(created_at)=CURDATE()')->fetch()['c'] ?? 0);

$regMap = [];
$regQ = $pdo->query('SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY d ASC');
foreach ($regQ->fetchAll() as $r) $regMap[(string) $r['d']] = (int) $r['c'];
$regLabels = []; $regVals = [];
for ($i=29;$i>=0;$i--){$d=date('Y-m-d',strtotime("-{$i} day"));$regLabels[]=date('M d',strtotime($d));$regVals[]=$regMap[$d]??0;}

$distQ = $pdo->query('SELECT goal_type, COUNT(*) c FROM diet_plans GROUP BY goal_type');
$goalDist = ['weight_loss'=>0,'gain'=>0,'maintain'=>0];
foreach ($distQ->fetchAll() as $r) $goalDist[(string)$r['goal_type']] = (int)$r['c'];

$activeQ = $pdo->query(
    'SELECT u.full_name, COUNT(fl.id) logs
     FROM users u LEFT JOIN food_log fl ON fl.user_id=u.id AND fl.log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY u.id,u.full_name ORDER BY logs DESC LIMIT 7'
);
$activeRows = $activeQ->fetchAll();
$activeNames=[];$activeCounts=[];foreach($activeRows as $r){$activeNames[]=(string)$r['full_name'];$activeCounts[]=(int)$r['logs'];}

$typeFilter = trim((string) ($_GET['activity_type'] ?? ''));
$logSql = 'SELECT * FROM activity_logs';
$where=[];$params=[];
if ($typeFilter !== '') {$where[]='action LIKE :a';$params[':a']='%'.$typeFilter.'%';}
if ($where) $logSql .= ' WHERE '.implode(' AND ',$where);
$logSql .= ' ORDER BY created_at DESC LIMIT 20';
$logsStmt = $pdo->prepare($logSql);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li class="active"><a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-doctor"></i>Dietitians</a></li>
<li><a href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Admin Dashboard</h5><small class="text-muted">System-wide overview and activity</small></div></nav>

<div class="grid-3 mb-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
<div class="stat-card"><h3><i class="fa-solid fa-users me-1"></i>Total Users</h3><div class="value"><?= $stats['users'] ?></div><small class="badge">+<?= $stats['new_users_week'] ?> this week</small></div>
<div class="stat-card"><h3><i class="fa-solid fa-user-doctor me-1"></i>Total Dietitians</h3><div class="value"><?= $stats['dietitians'] ?></div></div>
<div class="stat-card"><h3><i class="fa-solid fa-utensils me-1"></i>Active Plans</h3><div class="value"><?= $stats['active_plans'] ?></div></div>
<div class="stat-card warning"><h3><i class="fa-solid fa-hourglass-half me-1"></i>Pending Requests</h3><div class="value"><?= $stats['pending_requests'] ?></div></div>
<div class="stat-card info"><h3><i class="fa-solid fa-right-to-bracket me-1"></i>Today's Logins</h3><div class="value"><?= $stats['today_logins'] ?></div></div>
<div class="stat-card"><h3><i class="fa-solid fa-message me-1"></i>Messages Today</h3><div class="value"><?= $stats['messages_today'] ?></div></div>
</div>

<div class="row g-3 mb-3">
<div class="col-lg-4"><div class="card"><div class="card-header">User Registration Trend (30 Days)</div><div class="card-body"><canvas id="regChart" height="180"></canvas></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">Diet Plan Distribution by Goal</div><div class="card-body"><canvas id="goalChart" height="180"></canvas></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">Most Active Users</div><div class="card-body"><canvas id="activeChart" height="180"></canvas></div></div></div>
</div>

<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between"><span>Recent Activity Feed</span>
<form method="get" class="d-flex gap-2"><input class="form-control form-control-sm" name="activity_type" placeholder="Filter action" value="<?= e($typeFilter) ?>"><button class="btn btn-sm btn-outline">Apply</button></form>
</div><div class="card-body table-responsive"><table class="table table-striped"><thead><tr><th>User Type</th><th>User ID</th><th>Action</th><th>IP</th><th>Time</th></tr></thead><tbody>
<?php foreach($logs as $l): ?><tr><td><span class="badge"><?= e((string)$l['user_type']) ?></span></td><td><?= (int)$l['user_id'] ?></td><td><?= e((string)$l['action']) ?></td><td><?= e((string)$l['ip_address']) ?></td><td><?= e(date('M d, h:i A',strtotime((string)$l['created_at']))) ?></td></tr><?php endforeach; if(empty($logs)): ?><tr><td colspan="5" class="text-center text-muted">No activity found.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header">Quick Links</div><div class="card-body d-grid gap-2">
<a class="btn btn-primary" href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-plus me-1"></i>Add Dietitian</a>
<a class="btn btn-secondary" href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users me-1"></i>View Users</a>
<a class="btn btn-outline" href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils me-1"></i>Manage Plans</a>
<a class="btn btn-success" href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link me-1"></i>Assign Plans</a>
</div></div></div>
</div>
</div></main></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
new Chart(document.getElementById('regChart'),{type:'line',data:{labels:<?= json_encode($regLabels) ?>,datasets:[{label:'Users',data:<?= json_encode($regVals) ?>,borderColor:'#3498DB',backgroundColor:'rgba(52,152,219,.14)',fill:true,tension:.3}]},options:{plugins:{legend:{display:false}},responsive:true}});
new Chart(document.getElementById('goalChart'),{type:'pie',data:{labels:['Weight Loss','Gain','Maintain'],datasets:[{data:[<?= $goalDist['weight_loss'] ?>,<?= $goalDist['gain'] ?>,<?= $goalDist['maintain'] ?>],backgroundColor:['#2ECC71','#F39C12','#3498DB']}]},options:{responsive:true}});
new Chart(document.getElementById('activeChart'),{type:'bar',data:{labels:<?= json_encode($activeNames) ?>,datasets:[{data:<?= json_encode($activeCounts) ?>,backgroundColor:'#27AE60'}]},options:{indexAxis:'y',plugins:{legend:{display:false}},responsive:true}});
</script>
</body></html>

