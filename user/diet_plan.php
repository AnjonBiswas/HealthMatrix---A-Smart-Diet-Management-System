<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$alerts = [];

if ($userId <= 0) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'request_dietitian') {
    $dietitianId = (int) ($_POST['dietitian_id'] ?? 0);
    try {
        if ($dietitianId <= 0) {
            $alerts[] = ['type' => 'danger', 'text' => 'Please choose a dietitian.'];
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, status FROM dietitian_requests
                 WHERE user_id = :user_id AND dietitian_id = :dietitian_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':dietitian_id' => $dietitianId,
            ]);
            $existing = $stmt->fetch();

            if ($existing && (string) $existing['status'] === 'pending') {
                $alerts[] = ['type' => 'warning', 'text' => 'You already have a pending request for this dietitian.'];
            } elseif ($existing) {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE dietitian_requests
                     SET status = "pending", created_at = NOW()
                     WHERE id = :id AND user_id = :user_id'
                );
                $stmtUpdate->execute([
                    ':id' => (int) $existing['id'],
                    ':user_id' => $userId,
                ]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian request submitted successfully.'];
                logActivity($userId, 'user', 'Requested a dietitian');
            } else {
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO dietitian_requests (user_id, dietitian_id, status, created_at)
                     VALUES (:user_id, :dietitian_id, "pending", NOW())'
                );
                $stmtInsert->execute([
                    ':user_id' => $userId,
                    ':dietitian_id' => $dietitianId,
                ]);
                $alerts[] = ['type' => 'success', 'text' => 'Dietitian request submitted successfully.'];
                logActivity($userId, 'user', 'Requested a dietitian');
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Could not submit request right now.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'cancel_request') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    try {
        if ($requestId <= 0) {
            $alerts[] = ['type' => 'danger', 'text' => 'Invalid request id.'];
        } else {
            $stmtCancel = $pdo->prepare(
                'DELETE FROM dietitian_requests
                 WHERE id = :id AND user_id = :user_id AND status = "pending"'
            );
            $stmtCancel->execute([
                ':id' => $requestId,
                ':user_id' => $userId,
            ]);
            if ($stmtCancel->rowCount() > 0) {
                $alerts[] = ['type' => 'success', 'text' => 'Pending request canceled.'];
                logActivity($userId, 'user', 'Canceled dietitian request');
            } else {
                $alerts[] = ['type' => 'warning', 'text' => 'No pending request found to cancel.'];
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Could not cancel request right now.'];
    }
}

$stmtUser = $pdo->prepare('SELECT goal FROM users WHERE id = :id LIMIT 1');
$stmtUser->execute([':id' => $userId]);
$userGoal = (string) ($stmtUser->fetch()['goal'] ?? 'maintain');

$stmtAssigned = $pdo->prepare(
    'SELECT
        udp.id AS assignment_id,
        udp.assigned_date,
        udp.end_date,
        udp.status AS assignment_status,
        udp.dietitian_notes,
        dp.id AS diet_plan_id,
        dp.title,
        dp.description,
        dp.goal_type,
        dp.total_calories,
        dp.duration_days,
        dp.status AS plan_status,
        d.id AS dietitian_id,
        d.full_name AS dietitian_name,
        d.specialization
     FROM user_diet_plans udp
     JOIN diet_plans dp ON dp.id = udp.diet_plan_id
     JOIN dietitians d ON d.id = udp.dietitian_id
     WHERE udp.user_id = :user_id
     ORDER BY (udp.status = "active") DESC, udp.assigned_date DESC
     LIMIT 1'
);
$stmtAssigned->execute([':user_id' => $userId]);
$assignedPlan = $stmtAssigned->fetch();

$weeklyMeals = [];
if ($assignedPlan) {
    $stmtMeals = $pdo->prepare(
        'SELECT m.id, m.day_number, m.meal_type, m.meal_name, m.description, m.calories, m.protein, m.carbs, m.fat
         FROM meals m
         JOIN diet_plans dp ON dp.id = m.diet_plan_id
         WHERE m.diet_plan_id = :diet_plan_id
           AND dp.dietitian_id = :dietitian_id
           AND m.day_number BETWEEN 1 AND 7
         ORDER BY m.day_number ASC, FIELD(m.meal_type, "breakfast","lunch","dinner","snack"), m.id ASC'
    );
    $stmtMeals->execute([
        ':diet_plan_id' => (int) $assignedPlan['diet_plan_id'],
        ':dietitian_id' => (int) $assignedPlan['dietitian_id'],
    ]);
    $rows = $stmtMeals->fetchAll();

    for ($day = 1; $day <= 7; $day++) {
        $weeklyMeals[$day] = [
            'breakfast' => [],
            'lunch' => [],
            'dinner' => [],
            'snack' => [],
            'total' => 0,
        ];
    }

    foreach ($rows as $row) {
        $day = (int) $row['day_number'];
        $type = (string) $row['meal_type'];
        if (!isset($weeklyMeals[$day][$type])) {
            continue;
        }
        $weeklyMeals[$day][$type][] = $row;
        $weeklyMeals[$day]['total'] += (int) $row['calories'];
    }
}

$dietitians = [];
if (!$assignedPlan) {
    $stmtDietitians = $pdo->query(
        'SELECT id, full_name, profile_pic, specialization, experience_years, bio
         FROM dietitians
         WHERE status = "active"
         ORDER BY experience_years DESC, full_name ASC'
    );
    $dietitians = $stmtDietitians->fetchAll();
}

$requestMap = [];
if (!$assignedPlan && !empty($dietitians)) {
    $stmtReqs = $pdo->prepare(
        'SELECT id, dietitian_id, status
         FROM dietitian_requests
         WHERE user_id = :user_id'
    );
    $stmtReqs->execute([':user_id' => $userId]);
    foreach ($stmtReqs->fetchAll() as $req) {
        $requestMap[(int) $req['dietitian_id']] = [
            'id' => (int) $req['id'],
            'status' => (string) $req['status'],
        ];
    }
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diet Plan | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
    <style>
        .meal-macro {
            font-size: .75rem;
            border-radius: 999px;
            padding: .15rem .45rem;
            background: #ecf7f1;
            color: #1f7a4b;
            display: inline-block;
            margin-right: .25rem;
        }
        .dietitian-note {
            border-left: 4px solid #2ECC71;
            padding: .8rem 1rem;
            background: #f6fffa;
            border-radius: 8px;
            font-style: italic;
        }
        .dietitian-detail-box {
            background: #f8fbff;
            border: 1px solid #dce9f5;
            border-radius: 10px;
            padding: .65rem .75rem;
        }
        .dietitian-detail-box p {
            margin: 0;
            color: #516679;
            line-height: 1.45;
            font-size: .9rem;
        }
        .meal-item {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
        }
        .meal-check {
            margin-top: .2rem;
            width: 1.05rem;
            height: 1.05rem;
            cursor: pointer;
            accent-color: #16a34a;
            flex: 0 0 auto;
        }
        .meal-item-content {
            flex: 1;
            min-width: 0;
        }
        .meal-item.is-done .meal-item-content h6,
        .meal-item.is-done .meal-item-content small.text-muted {
            opacity: .7;
            text-decoration: line-through;
        }
        .day-actions {
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
            margin-top: .35rem;
            margin-bottom: .65rem;
        }
        @media print {
            .sidebar, .navbar, .hamburger, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            body {
                background: #fff !important;
            }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="container-fluid">
            <nav class="navbar">
                <button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div>
                    <h5 class="mb-0">My Diet Plan</h5>
                    <small class="text-muted">Personalized meals and nutrition schedule</small>
                </div>
                <?php if ($assignedPlan): ?>
                    <button class="btn btn-outline no-print right" onclick="window.print()"><i class="fa-solid fa-download me-1"></i>Download Plan</button>
                <?php endif; ?>
            </nav>

            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= e($alert['type']) ?>"><?= e($alert['text']) ?></div>
            <?php endforeach; ?>

            <?php if (!$assignedPlan): ?>
                <div class="card mb-3">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <i class="fa-solid fa-plate-wheat fa-3x text-success"></i>
                        </div>
                        <h4>No Diet Plan Assigned Yet</h4>
                        <p class="text-muted">You do not have an active plan right now. Request a dietitian and start your personalized journey.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Available Dietitians</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($dietitians as $d): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="border rounded p-3 h-100">
                                        <?php
                                        $profilePic = !empty($d['profile_pic'])
                                            ? SITE_URL . '/uploads/' . ltrim((string) $d['profile_pic'], '/')
                                            : SITE_URL . '/assets/images/default_avatar.png';
                                        $req = $requestMap[(int) $d['id']] ?? null;
                                        ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <img src="<?= e($profilePic) ?>" alt="avatar" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                                            <div>
                                                <h6 class="mb-0"><?= e((string) $d['full_name']) ?></h6>
                                                <small class="text-muted"><?= e((string) $d['specialization']) ?></small>
                                            </div>
                                        </div>
                                        <p class="mb-1 text-muted"><i class="fa-solid fa-stethoscope me-1"></i><?= e((string) $d['specialization']) ?></p>
                                        <small class="badge"><?= (int) $d['experience_years'] ?> years experience</small>
                                        <div class="mt-3 d-flex gap-2 flex-wrap">
                                            <?php if ($req && $req['status'] === 'pending'): ?>
                                                <span class="badge">Pending Request</span>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="cancel_request">
                                                    <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                                    <button class="btn btn-outline-danger btn-sm">Cancel Request</button>
                                                </form>
                                            <?php elseif ($req && $req['status'] === 'approved'): ?>
                                                <span class="badge">Approved</span>
                                            <?php else: ?>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="request_dietitian">
                                                    <input type="hidden" name="dietitian_id" value="<?= (int) $d['id'] ?>">
                                                    <button class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane me-1"></i>Request</button>
                                                </form>
                                                <?php if ($req && $req['status'] === 'rejected'): ?>
                                                    <small class="text-muted align-self-center">Previously rejected</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <button
                                                class="btn btn-outline btn-sm"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#dietitianBio<?= (int) $d['id'] ?>"
                                                aria-expanded="false"
                                                aria-controls="dietitianBio<?= (int) $d['id'] ?>"
                                            >
                                                <i class="fa-solid fa-circle-info me-1"></i>Read Bio
                                            </button>
                                        </div>
                                        <div class="collapse mt-2" id="dietitianBio<?= (int) $d['id'] ?>">
                                            <div class="dietitian-detail-box">
                                                <p>
                                                    <strong>Experience:</strong> <?= (int) $d['experience_years'] ?> years<br>
                                                    <strong>Bio:</strong>
                                                    <?= e((string) ($d['bio'] ?: 'Experienced dietitian providing practical and personalized nutrition guidance.')) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($dietitians)): ?>
                                <p class="text-muted mb-0">No active dietitians are available right now.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <h4 class="mb-1"><?= e((string) $assignedPlan['title']) ?></h4>
                                <p class="mb-1 text-muted"><?= e((string) $assignedPlan['description']) ?></p>
                                <small class="text-muted">
                                    Dietitian: <strong><?= e((string) $assignedPlan['dietitian_name']) ?></strong>
                                    (<?= e((string) $assignedPlan['specialization']) ?>)
                                </small>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div><span class="badge"><?= e(ucfirst((string) $assignedPlan['assignment_status'])) ?></span></div>
                                <small class="d-block text-muted mt-1">Start: <?= e(date('M d, Y', strtotime((string) $assignedPlan['assigned_date']))) ?></small>
                                <small class="d-block text-muted">End: <?= e(date('M d, Y', strtotime((string) $assignedPlan['end_date'] ?? date('Y-m-d')))) ?></small>
                                <small class="d-block text-muted">Goal: <?= e(ucwords(str_replace('_', ' ', (string) $assignedPlan['goal_type']))) ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Dietitian Notes</div>
                    <div class="card-body">
                        <blockquote class="dietitian-note mb-0">
                            <?= e((string) ($assignedPlan['dietitian_notes'] ?: 'Follow the plan consistently and stay hydrated.')) ?>
                        </blockquote>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Weekly Meal Schedule (Day 1 to Day 7)</div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <?php for ($day = 1; $day <= 7; $day++): ?>
                                <li class="nav-item">
                                    <button class="nav-link <?= $day === 1 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#day<?= $day ?>" type="button">Day <?= $day ?></button>
                                </li>
                            <?php endfor; ?>
                        </ul>

                        <div class="tab-content">
                            <?php for ($day = 1; $day <= 7; $day++): ?>
                                <?php $dayMeals = $weeklyMeals[$day] ?? ['breakfast'=>[],'lunch'=>[],'dinner'=>[],'snack'=>[],'total'=>0]; ?>
                                <div class="tab-pane fade <?= $day === 1 ? 'show active' : '' ?>" id="day<?= $day ?>">
                                    <div class="row g-3">
                                        <?php foreach (['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snacks'] as $type => $label): ?>
                                            <div class="col-md-6">
                                                <div class="card h-100">
                                                    <div class="card-header"><?= $label ?></div>
                                                    <div class="card-body">
                                                        <?php if (empty($dayMeals[$type])): ?>
                                                            <p class="text-muted mb-0">No <?= strtolower($label) ?> added.</p>
                                                        <?php else: ?>
                                                            <?php foreach ($dayMeals[$type] as $m): ?>
                                                                <div class="mb-2 pb-2 border-bottom meal-item" data-day="<?= $day ?>" data-meal-id="<?= (int) $m['id'] ?>">
                                                                    <input
                                                                        class="form-check-input meal-check"
                                                                        type="checkbox"
                                                                        aria-label="Mark meal done"
                                                                        data-day="<?= $day ?>"
                                                                        data-meal-id="<?= (int) $m['id'] ?>"
                                                                    >
                                                                    <div class="meal-item-content">
                                                                        <h6 class="mb-1"><?= e((string) $m['meal_name']) ?></h6>
                                                                        <small class="text-muted d-block mb-1"><?= e((string) $m['description']) ?></small>
                                                                        <span class="meal-macro"><?= (int) $m['calories'] ?> kcal</span>
                                                                        <span class="meal-macro">P <?= (float) $m['protein'] ?>g</span>
                                                                        <span class="meal-macro">C <?= (float) $m['carbs'] ?>g</span>
                                                                        <span class="meal-macro">F <?= (float) $m['fat'] ?>g</span>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="day-actions no-print">
                                        <button class="btn btn-outline-success btn-sm meal-mark-all" type="button" data-day="<?= $day ?>">
                                            <i class="fa-solid fa-check-double me-1"></i>Mark All Done
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm meal-clear-all" type="button" data-day="<?= $day ?>">
                                            <i class="fa-solid fa-rotate-left me-1"></i>Clear Done
                                        </button>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <span class="badge">Total Daily Calories: <?= (int) $dayMeals['total'] ?> kcal</span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.body.classList.toggle('sidebar-collapsed');
});

