<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['admin']);

$pdo = Database::getInstance()->getConnection();
$alerts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'assign_single') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $dietitianId = (int) ($_POST['dietitian_id'] ?? 0);
            $planId = (int) ($_POST['diet_plan_id'] ?? 0);
            $start = (string) ($_POST['start_date'] ?? date('Y-m-d'));
            $end = (string) ($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 day')));
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($userId > 0 && $dietitianId > 0 && $planId > 0) {
                $ins = $pdo->prepare(
                    'INSERT INTO user_diet_plans (user_id,diet_plan_id,dietitian_id,assigned_date,end_date,status,dietitian_notes)
                     VALUES (:u,:p,:d,:s,:e,"active",:n)'
                );
                $ins->execute([':u'=>$userId,':p'=>$planId,':d'=>$dietitianId,':s'=>$start,':e'=>$end,':n'=>$note]);
                $alerts[] = ['type' => 'success', 'text' => 'Assignment created.'];
            }
        }
        if ($action === 'csv_upload') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $alerts[] = ['type' => 'danger', 'text' => 'CSV upload failed.'];
            } else {
                $handle = fopen((string) $_FILES['csv_file']['tmp_name'], 'r');
                if ($handle === false) {
                    $alerts[] = ['type' => 'danger', 'text' => 'Could not read CSV file.'];
                } else {
                    $header = fgetcsv($handle);
                    $inserted = 0; $skipped = 0;
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) < 3) { $skipped++; continue; }
                        [$userEmail, $dietEmail, $planTitle] = array_map('trim', $row);
                        $u = $pdo->prepare('SELECT id FROM users WHERE email=:e LIMIT 1'); $u->execute([':e' => $userEmail]); $uid = (int) ($u->fetch()['id'] ?? 0);
                        $d = $pdo->prepare('SELECT id FROM dietitians WHERE email=:e LIMIT 1'); $d->execute([':e' => $dietEmail]); $did = (int) ($d->fetch()['id'] ?? 0);
                        $p = $pdo->prepare('SELECT id FROM diet_plans WHERE title=:t AND dietitian_id=:d LIMIT 1'); $p->execute([':t' => $planTitle, ':d' => $did]); $pid = (int) ($p->fetch()['id'] ?? 0);
                        if ($uid && $did && $pid) {
                            $ins = $pdo->prepare('INSERT INTO user_diet_plans (user_id,diet_plan_id,dietitian_id,assigned_date,end_date,status,dietitian_notes) VALUES (:u,:p,:d,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),"active","Bulk CSV assignment")');
                            $ins->execute([':u' => $uid, ':p' => $pid, ':d' => $did]);
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    }
                    fclose($handle);
                    $alerts[] = ['type' => 'success', 'text' => "CSV processed. Inserted: {$inserted}, Skipped: {$skipped}."];
                }
            }
        }
        if ($action === 'remove_assignment') {
            $id = (int) ($_POST['assignment_id'] ?? 0);
            if ($id > 0) {
                $del = $pdo->prepare('DELETE FROM user_diet_plans WHERE id=:id');
                $del->execute([':id' => $id]);
                $alerts[] = ['type' => 'success', 'text' => 'Assignment removed.'];
            }
        }
        if ($action === 'edit_assignment') {
            $id = (int) ($_POST['assignment_id'] ?? 0);
            $start = (string) ($_POST['start_date'] ?? date('Y-m-d'));
            $end = (string) ($_POST['end_date'] ?? date('Y-m-d'));
            $status = (string) ($_POST['status'] ?? 'active');
            if ($id > 0 && in_array($status, ['active', 'completed', 'pending'], true)) {
                $up = $pdo->prepare('UPDATE user_diet_plans SET assigned_date=:s,end_date=:e,status=:st WHERE id=:id');
                $up->execute([':s'=>$start,':e'=>$end,':st'=>$status,':id'=>$id]);
                $alerts[] = ['type' => 'success', 'text' => 'Assignment updated.'];
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Operation failed.'];
    }
}

$users = $pdo->query('SELECT id,full_name,email FROM users ORDER BY full_name')->fetchAll();
$dietitians = $pdo->query('SELECT id,full_name,email FROM dietitians WHERE status="active" ORDER BY full_name')->fetchAll();
$plans = $pdo->query('SELECT id,title,dietitian_id FROM diet_plans WHERE status="active" ORDER BY title')->fetchAll();

