<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/ai_helper.php';

header('Content-Type: application/json; charset=utf-8');

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

$ingredientQ = trim((string) ($_GET['ingredients'] ?? ''));
if ($ingredientQ === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide ingredients.']);
    exit;
}

$userTokens = array_values(array_filter(array_map('aiNormalizeToken', explode(',', $ingredientQ))));
if (empty($userTokens)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide valid ingredients.']);
    exit;
}

function ingredientMatches(string $a, string $b): bool
{
    return $a !== '' && $b !== '' && (str_contains($a, $b) || str_contains($b, $a));
}

function buildSuggestions(mysqli $db, array $userTokens): array
{
    $rows = [];
    $sql = "SELECT m.id, m.meal_name, m.short_description, m.calories, m.source,
                   GROUP_CONCAT(mi.ingredient_name ORDER BY mi.ingredient_name SEPARATOR ',') AS ingredients
            FROM meal_suggestions m
            LEFT JOIN meal_ingredients mi ON mi.meal_id = m.id
            GROUP BY m.id, m.meal_name, m.short_description, m.calories, m.source
            ORDER BY m.meal_name ASC
            LIMIT 100";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }

    while ($r = $res->fetch_assoc()) {
        $mealIngredients = [];
        $rawIng = trim((string) ($r['ingredients'] ?? ''));
        if ($rawIng !== '') {
            $mealIngredients = array_values(array_filter(array_map('aiNormalizeToken', explode(',', $rawIng))));
        }

        $matched = [];
        $missing = [];
        foreach ($mealIngredients as $mi) {
            $ok = false;
            foreach ($userTokens as $ut) {
                if (ingredientMatches($ut, $mi)) {
                    $matched[] = $mi;
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $missing[] = $mi;
            }
        }

        if (!empty($matched)) {
            $rows[] = [
                'meal_name' => (string) $r['meal_name'],
                'ingredients' => $mealIngredients,
                'description' => (string) $r['short_description'],
                'calories' => $r['calories'] !== null ? (int) $r['calories'] : null,
                'matched_ingredients' => array_values(array_unique($matched)),
                'missing_ingredients' => array_values(array_unique($missing)),
                'source' => (string) $r['source'],
                'match_count' => count(array_unique($matched)),
                'missing_count' => count(array_unique($missing)),
            ];
        }
    }

    usort($rows, function (array $a, array $b): int {
        if ($a['match_count'] !== $b['match_count']) return $b['match_count'] <=> $a['match_count'];
        if ($a['missing_count'] !== $b['missing_count']) return $a['missing_count'] <=> $b['missing_count'];
        return strcmp($a['meal_name'], $b['meal_name']);
    });
    return $rows;
}

try {
    $db = aiMysqli();
    aiEnsureSearchTables($db);

    $localSuggestions = buildSuggestions($db, $userTokens);
    $needsAi = count($localSuggestions) < 2 || (($localSuggestions[0]['match_count'] ?? 0) < 2);

    if (!$needsAi) {
        echo json_encode(['success' => true, 'source' => 'db', 'data' => array_slice($localSuggestions, 0, 12)]);
        exit;
    }

    $system = 'You are a meal suggestion assistant. Return ONLY valid JSON array. Each item must contain: meal_name (string), ingredients (array of strings), description (string), calories (int). Keep it simple.';
    $userPrompt = 'User has ingredients: ' . implode(', ', $userTokens) . '. Suggest 4 practical meals.';
    $aiText = aiOpenRouterChat($system, $userPrompt);
    $aiArr = $aiText ? aiExtractJsonArray($aiText) : null;

    if (is_array($aiArr)) {
        $insertMeal = $db->prepare(
            "INSERT INTO meal_suggestions (meal_name, short_description, calories, source)
             VALUES (?, ?, ?, 'ai')
             ON DUPLICATE KEY UPDATE short_description = VALUES(short_description), calories = VALUES(calories), source = 'ai'"
        );
        $findMeal = $db->prepare("SELECT id FROM meal_suggestions WHERE meal_name = ? LIMIT 1");
        $deleteIngredients = $db->prepare("DELETE FROM meal_ingredients WHERE meal_id = ?");
        $insertIng = $db->prepare("INSERT INTO meal_ingredients (meal_id, ingredient_name) VALUES (?, ?)");

        foreach ($aiArr as $item) {
            if (!is_array($item)) continue;
            $mealName = trim((string) ($item['meal_name'] ?? ''));
            $desc = trim((string) ($item['description'] ?? ''));
            $cal = (int) ($item['calories'] ?? 0);
            $ingredients = $item['ingredients'] ?? [];
            if ($mealName === '' || $desc === '' || !is_array($ingredients)) continue;
            $calories = $cal > 0 ? $cal : null;

            $insertMeal->bind_param('ssi', $mealName, $desc, $calories);
            $insertMeal->execute();

            $findMeal->bind_param('s', $mealName);
            $findMeal->execute();
            $mealId = (int) (($findMeal->get_result()->fetch_assoc()['id'] ?? 0));
            if ($mealId <= 0) continue;

            $deleteIngredients->bind_param('i', $mealId);
            $deleteIngredients->execute();

            foreach ($ingredients as $ingRaw) {
                $ing = aiNormalizeToken((string) $ingRaw);
                if ($ing === '') continue;
                $insertIng->bind_param('is', $mealId, $ing);
                $insertIng->execute();
            }
        }

        $insertMeal->close();
        $findMeal->close();
        $deleteIngredients->close();
        $insertIng->close();
    }

    $allSuggestions = buildSuggestions($db, $userTokens);
    if (!empty($allSuggestions)) {
        $hasAiTop = false;
        foreach (array_slice($allSuggestions, 0, 5) as $s) {
            if (($s['source'] ?? 'db') === 'ai') {
                $hasAiTop = true;
                break;
            }
        }
        echo json_encode([
            'success' => true,
            'source' => $hasAiTop ? 'ai' : 'db',
            'data' => array_slice($allSuggestions, 0, 12),
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'source' => 'none',
        'data' => [],
        'message' => 'No suitable meal suggestions found from DB or AI.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error during ingredient suggestions.']);
}

