<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: ' . SITE_URL . '/auth/login.php'); exit; }

$today = date('Y-m-d');
$goal = 2000;

$stmtToday = $pdo->prepare('SELECT id, amount_ml, created_at FROM water_log WHERE user_id = :u AND log_date = :d ORDER BY created_at ASC, id ASC');
$stmtToday->execute([':u' => $userId, ':d' => $today]);
$todayRows = $stmtToday->fetchAll();

$cumulative = 0;
$todayEntries = [];
foreach ($todayRows as $r) {
    $cumulative += (int) $r['amount_ml'];
    $todayEntries[] = [
        'id' => (int) $r['id'],
        'amount_ml' => (int) $r['amount_ml'],
        'time' => date('h:i A', strtotime((string) $r['created_at'])),
        'cumulative' => $cumulative,
    ];
}
$todayTotal = $cumulative;
$fillPercent = (int) min(100, round(($todayTotal / $goal) * 100));
$fillClass = $todayTotal < 800 ? 'danger' : ($todayTotal < 1500 ? 'warning' : 'success');

$stmt7 = $pdo->prepare(
    'SELECT log_date, COALESCE(SUM(amount_ml), 0) AS total
     FROM water_log
     WHERE user_id = :u AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY log_date
     ORDER BY log_date ASC'
);
$stmt7->execute([':u' => $userId]);
$rows7 = $stmt7->fetchAll();
$map7 = [];
foreach ($rows7 as $r) { $map7[(string) $r['log_date']] = (int) $r['total']; }
$labels7 = [];
$vals7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $labels7[] = date('M d', strtotime($d));
    $vals7[] = $map7[$d] ?? 0;
}
$avg = count($vals7) ? (int) round(array_sum($vals7) / count($vals7)) : 0;

