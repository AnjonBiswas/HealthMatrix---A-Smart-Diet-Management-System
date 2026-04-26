<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function sanitizeInput(mixed $data): mixed
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
}

function calculateBMI(float $weight, float $height): float
{
    if ($height <= 0) {
        return 0.0;
    }

    $heightInMeters = $height / 100;
    return round($weight / ($heightInMeters * $heightInMeters), 2);
}

function getBMICategory(float $bmi): string
{
    if ($bmi <= 0) {
        return 'Unknown';
    }
    if ($bmi < 18.5) {
        return 'Underweight';
    }
    if ($bmi < 25) {
        return 'Normal weight';
    }
    if ($bmi < 30) {
        return 'Overweight';
    }
    return 'Obese';
}

function calculateDailyCalories(
    int $age,
    float $weight,
    float $height,
    string $gender,
    string $activity,
    string $goal
): int {
    $gender = strtolower($gender);
    $activity = strtolower($activity);
    $goal = strtolower($goal);

    $bmr = 0.0;
    if ($gender === 'male') {
        $bmr = HB_MALE_BASE + (HB_MALE_WEIGHT * $weight) + (HB_MALE_HEIGHT * $height) - (HB_MALE_AGE * $age);
    } else {
        $bmr = HB_FEMALE_BASE + (HB_FEMALE_WEIGHT * $weight) + (HB_FEMALE_HEIGHT * $height) - (HB_FEMALE_AGE * $age);
    }

    $activityMultiplier = ACTIVITY_MULTIPLIERS[$activity] ?? ACTIVITY_MULTIPLIERS['sedentary'];
    $maintenanceCalories = $bmr * $activityMultiplier;

    $adjustment = GOAL_CALORIE_ADJUSTMENT[$goal] ?? 0;
    $recommended = $maintenanceCalories + $adjustment;

    return max(1200, (int) round($recommended));
}

function uploadImage(array $file, string $folder = 'general'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid upload parameters.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . (int) $file['error']];
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File exceeds max allowed size.'];
    }

    $originalName = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_FILE_TYPES, true)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        return ['success' => false, 'error' => 'Invalid MIME type.'];
    }

    $folder = trim($folder, '/\\');
    $targetDirectory = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        return ['success' => false, 'error' => 'Could not create upload directory.'];
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $targetPath = $targetDirectory . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    return [
        'success' => true,
        'path' => str_replace('\\', '/', $folder . '/' . $filename),
        'url' => rtrim(UPLOAD_URL, '/') . '/' . str_replace('\\', '/', $folder . '/' . $filename),
    ];
}

function sendAlert(string $message, string $type = 'info'): string
{
    $allowed = [
        'success' => 'alert-success',
        'danger' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info',
    ];

    $class = $allowed[$type] ?? $allowed['info'];
    $safeMessage = sanitizeInput($message);

    return sprintf(
        '<div class="alert %s" role="alert">%s</div>',
        $class,
        $safeMessage
    );
}

function formatDate(string $date, string $format = 'M d, Y'): string
{
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception) {
        return $date;
    }
}

function generatePagination(int $total, int $page, int $limit, string $baseUrl = '?page='): string
{
    $totalPages = (int) ceil($total / max($limit, 1));
    if ($totalPages <= 1) {
        return '';
    }

    $page = max(1, min($page, $totalPages));
    $html = '<nav class="pagination-wrapper"><ul class="pagination">';

    if ($page > 1) {
        $html .= '<li><a href="' . $baseUrl . ($page - 1) . '">Prev</a></li>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $baseUrl . $i . '">' . $i . '</a></li>';
    }

    if ($page < $totalPages) {
        $html .= '<li><a href="' . $baseUrl . ($page + 1) . '">Next</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

function logActivity(int $userId, string $userType, string $action): bool
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, user_type, action, ip_address, created_at)
             VALUES (:user_id, :user_type, :action, :ip_address, NOW())'
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':user_type' => $userType,
            ':action' => $action,
            ':ip_address' => $ipAddress,
        ]);
    } catch (Throwable) {
        return false;
    }
}

