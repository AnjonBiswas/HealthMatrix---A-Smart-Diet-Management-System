<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');
redirectIfNotLoggedIn(['user', 'dietitian', 'admin']);

$foods = [
['name'=>'Apple','calories'=>52,'protein'=>0.3,'carbs'=>14,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Banana','calories'=>89,'protein'=>1.1,'carbs'=>23,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Orange','calories'=>47,'protein'=>0.9,'carbs'=>12,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Mango','calories'=>60,'protein'=>0.8,'carbs'=>15,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Pineapple','calories'=>50,'protein'=>0.5,'carbs'=>13,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Papaya','calories'=>43,'protein'=>0.5,'carbs'=>11,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Watermelon','calories'=>30,'protein'=>0.6,'carbs'=>8,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Guava','calories'=>68,'protein'=>2.6,'carbs'=>14,'fat'=>1.0,'serving'=>'100g'],
['name'=>'Pomegranate','calories'=>83,'protein'=>1.7,'carbs'=>19,'fat'=>1.2,'serving'=>'100g'],
['name'=>'Grapes','calories'=>69,'protein'=>0.7,'carbs'=>18,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Pear','calories'=>57,'protein'=>0.4,'carbs'=>15,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Strawberry','calories'=>32,'protein'=>0.7,'carbs'=>8,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Blueberry','calories'=>57,'protein'=>0.7,'carbs'=>14,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Kiwi','calories'=>61,'protein'=>1.1,'carbs'=>15,'fat'=>0.5,'serving'=>'100g'],
['name'=>'Dates','calories'=>282,'protein'=>2.5,'carbs'=>75,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Broccoli','calories'=>34,'protein'=>2.8,'carbs'=>7,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Carrot','calories'=>41,'protein'=>0.9,'carbs'=>10,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Spinach','calories'=>23,'protein'=>2.9,'carbs'=>3.6,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Cabbage','calories'=>25,'protein'=>1.3,'carbs'=>6,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Cauliflower','calories'=>25,'protein'=>1.9,'carbs'=>5,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Tomato','calories'=>18,'protein'=>0.9,'carbs'=>3.9,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Cucumber','calories'=>16,'protein'=>0.7,'carbs'=>3.6,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Bell Pepper','calories'=>31,'protein'=>1,'carbs'=>6,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Onion','calories'=>40,'protein'=>1.1,'carbs'=>9.3,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Potato','calories'=>77,'protein'=>2,'carbs'=>17,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Sweet Potato','calories'=>86,'protein'=>1.6,'carbs'=>20,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Green Beans','calories'=>31,'protein'=>1.8,'carbs'=>7,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Peas','calories'=>81,'protein'=>5.4,'carbs'=>14,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Mushroom','calories'=>22,'protein'=>3.1,'carbs'=>3.3,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Beetroot','calories'=>43,'protein'=>1.6,'carbs'=>10,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Chicken Breast','calories'=>165,'protein'=>31,'carbs'=>0,'fat'=>3.6,'serving'=>'100g'],
['name'=>'Chicken Thigh','calories'=>209,'protein'=>26,'carbs'=>0,'fat'=>10.9,'serving'=>'100g'],
['name'=>'Egg','calories'=>155,'protein'=>13,'carbs'=>1.1,'fat'=>11,'serving'=>'100g'],
['name'=>'Egg White','calories'=>52,'protein'=>11,'carbs'=>0.7,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Tuna','calories'=>132,'protein'=>28,'carbs'=>0,'fat'=>1.3,'serving'=>'100g'],
['name'=>'Salmon','calories'=>208,'protein'=>20,'carbs'=>0,'fat'=>13,'serving'=>'100g'],
['name'=>'Mackerel','calories'=>205,'protein'=>19,'carbs'=>0,'fat'=>14,'serving'=>'100g'],
['name'=>'Paneer','calories'=>265,'protein'=>18,'carbs'=>1.2,'fat'=>20,'serving'=>'100g'],
['name'=>'Tofu','calories'=>76,'protein'=>8,'carbs'=>1.9,'fat'=>4.8,'serving'=>'100g'],
['name'=>'Dal (Lentils)','calories'=>116,'protein'=>9,'carbs'=>20,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Chickpeas','calories'=>164,'protein'=>9,'carbs'=>27,'fat'=>2.6,'serving'=>'100g'],
['name'=>'Kidney Beans','calories'=>127,'protein'=>8.7,'carbs'=>23,'fat'=>0.5,'serving'=>'100g'],
['name'=>'Black Beans','calories'=>132,'protein'=>8.9,'carbs'=>24,'fat'=>0.5,'serving'=>'100g'],
['name'=>'Soya Chunks','calories'=>345,'protein'=>52,'carbs'=>33,'fat'=>0.5,'serving'=>'100g'],
['name'=>'Greek Yogurt','calories'=>59,'protein'=>10,'carbs'=>3.6,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Rice (Cooked)','calories'=>130,'protein'=>2.7,'carbs'=>28,'fat'=>0.3,'serving'=>'100g'],
['name'=>'Brown Rice (Cooked)','calories'=>111,'protein'=>2.6,'carbs'=>23,'fat'=>0.9,'serving'=>'100g'],
['name'=>'Basmati Rice (Cooked)','calories'=>121,'protein'=>3.5,'carbs'=>25,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Chapati','calories'=>297,'protein'=>9,'carbs'=>50,'fat'=>7,'serving'=>'100g'],
['name'=>'Whole Wheat Bread','calories'=>247,'protein'=>13,'carbs'=>41,'fat'=>4.2,'serving'=>'100g'],
['name'=>'White Bread','calories'=>265,'protein'=>9,'carbs'=>49,'fat'=>3.2,'serving'=>'100g'],
['name'=>'Oats','calories'=>389,'protein'=>17,'carbs'=>66,'fat'=>7,'serving'=>'100g'],
['name'=>'Quinoa (Cooked)','calories'=>120,'protein'=>4.4,'carbs'=>21,'fat'=>1.9,'serving'=>'100g'],
['name'=>'Pasta (Cooked)','calories'=>131,'protein'=>5,'carbs'=>25,'fat'=>1.1,'serving'=>'100g'],
['name'=>'Poha','calories'=>130,'protein'=>2.6,'carbs'=>28,'fat'=>0.2,'serving'=>'100g'],
['name'=>'Upma','calories'=>149,'protein'=>4.2,'carbs'=>23,'fat'=>4.2,'serving'=>'100g'],
['name'=>'Corn Flakes','calories'=>357,'protein'=>7,'carbs'=>84,'fat'=>0.4,'serving'=>'100g'],
['name'=>'Milk (Toned)','calories'=>60,'protein'=>3.2,'carbs'=>4.8,'fat'=>3.3,'serving'=>'100ml'],
['name'=>'Skim Milk','calories'=>34,'protein'=>3.4,'carbs'=>5,'fat'=>0.1,'serving'=>'100ml'],
['name'=>'Curd (Yogurt)','calories'=>61,'protein'=>3.5,'carbs'=>4.7,'fat'=>3.3,'serving'=>'100g'],
['name'=>'Cheese','calories'=>402,'protein'=>25,'carbs'=>1.3,'fat'=>33,'serving'=>'100g'],
['name'=>'Cottage Cheese','calories'=>98,'protein'=>11,'carbs'=>3.4,'fat'=>4.3,'serving'=>'100g'],
['name'=>'Buttermilk','calories'=>40,'protein'=>3.3,'carbs'=>4.8,'fat'=>1,'serving'=>'100ml'],
['name'=>'Lassi','calories'=>95,'protein'=>3.2,'carbs'=>12,'fat'=>4,'serving'=>'100ml'],
['name'=>'Butter','calories'=>717,'protein'=>0.9,'carbs'=>0.1,'fat'=>81,'serving'=>'100g'],
['name'=>'Ghee','calories'=>900,'protein'=>0,'carbs'=>0,'fat'=>100,'serving'=>'100g'],
['name'=>'Idli','calories'=>58,'protein'=>2,'carbs'=>12,'fat'=>0.3,'serving'=>'1 piece'],
['name'=>'Dosa','calories'=>168,'protein'=>4,'carbs'=>28,'fat'=>3.7,'serving'=>'1 piece'],
['name'=>'Masala Dosa','calories'=>247,'protein'=>5.5,'carbs'=>35,'fat'=>9.5,'serving'=>'1 piece'],
['name'=>'Sambar','calories'=>75,'protein'=>3.8,'carbs'=>11,'fat'=>2,'serving'=>'100g'],
['name'=>'Rasam','calories'=>30,'protein'=>1,'carbs'=>5,'fat'=>0.8,'serving'=>'100g'],
['name'=>'Pongal','calories'=>160,'protein'=>4,'carbs'=>24,'fat'=>5.8,'serving'=>'100g'],
['name'=>'Biryani (Chicken)','calories'=>180,'protein'=>8,'carbs'=>24,'fat'=>6,'serving'=>'100g'],
['name'=>'Veg Biryani','calories'=>151,'protein'=>3.5,'carbs'=>22,'fat'=>5.4,'serving'=>'100g'],
['name'=>'Dal Makhani','calories'=>153,'protein'=>7,'carbs'=>17,'fat'=>6,'serving'=>'100g'],
['name'=>'Rajma Curry','calories'=>127,'protein'=>6,'carbs'=>18,'fat'=>3.5,'serving'=>'100g'],
['name'=>'Chole','calories'=>164,'protein'=>8.9,'carbs'=>27,'fat'=>2.6,'serving'=>'100g'],
['name'=>'Palak Paneer','calories'=>155,'protein'=>7,'carbs'=>5,'fat'=>11,'serving'=>'100g'],
['name'=>'Aloo Gobi','calories'=>92,'protein'=>2.7,'carbs'=>11,'fat'=>4.2,'serving'=>'100g'],
['name'=>'Bhindi Fry','calories'=>90,'protein'=>2,'carbs'=>8,'fat'=>5.5,'serving'=>'100g'],
['name'=>'Mixed Veg Curry','calories'=>95,'protein'=>2.6,'carbs'=>12,'fat'=>4,'serving'=>'100g'],
['name'=>'Kadhi','calories'=>103,'protein'=>4.6,'carbs'=>8.4,'fat'=>5.7,'serving'=>'100g'],
['name'=>'Paratha','calories'=>300,'protein'=>8,'carbs'=>45,'fat'=>10,'serving'=>'100g'],
['name'=>'Poori','calories'=>296,'protein'=>6,'carbs'=>40,'fat'=>12,'serving'=>'100g'],
['name'=>'Pav Bhaji','calories'=>180,'protein'=>4.5,'carbs'=>25,'fat'=>7,'serving'=>'100g'],
['name'=>'Vada','calories'=>200,'protein'=>6,'carbs'=>20,'fat'=>10,'serving'=>'1 piece'],
['name'=>'Samosa','calories'=>262,'protein'=>4,'carbs'=>30,'fat'=>14,'serving'=>'1 piece'],
['name'=>'Kheer','calories'=>180,'protein'=>4,'carbs'=>24,'fat'=>7,'serving'=>'100g'],
['name'=>'Halwa','calories'=>250,'protein'=>3,'carbs'=>35,'fat'=>11,'serving'=>'100g'],
['name'=>'Peanut Butter','calories'=>588,'protein'=>25,'carbs'=>20,'fat'=>50,'serving'=>'100g'],
['name'=>'Almonds','calories'=>579,'protein'=>21,'carbs'=>22,'fat'=>50,'serving'=>'100g'],
['name'=>'Cashews','calories'=>553,'protein'=>18,'carbs'=>30,'fat'=>44,'serving'=>'100g'],
['name'=>'Walnuts','calories'=>654,'protein'=>15,'carbs'=>14,'fat'=>65,'serving'=>'100g'],
['name'=>'Chia Seeds','calories'=>486,'protein'=>17,'carbs'=>42,'fat'=>31,'serving'=>'100g'],
['name'=>'Flax Seeds','calories'=>534,'protein'=>18,'carbs'=>29,'fat'=>42,'serving'=>'100g'],
['name'=>'Honey','calories'=>304,'protein'=>0.3,'carbs'=>82,'fat'=>0,'serving'=>'100g'],
['name'=>'Jaggery','calories'=>383,'protein'=>0.4,'carbs'=>98,'fat'=>0.1,'serving'=>'100g'],
['name'=>'Sugar','calories'=>387,'protein'=>0,'carbs'=>100,'fat'=>0,'serving'=>'100g'],
['name'=>'Black Coffee','calories'=>2,'protein'=>0.3,'carbs'=>0,'fat'=>0,'serving'=>'100ml'],
['name'=>'Green Tea','calories'=>1,'protein'=>0,'carbs'=>0.2,'fat'=>0,'serving'=>'100ml'],
['name'=>'Coconut Water','calories'=>19,'protein'=>0.7,'carbs'=>3.7,'fat'=>0.2,'serving'=>'100ml']
];

$q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
if ($q === '') {
    echo json_encode(['success' => true, 'data' => array_slice($foods, 0, 20)]);
    exit;
}

$results = array_values(array_filter($foods, static function (array $food) use ($q): bool {
    return str_contains(mb_strtolower((string) $food['name']), $q);
}));

echo json_encode(['success' => true, 'data' => array_slice($results, 0, 50)]);

