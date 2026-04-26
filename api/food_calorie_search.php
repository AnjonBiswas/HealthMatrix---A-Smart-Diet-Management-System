<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/ai_helper.php';

header('Content-Type: application/json; charset=utf-8');

function foodImageUrl(string $foodName): ?string
{
    $name = strtolower(trim($foodName));
    $map = [
        'apple' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?auto=format&fit=crop&w=120&q=80',
        'banana' => 'https://images.unsplash.com/photo-1574226516831-e1dff420e37f?auto=format&fit=crop&w=120&q=80',
        'egg' => 'https://images.unsplash.com/photo-1518569656558-1f25e69d93d7?auto=format&fit=crop&w=120&q=80',
        'rice' => 'https://images.unsplash.com/photo-1516684732162-798a0062be99?auto=format&fit=crop&w=120&q=80',
        'bread' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=120&q=80',
        'milk' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=120&q=80',
        'potato' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=120&q=80',
        'lentil' => 'https://images.unsplash.com/photo-1515543904379-3d757afe72e4?auto=format&fit=crop&w=120&q=80',
        'chicken' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=120&q=80',
        'yogurt' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=120&q=80',
        'orange' => 'https://images.unsplash.com/photo-1547514701-42782101795e?auto=format&fit=crop&w=120&q=80',
        'mango' => 'https://images.unsplash.com/photo-1553279768-865429fa0078?auto=format&fit=crop&w=120&q=80',
        'avocado' => 'https://images.unsplash.com/photo-1523049673857-eb18f1d7b578?auto=format&fit=crop&w=120&q=80',
        'broccoli' => 'https://images.unsplash.com/photo-1459411621453-7b03977f4bfc?auto=format&fit=crop&w=120&q=80',
        'salmon' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=120&q=80',
        'oat' => 'https://images.unsplash.com/photo-1517673132405-a56a62b18caf?auto=format&fit=crop&w=120&q=80',
        'peanut butter' => 'https://images.unsplash.com/photo-1621939514649-280e2ee25f60?auto=format&fit=crop&w=120&q=80',
        'cheese' => 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?auto=format&fit=crop&w=120&q=80',
        'olive oil' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=120&q=80',
    ];

    foreach ($map as $keyword => $url) {
        if (str_contains($name, $keyword)) {
            return $url;
        }
    }
    return null;
}

if (!isLoggedIn() || !hasRole('user')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter at least 2 characters.']);
    exit;
}

try {
    $db = aiMysqli();
    aiEnsureSearchTables($db);

    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        "SELECT food_name, calories, protein, carbs, fat, serving_unit, source
         FROM food_calories
         WHERE food_name LIKE ?
         ORDER BY source = 'db' DESC, food_name ASC
         LIMIT 20"
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'food_name' => (string) $r['food_name'],
            'calories' => (int) $r['calories'],
            'protein' => $r['protein'] !== null ? (float) $r['protein'] : null,
            'carbs' => $r['carbs'] !== null ? (float) $r['carbs'] : null,
            'fat' => $r['fat'] !== null ? (float) $r['fat'] : null,
            'serving_unit' => (string) $r['serving_unit'],
            'image_url' => foodImageUrl((string) $r['food_name']),
            'source' => (string) $r['source'],
        ];
    }
    $stmt->close();

    $shouldUseAiEnhance = empty($rows);
    if (!$shouldUseAiEnhance) {
        $firstName = strtolower(trim((string) ($rows[0]['food_name'] ?? '')));
        $queryName = strtolower(trim($q));
        $hasNullMacros = false;
        foreach ($rows as $it) {
            if ($it['protein'] === null || $it['carbs'] === null || $it['fat'] === null) {
                $hasNullMacros = true;
                break;
            }
        }
        // AI enhance when exact match is weak or macro data is incomplete.
        $shouldUseAiEnhance = ($firstName !== $queryName) || $hasNullMacros;
    }

    $aiObj = null;
    if ($shouldUseAiEnhance) {
        $systemPrompt = 'You are a nutrition assistant. Return only valid JSON object with keys: food_name (string), calories (int), protein (number), carbs (number), fat (number), serving_unit (string).';
        $userPrompt = 'Food: "' . $q . '". Estimate common calories and serving unit. Output JSON only.';
        $aiText = aiOpenRouterChat($systemPrompt, $userPrompt);
        $aiObj = $aiText ? aiExtractJsonObject($aiText) : null;
    }

    if (($aiObj === null) && !empty($rows)) {
        echo json_encode(['success' => true, 'source' => 'db', 'data' => $rows]);
        exit;
    }

    if (!$aiObj || !isset($aiObj['food_name'], $aiObj['calories'], $aiObj['serving_unit'])) {
        echo json_encode([
            'success' => true,
            'source' => !empty($rows) ? 'db' : 'none',
            'data' => !empty($rows) ? $rows : [],
            'message' => !empty($rows)
                ? 'Showing DB result (AI enhancement unavailable right now).'
                : 'No food found in DB and AI could not generate a reliable answer.'
        ]);
        exit;
    }

    $foodName = trim((string) $aiObj['food_name']);
    $calories = (int) $aiObj['calories'];
    $protein = isset($aiObj['protein']) ? (float) $aiObj['protein'] : null;
    $carbs = isset($aiObj['carbs']) ? (float) $aiObj['carbs'] : null;
    $fat = isset($aiObj['fat']) ? (float) $aiObj['fat'] : null;
    $servingUnit = trim((string) $aiObj['serving_unit']);

    if ($foodName === '' || $calories <= 0 || $servingUnit === '') {
        echo json_encode([
            'success' => true,
            'source' => !empty($rows) ? 'db' : 'none',
            'data' => !empty($rows) ? $rows : [],
            'message' => !empty($rows)
                ? 'Showing DB result (AI response was invalid).'
                : 'AI response was invalid.'
        ]);
        exit;
    }

    $upsert = $db->prepare(
        "INSERT INTO food_calories (food_name, calories, protein, carbs, fat, serving_unit, source)
         VALUES (?, ?, ?, ?, ?, ?, 'ai')
         ON DUPLICATE KEY UPDATE
            calories = VALUES(calories),
            protein = VALUES(protein),
            carbs = VALUES(carbs),
            fat = VALUES(fat),
            serving_unit = VALUES(serving_unit),
            source = 'ai'"
    );
    $upsert->bind_param('siddds', $foodName, $calories, $protein, $carbs, $fat, $servingUnit);
    $upsert->execute();
    $upsert->close();

    if (!empty($rows)) {
        $rows[0] = [
            'food_name' => $foodName,
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
            'serving_unit' => $servingUnit,
            'image_url' => foodImageUrl($foodName),
            'source' => 'ai',
        ];
        echo json_encode([
            'success' => true,
            'source' => 'db_ai',
            'data' => $rows,
            'message' => 'Enhanced with AI',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'source' => 'ai',
        'data' => [[
            'food_name' => $foodName,
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
            'serving_unit' => $servingUnit,
            'image_url' => foodImageUrl($foodName),
            'source' => 'ai',
        ]],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error during food search.']);
}