function ensureNutritionSearchTables(PDO $pdo): void
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS food_calorie_reference (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                food_name VARCHAR(120) NOT NULL UNIQUE,
                calories_est INT NOT NULL,
                serving_unit VARCHAR(60) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ingredient_meal_suggestions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                meal_name VARCHAR(150) NOT NULL UNIQUE,
                ingredient_list TEXT NOT NULL,
                short_description VARCHAR(255) NOT NULL,
                calories_est INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $foodCount = (int) $pdo->query('SELECT COUNT(*) FROM food_calorie_reference')->fetchColumn();
        if ($foodCount === 0) {
            $foods = [
                ['Apple', 52, 'per 100g'],
                ['Banana', 89, 'per 100g'],
                ['Egg', 78, 'per piece'],
                ['White Rice (cooked)', 130, 'per 100g'],
                ['Brown Rice (cooked)', 112, 'per 100g'],
                ['Bread', 80, 'per slice'],
                ['Chicken Breast (cooked)', 165, 'per 100g'],
                ['Salmon (cooked)', 208, 'per 100g'],
                ['Milk', 103, 'per cup'],
                ['Yogurt (plain)', 61, 'per 100g'],
                ['Lentils (cooked)', 116, 'per 100g'],
                ['Potato (boiled)', 87, 'per 100g'],
                ['Oats (dry)', 389, 'per 100g'],
                ['Peanut Butter', 94, 'per tbsp'],
                ['Cheddar Cheese', 113, 'per 28g'],
                ['Orange', 47, 'per 100g'],
                ['Mango', 60, 'per 100g'],
                ['Avocado', 160, 'per 100g'],
                ['Broccoli', 35, 'per 100g'],
                ['Olive Oil', 119, 'per tbsp'],
            ];

            $stmtFood = $pdo->prepare(
                'INSERT INTO food_calorie_reference (food_name, calories_est, serving_unit)
                 VALUES (:food_name, :calories_est, :serving_unit)'
            );
            foreach ($foods as [$foodName, $calories, $serving]) {
                $stmtFood->execute([
                    ':food_name' => $foodName,
                    ':calories_est' => $calories,
                    ':serving_unit' => $serving,
                ]);
            }
        }

        $mealCount = (int) $pdo->query('SELECT COUNT(*) FROM ingredient_meal_suggestions')->fetchColumn();
        if ($mealCount === 0) {
            $meals = [
                ['Potato Lentil Curry', 'potato, lentils, onion, garlic, turmeric, oil, salt', 'Comforting curry with soft potatoes and protein-rich lentils.', 320],
                ['Egg Fried Rice', 'rice, egg, oil, onion, garlic, soy sauce, salt', 'Quick stir-fried rice with egg and savory flavor.', 410],
                ['Banana Oat Smoothie', 'banana, oats, milk, honey', 'Simple energy smoothie for breakfast or post-workout.', 260],
                ['Vegetable Omelette', 'egg, onion, tomato, spinach, oil, salt, pepper', 'High-protein omelette with fresh vegetables.', 230],
                ['Chicken Stir Fry', 'chicken, onion, garlic, bell pepper, oil, soy sauce', 'Lean chicken tossed with veggies in light sauce.', 360],
                ['Dal Soup', 'lentils, onion, garlic, turmeric, cumin, oil, salt', 'Light and nourishing lentil soup.', 240],
                ['Potato Egg Hash', 'potato, egg, onion, oil, salt, pepper', 'Pan-cooked potato and egg skillet meal.', 300],
                ['Tomato Garlic Pasta', 'pasta, tomato, garlic, olive oil, salt, chili flakes', 'Basic pasta with garlic tomato flavor.', 420],
                ['Rice and Grilled Fish', 'rice, fish, oil, lemon, salt, pepper', 'Balanced rice plate with grilled fish.', 490],
                ['Chickpea Salad Bowl', 'chickpeas, cucumber, tomato, onion, lemon, olive oil, salt', 'Refreshing high-fiber salad bowl.', 280],
            ];

            $stmtMeal = $pdo->prepare(
                'INSERT INTO ingredient_meal_suggestions
                 (meal_name, ingredient_list, short_description, calories_est)
                 VALUES (:meal_name, :ingredient_list, :short_description, :calories_est)'
            );
            foreach ($meals as [$mealName, $ingredients, $desc, $calories]) {
                $stmtMeal->execute([
                    ':meal_name' => $mealName,
                    ':ingredient_list' => $ingredients,
                    ':short_description' => $desc,
                    ':calories_est' => $calories,
                ]);
            }
        }
    } catch (Throwable) {
        // keep pages usable even if table creation fails
    }
}