<?php if ($assignedPlan): ?>
(function () {
    const storageKey = 'hm_meal_done_<?= (int) $userId ?>_<?= (int) $assignedPlan['diet_plan_id'] ?>';
    let doneState = {};

    try {
        const raw = localStorage.getItem(storageKey);
        doneState = raw ? JSON.parse(raw) : {};
    } catch (err) {
        doneState = {};
    }

    function persist() {
        localStorage.setItem(storageKey, JSON.stringify(doneState));
    }

    function isDone(day, mealId) {
        return Array.isArray(doneState[day]) && doneState[day].includes(mealId);
    }

    function setDone(day, mealId, checked) {
        if (!Array.isArray(doneState[day])) doneState[day] = [];
        if (checked) {
            if (!doneState[day].includes(mealId)) doneState[day].push(mealId);
        } else {
            doneState[day] = doneState[day].filter(id => id !== mealId);
        }
        persist();
    }

    function applyItemVisual(day, mealId, checked) {
        const row = document.querySelector('.meal-item[data-day="' + day + '"][data-meal-id="' + mealId + '"]');
        if (!row) return;
        row.classList.toggle('is-done', checked);
    }

    const toggles = Array.from(document.querySelectorAll('.meal-check'));
    toggles.forEach((checkbox) => {
        const day = String(checkbox.dataset.day || '');
        const mealId = Number(checkbox.dataset.mealId || 0);
        if (!day || mealId <= 0) return;

        const checked = isDone(day, mealId);
        checkbox.checked = checked;
        applyItemVisual(day, mealId, checked);

        checkbox.addEventListener('change', function () {
            setDone(day, mealId, this.checked);
            applyItemVisual(day, mealId, this.checked);
        });
    });

    document.querySelectorAll('.meal-mark-all').forEach((btn) => {
        btn.addEventListener('click', function () {
            const day = String(this.dataset.day || '');
            if (!day) return;
            const dayChecks = document.querySelectorAll('.meal-check[data-day="' + day + '"]');
            dayChecks.forEach((checkbox) => {
                checkbox.checked = true;
                const mealId = Number(checkbox.dataset.mealId || 0);
                if (mealId > 0) {
                    setDone(day, mealId, true);
                    applyItemVisual(day, mealId, true);
                }
            });
        });
    });

    document.querySelectorAll('.meal-clear-all').forEach((btn) => {
        btn.addEventListener('click', function () {
            const day = String(this.dataset.day || '');
            if (!day) return;
            doneState[day] = [];
            persist();
            const dayChecks = document.querySelectorAll('.meal-check[data-day="' + day + '"]');
            dayChecks.forEach((checkbox) => {
                checkbox.checked = false;
                const mealId = Number(checkbox.dataset.mealId || 0);
                if (mealId > 0) applyItemVisual(day, mealId, false);
            });
        });
    });
})();
<?php endif; ?>
</script>
</body>
</html>
