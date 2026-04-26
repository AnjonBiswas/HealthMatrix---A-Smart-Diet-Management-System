<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
redirectIfNotLoggedIn(['user']);

$pdo = Database::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'daily_summary')));

function out(array $p, int $code = 200): void { http_response_code($code); echo json_encode($p); exit; }

if ($userId <= 0) out(['success' => false, 'message' => 'Unauthorized'], 401);

try {
    if ($action === 'daily_summary') {
        $s = $pdo->prepare(
            'SELECT COALESCE(SUM(calories),0) calories,COALESCE(SUM(protein),0) protein,COALESCE(SUM(carbs),0) carbs,COALESCE(SUM(fat),0) fat
             FROM food_log WHERE user_id=:u AND log_date=CURDATE()'
        );
        $s->execute([':u' => $userId]);
        $food = $s->fetch();

        $w = $pdo->prepare('SELECT COALESCE(SUM(amount_ml),0) water FROM water_log WHERE user_id=:u AND log_date=CURDATE()');
        $w->execute([':u' => $userId]);
        $water = (int) ($w->fetch()['water'] ?? 0);

        out(['success' => true, 'data' => [
            'calories_today' => (int) ($food['calories'] ?? 0),
            'water_today' => $water,
            'protein' => (float) ($food['protein'] ?? 0),
            'carbs' => (float) ($food['carbs'] ?? 0),
            'fat' => (float) ($food['fat'] ?? 0),
        ]]);
    }

    if ($action === 'weekly_calories') {
        $q = $pdo->prepare(
            'SELECT log_date, COALESCE(SUM(calories),0) total
             FROM food_log
             WHERE user_id=:u AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY log_date ORDER BY log_date ASC'
        );
        $q->execute([':u' => $userId]);
        $map = [];
        foreach ($q->fetchAll() as $r) $map[(string) $r['log_date']] = (int) $r['total'];
        $labels=[];$vals=[];
        for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime("-{$i} day"));$labels[]=date('M d',strtotime($d));$vals[]=$map[$d]??0;}
        out(['success' => true, 'data' => ['labels' => $labels, 'values' => $vals]]);
    }

    if ($action === 'weight_history') {
        $q = $pdo->prepare('SELECT log_date, weight FROM weight_log WHERE user_id=:u AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY log_date ASC');
        $q->execute([':u' => $userId]);
        $rows = $q->fetchAll();
        $labels=[];$vals=[];
        foreach($rows as $r){$labels[]=date('M d',strtotime((string)$r['log_date']));$vals[]=(float)$r['weight'];}
        out(['success' => true, 'data' => ['labels' => $labels, 'values' => $vals]]);
    }

    if ($action === 'bmi_data') {
        $u = $pdo->prepare('SELECT bmi, height FROM users WHERE id=:u LIMIT 1'); $u->execute([':u' => $userId]); $user = $u->fetch();
        $height = (float) ($user['height'] ?? 0);
        $q = $pdo->prepare('SELECT log_date, weight FROM weight_log WHERE user_id=:u ORDER BY log_date ASC LIMIT 30');
        $q->execute([':u' => $userId]);
        $history = [];
        foreach($q->fetchAll() as $r){
            $w=(float)$r['weight']; $b=$height>0?calculateBMI($w,$height):0;
            $history[]=['date'=>(string)$r['log_date'],'bmi'=>$b];
        }
        out(['success' => true, 'data' => ['current_bmi' => (float) ($user['bmi'] ?? 0), 'history' => $history]]);
    }

    if ($action === 'plan_progress') {
        $plan = $pdo->prepare('SELECT udp.assigned_date, dp.duration_days, dp.id plan_id FROM user_diet_plans udp JOIN diet_plans dp ON dp.id=udp.diet_plan_id WHERE udp.user_id=:u AND udp.status="active" ORDER BY udp.assigned_date DESC LIMIT 1');
        $plan->execute([':u' => $userId]);
        $p = $plan->fetch();
        if (!$p) out(['success'=>true,'data'=>['followed_percent'=>0,'message'=>'No active plan']]);
        $dur=max(1,(int)$p['duration_days']);
        $day=((int)(new DateTime((string)$p['assigned_date']))->diff(new DateTime(date('Y-m-d')))->format('%a')%$dur)+1;
        $m = $pdo->prepare('SELECT COUNT(*) c FROM meals WHERE diet_plan_id=:p AND day_number=:d');
        $m->execute([':p'=>(int)$p['plan_id'],':d'=>$day]); $planned=(int)($m->fetch()['c']??0);
        $f = $pdo->prepare('SELECT COUNT(*) c FROM food_log WHERE user_id=:u AND log_date=CURDATE()');
        $f->execute([':u'=>$userId]); $logged=(int)($f->fetch()['c']??0);
        $percent = $planned>0 ? min(100,(int)round(($logged/$planned)*100)) : 0;
        out(['success'=>true,'data'=>['followed_percent'=>$percent,'planned_meals'=>$planned,'logged_entries'=>$logged]]);
    }

    out(['success' => false, 'message' => 'Unsupported action'], 422);
} catch (Throwable $e) {
    out(['success' => false, 'message' => 'Could not fetch stats'], 500);
}

