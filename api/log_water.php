<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
redirectIfNotLoggedIn(['user']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$userId = (int) (getUserId() ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'add')));
$logDate = trim((string) ($_POST['log_date'] ?? date('Y-m-d')));

function waterOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function getTodayWaterData(PDO $pdo, int $userId, string $logDate): array
{
    $stmtTotal = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) AS total FROM water_log WHERE user_id = :u AND log_date = :d');
    $stmtTotal->execute([':u' => $userId, ':d' => $logDate]);
    $total = (int) ($stmtTotal->fetch()['total'] ?? 0);

    $stmtRows = $pdo->prepare(
        'SELECT id, amount_ml, created_at
         FROM water_log
         WHERE user_id = :u AND log_date = :d
         ORDER BY created_at ASC, id ASC'
    );
    $stmtRows->execute([':u' => $userId, ':d' => $logDate]);
    $rows = $stmtRows->fetchAll();

    $cumulative = 0;
    $list = [];
    foreach ($rows as $r) {
        $amount = (int) $r['amount_ml'];
        $cumulative += $amount;
        $list[] = [
            'id' => (int) $r['id'],
            'amount_ml' => $amount,
            'time' => date('h:i A', strtotime((string) $r['created_at'])),
            'cumulative' => $cumulative,
        ];
    }

    return ['total' => $total, 'entries' => $list];
}

try {
    if ($userId <= 0) {
        waterOut(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
        waterOut(['success' => false, 'message' => 'Invalid date format'], 422);
    }

    if ($action === 'get_today') {
        $data = getTodayWaterData($pdo, $userId, $logDate);
        waterOut(['success' => true, 'data' => $data]);
    }

    if ($action === 'add') {
        $amountMl = (int) ($_POST['amount_ml'] ?? 0);
        if ($amountMl <= 0) {
            waterOut(['success' => false, 'message' => 'Amount must be greater than zero'], 422);
        }
        if ($amountMl > 5000) {
            waterOut(['success' => false, 'message' => 'Amount is too high'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO water_log (user_id, amount_ml, log_date, created_at)
             VALUES (:u, :a, :d, NOW())'
        );
        $stmt->execute([
            ':u' => $userId,
            ':a' => $amountMl,
            ':d' => $logDate,
        ]);

        logActivity($userId, 'user', 'Logged water intake');
        $data = getTodayWaterData($pdo, $userId, $logDate);
        waterOut(['success' => true, 'message' => 'Water logged', 'data' => $data]);
    }

    if ($action === 'delete') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        if ($logId <= 0) {
            waterOut(['success' => false, 'message' => 'Invalid entry id'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM water_log WHERE id = :id AND user_id = :u');
        $stmt->execute([':id' => $logId, ':u' => $userId]);
        if ($stmt->rowCount() <= 0) {
            waterOut(['success' => false, 'message' => 'Entry not found'], 404);
        }

        logActivity($userId, 'user', 'Deleted water entry');
        $data = getTodayWaterData($pdo, $userId, $logDate);
        waterOut(['success' => true, 'message' => 'Water entry deleted', 'data' => $data]);
    }

    waterOut(['success' => false, 'message' => 'Unsupported action'], 422);
} catch (Throwable $e) {
    waterOut(['success' => false, 'message' => 'Could not process request'], 500);
}

