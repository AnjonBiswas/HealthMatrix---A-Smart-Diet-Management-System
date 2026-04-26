<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';

function aiMysqli(): mysqli
{
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_errno) {
        throw new RuntimeException('MySQL connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}

function aiJsonDecodeSafe(string $json): ?array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
}

function aiExtractJsonObject(string $text): ?array
{
    $text = trim($text);
    $direct = aiJsonDecodeSafe($text);
    if ($direct !== null) {
        return $direct;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $slice = substr($text, $start, $end - $start + 1);
    return aiJsonDecodeSafe($slice);
}

function aiExtractJsonArray(string $text): ?array
{
    $text = trim($text);
    $direct = aiJsonDecodeSafe($text);
    if ($direct !== null && array_is_list($direct)) {
        return $direct;
    }

    $start = strpos($text, '[');
    $end = strrpos($text, ']');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $slice = substr($text, $start, $end - $start + 1);
    $decoded = aiJsonDecodeSafe($slice);
    return ($decoded !== null && array_is_list($decoded)) ? $decoded : null;
}

function aiOpenRouterChat(string $systemPrompt, string $userPrompt): ?string
{
    if (trim(OPENROUTER_API_KEY) === '') {
        return null;
    }

    $models = [OPENROUTER_MODEL];
    if (defined('OPENROUTER_FALLBACK_MODEL') && trim((string) OPENROUTER_FALLBACK_MODEL) !== '') {
        $models[] = OPENROUTER_FALLBACK_MODEL;
    }

    foreach ($models as $model) {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.2,
        ];

        $ch = curl_init(OPENROUTER_URL);
        if ($ch === false) {
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'Content-Type: application/json',
                'HTTP-Referer: ' . SITE_URL,
                'X-Title: HealthMatrix',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            continue;
        }

        $parsed = aiJsonDecodeSafe($raw);
        if (!$parsed || !isset($parsed['choices'][0]['message']['content'])) {
            continue;
        }

        $content = (string) $parsed['choices'][0]['message']['content'];
        if (trim($content) !== '') {
            return $content;
        }
    }

    return null;
}

function aiEnsureSearchTables(mysqli $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS food_calories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            food_name VARCHAR(120) NOT NULL,
            calories INT NOT NULL,
            protein DECIMAL(7,2) NULL,
            carbs DECIMAL(7,2) NULL,
            fat DECIMAL(7,2) NULL,
            serving_unit VARCHAR(80) NOT NULL,
            source ENUM('db','ai') NOT NULL DEFAULT 'db',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_food_name (food_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backward-compatible: add nutrition columns when table existed before this feature.
    $hasProtein = $db->query("SHOW COLUMNS FROM food_calories LIKE 'protein'");
    if ($hasProtein && !$hasProtein->fetch_assoc()) {
        $db->query("ALTER TABLE food_calories ADD COLUMN protein DECIMAL(7,2) NULL AFTER calories");
    }
    $hasCarbs = $db->query("SHOW COLUMNS FROM food_calories LIKE 'carbs'");
    if ($hasCarbs && !$hasCarbs->fetch_assoc()) {
        $db->query("ALTER TABLE food_calories ADD COLUMN carbs DECIMAL(7,2) NULL AFTER protein");
    }
    $hasFat = $db->query("SHOW COLUMNS FROM food_calories LIKE 'fat'");
    if ($hasFat && !$hasFat->fetch_assoc()) {
        $db->query("ALTER TABLE food_calories ADD COLUMN fat DECIMAL(7,2) NULL AFTER carbs");
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS meal_suggestions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meal_name VARCHAR(150) NOT NULL,
            short_description VARCHAR(255) NOT NULL,
            calories INT NULL,
            source ENUM('db','ai') NOT NULL DEFAULT 'db',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_meal_name (meal_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS meal_ingredients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meal_id BIGINT UNSIGNED NOT NULL,
            ingredient_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_meal_id (meal_id),
            CONSTRAINT fk_meal_ingredients_meal_id
                FOREIGN KEY (meal_id) REFERENCES meal_suggestions(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $countFood = (int) ($db->query("SELECT COUNT(*) AS c FROM food_calories")->fetch_assoc()['c'] ?? 0);
    if ($countFood === 0) {
        $seed = [
            ['Apple', 52, 0.30, 14.00, 0.20, 'per 100g'],
            ['Banana', 89, 1.10, 23.00, 0.30, 'per 100g'],
            ['Egg', 78, 6.30, 0.60, 5.30, 'per piece'],
            ['Rice', 130, 2.70, 28.00, 0.30, 'per 100g'],
            ['Bread', 80, 3.80, 14.00, 1.00, 'per slice'],
            ['Milk', 103, 8.00, 12.00, 2.40, 'per cup'],
            ['Potato', 87, 1.90, 20.00, 0.10, 'per 100g'],
            ['Lentils', 116, 9.00, 20.00, 0.40, 'per 100g'],
            ['Chicken Breast', 165, 31.00, 0.00, 3.60, 'per 100g'],
            ['Yogurt', 61, 3.50, 4.70, 3.30, 'per 100g'],
        ];
        $stmt = $db->prepare("INSERT INTO food_calories (food_name, calories, protein, carbs, fat, serving_unit, source) VALUES (?, ?, ?, ?, ?, ?, 'db')");
        if ($stmt) {
            foreach ($seed as [$name, $cal, $protein, $carbs, $fat, $unit]) {
                $stmt->bind_param('siddds', $name, $cal, $protein, $carbs, $fat, $unit);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $countMeals = (int) ($db->query("SELECT COUNT(*) AS c FROM meal_suggestions")->fetch_assoc()['c'] ?? 0);
    if ($countMeals === 0) {
        $seedMeals = [
            ['Potato Lentil Curry', 'Comforting curry with lentils and potatoes.', 320, ['potato', 'lentils', 'oil', 'onion', 'turmeric', 'salt']],
            ['Egg Fried Rice', 'Quick stir-fry rice with egg.', 410, ['rice', 'egg', 'oil', 'onion', 'soy sauce', 'salt']],
            ['Vegetable Omelette', 'Protein-rich omelette with vegetables.', 230, ['egg', 'onion', 'tomato', 'spinach', 'oil', 'salt']],
            ['Dal Soup', 'Light and nourishing lentil soup.', 240, ['lentils', 'onion', 'garlic', 'turmeric', 'cumin', 'oil', 'salt']],
            ['Chicken Stir Fry', 'Chicken with veggies and light sauce.', 360, ['chicken', 'onion', 'garlic', 'bell pepper', 'oil', 'soy sauce']],
        ];

        $stmtMeal = $db->prepare("INSERT INTO meal_suggestions (meal_name, short_description, calories, source) VALUES (?, ?, ?, 'db')");
        $stmtIng = $db->prepare("INSERT INTO meal_ingredients (meal_id, ingredient_name) VALUES (?, ?)");
        if ($stmtMeal && $stmtIng) {
            foreach ($seedMeals as [$mealName, $desc, $cal, $ings]) {
                $stmtMeal->bind_param('ssi', $mealName, $desc, $cal);
                $stmtMeal->execute();
                $mealId = (int) $db->insert_id;
                foreach ($ings as $ing) {
                    $stmtIng->bind_param('is', $mealId, $ing);
                    $stmtIng->execute();
                }
            }
            $stmtMeal->close();
            $stmtIng->close();
        }
    }
}

function aiNormalizeToken(string $value): string
{
    $v = strtolower(trim($value));
    $v = preg_replace('/[^a-z0-9\s]/', '', $v) ?? '';
    $v = preg_replace('/\s+/', ' ', $v) ?? '';
    return trim($v);
}