$stmtStreak = $pdo->prepare(
    'SELECT log_date, COALESCE(SUM(amount_ml),0) AS total
     FROM water_log
     WHERE user_id = :u
     GROUP BY log_date
     ORDER BY log_date ASC'
);
$stmtStreak->execute([':u' => $userId]);
$daily = $stmtStreak->fetchAll();
$bestStreak = 0;
$currentStreak = 0;
$prevDate = null;
foreach ($daily as $d) {
    if ((int) $d['total'] < $goal) { $currentStreak = 0; $prevDate = null; continue; }
    $cur = (string) $d['log_date'];
    if ($prevDate === null) {
        $currentStreak = 1;
    } else {
        $diff = (new DateTime($prevDate))->diff(new DateTime($cur))->days;
        $currentStreak = $diff === 1 ? $currentStreak + 1 : 1;
    }
    $prevDate = $cur;
    if ($currentStreak > $bestStreak) { $bestStreak = $currentStreak; }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Tracker | HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css">
    <style>
        .water-wrap{display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
        .bottle{position:relative;width:150px;height:300px;border:5px solid #8fb3c9;border-radius:18px 18px 22px 22px;overflow:hidden;background:#f7fbff}
        .bottle::before{content:"";position:absolute;top:-20px;left:45px;width:52px;height:25px;border:5px solid #8fb3c9;border-bottom:none;border-radius:10px 10px 0 0;background:#fff}
        .fill{position:absolute;left:0;right:0;bottom:0;height:0;transition:height .45s ease}
        .fill.success{background:linear-gradient(180deg,#57d68e,#2ECC71)}
        .fill.warning{background:linear-gradient(180deg,#ffd36e,#F39C12)}
        .fill.danger{background:linear-gradient(180deg,#ff8b7f,#E74C3C)}
        .wave{position:absolute;top:-14px;left:0;width:200%;height:28px;background:rgba(255,255,255,.45);border-radius:42%;animation:wave 2.8s linear infinite}
        @keyframes wave{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
    </style>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar"><div class="sidebar-brand"><img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo" class="brand-logo"></div>
        <ul class="sidebar-menu">
            <li><a href="<?= SITE_URL ?>/user/dashboard.php"><i class="fa-solid fa-chart-line"></i>Dashboard</a></li>
            <li><a href="<?= SITE_URL ?>/user/profile.php"><i class="fa-solid fa-user"></i>Profile</a></li>
            <li><a href="<?= SITE_URL ?>/user/diet_plan.php"><i class="fa-solid fa-utensils"></i>Diet Plan</a></li>
            <li><a href="<?= SITE_URL ?>/user/food_log.php"><i class="fa-solid fa-bowl-food"></i>Food Log</a></li>
            <li class="active"><a href="<?= SITE_URL ?>/user/water_tracker.php"><i class="fa-solid fa-glass-water"></i>Water Tracker</a></li>
            <li><a href="<?= SITE_URL ?>/user/progress.php"><i class="fa-solid fa-weight-scale"></i>Progress</a></li>
            <li><a href="<?= SITE_URL ?>/user/messages.php"><i class="fa-solid fa-message"></i>Messages</a></li>
            <li><a href="<?= SITE_URL ?>/user/favorites.php"><i class="fa-solid fa-heart"></i>Favorites</a></li>
            <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a></li>
        </ul>
    </aside>
    <main class="main-content"><div class="container-fluid">
        <nav class="navbar"><button class="hamburger" id="sidebarToggle"><span></span><span></span><span></span></button><div><h5 class="mb-0">Water Tracker</h5><small class="text-muted">Goal: 2000ml (8 glasses)</small></div></nav>
        <div id="waterAlert"></div>

        <div class="card mb-3"><div class="card-body">
            <div class="water-wrap">
                <div class="bottle">
                    <div id="waterFill" class="fill <?= $fillClass ?>" style="height:<?= $fillPercent ?>%">
                        <div class="wave"></div>
                    </div>
                </div>
                <div>
                    <h2 id="waterDisplay"><?= $todayTotal ?> / <?= $goal ?> ml</h2>
                    <p class="mb-2">Today's hydration progress</p>
                    <div class="progress" style="width:260px"><div id="waterProgress" class="progress-bar" style="width:<?= $fillPercent ?>%"></div></div>
                </div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header">Quick Add</div><div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-primary add-water" data-amount="100">+100ml</button>
                <button class="btn btn-primary add-water" data-amount="200">+200ml</button>
                <button class="btn btn-success add-water" data-amount="250">+250ml (glass)</button>
                <button class="btn btn-secondary add-water" data-amount="500">+500ml (bottle)</button>
            </div>
            <div class="row g-2">
                <div class="col-md-3"><input type="number" min="1" id="customAmount" class="form-control" placeholder="Custom ml"></div>
                <div class="col-md-2"><button class="btn btn-outline w-100" id="addCustomBtn">Add</button></div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header">Today's Log</div><div class="card-body table-responsive">
            <table class="table table-striped"><thead><tr><th>Time</th><th>Amount</th><th>Cumulative</th><th>Action</th></tr></thead><tbody id="waterLogBody">
                <?php foreach($todayEntries as $entry): ?>
                    <tr data-id="<?= (int) $entry['id'] ?>"><td><?= e($entry['time']) ?></td><td><?= (int) $entry['amount_ml'] ?> ml</td><td><?= (int) $entry['cumulative'] ?> ml</td><td><button class="btn btn-sm btn-danger del-water" data-id="<?= (int) $entry['id'] ?>">Delete</button></td></tr>
                <?php endforeach; ?>
                <?php if (empty($todayEntries)): ?><tr><td colspan="4" class="text-center text-muted">No water logs yet today.</td></tr><?php endif; ?>
            </tbody></table>
        </div></div>

        <div class="row g-3">
            <div class="col-lg-8"><div class="card"><div class="card-header">Last 7 Days</div><div class="card-body"><canvas id="water7Chart" height="130"></canvas></div></div></div>
            <div class="col-lg-4">
                <div class="card mb-3"><div class="card-header">Daily Average</div><div class="card-body"><h4><?= $avg ?> ml/day</h4></div></div>
                <div class="card"><div class="card-header">Best Streak</div><div class="card-body"><h4><?= $bestStreak ?> day(s)</h4><small class="text-muted">Days meeting 2000ml goal</small></div></div>
            </div>
        </div>
    </div></main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
const api='<?= SITE_URL ?>/api/log_water.php', goal=<?= $goal ?>, today='<?= $today ?>';
const alertBox=document.getElementById('waterAlert'), body=document.getElementById('waterLogBody');
const fill=document.getElementById('waterFill'), display=document.getElementById('waterDisplay'), progress=document.getElementById('waterProgress');
function alertMsg(msg,type='success'){alertBox.innerHTML=`<div class="alert alert-${type}">${msg}</div>`;}
async function post(data){const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});return await r.json();}
function render(data){
  const total=data.total||0, pct=Math.min(100,Math.round((total/goal)*100));
  display.textContent=`${total} / ${goal} ml`; progress.style.width=pct+'%'; fill.style.height=pct+'%';
  fill.classList.remove('danger','warning','success'); fill.classList.add(total<800?'danger':(total<1500?'warning':'success'));
  if(!data.entries||!data.entries.length){body.innerHTML='<tr><td colspan="4" class="text-center text-muted">No water logs yet today.</td></tr>';return;}
  body.innerHTML=data.entries.map(e=>`<tr><td>${e.time}</td><td>${e.amount_ml} ml</td><td>${e.cumulative} ml</td><td><button class="btn btn-sm btn-danger del-water" data-id="${e.id}">Delete</button></td></tr>`).join('');
  bindDelete();
}
async function refresh(){const rs=await post({action:'get_today',log_date:today}); if(rs.success) render(rs.data);}
async function addWater(amount){const rs=await post({action:'add',amount_ml:amount,log_date:today}); if(rs.success){render(rs.data);alertMsg(rs.message);} else alertMsg(rs.message||'Failed','danger');}
async function delWater(id){if(!confirm('Delete this entry?'))return; const rs=await post({action:'delete',log_id:id,log_date:today}); if(rs.success){render(rs.data);alertMsg(rs.message);} else alertMsg(rs.message||'Failed','danger');}
function bindDelete(){document.querySelectorAll('.del-water').forEach(b=>b.onclick=()=>delWater(b.dataset.id));}
document.querySelectorAll('.add-water').forEach(b=>b.onclick=()=>addWater(parseInt(b.dataset.amount,10)));
document.getElementById('addCustomBtn').onclick=()=>{const n=parseInt(document.getElementById('customAmount').value||'0',10); if(n>0)addWater(n); else alertMsg('Enter valid amount','warning');};
bindDelete(); setInterval(refresh,30000);

new Chart(document.getElementById('water7Chart'),{type:'bar',data:{labels:<?= json_encode($labels7) ?>,datasets:[{label:'ml',data:<?= json_encode($vals7) ?>,backgroundColor:'#3498DB'}]},options:{plugins:{legend:{display:false}},responsive:true}});
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-collapsed'));
</script>
</body>
</html>

