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

$allowedMealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
$allowedUnits = ['g', 'ml', 'piece', 'cup', 'serving', 'plan_meal'];

function jsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function fetchDailySummary(PDO $pdo, int $userId, string $logDate): array
{
    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(calories), 0) AS calories,
            COALESCE(SUM(protein), 0) AS protein,
            COALESCE(SUM(carbs), 0) AS carbs,
            COALESCE(SUM(fat), 0) AS fat
         FROM food_log
         WHERE user_id = :user_id AND log_date = :log_date'
    );
    $stmt->execute([':user_id' => $userId, ':log_date' => $logDate]);
    $summary = $stmt->fetch() ?: ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];

    return [
        'calories' => (int) $summary['calories'],
        'protein' => (float) $summary['protein'],
        'carbs' => (float) $summary['carbs'],
        'fat' => (float) $summary['fat'],
    ];
}

try {
    if ($userId <= 0) {
        jsonOut(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if ($action === 'add') {
        $foodName = trim((string) ($_POST['food_name'] ?? ''));
        $mealType = strtolower(trim((string) ($_POST['meal_type'] ?? '')));
        $quantity = (float) ($_POST['quantity'] ?? 1);
        $unit = strtolower(trim((string) ($_POST['unit'] ?? 'piece')));
        $calories = (int) ($_POST['calories'] ?? 0);
        $protein = (float) ($_POST['protein'] ?? 0);
        $carbs = (float) ($_POST['carbs'] ?? 0);
        $fat = (float) ($_POST['fat'] ?? 0);
        $logDate = trim((string) ($_POST['log_date'] ?? date('Y-m-d')));

        if ($foodName === '' || mb_strlen($foodName) < 2) {
            jsonOut(['success' => false, 'message' => 'Food name is required.'], 422);
        }
        if (!in_array($mealType, $allowedMealTypes, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid meal type.'], 422);
        }
        if ($quantity <= 0) {
            jsonOut(['success' => false, 'message' => 'Quantity must be greater than zero.'], 422);
        }
        if (!in_array($unit, $allowedUnits, true)) {
            $unit = 'piece';
        }
        if ($calories <= 0) {
            jsonOut(['success' => false, 'message' => 'Calories must be greater than zero.'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO food_log
             (user_id, food_name, meal_type, calories, protein, carbs, fat, quantity, unit, log_date, created_at)
             VALUES
             (:user_id, :food_name, :meal_type, :calories, :protein, :carbs, :fat, :quantity, :unit, :log_date, NOW())'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':food_name' => $foodName,
            ':meal_type' => $mealType,
            ':calories' => $calories,
            ':protein' => $protein,
            ':carbs' => $carbs,
            ':fat' => $fat,
            ':quantity' => $quantity,
            ':unit' => $unit,
            ':log_date' => $logDate,
        ]);

        logActivity($userId, 'user', 'Added food log entry');
        jsonOut([
            'success' => true,
            'message' => 'Food added successfully.',
            'log_id' => (int) $pdo->lastInsertId(),
            'summary' => fetchDailySummary($pdo, $userId, $logDate),
        ]);
    }

    if ($action === 'delete') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        if ($logId <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid log id.'], 422);
        }

        $stmtGet = $pdo->prepare('SELECT log_date FROM food_log WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmtGet->execute([':id' => $logId, ':user_id' => $userId]);
        $row = $stmtGet->fetch();
        if (!$row) {
            jsonOut(['success' => false, 'message' => 'Food log not found.'], 404);
        }

        $logDate = (string) $row['log_date'];
        $stmt = $pdo->prepare('DELETE FROM food_log WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $logId, ':user_id' => $userId]);

        logActivity($userId, 'user', 'Deleted food log entry');
        jsonOut([
            'success' => true,
            'message' => 'Food entry deleted.',
            'summary' => fetchDailySummary($pdo, $userId, $logDate),
        ]);
    }

    if ($action === 'edit') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $foodName = trim((string) ($_POST['food_name'] ?? ''));
        $mealType = strtolower(trim((string) ($_POST['meal_type'] ?? '')));
        $quantity = (float) ($_POST['quantity'] ?? 1);
        $unit = strtolower(trim((string) ($_POST['unit'] ?? 'piece')));
        $calories = (int) ($_POST['calories'] ?? 0);
        $protein = (float) ($_POST['protein'] ?? 0);
        $carbs = (float) ($_POST['carbs'] ?? 0);
        $fat = (float) ($_POST['fat'] ?? 0);

        if ($logId <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid log id.'], 422);
        }
        if ($foodName === '' || mb_strlen($foodName) < 2) {
            jsonOut(['success' => false, 'message' => 'Food name is required.'], 422);
        }
        if (!in_array($mealType, $allowedMealTypes, true)) {
            jsonOut(['success' => false, 'message' => 'Invalid meal type.'], 422);
        }
        if ($quantity <= 0) {
            jsonOut(['success' => false, 'message' => 'Quantity must be greater than zero.'], 422);
        }
        if ($calories <= 0) {
            jsonOut(['success' => false, 'message' => 'Calories must be greater than zero.'], 422);
        }
        if (!in_array($unit, $allowedUnits, true)) {
            $unit = 'piece';
        }

        $stmtLog = $pdo->prepare('SELECT log_date FROM food_log WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmtLog->execute([':id' => $logId, ':user_id' => $userId]);
        $existing = $stmtLog->fetch();
        if (!$existing) {
            jsonOut(['success' => false, 'message' => 'Food log not found.'], 404);
        }
        $logDate = (string) $existing['log_date'];

        $stmt = $pdo->prepare(
            'UPDATE food_log
             SET food_name = :food_name, meal_type = :meal_type, quantity = :quantity, unit = :unit,
                 calories = :calories, protein = :protein, carbs = :carbs, fat = :fat
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            ':food_name' => $foodName,
            ':meal_type' => $mealType,
            ':quantity' => $quantity,
            ':unit' => $unit,
            ':calories' => $calories,
            ':protein' => $protein,
            ':carbs' => $carbs,
            ':fat' => $fat,
            ':id' => $logId,
            ':user_id' => $userId,
        ]);

        logActivity($userId, 'user', 'Edited food log entry');
        jsonOut([
            'success' => true,
            'message' => 'Food entry updated.',
            'summary' => fetchDailySummary($pdo, $userId, $logDate),
        ]);
    }

    if ($action === 'add_favorite') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $foodName = trim((string) ($_POST['food_name'] ?? ''));
        $calories = (int) ($_POST['calories'] ?? 0);
        $protein = (float) ($_POST['protein'] ?? 0);
        $carbs = (float) ($_POST['carbs'] ?? 0);
        $fat = (float) ($_POST['fat'] ?? 0);

        if ($logId > 0) {
            $stmtLog = $pdo->prepare(
                'SELECT food_name, calories, protein, carbs, fat
                 FROM food_log
                 WHERE id = :id AND user_id = :user_id
                 LIMIT 1'
            );
            $stmtLog->execute([':id' => $logId, ':user_id' => $userId]);
            $log = $stmtLog->fetch();
            if (!$log) {
                jsonOut(['success' => false, 'message' => 'Log not found.'], 404);
            }
            $foodName = (string) $log['food_name'];
            $calories = (int) $log['calories'];
            $protein = (float) $log['protein'];
            $carbs = (float) $log['carbs'];
            $fat = (float) $log['fat'];
        }

        if ($foodName === '' || $calories <= 0) {
            jsonOut(['success' => false, 'message' => 'Favorite requires valid food data.'], 422);
        }

        $stmtExists = $pdo->prepare(
            'SELECT id FROM user_favorite_meals
             WHERE user_id = :user_id AND meal_name = :meal_name
             LIMIT 1'
        );
        $stmtExists->execute([':user_id' => $userId, ':meal_name' => $foodName]);
        if ($stmtExists->fetchColumn()) {
            jsonOut(['success' => false, 'message' => 'Already in favorites.'], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO user_favorite_meals
             (user_id, meal_name, calories, protein, carbs, fat, created_at)
             VALUES
             (:user_id, :meal_name, :calories, :protein, :carbs, :fat, NOW())'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':meal_name' => $foodName,
            ':calories' => $calories,
            ':protein' => $protein,
            ':carbs' => $carbs,
            ':fat' => $fat,
        ]);

        jsonOut(['success' => true, 'message' => 'Added to favorites.', 'favorite_id' => (int) $pdo->lastInsertId()]);
    }

    if ($action === 'remove_favorite') {
        $favoriteId = (int) ($_POST['favorite_id'] ?? 0);
        if ($favoriteId <= 0) {
            jsonOut(['success' => false, 'message' => 'Invalid favorite id.'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM user_favorite_meals WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $favoriteId, ':user_id' => $userId]);
        jsonOut(['success' => true, 'message' => 'Favorite removed.']);
    }

    jsonOut(['success' => false, 'message' => 'Unsupported action.'], 422);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Server error while processing food log.'], 500);
}

