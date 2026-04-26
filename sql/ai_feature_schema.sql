-- AI fallback feature schema updates (MySQL/MariaDB)
-- Run once if you want manual DB setup.

CREATE TABLE IF NOT EXISTS food_calories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meal_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_name VARCHAR(150) NOT NULL,
    short_description VARCHAR(255) NOT NULL,
    calories INT NULL,
    source ENUM('db','ai') NOT NULL DEFAULT 'db',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_meal_name (meal_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meal_ingredients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_id BIGINT UNSIGNED NOT NULL,
    ingredient_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_meal_id (meal_id),
    CONSTRAINT fk_meal_ingredients_meal_id
        FOREIGN KEY (meal_id) REFERENCES meal_suggestions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
