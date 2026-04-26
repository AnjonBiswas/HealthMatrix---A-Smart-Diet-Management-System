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
        if ($action === 'approve_request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $planId = (int) ($_POST['diet_plan_id'] ?? 0);
            if ($requestId <= 0 || $planId <= 0) {
                $alerts[] = ['type' => 'danger', 'text' => 'Invalid request or plan.'];
            } else {
                $stmtReq = $pdo->prepare('SELECT id,user_id,status FROM dietitian_requests WHERE id=:id AND dietitian_id=:d LIMIT 1');
                $stmtReq->execute([':id' => $requestId, ':d' => $dietitianId]);
                $req = $stmtReq->fetch();

                $stmtPlan = $pdo->prepare('SELECT id,duration_days FROM diet_plans WHERE id=:id AND dietitian_id=:d LIMIT 1');
                $stmtPlan->execute([':id' => $planId, ':d' => $dietitianId]);
                $plan = $stmtPlan->fetch();

                if (!$req || !$plan || (string) $req['status'] !== 'pending') {
                    $alerts[] = ['type' => 'danger', 'text' => 'Request or plan not valid for approval.'];
                } else {
                    $start = date('Y-m-d');
                    $end = date('Y-m-d', strtotime('+' . max(1, (int) $plan['duration_days']) . ' day'));
                    $stmtAssign = $pdo->prepare(
                        'INSERT INTO user_diet_plans
                         (user_id,diet_plan_id,dietitian_id,assigned_date,end_date,status,dietitian_notes)
                         VALUES (:u,:p,:d,:a,:e,"active","Plan assigned by dietitian dashboard")'
                    );
                    $stmtAssign->execute([
                        ':u' => (int) $req['user_id'],
                        ':p' => $planId,
                        ':d' => $dietitianId,
                        ':a' => $start,
                        ':e' => $end,
                    ]);

                    $stmtUpd = $pdo->prepare('UPDATE dietitian_requests SET status="approved" WHERE id=:id');
                    $stmtUpd->execute([':id' => $requestId]);
                    $alerts[] = ['type' => 'success', 'text' => 'Request approved and plan assigned.'];
                    logActivity($dietitianId, 'dietitian', 'Approved dietitian request');
                }
            }
        }

        if ($action === 'reject_request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            if ($requestId > 0) {
                $stmt = $pdo->prepare('UPDATE dietitian_requests SET status="rejected" WHERE id=:id AND dietitian_id=:d AND status="pending"');
                $stmt->execute([':id' => $requestId, ':d' => $dietitianId]);
                $alerts[] = ['type' => 'success', 'text' => 'Request rejected.'];
                logActivity($dietitianId, 'dietitian', 'Rejected dietitian request');
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed. Please try again.'];
    }
}

$stats = [
    'users' => 0,
    'plans' => 0,
    'pending' => 0,
    'unread' => 0,
];

$q = $pdo->prepare('SELECT COUNT(DISTINCT user_id) c FROM user_diet_plans WHERE dietitian_id=:d');
$q->execute([':d' => $dietitianId]);
$stats['users'] = (int) ($q->fetch()['c'] ?? 0);

$q = $pdo->prepare('SELECT COUNT(*) c FROM diet_plans WHERE dietitian_id=:d AND status="active"');
$q->execute([':d' => $dietitianId]);
$stats['plans'] = (int) ($q->fetch()['c'] ?? 0);

$q = $pdo->prepare('SELECT COUNT(*) c FROM dietitian_requests WHERE dietitian_id=:d AND status="pending"');
$q->execute([':d' => $dietitianId]);
$stats['pending'] = (int) ($q->fetch()['c'] ?? 0);

$q = $pdo->prepare('SELECT COUNT(*) c FROM messages WHERE receiver_type="dietitian" AND receiver_id=:d AND sender_type="user" AND is_read=0');
$q->execute([':d' => $dietitianId]);
$stats['unread'] = (int) ($q->fetch()['c'] ?? 0);

$plans = $pdo->prepare('SELECT id,title FROM diet_plans WHERE dietitian_id=:d ORDER BY created_at DESC');
$plans->execute([':d' => $dietitianId]);
$dietPlans = $plans->fetchAll();

$activity = $pdo->prepare(
    'SELECT u.full_name user_name, dp.title plan_name, MAX(fl.log_date) last_log_date,
            udp.assigned_date, udp.end_date, udp.id udp_id
     FROM user_diet_plans udp
     JOIN users u ON u.id=udp.user_id
     JOIN diet_plans dp ON dp.id=udp.diet_plan_id
     LEFT JOIN food_log fl ON fl.user_id=u.id
     WHERE udp.dietitian_id=:d
     GROUP BY udp.id,u.full_name,dp.title,udp.assigned_date,udp.end_date
     ORDER BY MAX(fl.log_date) DESC, udp.assigned_date DESC
     LIMIT 15'
);
$activity->execute([':d' => $dietitianId]);
$recentActivity = $activity->fetchAll();

$pendingReq = $pdo->prepare(
    'SELECT dr.id, dr.user_id, dr.created_at, u.full_name
     FROM dietitian_requests dr
     JOIN users u ON u.id=dr.user_id
     WHERE dr.dietitian_id=:d AND dr.status="pending"
     ORDER BY dr.created_at ASC'
);
$pendingReq->execute([':d' => $dietitianId]);
$pendingRequests = $pendingReq->fetchAll();

$msg = $pdo->prepare(
    'SELECT m.sender_id user_id, u.full_name user_name, m.message, m.created_at
     FROM messages m
     JOIN users u ON u.id=m.sender_id
     WHERE m.receiver_type="dietitian" AND m.receiver_id=:d AND m.sender_type="user"
     ORDER BY m.created_at DESC
     LIMIT 6'
);
$msg->execute([':d' => $dietitianId]);
$recentMessages = $msg->fetchAll();