$assignments = $pdo->query(
    'SELECT udp.id,u.full_name user_name,u.email user_email,d.full_name dietitian_name,d.email dietitian_email,
            dp.title plan_title,udp.assigned_date,udp.end_date,udp.status
     FROM user_diet_plans udp
     JOIN users u ON u.id=udp.user_id
     JOIN dietitians d ON d.id=udp.dietitian_id
     JOIN diet_plans dp ON dp.id=udp.diet_plan_id
     ORDER BY udp.assigned_date DESC'
)->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Diet Plans | HealthMatrix</title>
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
<li class="active"><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Assign Dietitian to User</h5><small class="text-muted">Manual and bulk assignment tools</small></div></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="row g-3 mb-3">
<div class="col-lg-7"><div class="card"><div class="card-header">Single Assignment</div><div class="card-body">
<form method="post" class="row g-2">
<input type="hidden" name="action" value="assign_single">
<div class="col-md-6"><label class="form-label">User</label><select class="form-control" name="user_id" required><option value="">Select user</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e((string)$u['full_name']) ?> (<?= e((string)$u['email']) ?>)</option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Dietitian</label><select class="form-control" id="dietitianSelect" name="dietitian_id" required><option value="">Select dietitian</option><?php foreach($dietitians as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e((string)$d['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Diet Plan</label><select class="form-control" id="planSelect" name="diet_plan_id" required><option value="">Select plan</option><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>" data-dietitian="<?= (int)$p['dietitian_id'] ?>"><?= e((string)$p['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date" value="<?= date('Y-m-d') ?>"></div>
<div class="col-md-3"><label class="form-label">End</label><input type="date" class="form-control" name="end_date" value="<?= date('Y-m-d',strtotime('+30 day')) ?>"></div>
<div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="note" rows="2"></textarea></div>
<div class="col-12"><button class="btn btn-primary">Submit Assignment</button></div>
</form>
</div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Bulk Assignment (CSV)</div><div class="card-body">
<p class="text-muted mb-2">CSV columns: <code>user_email,dietitian_email,plan_title</code></p>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="csv_upload">
<input type="file" class="form-control mb-2" name="csv_file" accept=".csv" required>
<button class="btn btn-success">Upload & Assign</button>
</form>
</div></div></div>
</div>

<div class="card"><div class="card-header">Current Assignments</div><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>User</th><th>Dietitian</th><th>Plan</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($assignments as $a): ?>
<tr><td><?= e((string)$a['user_name']) ?><small class="d-block text-muted"><?= e((string)$a['user_email']) ?></small></td><td><?= e((string)$a['dietitian_name']) ?><small class="d-block text-muted"><?= e((string)$a['dietitian_email']) ?></small></td><td><?= e((string)$a['plan_title']) ?></td><td><?= e((string)$a['assigned_date']) ?></td><td><?= e((string)$a['end_date']) ?></td><td><span class="badge"><?= e((string)$a['status']) ?></span></td>
<td class="d-flex gap-1">
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$a['id'] ?>">Edit</button>
<form method="post" onsubmit="return confirm('Remove assignment?');"><input type="hidden" name="action" value="remove_assignment"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-danger">Remove</button></form>
</td></tr>
<?php endforeach; if(empty($assignments)): ?><tr><td colspan="7" class="text-center text-muted">No assignments found.</td></tr><?php endif; ?>
</tbody></table></div></div>

<?php foreach($assignments as $a): ?>
<div class="modal fade" id="edit<?= (int)$a['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Assignment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
<input type="hidden" name="action" value="edit_assignment"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
<label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" value="<?= e((string)$a['assigned_date']) ?>">
<label class="form-label mt-2">End Date</label><input type="date" class="form-control" name="end_date" value="<?= e((string)$a['end_date']) ?>">
<label class="form-label mt-2">Status</label><select class="form-control" name="status"><option value="active" <?= $a['status']==='active'?'selected':'' ?>>Active</option><option value="completed" <?= $a['status']==='completed'?'selected':'' ?>>Completed</option><option value="pending" <?= $a['status']==='pending'?'selected':'' ?>>Pending</option></select>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>
<?php endforeach; ?>

</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
document.getElementById('dietitianSelect')?.addEventListener('change',function(){const did=this.value;const p=document.getElementById('planSelect');[...p.options].forEach(o=>{if(!o.value)return;o.hidden=(o.dataset.dietitian!==did)});p.value='';});
</script>
</body></html>

