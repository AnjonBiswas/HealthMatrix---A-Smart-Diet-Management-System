<?php
declare(strict_types=1);

/**
 * Default starter meal templates for dietitians.
 * Each template contains one structured day that can be expanded in Create Plan.
 */
function getDefaultMealTemplateLibrary(): array
{
    return [
        [
            'template_name' => 'Weight Loss - High Protein Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Greek Yogurt Berry Bowl', 'description' => 'Greek yogurt, berries, chia seeds', 'calories' => 320, 'protein' => 24, 'carbs' => 30, 'fat' => 10],
                    ['meal_type' => 'lunch', 'meal_name' => 'Grilled Chicken Salad', 'description' => 'Chicken breast with mixed greens and olive oil', 'calories' => 450, 'protein' => 42, 'carbs' => 18, 'fat' => 22],
                    ['meal_type' => 'dinner', 'meal_name' => 'Baked Fish & Veggies', 'description' => 'White fish, broccoli, carrots', 'calories' => 430, 'protein' => 38, 'carbs' => 24, 'fat' => 18],
                    ['meal_type' => 'snack', 'meal_name' => 'Boiled Eggs + Cucumber', 'description' => '2 eggs with cucumber sticks', 'calories' => 180, 'protein' => 13, 'carbs' => 4, 'fat' => 11],
                ],
            ],
        ],
        [
            'template_name' => 'Weight Loss - Low Carb Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Veggie Omelette', 'description' => 'Eggs, spinach, mushroom, tomato', 'calories' => 300, 'protein' => 22, 'carbs' => 9, 'fat' => 19],
                    ['meal_type' => 'lunch', 'meal_name' => 'Chicken Lettuce Wraps', 'description' => 'Minced chicken and veggies in lettuce leaves', 'calories' => 410, 'protein' => 35, 'carbs' => 14, 'fat' => 22],
                    ['meal_type' => 'dinner', 'meal_name' => 'Paneer & Stir Fry Veg', 'description' => 'Low oil paneer stir fry with vegetables', 'calories' => 460, 'protein' => 28, 'carbs' => 16, 'fat' => 30],
                    ['meal_type' => 'snack', 'meal_name' => 'Mixed Nuts Portion', 'description' => 'Almonds and walnuts', 'calories' => 170, 'protein' => 6, 'carbs' => 5, 'fat' => 14],
                ],
            ],
        ],
        [
            'template_name' => 'Maintain - Balanced Classic Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Oats With Banana', 'description' => 'Rolled oats, milk, banana slices', 'calories' => 360, 'protein' => 14, 'carbs' => 57, 'fat' => 8],
                    ['meal_type' => 'lunch', 'meal_name' => 'Rice, Dal, Chicken Curry', 'description' => 'Balanced home-style meal', 'calories' => 620, 'protein' => 36, 'carbs' => 68, 'fat' => 20],
                    ['meal_type' => 'dinner', 'meal_name' => 'Roti, Mixed Veg, Fish', 'description' => '2 roti, fish and vegetables', 'calories' => 560, 'protein' => 35, 'carbs' => 52, 'fat' => 22],
                    ['meal_type' => 'snack', 'meal_name' => 'Fruit + Peanut Butter', 'description' => 'Apple with peanut butter', 'calories' => 220, 'protein' => 6, 'carbs' => 24, 'fat' => 11],
                ],
            ],
        ],
        [
            'template_name' => 'Maintain - Vegetarian Balanced Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Poha With Peanuts', 'description' => 'Flattened rice with vegetables', 'calories' => 340, 'protein' => 9, 'carbs' => 52, 'fat' => 10],
                    ['meal_type' => 'lunch', 'meal_name' => 'Brown Rice + Rajma', 'description' => 'Kidney beans and brown rice', 'calories' => 580, 'protein' => 20, 'carbs' => 88, 'fat' => 12],
                    ['meal_type' => 'dinner', 'meal_name' => 'Roti + Paneer Bhurji', 'description' => 'Whole wheat roti with paneer', 'calories' => 550, 'protein' => 26, 'carbs' => 50, 'fat' => 26],
                    ['meal_type' => 'snack', 'meal_name' => 'Yogurt + Seeds', 'description' => 'Curd with pumpkin seeds', 'calories' => 190, 'protein' => 10, 'carbs' => 11, 'fat' => 11],
                ],
            ],
        ],
        [
            'template_name' => 'Lean Gain - Muscle Builder Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Egg & Toast Power Plate', 'description' => 'Eggs, whole grain toast, avocado', 'calories' => 520, 'protein' => 29, 'carbs' => 36, 'fat' => 28],
                    ['meal_type' => 'lunch', 'meal_name' => 'Chicken Rice Bowl', 'description' => 'Grilled chicken, rice, beans', 'calories' => 760, 'protein' => 48, 'carbs' => 82, 'fat' => 25],
                    ['meal_type' => 'dinner', 'meal_name' => 'Beef/Paneer + Potato', 'description' => 'High energy lean-gain dinner', 'calories' => 740, 'protein' => 43, 'carbs' => 66, 'fat' => 30],
                    ['meal_type' => 'snack', 'meal_name' => 'Protein Shake + Banana', 'description' => 'Post-workout snack', 'calories' => 310, 'protein' => 26, 'carbs' => 33, 'fat' => 8],
                ],
            ],
        ],
        [
            'template_name' => 'Lean Gain - Vegetarian Bulk Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Paneer Stuffed Paratha', 'description' => 'Paratha with curd', 'calories' => 560, 'protein' => 23, 'carbs' => 52, 'fat' => 28],
                    ['meal_type' => 'lunch', 'meal_name' => 'Rice + Chole + Salad', 'description' => 'Chickpea curry meal', 'calories' => 760, 'protein' => 26, 'carbs' => 104, 'fat' => 22],
                    ['meal_type' => 'dinner', 'meal_name' => 'Soya Chunk Stir Fry + Roti', 'description' => 'Protein-focused vegetarian dinner', 'calories' => 700, 'protein' => 44, 'carbs' => 68, 'fat' => 24],
                    ['meal_type' => 'snack', 'meal_name' => 'Milk Smoothie + Nuts', 'description' => 'Calorie-dense shake', 'calories' => 360, 'protein' => 15, 'carbs' => 32, 'fat' => 19],
                ],
            ],
        ],
        [
            'template_name' => 'Diabetes Friendly - Moderate Carb Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Besan Chilla + Yogurt', 'description' => 'Low GI breakfast', 'calories' => 320, 'protein' => 16, 'carbs' => 30, 'fat' => 13],
                    ['meal_type' => 'lunch', 'meal_name' => 'Multigrain Roti + Dal + Veg', 'description' => 'Fiber-rich plate', 'calories' => 520, 'protein' => 22, 'carbs' => 56, 'fat' => 20],
                    ['meal_type' => 'dinner', 'meal_name' => 'Grilled Fish + Saute Veg', 'description' => 'Protein and non-starchy vegetables', 'calories' => 460, 'protein' => 36, 'carbs' => 20, 'fat' => 24],
                    ['meal_type' => 'snack', 'meal_name' => 'Roasted Chana', 'description' => 'Low sugar crunchy snack', 'calories' => 170, 'protein' => 9, 'carbs' => 18, 'fat' => 6],
                ],
            ],
        ],
        [
            'template_name' => 'PCOS Support - Anti Inflammatory Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Chia Oats Pudding', 'description' => 'Oats, chia, berries, almond milk', 'calories' => 340, 'protein' => 12, 'carbs' => 39, 'fat' => 15],
                    ['meal_type' => 'lunch', 'meal_name' => 'Quinoa Chickpea Bowl', 'description' => 'Quinoa, chickpeas, leafy greens', 'calories' => 540, 'protein' => 21, 'carbs' => 62, 'fat' => 21],
                    ['meal_type' => 'dinner', 'meal_name' => 'Salmon/Tofu + Veg', 'description' => 'Omega-3 rich dinner', 'calories' => 500, 'protein' => 33, 'carbs' => 26, 'fat' => 27],
                    ['meal_type' => 'snack', 'meal_name' => 'Pumpkin Seeds + Fruit', 'description' => 'Micronutrient-rich snack', 'calories' => 200, 'protein' => 8, 'carbs' => 17, 'fat' => 11],
                ],
            ],
        ],
        [
            'template_name' => 'Heart Healthy - Low Sodium Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Fruit Yogurt Parfait', 'description' => 'Low-fat yogurt and fruits', 'calories' => 310, 'protein' => 15, 'carbs' => 41, 'fat' => 9],
                    ['meal_type' => 'lunch', 'meal_name' => 'Lentil Soup + Whole Bread', 'description' => 'Low sodium lentil soup meal', 'calories' => 500, 'protein' => 22, 'carbs' => 65, 'fat' => 14],
                    ['meal_type' => 'dinner', 'meal_name' => 'Baked Chicken + Sweet Potato', 'description' => 'Heart-friendly complete plate', 'calories' => 560, 'protein' => 38, 'carbs' => 48, 'fat' => 21],
                    ['meal_type' => 'snack', 'meal_name' => 'Unsalted Nuts', 'description' => 'Small handful nuts', 'calories' => 180, 'protein' => 5, 'carbs' => 6, 'fat' => 15],
                ],
            ],
        ],
        [
            'template_name' => 'Quick Office Day - Easy Prep',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Overnight Oats Jar', 'description' => 'Prep in 5 minutes', 'calories' => 350, 'protein' => 14, 'carbs' => 50, 'fat' => 10],
                    ['meal_type' => 'lunch', 'meal_name' => 'Chicken Wrap + Salad', 'description' => 'Portable lunch option', 'calories' => 560, 'protein' => 34, 'carbs' => 51, 'fat' => 22],
                    ['meal_type' => 'dinner', 'meal_name' => 'One Pan Rice & Veg', 'description' => 'Simple weekday dinner', 'calories' => 590, 'protein' => 22, 'carbs' => 77, 'fat' => 19],
                    ['meal_type' => 'snack', 'meal_name' => 'Protein Bar', 'description' => 'Grab-and-go option', 'calories' => 210, 'protein' => 16, 'carbs' => 18, 'fat' => 8],
                ],
            ],
        ],
        [
            'template_name' => 'Budget Friendly Family Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Vegetable Upma', 'description' => 'Affordable wholesome breakfast', 'calories' => 330, 'protein' => 9, 'carbs' => 49, 'fat' => 11],
                    ['meal_type' => 'lunch', 'meal_name' => 'Rice, Egg Curry, Veg', 'description' => 'Cost-effective balanced meal', 'calories' => 610, 'protein' => 23, 'carbs' => 72, 'fat' => 22],
                    ['meal_type' => 'dinner', 'meal_name' => 'Khichdi + Curd', 'description' => 'Comfort and nutrition', 'calories' => 520, 'protein' => 19, 'carbs' => 69, 'fat' => 16],
                    ['meal_type' => 'snack', 'meal_name' => 'Seasonal Fruit', 'description' => 'Simple fruit snack', 'calories' => 120, 'protein' => 2, 'carbs' => 29, 'fat' => 0],
                ],
            ],
        ],
        [
            'template_name' => 'High Fiber Gut Friendly Day',
            'meal_data' => [
                1 => [
                    ['meal_type' => 'breakfast', 'meal_name' => 'Bran Cereal + Milk', 'description' => 'Fiber-rich start', 'calories' => 320, 'protein' => 13, 'carbs' => 47, 'fat' => 8],
                    ['meal_type' => 'lunch', 'meal_name' => 'Barley Veg Bowl', 'description' => 'Barley, beans and veggies', 'calories' => 540, 'protein' => 21, 'carbs' => 76, 'fat' => 14],
                    ['meal_type' => 'dinner', 'meal_name' => 'Lentil Pasta + Veg Sauce', 'description' => 'High fiber dinner option', 'calories' => 600, 'protein' => 29, 'carbs' => 78, 'fat' => 18],
                    ['meal_type' => 'snack', 'meal_name' => 'Pear + Flax Seeds', 'description' => 'Digestive support snack', 'calories' => 160, 'protein' => 3, 'carbs' => 27, 'fat' => 4],
                ],
            ],
        ],
    ];
}

/**
 * Seed default templates for a dietitian account if missing.
 */
function ensureDietitianStarterTemplates(PDO $pdo, int $dietitianId): void
{
    if ($dietitianId <= 0) {
        return;
    }

    $library = getDefaultMealTemplateLibrary();
    if (empty($library)) {
        return;
    }

    $existingStmt = $pdo->prepare('SELECT template_name FROM meal_templates WHERE dietitian_id = :d');
    $existingStmt->execute([':d' => $dietitianId]);
    $existingNames = [];
    foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $existingNames[(string) $name] = true;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO meal_templates (dietitian_id, template_name, meal_data, created_at)
         VALUES (:d, :name, :data, NOW())'
    );

    foreach ($library as $template) {
        $name = (string) ($template['template_name'] ?? '');
        $mealData = $template['meal_data'] ?? null;
        if ($name === '' || !is_array($mealData) || isset($existingNames[$name])) {
            continue;
        }

        $insertStmt->execute([
            ':d' => $dietitianId,
            ':name' => $name,
            ':data' => json_encode($mealData, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