$exp = $pdo->prepare(
    'SELECT u.full_name, dp.title plan_name, udp.end_date
     FROM user_diet_plans udp
     JOIN users u ON u.id=udp.user_id
     JOIN diet_plans dp ON dp.id=udp.diet_plan_id
     WHERE udp.dietitian_id=:d AND udp.status="active"
       AND udp.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
     ORDER BY udp.end_date ASC'
);
$exp->execute([':d' => $dietitianId]);
$expiring = $exp->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function progressPct(string $start, ?string $end): int {
    if (!$end) return 0;
    $s = strtotime($start); $e = strtotime($end); $n = strtotime(date('Y-m-d'));
    if ($e <= $s) return 0;
    return max(0, min(100, (int) round((($n - $s) / ($e - $s)) * 100)));
}
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dietitian Dashboard | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li class="active"><a href="<?= SITE_URL ?>/dietitian/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus"></i>Create Plan</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
<li><a href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group"></i>Templates</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Dietitian Dashboard</h5><small class="text-muted">Overview and pending actions</small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="dashboard-stats mb-3">
<div class="stat-card"><h3>Total Assigned Users</h3><div class="value"><?= $stats['users'] ?></div></div>
<div class="stat-card"><h3>Active Diet Plans</h3><div class="value"><?= $stats['plans'] ?></div></div>
<div class="stat-card warning"><h3>Pending Requests</h3><div class="value"><?= $stats['pending'] ?></div></div>
<div class="stat-card info"><h3>Unread Messages</h3><div class="value"><?= $stats['unread'] ?></div></div>
</div>

<div class="row g-3">
<div class="col-lg-8">
<div class="card mb-3"><div class="card-header">Recent User Activity</div><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>User Name</th><th>Plan Name</th><th>Last Log Date</th><th>Progress %</th><th>Action</th></tr></thead><tbody>
<?php foreach($recentActivity as $r): $p=progressPct((string)$r['assigned_date'], (string)($r['end_date']??'')); ?>
<tr><td><?= e((string)$r['user_name']) ?></td><td><?= e((string)$r['plan_name']) ?></td><td><?= $r['last_log_date']?e(date('M d, Y',strtotime((string)$r['last_log_date']))):'N/A' ?></td><td><?= $p ?>%</td><td><a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/dietitian/users.php">View</a></td></tr>
<?php endforeach; if(empty($recentActivity)): ?><tr><td colspan="5" class="text-center text-muted">No activity records.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="card"><div class="card-header">Pending Requests</div><div class="card-body">
<?php foreach($pendingRequests as $r): ?>
<div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between align-items-start gap-2">
<div><strong><?= e((string)$r['full_name']) ?></strong><br><small class="text-muted">Requested on <?= e(date('M d, Y',strtotime((string)$r['created_at']))) ?></small></div>
<div class="d-flex gap-2">
<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveReq<?= (int)$r['id'] ?>">Approve</button>
<form method="post"><input type="hidden" name="action" value="reject_request"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm">Reject</button></form>
</div></div></div>
<div class="modal fade" id="approveReq<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Approve Request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
<input type="hidden" name="action" value="approve_request"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
<label class="form-label">Select Plan for <?= e((string)$r['full_name']) ?></label>
<select class="form-control" name="diet_plan_id" required><option value="">Choose a plan</option><?php foreach($dietPlans as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e((string)$p['title']) ?></option><?php endforeach; ?></select>
</div><div class="modal-footer"><button class="btn btn-outline" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Approve & Assign</button></div></form></div></div></div>
<?php endforeach; if(empty($pendingRequests)): ?><p class="text-muted mb-0">No pending requests.</p><?php endif; ?>
</div></div>
</div>

<div class="col-lg-4">
<div class="card mb-3"><div class="card-header">Quick Actions</div><div class="card-body d-grid gap-2">
<a class="btn btn-primary" href="<?= SITE_URL ?>/dietitian/create_plan.php"><i class="fa-solid fa-plus me-1"></i>Create New Plan</a>
<a class="btn btn-secondary" href="<?= SITE_URL ?>/dietitian/users.php"><i class="fa-solid fa-users me-1"></i>Manage Users</a>
<a class="btn btn-outline" href="<?= SITE_URL ?>/dietitian/templates.php"><i class="fa-solid fa-layer-group me-1"></i>Templates</a>
</div></div>

<div class="card mb-3"><div class="card-header">Recent Messages</div><div class="card-body">
<?php foreach($recentMessages as $m): ?><div class="border-bottom pb-2 mb-2"><strong><?= e((string)$m['user_name']) ?></strong><small class="text-muted d-block"><?= e(date('M d, h:i A',strtotime((string)$m['created_at']))) ?></small><small><?= e(mb_strimwidth((string)$m['message'],0,60,'...')) ?></small></div><?php endforeach; if(empty($recentMessages)): ?><p class="text-muted mb-0">No recent messages.</p><?php endif; ?>
</div></div>

<div class="card"><div class="card-header">Plan Expiry This Week</div><div class="card-body">
<?php foreach($expiring as $x): ?><div class="border rounded p-2 mb-2"><strong><?= e((string)$x['full_name']) ?></strong><small class="d-block text-muted"><?= e((string)$x['plan_name']) ?></small><small>Expires: <?= e(date('D, M d',strtotime((string)$x['end_date']))) ?></small></div><?php endforeach; if(empty($expiring)): ?><p class="text-muted mb-0">No plans expiring this week.</p><?php endif; ?>
</div></div>
</div>
</div>
</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

