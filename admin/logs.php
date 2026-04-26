<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['admin']);

$pdo = Database::getInstance()->getConnection();
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$userType = trim((string) ($_GET['user_type'] ?? ''));
$actionType = trim((string) ($_GET['action_type'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$where = [];
$params = [];
if (in_array($userType, ['admin', 'dietitian', 'user'], true)) { $where[] = 'user_type=:ut'; $params[':ut'] = $userType; }
if ($actionType !== '') { $where[] = 'action LIKE :ac'; $params[':ac'] = '%' . $actionType . '%'; }
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'DATE(created_at)>=:df'; $params[':df'] = $dateFrom; }
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'DATE(created_at)<=:dt'; $params[':dt'] = $dateTo; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($export) {
    $stmt = $pdo->prepare('SELECT id,user_id,user_type,action,ip_address,created_at FROM activity_logs' . $whereSql . ' ORDER BY created_at DESC');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity_logs_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User ID', 'User Type', 'Action', 'IP Address', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['user_id'], $r['user_type'], $r['action'], $r['ip_address'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) c FROM activity_logs' . $whereSql);
$countStmt->execute($params);
$total = (int) ($countStmt->fetch()['c'] ?? 0);
$offset = ($page - 1) * $perPage;
$listStmt = $pdo->prepare('SELECT id,user_id,user_type,action,ip_address,created_at FROM activity_logs' . $whereSql . " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$listStmt->execute($params);
$logs = $listStmt->fetchAll();

function actionClass(string $action): string {
    $a = strtolower($action);
    if (str_contains($a, 'login')) return 'info';
    if (str_contains($a, 'register') || str_contains($a, 'create')) return 'success';
    if (str_contains($a, 'delete') || str_contains($a, 'reject')) return 'danger';
    if (str_contains($a, 'update') || str_contains($a, 'edit')) return 'warning';
    return 'secondary';
}
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$q = $_GET; unset($q['page'], $q['export']); $baseQuery = $q ? ('?' . http_build_query($q) . '&page=') : '?page=';
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-doctor"></i>Dietitians</a></li>
<li><a href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li class="active"><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Activity Logs</h5><small class="text-muted">Track platform activity with export</small></div></nav>

<div class="card mb-3"><div class="card-body">
<form class="row g-2">
<div class="col-md-2"><select name="user_type" class="form-control"><option value="">User Type</option><option value="admin" <?= $userType==='admin'?'selected':'' ?>>Admin</option><option value="dietitian" <?= $userType==='dietitian'?'selected':'' ?>>Dietitian</option><option value="user" <?= $userType==='user'?'selected':'' ?>>User</option></select></div>
<div class="col-md-3"><input name="action_type" class="form-control" placeholder="Action type" value="<?= e($actionType) ?>"></div>
<div class="col-md-2"><input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>"></div>
<div class="col-md-2"><input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>"></div>
<div class="col-md-1"><button class="btn btn-outline w-100">Filter</button></div>
<div class="col-md-2"><a class="btn btn-success w-100" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</a></div>
</form></div></div>

<div class="card"><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>ID</th><th>User ID</th><th>User Type</th><th>Action</th><th>IP</th><th>Date/Time</th></tr></thead><tbody>
<?php foreach($logs as $l): $ac=actionClass((string)$l['action']); ?>
<tr><td><?= (int)$l['id'] ?></td><td><?= (int)$l['user_id'] ?></td><td><span class="badge"><?= e((string)$l['user_type']) ?></span></td><td><span class="badge bg-<?= $ac ?>-subtle text-<?= $ac ?>-emphasis"><?= e((string)$l['action']) ?></span></td><td><?= e((string)$l['ip_address']) ?></td><td><?= e(date('M d, Y h:i A',strtotime((string)$l['created_at']))) ?></td></tr>
<?php endforeach; if(empty($logs)): ?><tr><td colspan="6" class="text-center text-muted">No logs found.</td></tr><?php endif; ?>
</tbody></table>
<?= generatePagination($total,$page,$perPage,$baseQuery) ?>
</div></div>

</div></main></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

