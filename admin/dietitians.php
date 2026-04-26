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
        if ($action === 'add') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $spec = trim((string) ($_POST['specialization'] ?? ''));
            $exp = (int) ($_POST['experience_years'] ?? 0);
            $bio = trim((string) ($_POST['bio'] ?? ''));
            if ($fullName && filter_var($email, FILTER_VALIDATE_EMAIL) && $password) {
                $ins = $pdo->prepare(
                    'INSERT INTO dietitians (full_name,email,password,phone,specialization,experience_years,bio,status,created_at)
                     VALUES (:f,:e,:p,:ph,:s,:x,:b,"active",NOW())'
                );
                $ins->execute([':f'=>$fullName,':e'=>$email,':p'=>password_hash($password,PASSWORD_DEFAULT),':ph'=>$phone,':s'=>$spec,':x'=>$exp,':b'=>$bio]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian added successfully.'];
            }
        }
        if ($action === 'update') {
            $id = (int) ($_POST['dietitian_id'] ?? 0);
            $name = trim((string) ($_POST['full_name'] ?? ''));
            $spec = trim((string) ($_POST['specialization'] ?? ''));
            $status = (string) ($_POST['status'] ?? 'active');
            if ($id > 0 && in_array($status, ['active','inactive','pending'], true)) {
                $up = $pdo->prepare('UPDATE dietitians SET full_name=:n,specialization=:s,status=:st WHERE id=:id');
                $up->execute([':n'=>$name,':s'=>$spec,':st'=>$status,':id'=>$id]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian updated.'];
            }
        }
        if ($action === 'approve' || $action === 'reject') {
            $id = (int) ($_POST['dietitian_id'] ?? 0);
            $status = $action === 'approve' ? 'active' : 'inactive';
            if ($id > 0) {
                $up = $pdo->prepare('UPDATE dietitians SET status=:s WHERE id=:id');
                $up->execute([':s' => $status, ':id' => $id]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian status updated.'];
            }
        }
        if ($action === 'toggle') {
            $id = (int) ($_POST['dietitian_id'] ?? 0);
            $new = (string) ($_POST['new_status'] ?? 'active');
            if ($id > 0 && in_array($new, ['active','inactive'], true)) {
                $up = $pdo->prepare('UPDATE dietitians SET status=:s WHERE id=:id');
                $up->execute([':s'=>$new,':id'=>$id]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian status changed.'];
            }
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['dietitian_id'] ?? 0);
            if ($id > 0) {
                $del = $pdo->prepare('DELETE FROM dietitians WHERE id=:id');
                $del->execute([':id' => $id]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian deleted.'];
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Action failed.'];
    }
}

$stmt = $pdo->query(
    'SELECT d.*, COALESCE(cnt.users_count,0) users_count
     FROM dietitians d
     LEFT JOIN (
       SELECT dietitian_id, COUNT(DISTINCT user_id) users_count
       FROM user_diet_plans GROUP BY dietitian_id
     ) cnt ON cnt.dietitian_id=d.id
     ORDER BY d.created_at DESC'
);
$rows = $stmt->fetchAll();

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Dietitians | HealthMatrix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
</head><body>
<div class="app-layout"><aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
<ul class="sidebar-menu">
<li><a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
<li><a href="<?= SITE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i>Users</a></li>
<li class="active"><a href="<?= SITE_URL ?>/admin/dietitians.php"><i class="fa-solid fa-user-doctor"></i>Dietitians</a></li>
<li><a href="<?= SITE_URL ?>/admin/diet_plans.php"><i class="fa-solid fa-utensils"></i>Diet Plans</a></li>
<li><a href="<?= SITE_URL ?>/admin/assign.php"><i class="fa-solid fa-link"></i>Assign</a></li>
<li><a href="<?= SITE_URL ?>/admin/logs.php"><i class="fa-solid fa-clipboard-list"></i>Logs</a></li>
<li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li></ul></aside>
<main class="main-content"><div class="container-fluid">
<nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Manage Dietitians</h5><small class="text-muted">Approve, update, and monitor dietitians</small></div><button class="btn btn-primary right" data-bs-toggle="modal" data-bs-target="#addDietitianModal">Add New Dietitian</button></nav>
<?php foreach($alerts as $a): ?><div class="alert alert-<?= e($a['type']) ?>"><?= e($a['text']) ?></div><?php endforeach; ?>

<div class="card"><div class="card-body table-responsive">
<table class="table table-striped"><thead><tr><th>Name</th><th>Email</th><th>Specialization</th><th>Experience</th><th>Users Count</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?= e((string)$r['full_name']) ?></td><td><?= e((string)$r['email']) ?></td><td><?= e((string)$r['specialization']) ?></td><td><?= (int)$r['experience_years'] ?> yrs</td><td><?= (int)$r['users_count'] ?></td><td><span class="badge"><?= e((string)$r['status']) ?></span></td>
<td class="d-flex gap-1 flex-wrap">
<button class="btn btn-sm btn-outline" data-bs-toggle="modal" data-bs-target="#view<?= (int)$r['id'] ?>">View</button>
<?php if((string)$r['status']==='pending'): ?>
<form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="dietitian_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-success">Approve</button></form>
<form method="post"><input type="hidden" name="action" value="reject"><input type="hidden" name="dietitian_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger">Reject</button></form>
<?php endif; ?>
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$r['id'] ?>">Edit</button>
<form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="dietitian_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="new_status" value="<?= $r['status']==='active'?'inactive':'active' ?>"><button class="btn btn-sm btn-warning"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button></form>
<a class="btn btn-sm btn-secondary" href="<?= SITE_URL ?>/admin/diet_plans.php?dietitian_id=<?= (int)$r['id'] ?>">Plans</a>
<form method="post" onsubmit="return confirm('Delete this dietitian?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="dietitian_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
</td>
</tr>
<?php endforeach; if(empty($rows)): ?><tr><td colspan="7" class="text-center text-muted">No dietitians found.</td></tr><?php endif; ?>
</tbody></table></div></div>

<?php foreach($rows as $r): ?>
<div class="modal fade" id="view<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Dietitian Profile</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<p class="mb-1"><strong>Name:</strong> <?= e((string)$r['full_name']) ?></p>
<p class="mb-1"><strong>Email:</strong> <?= e((string)$r['email']) ?></p>
<p class="mb-1"><strong>Phone:</strong> <?= e((string)($r['phone']??'N/A')) ?></p>
<p class="mb-1"><strong>Specialization:</strong> <?= e((string)$r['specialization']) ?></p>
<p class="mb-1"><strong>Experience:</strong> <?= (int)$r['experience_years'] ?> years</p>
<p class="mb-0"><strong>Bio:</strong> <?= e((string)($r['bio']??'')) ?></p>
</div></div></div></div>

<div class="modal fade" id="edit<?= (int)$r['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Dietitian</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
<input type="hidden" name="action" value="update"><input type="hidden" name="dietitian_id" value="<?= (int)$r['id'] ?>">
<label class="form-label">Name</label><input class="form-control" name="full_name" value="<?= e((string)$r['full_name']) ?>" required>
<label class="form-label mt-2">Specialization</label><input class="form-control" name="specialization" value="<?= e((string)$r['specialization']) ?>">
<label class="form-label mt-2">Status</label><select class="form-control" name="status"><option value="active" <?= $r['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $r['status']==='inactive'?'selected':'' ?>>Inactive</option><option value="pending" <?= $r['status']==='pending'?'selected':'' ?>>Pending</option></select>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>
<?php endforeach; ?>

<div class="modal fade" id="addDietitianModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add New Dietitian</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body row g-2">
<input type="hidden" name="action" value="add">
<div class="col-12"><label class="form-label">Name</label><input class="form-control" name="full_name" required></div>
<div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
<div class="col-12"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
<div class="col-12"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
<div class="col-12"><label class="form-label">Specialization</label><input class="form-control" name="specialization"></div>
<div class="col-12"><label class="form-label">Experience</label><input type="number" min="0" class="form-control" name="experience_years"></div>
<div class="col-12"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="2"></textarea></div>
</div><div class="modal-footer"><button class="btn btn-success">Add Dietitian</button></div></form>
</div></div></div>

</div></main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>

