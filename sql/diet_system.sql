-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 08:41 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `diet_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_type` enum('user','dietitian','admin') NOT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_type`, `action`, `ip_address`, `created_at`) VALUES
(1, 1, 'admin', 'Created diet plan #1', '192.168.1.10', '2026-04-01 09:01:00'),
(2, 1, 'dietitian', 'Updated meal template', '192.168.1.20', '2026-04-05 14:12:00'),
(3, 1, 'user', 'Logged breakfast', '192.168.1.30', '2026-04-15 08:11:00'),
(4, 2, 'user', 'Logged weight', '192.168.1.31', '2026-04-15 10:11:00'),
(5, 3, 'user', 'Sent message to dietitian', '192.168.1.32', '2026-04-15 09:01:00'),
(6, 1, 'admin', 'Logged in', '127.0.0.1', '2026-04-20 15:27:03'),
(7, 1, 'user', 'Logged in', '127.0.0.1', '2026-04-20 15:27:03'),
(8, 1, 'admin', 'Logged in', '127.0.0.1', '2026-04-20 15:27:40'),
(9, 1, 'user', 'Logged in', '127.0.0.1', '2026-04-20 15:27:40'),
(10, 1, 'admin', 'Logged in', '127.0.0.1', '2026-04-20 15:28:05'),
(11, 1, 'user', 'Logged in', '127.0.0.1', '2026-04-20 15:28:06'),
(12, 1, 'user', 'Logged in', '127.0.0.1', '2026-04-20 15:28:26'),
(13, 2, 'user', 'Logged in', '::1', '2026-04-20 15:36:37'),
(14, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:47'),
(15, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:49'),
(16, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:51'),
(17, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:51'),
(18, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:51'),
(19, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:51'),
(20, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:52'),
(21, 2, 'user', 'Logged water intake', '::1', '2026-04-20 15:40:52'),
(22, 2, 'user', 'Deleted water entry', '::1', '2026-04-20 15:40:58'),
(23, 2, 'user', 'Deleted water entry', '::1', '2026-04-20 15:41:01'),
(24, 2, 'user', 'Updated personal profile info', '::1', '2026-04-20 15:41:42'),
(25, 2, 'user', 'Updated personal profile info', '::1', '2026-04-20 15:52:09'),
(26, 2, 'user', 'Logged out', '::1', '2026-04-20 15:53:18'),
(27, 1, 'dietitian', 'Logged in', '::1', '2026-04-20 15:53:47'),
(28, 1, 'dietitian', 'Logged out', '::1', '2026-04-20 17:30:36'),
(29, 1, 'admin', 'Logged in', '::1', '2026-04-20 17:30:49'),
(30, 1, 'admin', 'Logged out', '::1', '2026-04-20 17:34:07'),
(31, 4, 'user', 'Registered new account', '::1', '2026-04-20 17:37:15'),
(32, 4, 'user', 'Requested a dietitian', '::1', '2026-04-20 17:37:51'),
(33, 4, 'user', 'Logged out', '::1', '2026-04-20 17:37:58'),
(34, 1, 'dietitian', 'Logged in', '::1', '2026-04-20 17:38:13'),
(35, 1, 'dietitian', 'Logged out', '::1', '2026-04-20 17:39:15'),
(36, 2, 'user', 'Logged in', '::1', '2026-04-20 18:34:36'),
(37, 2, 'user', 'Logged out', '::1', '2026-04-20 18:37:47'),
(38, 1, 'admin', 'Logged in', '::1', '2026-04-20 19:35:09'),
(39, 1, 'admin', 'Logged out', '::1', '2026-04-20 19:38:59'),
(40, 3, 'dietitian', 'Submitted dietitian registration request', '::1', '2026-04-20 19:54:33'),
(41, 1, 'admin', 'Logged in', '::1', '2026-04-20 19:54:51'),
(42, 1, 'admin', 'Logged out', '::1', '2026-04-20 19:55:50'),
(43, 3, 'dietitian', 'Logged in', '::1', '2026-04-20 19:56:03'),
(44, 3, 'dietitian', 'Logged out', '::1', '2026-04-20 20:09:58'),
(45, 1, 'dietitian', 'Logged in', '::1', '2026-04-20 20:10:10'),
(46, 1, 'dietitian', 'Approved dietitian request', '::1', '2026-04-20 20:21:08'),
(47, 1, 'dietitian', 'Assigned template to user', '::1', '2026-04-20 20:21:33'),
(48, 1, 'dietitian', 'Logged out', '::1', '2026-04-20 20:21:53'),
(49, 4, 'user', 'Logged in', '::1', '2026-04-20 20:22:15'),
(50, 4, 'user', 'Updated meal completion status', '::1', '2026-04-20 20:23:14'),
(51, 4, 'user', 'Logged out', '::1', '2026-04-20 20:23:41'),
(52, 1, 'dietitian', 'Logged in', '::1', '2026-04-20 20:24:02'),
(53, 1, 'dietitian', 'Assigned template to user', '::1', '2026-04-20 20:24:33'),
(54, 1, 'dietitian', 'Logged out', '::1', '2026-04-20 20:24:50'),
(55, 4, 'user', 'Logged in', '::1', '2026-04-20 20:25:12'),
(56, 4, 'user', 'Logged out', '::1', '2026-04-20 20:25:48'),
(57, 1, 'dietitian', 'Logged in', '::1', '2026-04-20 20:26:02'),
(58, 1, 'dietitian', 'Assigned template to user', '::1', '2026-04-20 20:26:45'),
(59, 1, 'dietitian', 'Logged out', '::1', '2026-04-20 20:26:54'),
(60, 4, 'user', 'Logged in', '::1', '2026-04-20 20:27:08'),
(61, 2, 'user', 'Logged in', '::1', '2026-04-21 06:50:41'),
(62, 2, 'user', 'Logged in', '::1', '2026-04-21 14:57:27'),
(63, 2, 'user', 'Logged out', '::1', '2026-04-21 15:08:57'),
(64, 2, 'user', 'Logged in', '::1', '2026-04-21 18:31:35'),
(65, 2, 'user', 'Logged water intake', '::1', '2026-04-21 18:31:46'),
(66, 2, 'user', 'Sent message', '::1', '2026-04-21 18:35:46'),
(67, 2, 'user', 'Logged out', '::1', '2026-04-21 18:42:56'),
(68, 5, 'user', 'Registered new account', '::1', '2026-04-21 18:44:51'),
(69, 2, 'user', 'Logged in', '::1', '2026-04-22 06:27:43'),
(70, 2, 'user', 'Logged out', '::1', '2026-04-22 06:28:15'),
(71, 5, 'user', 'Logged in', '::1', '2026-04-22 06:28:31'),
(72, 5, 'user', 'Logged water intake', '::1', '2026-04-22 06:30:36');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'System Admin', 'test@gmail.com', 'admin123', '2026-04-01 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `dietitians`
--

CREATE TABLE `dietitians` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `experience_years` tinyint(3) UNSIGNED DEFAULT 0,
  `bio` text DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dietitians`
--

INSERT INTO `dietitians` (`id`, `full_name`, `email`, `password`, `phone`, `profile_pic`, `specialization`, `experience_years`, `bio`, `status`, `created_at`) VALUES
(1, 'Dr. Sarah Thompson', 'sarah@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801700000001', 'dietitian_avatar_1.svg', 'Weight Management (Toronto, Canada)', 8, 'Canadian dietitian focused on sustainable fat-loss and metabolic health.', 'active', '2026-03-20 09:00:00'),
(2, 'Dr. Ethan Brooks', 'ethan@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801700000002', 'dietitian_avatar_2.svg', 'Sports Nutrition (Vancouver, Canada)', 6, 'Canadian sports dietitian specializing in athletic performance and recovery.', 'active', '2026-03-21 09:30:00'),
(3, 'Test Dietitian', 'test@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '01635607817', 'dietitian_avatar_3.svg', 'vibe', 13, 'Registration No: 1234', 'active', '2026-04-20 19:54:33'),
(4, 'Dr. Ayesha Rahman', 'ayesha@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801711001101', 'dietitian_avatar_4.svg', 'Clinical Nutrition (Dhaka)', 9, 'Specialized in PCOS, thyroid management, and sustainable weight loss.', 'active', '2026-04-21 18:48:34'),
(5, 'Dr. Farhan Ahmed', 'farhan@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801811001102', 'dietitian_avatar_5.svg', 'Sports Nutrition (Dhaka)', 7, 'Focuses on muscle gain, endurance diets, and performance meal planning.', 'active', '2026-04-21 18:48:34'),
(6, 'Dr. Nusrat Jahan', 'nusrat@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801911001103', 'dietitian_avatar_6.svg', 'Diabetes Nutrition (Chattogram)', 11, 'Works on glycemic control through culturally practical meal plans.', 'active', '2026-04-21 18:48:34'),
(7, 'Dr. Arindam Sen', 'arindam@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+919811001104', 'dietitian_avatar_7.svg', 'Weight Management (Kolkata)', 8, 'Helps clients with fat loss and long-term habit-based nutrition.', 'active', '2026-04-21 18:48:34'),
(8, 'Dr. Priya Nair', 'priya@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+919822001105', 'dietitian_avatar_8.svg', 'Cardiac Diet Therapy (Bengaluru)', 10, 'Designs heart-healthy meal structures with sodium and lipid control.', 'active', '2026-04-21 18:48:34'),
(9, 'Dr. Liam Carter', 'liam@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+15140001109', 'dietitian_avatar_9.svg', 'Clinical Nutrition (Montreal, Canada)', 6, 'Canada-based clinical nutrition specialist helping clients improve metabolic health, digestive comfort, and long-term eating habits.', 'active', '2026-04-21 18:48:35'),
(10, 'Dr. Meher Afroz', 'meher@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+8801511001107', 'dietitian_avatar_10.svg', 'Maternal Nutrition (Rajshahi)', 12, 'Guides prenatal and postnatal nutrition plans for mothers.', 'active', '2026-04-21 18:48:35'),
(11, 'Dr. Rohan Kapoor', 'rohan@gmail.com', '$2y$10$Ag1IRqJDy4xl34a5P9wxdOIOPMhbiSFULt5b1POigHcfkb90RWrOq', '+917700110108', 'dietitian_avatar_11.svg', 'Clinical & Bariatric Nutrition (Mumbai)', 9, 'Experienced in bariatric recovery and metabolic health plans.', 'active', '2026-04-21 18:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `dietitian_requests`
--

CREATE TABLE `dietitian_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `dietitian_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dietitian_requests`
--

INSERT INTO `dietitian_requests` (`id`, `user_id`, `dietitian_id`, `status`, `created_at`) VALUES
(1, 1, 1, 'approved', '2026-04-01 08:30:00'),
(2, 2, 2, 'approved', '2026-04-02 09:30:00'),
(3, 3, 1, 'pending', '2026-04-03 10:30:00'),
(4, 4, 1, 'approved', '2026-04-20 17:37:51');

-- --------------------------------------------------------

--
-- Table structure for table `diet_plans`
--

CREATE TABLE `diet_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `dietitian_id` bigint(20) UNSIGNED NOT NULL,
  `goal_type` enum('weight_loss','gain','maintain') NOT NULL,
  `total_calories` smallint(5) UNSIGNED NOT NULL,
  `duration_days` smallint(5) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `diet_plans`
--

INSERT INTO `diet_plans` (`id`, `title`, `description`, `dietitian_id`, `goal_type`, `total_calories`, `duration_days`, `status`, `created_at`) VALUES
(1, 'Lean Start 1500', 'Structured calorie-deficit plan for healthy weight loss.', 1, 'weight_loss', 1500, 30, 'active', '2026-04-01 09:00:00'),
(2, 'Muscle Gain 2600', 'High-protein surplus plan for lean mass gain.', 2, 'gain', 2600, 45, 'active', '2026-04-01 09:15:00'),
(3, 'Balanced Maintain 1900', 'Balanced maintenance plan for long-term consistency.', 1, 'maintain', 1900, 30, 'active', '2026-04-01 09:30:00'),
(4, 'Weight Loss - High Protein Day - Anjon Biswas', 'Auto-created from template \"Weight Loss - High Protein Day\"', 1, 'weight_loss', 1380, 1, 'active', '2026-04-20 20:21:33'),
(5, 'Weight Loss - Low Carb Day - Anjon Biswas', 'Auto-created from template \"Weight Loss - Low Carb Day\"', 1, 'weight_loss', 1340, 1, 'active', '2026-04-20 20:24:33'),
(6, 'High Fiber Gut Friendly Day - Anjon Biswas', 'Auto-created from template \"High Fiber Gut Friendly Day\"', 1, 'maintain', 1620, 1, 'active', '2026-04-20 20:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `food_calories`
--

CREATE TABLE `food_calories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `food_name` varchar(120) NOT NULL,
  `calories` int(11) NOT NULL,
  `protein` decimal(7,2) DEFAULT NULL,
  `carbs` decimal(7,2) DEFAULT NULL,
  `fat` decimal(7,2) DEFAULT NULL,
  `serving_unit` varchar(80) NOT NULL,
  `source` enum('db','ai') NOT NULL DEFAULT 'db',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_calories`
--

INSERT INTO `food_calories` (`id`, `food_name`, `calories`, `protein`, `carbs`, `fat`, `serving_unit`, `source`, `created_at`) VALUES
(1, 'Apple', 52, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(2, 'Banana', 89, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(3, 'Egg', 78, NULL, NULL, NULL, 'per piece', 'db', '2026-04-21 19:40:34'),
(4, 'Rice', 130, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(5, 'Bread', 80, NULL, NULL, NULL, 'per slice', 'db', '2026-04-21 19:40:34'),
(6, 'Milk', 103, NULL, NULL, NULL, 'per cup', 'db', '2026-04-21 19:40:34'),
(7, 'Potato', 87, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(8, 'Lentils', 116, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(9, 'Chicken Breast', 165, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(10, 'Yogurt', 61, NULL, NULL, NULL, 'per 100g', 'db', '2026-04-21 19:40:34'),
(11, 'mango', 99, 0.80, 25.00, 0.60, 'cup', 'ai', '2026-04-21 19:41:00'),
(12, 'avocado', 160, NULL, NULL, NULL, 'medium fruit', 'ai', '2026-04-21 19:41:12'),
(13, 'porota', 250, NULL, NULL, NULL, 'piece', 'ai', '2026-04-21 19:41:25'),
(14, 'teanut', 580, NULL, NULL, NULL, '100g', 'ai', '2026-04-21 19:41:42'),
(15, 'pineapple', 50, NULL, NULL, NULL, '100g', 'ai', '2026-04-21 19:42:38'),
(16, 'cane', 350, NULL, NULL, NULL, '100g', 'ai', '2026-04-21 19:42:47'),
(17, 'sugar', 387, NULL, NULL, NULL, '100g', 'ai', '2026-04-21 19:42:54'),
(18, 'vaat', 150, NULL, NULL, NULL, 'piece', 'ai', '2026-04-21 19:43:07'),
(19, 'vorta', 250, NULL, NULL, NULL, '100g', 'ai', '2026-04-21 19:43:21'),
(20, 'Luffa', 20, 1.00, 4.80, 0.10, '100 g', 'ai', '2026-04-21 20:00:58'),
(21, 'muskmelon', 34, 0.80, 8.20, 0.20, '100 g', 'ai', '2026-04-21 20:03:31'),
(23, 'Pomelo', 38, 0.80, 9.60, 0.04, '100 g', 'ai', '2026-04-21 20:07:33'),
(24, 'Citrus medica', 30, 0.60, 8.00, 0.20, '100 g', 'ai', '2026-04-21 20:08:24'),
(25, 'melon', 60, 0.90, 15.00, 0.20, '1 cup (160 g) diced', 'ai', '2026-04-21 20:11:54'),
(27, 'Longan', 60, 0.80, 15.00, 0.10, '100 g', 'ai', '2026-04-21 20:23:33'),
(28, 'Soyabean', 446, 36.50, 30.00, 20.00, '100 g', 'ai', '2026-04-22 06:32:26');

-- --------------------------------------------------------

--
-- Table structure for table `food_calorie_reference`
--

CREATE TABLE `food_calorie_reference` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `food_name` varchar(120) NOT NULL,
  `calories_est` int(11) NOT NULL,
  `serving_unit` varchar(60) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_calorie_reference`
--

INSERT INTO `food_calorie_reference` (`id`, `food_name`, `calories_est`, `serving_unit`, `created_at`) VALUES
(1, 'Apple', 52, 'per 100g', '2026-04-21 19:21:21'),
(2, 'Banana', 89, 'per 100g', '2026-04-21 19:21:21'),
(3, 'Egg', 78, 'per piece', '2026-04-21 19:21:21'),
(4, 'White Rice (cooked)', 130, 'per 100g', '2026-04-21 19:21:21'),
(5, 'Brown Rice (cooked)', 112, 'per 100g', '2026-04-21 19:21:21'),
(6, 'Bread', 80, 'per slice', '2026-04-21 19:21:21'),
(7, 'Chicken Breast (cooked)', 165, 'per 100g', '2026-04-21 19:21:21'),
(8, 'Salmon (cooked)', 208, 'per 100g', '2026-04-21 19:21:21'),
(9, 'Milk', 103, 'per cup', '2026-04-21 19:21:21'),
(10, 'Yogurt (plain)', 61, 'per 100g', '2026-04-21 19:21:21'),
(11, 'Lentils (cooked)', 116, 'per 100g', '2026-04-21 19:21:21'),
(12, 'Potato (boiled)', 87, 'per 100g', '2026-04-21 19:21:21'),
(13, 'Oats (dry)', 389, 'per 100g', '2026-04-21 19:21:21'),
(14, 'Peanut Butter', 94, 'per tbsp', '2026-04-21 19:21:21'),
(15, 'Cheddar Cheese', 113, 'per 28g', '2026-04-21 19:21:21'),
(16, 'Orange', 47, 'per 100g', '2026-04-21 19:21:21'),
(17, 'Mango', 60, 'per 100g', '2026-04-21 19:21:21'),
(18, 'Avocado', 160, 'per 100g', '2026-04-21 19:21:21'),
(19, 'Broccoli', 35, 'per 100g', '2026-04-21 19:21:21'),
(20, 'Olive Oil', 119, 'per tbsp', '2026-04-21 19:21:21');

-- --------------------------------------------------------

--
-- Table structure for table `food_log`
--

CREATE TABLE `food_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `food_name` varchar(160) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `calories` smallint(5) UNSIGNED NOT NULL,
  `protein` decimal(6,2) DEFAULT 0.00,
  `carbs` decimal(6,2) DEFAULT 0.00,
  `fat` decimal(6,2) DEFAULT 0.00,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) NOT NULL DEFAULT 'serving',
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_log`
--

INSERT INTO `food_log` (`id`, `user_id`, `food_name`, `meal_type`, `calories`, `protein`, `carbs`, `fat`, `quantity`, `unit`, `log_date`, `created_at`) VALUES
(1, 1, 'Oats & Berries', 'breakfast', 320, 15.00, 48.00, 8.00, 1.00, 'bowl', '2026-04-15', '2026-04-15 08:10:00'),
(2, 1, 'Grilled Chicken Salad', 'lunch', 420, 38.00, 22.00, 18.00, 1.00, 'plate', '2026-04-15', '2026-04-15 13:05:00'),
(3, 2, 'Peanut Butter Oats', 'breakfast', 620, 24.00, 78.00, 22.00, 1.00, 'bowl', '2026-04-15', '2026-04-15 08:25:00'),
(4, 2, 'Protein Shake', 'snack', 420, 32.00, 42.00, 12.00, 1.00, 'glass', '2026-04-15', '2026-04-15 17:20:00'),
(5, 3, 'Veg Omelette', 'breakfast', 420, 24.00, 28.00, 20.00, 1.00, 'plate', '2026-04-15', '2026-04-15 08:45:00'),
(6, 3, 'Paneer Veg Curry', 'dinner', 650, 30.00, 58.00, 30.00, 1.00, 'plate', '2026-04-15', '2026-04-15 20:00:00'),
(7, 4, 'Veg Omelette', 'breakfast', 420, 24.00, 28.00, 20.00, 1.00, 'plan_meal', '2026-04-21', '2026-04-20 20:23:14');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_meal_suggestions`
--

CREATE TABLE `ingredient_meal_suggestions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `meal_name` varchar(150) NOT NULL,
  `ingredient_list` text NOT NULL,
  `short_description` varchar(255) NOT NULL,
  `calories_est` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ingredient_meal_suggestions`
--

INSERT INTO `ingredient_meal_suggestions` (`id`, `meal_name`, `ingredient_list`, `short_description`, `calories_est`, `created_at`) VALUES
(1, 'Potato Lentil Curry', 'potato, lentils, onion, garlic, turmeric, oil, salt', 'Comforting curry with soft potatoes and protein-rich lentils.', 320, '2026-04-21 19:21:21'),
(2, 'Egg Fried Rice', 'rice, egg, oil, onion, garlic, soy sauce, salt', 'Quick stir-fried rice with egg and savory flavor.', 410, '2026-04-21 19:21:21'),
(3, 'Banana Oat Smoothie', 'banana, oats, milk, honey', 'Simple energy smoothie for breakfast or post-workout.', 260, '2026-04-21 19:21:21'),
(4, 'Vegetable Omelette', 'egg, onion, tomato, spinach, oil, salt, pepper', 'High-protein omelette with fresh vegetables.', 230, '2026-04-21 19:21:21'),
(5, 'Chicken Stir Fry', 'chicken, onion, garlic, bell pepper, oil, soy sauce', 'Lean chicken tossed with veggies in light sauce.', 360, '2026-04-21 19:21:21'),
(6, 'Dal Soup', 'lentils, onion, garlic, turmeric, cumin, oil, salt', 'Light and nourishing lentil soup.', 240, '2026-04-21 19:21:21'),
(7, 'Potato Egg Hash', 'potato, egg, onion, oil, salt, pepper', 'Pan-cooked potato and egg skillet meal.', 300, '2026-04-21 19:21:21'),
(8, 'Tomato Garlic Pasta', 'pasta, tomato, garlic, olive oil, salt, chili flakes', 'Basic pasta with garlic tomato flavor.', 420, '2026-04-21 19:21:21'),
(9, 'Rice and Grilled Fish', 'rice, fish, oil, lemon, salt, pepper', 'Balanced rice plate with grilled fish.', 490, '2026-04-21 19:21:21'),
(10, 'Chickpea Salad Bowl', 'chickpeas, cucumber, tomato, onion, lemon, olive oil, salt', 'Refreshing high-fiber salad bowl.', 280, '2026-04-21 19:21:21');

-- --------------------------------------------------------

--
-- Table structure for table `meals`
--

CREATE TABLE `meals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `diet_plan_id` bigint(20) UNSIGNED NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `meal_name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `calories` smallint(5) UNSIGNED NOT NULL,
  `protein` decimal(6,2) NOT NULL,
  `carbs` decimal(6,2) NOT NULL,
  `fat` decimal(6,2) NOT NULL,
  `day_number` smallint(5) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meals`
--

INSERT INTO `meals` (`id`, `diet_plan_id`, `meal_type`, `meal_name`, `description`, `calories`, `protein`, `carbs`, `fat`, `day_number`, `created_at`) VALUES
(1, 1, 'breakfast', 'Oats & Berries', 'Rolled oats with low-fat milk and berries', 320, 15.00, 48.00, 8.00, 1, '2026-04-01 10:00:00'),
(2, 1, 'lunch', 'Grilled Chicken Salad', 'Chicken breast, greens, olive oil dressing', 420, 38.00, 22.00, 18.00, 1, '2026-04-01 10:00:00'),
(3, 1, 'dinner', 'Fish with Veggies', 'Baked fish with steamed vegetables', 460, 40.00, 25.00, 20.00, 1, '2026-04-01 10:00:00'),
(4, 1, 'snack', 'Greek Yogurt', 'Plain yogurt with chia seeds', 180, 14.00, 12.00, 7.00, 1, '2026-04-01 10:00:00'),
(5, 1, 'breakfast', 'Egg White Toast', 'Egg whites with whole grain toast', 300, 24.00, 28.00, 9.00, 2, '2026-04-01 10:00:00'),
(6, 1, 'lunch', 'Turkey Wrap', 'Whole wheat wrap with turkey and salad', 410, 33.00, 36.00, 14.00, 2, '2026-04-01 10:00:00'),
(7, 1, 'dinner', 'Lentil Soup Bowl', 'Lentil soup with side cucumber salad', 500, 28.00, 55.00, 14.00, 2, '2026-04-01 10:00:00'),
(8, 1, 'snack', 'Apple & Almonds', 'Fresh apple with almonds', 190, 6.00, 22.00, 9.00, 2, '2026-04-01 10:00:00'),
(9, 1, 'breakfast', 'Smoothie Bowl', 'Spinach banana protein smoothie', 310, 20.00, 38.00, 8.00, 3, '2026-04-01 10:00:00'),
(10, 1, 'lunch', 'Quinoa Chicken Bowl', 'Quinoa, grilled chicken, mixed veg', 430, 35.00, 40.00, 12.00, 3, '2026-04-01 10:00:00'),
(11, 1, 'dinner', 'Tofu Stir Fry', 'Tofu with broccoli and peppers', 470, 30.00, 42.00, 18.00, 3, '2026-04-01 10:00:00'),
(12, 1, 'snack', 'Cottage Cheese', 'Low-fat cottage cheese cup', 170, 15.00, 8.00, 7.00, 3, '2026-04-01 10:00:00'),
(13, 2, 'breakfast', 'Peanut Butter Oats', 'Oats, banana, peanut butter, milk', 620, 24.00, 78.00, 22.00, 1, '2026-04-01 10:10:00'),
(14, 2, 'lunch', 'Chicken Rice Plate', 'Chicken thigh, rice, veggies', 760, 45.00, 85.00, 24.00, 1, '2026-04-01 10:10:00'),
(15, 2, 'dinner', 'Beef Pasta', 'Lean beef pasta with tomato sauce', 820, 50.00, 88.00, 26.00, 1, '2026-04-01 10:10:00'),
(16, 2, 'snack', 'Protein Shake', 'Milk, whey, oats, dates', 420, 32.00, 42.00, 12.00, 1, '2026-04-01 10:10:00'),
(17, 2, 'breakfast', 'Egg & Avocado Toast', 'Whole eggs, toast, avocado', 590, 28.00, 46.00, 30.00, 2, '2026-04-01 10:10:00'),
(18, 2, 'lunch', 'Salmon Potato Bowl', 'Salmon, potatoes, greens', 770, 47.00, 70.00, 28.00, 2, '2026-04-01 10:10:00'),
(19, 2, 'dinner', 'Chicken Burrito Bowl', 'Rice, beans, chicken, corn', 820, 52.00, 90.00, 22.00, 2, '2026-04-01 10:10:00'),
(20, 2, 'snack', 'Yogurt Granola', 'Greek yogurt with granola and honey', 410, 22.00, 48.00, 14.00, 2, '2026-04-01 10:10:00'),
(21, 2, 'breakfast', 'High-Cal Pancakes', 'Oat pancakes with nut butter', 600, 25.00, 68.00, 22.00, 3, '2026-04-01 10:10:00'),
(22, 2, 'lunch', 'Beef Rice Bowl', 'Lean beef, jasmine rice, veg', 780, 49.00, 82.00, 24.00, 3, '2026-04-01 10:10:00'),
(23, 2, 'dinner', 'Fish & Couscous', 'White fish with couscous salad', 810, 50.00, 86.00, 23.00, 3, '2026-04-01 10:10:00'),
(24, 2, 'snack', 'Trail Mix & Milk', 'Nuts, raisins, milk', 400, 16.00, 34.00, 22.00, 3, '2026-04-01 10:10:00'),
(25, 3, 'breakfast', 'Veg Omelette', '2-egg omelette with toast', 420, 24.00, 28.00, 20.00, 1, '2026-04-01 10:20:00'),
(26, 3, 'lunch', 'Brown Rice Chicken', 'Brown rice with grilled chicken', 560, 36.00, 62.00, 16.00, 1, '2026-04-01 10:20:00'),
(27, 3, 'dinner', 'Paneer Veg Curry', 'Paneer curry with chapati', 650, 30.00, 58.00, 30.00, 1, '2026-04-01 10:20:00'),
(28, 3, 'snack', 'Fruit & Nuts', 'Seasonal fruit with nuts', 260, 6.00, 30.00, 12.00, 1, '2026-04-01 10:20:00'),
(29, 3, 'breakfast', 'Yogurt Parfait', 'Yogurt, fruits, oats', 430, 20.00, 50.00, 14.00, 2, '2026-04-01 10:20:00'),
(30, 3, 'lunch', 'Tuna Sandwich', 'Whole wheat tuna sandwich', 540, 35.00, 52.00, 20.00, 2, '2026-04-01 10:20:00'),
(31, 3, 'dinner', 'Chicken Stir Fry', 'Chicken and vegetables with rice', 670, 40.00, 66.00, 22.00, 2, '2026-04-01 10:20:00'),
(32, 3, 'snack', 'Hummus & Carrot', 'Hummus with carrot sticks', 240, 8.00, 22.00, 12.00, 2, '2026-04-01 10:20:00'),
(33, 3, 'breakfast', 'Chia Pudding', 'Chia seeds with milk and fruit', 410, 16.00, 44.00, 18.00, 3, '2026-04-01 10:20:00'),
(34, 3, 'lunch', 'Lentil & Rice', 'Lentils, rice, salad', 560, 24.00, 78.00, 12.00, 3, '2026-04-01 10:20:00'),
(35, 3, 'dinner', 'Grilled Fish Plate', 'Fish, quinoa, sauteed veg', 680, 45.00, 54.00, 26.00, 3, '2026-04-01 10:20:00'),
(36, 3, 'snack', 'Boiled Eggs', 'Two boiled eggs', 230, 14.00, 2.00, 16.00, 3, '2026-04-01 10:20:00'),
(37, 4, 'breakfast', 'Greek Yogurt Berry Bowl', 'Greek yogurt, berries, chia seeds', 320, 24.00, 30.00, 10.00, 1, '2026-04-20 20:21:33'),
(38, 4, 'lunch', 'Grilled Chicken Salad', 'Chicken breast with mixed greens and olive oil', 450, 42.00, 18.00, 22.00, 1, '2026-04-20 20:21:33'),
(39, 4, 'dinner', 'Baked Fish & Veggies', 'White fish, broccoli, carrots', 430, 38.00, 24.00, 18.00, 1, '2026-04-20 20:21:33'),
(40, 4, 'snack', 'Boiled Eggs + Cucumber', '2 eggs with cucumber sticks', 180, 13.00, 4.00, 11.00, 1, '2026-04-20 20:21:33'),
(41, 5, 'breakfast', 'Veggie Omelette', 'Eggs, spinach, mushroom, tomato', 300, 22.00, 9.00, 19.00, 1, '2026-04-20 20:24:33'),
(42, 5, 'lunch', 'Chicken Lettuce Wraps', 'Minced chicken and veggies in lettuce leaves', 410, 35.00, 14.00, 22.00, 1, '2026-04-20 20:24:33'),
(43, 5, 'dinner', 'Paneer & Stir Fry Veg', 'Low oil paneer stir fry with vegetables', 460, 28.00, 16.00, 30.00, 1, '2026-04-20 20:24:33'),
(44, 5, 'snack', 'Mixed Nuts Portion', 'Almonds and walnuts', 170, 6.00, 5.00, 14.00, 1, '2026-04-20 20:24:33'),
(45, 6, 'breakfast', 'Bran Cereal + Milk', 'Fiber-rich start', 320, 13.00, 47.00, 8.00, 1, '2026-04-20 20:26:45'),
(46, 6, 'lunch', 'Barley Veg Bowl', 'Barley, beans and veggies', 540, 21.00, 76.00, 14.00, 1, '2026-04-20 20:26:45'),
(47, 6, 'dinner', 'Lentil Pasta + Veg Sauce', 'High fiber dinner option', 600, 29.00, 78.00, 18.00, 1, '2026-04-20 20:26:45'),
(48, 6, 'snack', 'Pear + Flax Seeds', 'Digestive support snack', 160, 3.00, 27.00, 4.00, 1, '2026-04-20 20:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `meal_ingredients`
--

CREATE TABLE `meal_ingredients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `meal_id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_name` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_ingredients`
--

INSERT INTO `meal_ingredients` (`id`, `meal_id`, `ingredient_name`, `created_at`) VALUES
(1, 1, 'potato', '2026-04-21 19:40:34'),
(2, 1, 'lentils', '2026-04-21 19:40:34'),
(3, 1, 'oil', '2026-04-21 19:40:34'),
(4, 1, 'onion', '2026-04-21 19:40:34'),
(5, 1, 'turmeric', '2026-04-21 19:40:34'),
(6, 1, 'salt', '2026-04-21 19:40:34'),
(7, 2, 'rice', '2026-04-21 19:40:34'),
(8, 2, 'egg', '2026-04-21 19:40:34'),
(9, 2, 'oil', '2026-04-21 19:40:34'),
(10, 2, 'onion', '2026-04-21 19:40:34'),
(11, 2, 'soy sauce', '2026-04-21 19:40:34'),
(12, 2, 'salt', '2026-04-21 19:40:34'),
(13, 3, 'egg', '2026-04-21 19:40:34'),
(14, 3, 'onion', '2026-04-21 19:40:34'),
(15, 3, 'tomato', '2026-04-21 19:40:34'),
(16, 3, 'spinach', '2026-04-21 19:40:34'),
(17, 3, 'oil', '2026-04-21 19:40:34'),
(18, 3, 'salt', '2026-04-21 19:40:34'),
(19, 4, 'lentils', '2026-04-21 19:40:34'),
(20, 4, 'onion', '2026-04-21 19:40:34'),
(21, 4, 'garlic', '2026-04-21 19:40:34'),
(22, 4, 'turmeric', '2026-04-21 19:40:34'),
(23, 4, 'cumin', '2026-04-21 19:40:34'),
(24, 4, 'oil', '2026-04-21 19:40:34'),
(25, 4, 'salt', '2026-04-21 19:40:34'),
(26, 5, 'chicken', '2026-04-21 19:40:34'),
(27, 5, 'onion', '2026-04-21 19:40:34'),
(28, 5, 'garlic', '2026-04-21 19:40:34'),
(29, 5, 'bell pepper', '2026-04-21 19:40:34'),
(30, 5, 'oil', '2026-04-21 19:40:34'),
(31, 5, 'soy sauce', '2026-04-21 19:40:34'),
(32, 6, 'chicken breast', '2026-04-21 19:46:11'),
(33, 6, 'almond flour', '2026-04-21 19:46:11'),
(34, 6, 'egg', '2026-04-21 19:46:11'),
(35, 6, 'olive oil', '2026-04-21 19:46:11'),
(36, 6, 'salt', '2026-04-21 19:46:11'),
(37, 6, 'pepper', '2026-04-21 19:46:11'),
(38, 7, 'whole wheat pasta', '2026-04-21 19:46:11'),
(39, 7, 'walnuts', '2026-04-21 19:46:11'),
(40, 7, 'basil', '2026-04-21 19:46:11'),
(41, 7, 'garlic', '2026-04-21 19:46:11'),
(42, 7, 'olive oil', '2026-04-21 19:46:11'),
(43, 7, 'parmesan cheese', '2026-04-21 19:46:11'),
(44, 7, 'salt', '2026-04-21 19:46:11'),
(45, 8, 'tofu', '2026-04-21 19:46:11'),
(46, 8, 'peanut butter', '2026-04-21 19:46:11'),
(47, 8, 'soy sauce', '2026-04-21 19:46:11'),
(48, 8, 'ginger', '2026-04-21 19:46:11'),
(49, 8, 'garlic', '2026-04-21 19:46:11'),
(50, 8, 'mixed vegetables', '2026-04-21 19:46:11'),
(51, 8, 'sesame oil', '2026-04-21 19:46:11'),
(52, 8, 'rice', '2026-04-21 19:46:11'),
(53, 9, 'mixed greens', '2026-04-21 19:46:11'),
(54, 9, 'cashews', '2026-04-21 19:46:11'),
(55, 9, 'apple slices', '2026-04-21 19:46:11'),
(56, 9, 'cranberries', '2026-04-21 19:46:11'),
(57, 9, 'feta cheese', '2026-04-21 19:46:11'),
(58, 9, 'balsamic vinaigrette', '2026-04-21 19:46:11'),
(59, 10, 'alu potato', '2026-04-21 19:46:43'),
(60, 10, 'dim dumplings', '2026-04-21 19:46:43'),
(61, 10, 'onion', '2026-04-21 19:46:43'),
(62, 10, 'tomato', '2026-04-21 19:46:43'),
(63, 10, 'curry powder', '2026-04-21 19:46:43'),
(64, 10, 'oil', '2026-04-21 19:46:43'),
(65, 10, 'salt', '2026-04-21 19:46:43'),
(66, 11, 'alu potato', '2026-04-21 19:46:43'),
(67, 11, 'dim dumplings', '2026-04-21 19:46:43'),
(68, 11, 'soy sauce', '2026-04-21 19:46:43'),
(69, 11, 'garlic', '2026-04-21 19:46:43'),
(70, 11, 'green pepper', '2026-04-21 19:46:43'),
(71, 11, 'oil', '2026-04-21 19:46:43'),
(72, 12, 'alu potato', '2026-04-21 19:46:43'),
(73, 12, 'dim dumplings', '2026-04-21 19:46:43'),
(74, 12, 'vegetable broth', '2026-04-21 19:46:43'),
(75, 12, 'carrot', '2026-04-21 19:46:43'),
(76, 12, 'celery', '2026-04-21 19:46:43'),
(77, 12, 'salt', '2026-04-21 19:46:43'),
(78, 12, 'pepper', '2026-04-21 19:46:43'),
(79, 13, 'alu potato', '2026-04-21 19:46:43'),
(80, 13, 'dim dumplings', '2026-04-21 19:46:43'),
(81, 13, 'cheddar cheese', '2026-04-21 19:46:43'),
(82, 13, 'cream', '2026-04-21 19:46:43'),
(83, 13, 'butter', '2026-04-21 19:46:43'),
(84, 13, 'salt', '2026-04-21 19:46:43'),
(85, 13, 'pepper', '2026-04-21 19:46:43'),
(86, 14, 'shutki dried fish', '2026-04-21 19:47:59'),
(87, 14, 'basmati rice', '2026-04-21 19:47:59'),
(88, 14, 'onion', '2026-04-21 19:47:59'),
(89, 14, 'garlic', '2026-04-21 19:47:59'),
(90, 14, 'ginger', '2026-04-21 19:47:59'),
(91, 14, 'turmeric', '2026-04-21 19:47:59'),
(92, 14, 'cumin', '2026-04-21 19:47:59'),
(93, 14, 'green chilies', '2026-04-21 19:47:59'),
(94, 14, 'oil', '2026-04-21 19:47:59'),
(95, 14, 'salt', '2026-04-21 19:47:59'),
(96, 15, 'shutki', '2026-04-21 19:47:59'),
(97, 15, 'cooked rice', '2026-04-21 19:47:59'),
(98, 15, 'egg', '2026-04-21 19:47:59'),
(99, 15, 'carrot', '2026-04-21 19:47:59'),
(100, 15, 'peas', '2026-04-21 19:47:59'),
(101, 15, 'soy sauce', '2026-04-21 19:47:59'),
(102, 15, 'green onion', '2026-04-21 19:47:59'),
(103, 15, 'oil', '2026-04-21 19:47:59'),
(104, 15, 'salt', '2026-04-21 19:47:59'),
(105, 15, 'pepper', '2026-04-21 19:47:59'),
(106, 16, 'shutki', '2026-04-21 19:47:59'),
(107, 16, 'water', '2026-04-21 19:47:59'),
(108, 16, 'tomato', '2026-04-21 19:47:59'),
(109, 16, 'onion', '2026-04-21 19:47:59'),
(110, 16, 'garlic', '2026-04-21 19:47:59'),
(111, 16, 'bay leaf', '2026-04-21 19:47:59'),
(112, 16, 'polao cooked rice cubes', '2026-04-21 19:47:59'),
(113, 16, 'oil', '2026-04-21 19:47:59'),
(114, 16, 'salt', '2026-04-21 19:47:59'),
(115, 16, 'pepper', '2026-04-21 19:47:59'),
(116, 17, 'shutki', '2026-04-21 19:47:59'),
(117, 17, 'polao', '2026-04-21 19:47:59'),
(118, 17, 'egg', '2026-04-21 19:47:59'),
(119, 17, 'bread crumbs', '2026-04-21 19:47:59'),
(120, 17, 'green chili', '2026-04-21 19:47:59'),
(121, 17, 'coriander', '2026-04-21 19:47:59'),
(122, 17, 'oil', '2026-04-21 19:47:59'),
(123, 17, 'salt', '2026-04-21 19:47:59'),
(124, 18, 'green peas', '2026-04-21 19:55:20'),
(125, 18, 'cabbage', '2026-04-21 19:55:20'),
(126, 18, 'garlic', '2026-04-21 19:55:20'),
(127, 18, 'soy sauce', '2026-04-21 19:55:20'),
(128, 18, 'olive oil', '2026-04-21 19:55:20'),
(129, 19, 'green peas', '2026-04-21 19:55:21'),
(130, 19, 'cabbage', '2026-04-21 19:55:21'),
(131, 19, 'onion', '2026-04-21 19:55:21'),
(132, 19, 'vegetable broth', '2026-04-21 19:55:21'),
(133, 19, 'carrot', '2026-04-21 19:55:21'),
(134, 19, 'thyme', '2026-04-21 19:55:21'),
(135, 20, 'green peas', '2026-04-21 19:55:21'),
(136, 20, 'cabbage', '2026-04-21 19:55:21'),
(137, 20, 'lemon juice', '2026-04-21 19:55:21'),
(138, 20, 'olive oil', '2026-04-21 19:55:21'),
(139, 20, 'salt', '2026-04-21 19:55:21'),
(140, 20, 'pepper', '2026-04-21 19:55:21'),
(141, 21, 'green peas', '2026-04-21 19:55:21'),
(142, 21, 'cabbage', '2026-04-21 19:55:21'),
(143, 21, 'cooked rice', '2026-04-21 19:55:21'),
(144, 21, 'egg', '2026-04-21 19:55:21'),
(145, 21, 'soy sauce', '2026-04-21 19:55:21'),
(146, 21, 'green onion', '2026-04-21 19:55:21'),
(147, 22, 'calabash', '2026-04-21 20:00:02'),
(148, 22, 'luffa', '2026-04-21 20:00:02'),
(149, 22, 'garlic', '2026-04-21 20:00:02'),
(150, 22, 'chili flakes', '2026-04-21 20:00:02'),
(151, 22, 'soy sauce', '2026-04-21 20:00:02'),
(152, 22, 'sesame oil', '2026-04-21 20:00:02'),
(153, 22, 'green onions', '2026-04-21 20:00:02'),
(154, 23, 'calabash', '2026-04-21 20:00:02'),
(155, 23, 'luffa', '2026-04-21 20:00:02'),
(156, 23, 'vegetable broth', '2026-04-21 20:00:02'),
(157, 23, 'ginger', '2026-04-21 20:00:02'),
(158, 23, 'carrot', '2026-04-21 20:00:02'),
(159, 23, 'salt', '2026-04-21 20:00:02'),
(160, 23, 'pepper', '2026-04-21 20:00:02'),
(161, 24, 'luffa', '2026-04-21 20:00:02'),
(162, 24, 'calabash', '2026-04-21 20:00:02'),
(163, 24, 'olive oil', '2026-04-21 20:00:02'),
(164, 24, 'lemon juice', '2026-04-21 20:00:02'),
(165, 24, 'herbs de provence', '2026-04-21 20:00:02'),
(166, 24, 'salt', '2026-04-21 20:00:02'),
(167, 24, 'pepper', '2026-04-21 20:00:02'),
(168, 25, 'calabash', '2026-04-21 20:00:02'),
(169, 25, 'luffa', '2026-04-21 20:00:02'),
(170, 25, 'coconut milk', '2026-04-21 20:00:02'),
(171, 25, 'curry paste', '2026-04-21 20:00:02'),
(172, 25, 'onion', '2026-04-21 20:00:02'),
(173, 25, 'tomato', '2026-04-21 20:00:02'),
(174, 25, 'coriander', '2026-04-21 20:00:02'),
(175, 26, 'soybeans', '2026-04-22 06:33:16'),
(176, 26, 'bell pepper', '2026-04-22 06:33:16'),
(177, 26, 'carrot', '2026-04-22 06:33:16'),
(178, 26, 'onion', '2026-04-22 06:33:16'),
(179, 26, 'garlic', '2026-04-22 06:33:16'),
(180, 26, 'soy sauce', '2026-04-22 06:33:16'),
(181, 26, 'olive oil', '2026-04-22 06:33:16'),
(182, 27, 'soybeans', '2026-04-22 06:33:16'),
(183, 27, 'tahini', '2026-04-22 06:33:16'),
(184, 27, 'lemon juice', '2026-04-22 06:33:16'),
(185, 27, 'garlic', '2026-04-22 06:33:16'),
(186, 27, 'wholewheat tortilla', '2026-04-22 06:33:16'),
(187, 27, 'lettuce', '2026-04-22 06:33:16'),
(188, 27, 'tomato', '2026-04-22 06:33:16'),
(189, 28, 'soybeans', '2026-04-22 06:33:16'),
(190, 28, 'ground turkey or beef optional', '2026-04-22 06:33:16'),
(191, 28, 'tomato sauce', '2026-04-22 06:33:16'),
(192, 28, 'onion', '2026-04-22 06:33:16'),
(193, 28, 'bell pepper', '2026-04-22 06:33:16'),
(194, 28, 'chili powder', '2026-04-22 06:33:16'),
(195, 28, 'cumin', '2026-04-22 06:33:16'),
(196, 28, 'kidney beans', '2026-04-22 06:33:16'),
(197, 29, 'soybeans', '2026-04-22 06:33:16'),
(198, 29, 'cucumber', '2026-04-22 06:33:16'),
(199, 29, 'carrot', '2026-04-22 06:33:16'),
(200, 29, 'green onion', '2026-04-22 06:33:16'),
(201, 29, 'sesame oil', '2026-04-22 06:33:16'),
(202, 29, 'rice vinegar', '2026-04-22 06:33:16'),
(203, 29, 'soy sauce', '2026-04-22 06:33:16'),
(204, 29, 'sesame seeds', '2026-04-22 06:33:16'),
(205, 30, 'soybeans', '2026-04-22 06:33:39'),
(206, 30, 'onion', '2026-04-22 06:33:39'),
(207, 30, 'garlic', '2026-04-22 06:33:39'),
(208, 30, 'soy sauce', '2026-04-22 06:33:39'),
(209, 30, 'olive oil', '2026-04-22 06:33:39'),
(210, 31, 'soybeans', '2026-04-22 06:33:39'),
(211, 31, 'onion', '2026-04-22 06:33:39'),
(212, 31, 'vegetable broth', '2026-04-22 06:33:39'),
(213, 31, 'carrot', '2026-04-22 06:33:39'),
(214, 31, 'celery', '2026-04-22 06:33:39'),
(215, 31, 'pepper', '2026-04-22 06:33:39'),
(216, 32, 'soybeans', '2026-04-22 06:33:39'),
(217, 32, 'red onion', '2026-04-22 06:33:39'),
(218, 32, 'cucumber', '2026-04-22 06:33:39'),
(219, 32, 'lemon juice', '2026-04-22 06:33:39'),
(220, 32, 'olive oil', '2026-04-22 06:33:39'),
(221, 32, 'salt', '2026-04-22 06:33:39'),
(222, 33, 'mashed soybeans', '2026-04-22 06:33:39'),
(223, 33, 'finely chopped onion', '2026-04-22 06:33:39'),
(224, 33, 'egg', '2026-04-22 06:33:39'),
(225, 33, 'breadcrumbs', '2026-04-22 06:33:39'),
(226, 33, 'spices', '2026-04-22 06:33:39'),
(227, 33, 'oil for frying', '2026-04-22 06:33:39');

-- --------------------------------------------------------

--
-- Table structure for table `meal_suggestions`
--

CREATE TABLE `meal_suggestions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `meal_name` varchar(150) NOT NULL,
  `short_description` varchar(255) NOT NULL,
  `calories` int(11) DEFAULT NULL,
  `source` enum('db','ai') NOT NULL DEFAULT 'db',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_suggestions`
--

INSERT INTO `meal_suggestions` (`id`, `meal_name`, `short_description`, `calories`, `source`, `created_at`) VALUES
(1, 'Potato Lentil Curry', 'Comforting curry with lentils and potatoes.', 320, 'db', '2026-04-21 19:40:34'),
(2, 'Egg Fried Rice', 'Quick stir-fry rice with egg.', 410, 'db', '2026-04-21 19:40:34'),
(3, 'Vegetable Omelette', 'Protein-rich omelette with vegetables.', 230, 'db', '2026-04-21 19:40:34'),
(4, 'Dal Soup', 'Light and nourishing lentil soup.', 240, 'db', '2026-04-21 19:40:34'),
(5, 'Chicken Stir Fry', 'Chicken with veggies and light sauce.', 360, 'db', '2026-04-21 19:40:34'),
(6, 'Almond-Crusted Chicken', 'Chicken breast coated in almond flour and baked until golden, served with a side of steamed vegetables.', 420, 'ai', '2026-04-21 19:46:11'),
(7, 'Walnut Pesto Pasta', 'A quick pasta tossed with a homemade walnut pesto for a nutty, flavorful sauce.', 530, 'ai', '2026-04-21 19:46:11'),
(8, 'Peanut Stir‑Fry', 'Crispy tofu and veggies stir‑fried in a savory peanut sauce, served over steamed rice.', 480, 'ai', '2026-04-21 19:46:11'),
(9, 'Cashew‑Apple Salad', 'A fresh salad combining sweet apple, crunchy cashews, and tangy vinaigrette for a light meal.', 310, 'ai', '2026-04-21 19:46:11'),
(10, 'Alu Dim Curry', 'A quick curry where diced potatoes and dumplings simmer together in a spiced tomato base.', 420, 'ai', '2026-04-21 19:46:43'),
(11, 'Fried Alu Dim Stir‑Fry', 'Crispy potato slices and dumplings tossed with garlic and pepper in a savory soy glaze.', 380, 'ai', '2026-04-21 19:46:43'),
(12, 'Alu Dim Soup', 'A comforting broth filled with tender potato cubes and soft dumplings, perfect for a light meal.', 310, 'ai', '2026-04-21 19:46:43'),
(13, 'Baked Alu Dim Casserole', 'Layered potatoes and dumplings baked with creamy cheese sauce until golden and bubbly.', 470, 'ai', '2026-04-21 19:46:43'),
(14, 'Shutki Polao', 'A fragrant rice pilaf cooked with shredded shutki, aromatic spices, and a hint of heat.', 420, 'ai', '2026-04-21 19:47:59'),
(15, 'Shutki Fried Rice', 'Quick stir‑fried rice with flaked shutki, vegetables, and a light soy‑sauce glaze.', 380, 'ai', '2026-04-21 19:47:59'),
(16, 'Shutki Soup with Polao Croutons', 'A comforting fish broth flavored with shutki, served with crispy fried polao croutons.', 310, 'ai', '2026-04-21 19:47:59'),
(17, 'Shutki Rice Patties', 'Mashed shutki mixed with leftover polao, formed into patties and shallow‑fried until golden.', 350, 'ai', '2026-04-21 19:47:59'),
(18, 'Green Pea and Cabbage Stir‑Fry', 'Quick stir‑fry of peas and shredded cabbage with garlic and a splash of soy sauce, served over rice or noodles.', 210, 'ai', '2026-04-21 19:55:20'),
(19, 'Pea‑Cabbage Soup', 'A light, comforting soup simmered with peas, cabbage, and aromatic vegetables, blended for a smooth texture.', 150, 'ai', '2026-04-21 19:55:20'),
(20, 'Cabbage‑Pea Salad with Lemon Vinaigrette', 'Fresh shredded cabbage tossed with peas and a bright lemon‑olive oil dressing, perfect as a side or light main.', 120, 'ai', '2026-04-21 19:55:21'),
(21, 'Pea and Cabbage Fried Rice', 'Classic fried rice enriched with peas, crisp cabbage, and a scrambled egg, seasoned with soy sauce.', 280, 'ai', '2026-04-21 19:55:21'),
(22, 'Spicy Calabash and Luffa Stir‑Fry', 'Thinly sliced calabash and luffa quickly stir‑fried with garlic and chili, finished with soy sauce and a drizzle of sesame oil.', 210, 'ai', '2026-04-21 20:00:02'),
(23, 'Calabash‑Luffa Soup', 'A comforting broth simmered with diced calabash, luffa, and carrots, flavored with ginger and seasoned to taste.', 150, 'ai', '2026-04-21 20:00:02'),
(24, 'Grilled Luffa & Calabash Skewers', 'Chunks of luffa and calabash brushed with olive oil, lemon, and herbs, then grilled until lightly charred.', 180, 'ai', '2026-04-21 20:00:02'),
(25, 'Calabash‑Luffa Curry', 'A creamy coconut‑curry stew with tender pieces of calabash and luffa, served over rice or eaten alone.', 320, 'ai', '2026-04-21 20:00:02'),
(26, 'Soybean Stir‑Fry with Veggies', 'Quick stir‑fry of boiled soybeans with colorful vegetables, seasoned with garlic and soy sauce. Serve over rice or noodles.', 420, 'ai', '2026-04-22 06:33:16'),
(27, 'Soybean Hummus Wrap', 'Blend cooked soybeans with tahini, lemon, and garlic to make a creamy hummus. Spread on a tortilla, add fresh lettuce and tomato, and roll up.', 350, 'ai', '2026-04-22 06:33:16'),
(28, 'Soybean Chili', 'Hearty chili featuring soybeans as the protein base, simmered with tomatoes, spices, and optional meat for extra richness.', 480, 'ai', '2026-04-22 06:33:16'),
(29, 'Soybean Salad with Sesame Dressing', 'A refreshing cold salad of soybeans, crisp cucumber, and shredded carrot tossed in a tangy sesame‑vinegar dressing.', 300, 'ai', '2026-04-22 06:33:16'),
(30, 'Soybean and Onion Stir‑Fry', 'Quick stir‑fry of boiled soybeans with sliced onion and garlic, seasoned with soy sauce.', 320, 'ai', '2026-04-22 06:33:39'),
(31, 'Soybean Onion Soup', 'Hearty soup with simmered soybeans and caramelized onion in a light vegetable broth.', 250, 'ai', '2026-04-22 06:33:39'),
(32, 'Soybean Onion Salad', 'Refreshing cold salad of soybeans tossed with thinly sliced red onion and a lemon‑olive oil dressing.', 210, 'ai', '2026-04-22 06:33:39'),
(33, 'Soybean Onion Patties', 'Crispy patties made from mashed soybeans and onion, pan‑fried until golden.', 380, 'ai', '2026-04-22 06:33:39');

-- --------------------------------------------------------

--
-- Table structure for table `meal_templates`
--

CREATE TABLE `meal_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dietitian_id` bigint(20) UNSIGNED NOT NULL,
  `template_name` varchar(150) NOT NULL,
  `meal_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`meal_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meal_templates`
--

INSERT INTO `meal_templates` (`id`, `dietitian_id`, `template_name`, `meal_data`, `created_at`) VALUES
(1, 1, 'Low-Calorie Day Template', '{\"breakfast\":\"Oats & Berries\",\"lunch\":\"Grilled Chicken Salad\",\"dinner\":\"Fish with Veggies\",\"snack\":\"Greek Yogurt\"}', '2026-04-01 12:00:00'),
(2, 2, 'High-Protein Gain Template', '{\"breakfast\":\"Peanut Butter Oats\",\"lunch\":\"Chicken Rice Plate\",\"dinner\":\"Beef Pasta\",\"snack\":\"Protein Shake\"}', '2026-04-01 12:10:00'),
(3, 3, 'Weight Loss - High Protein Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Greek Yogurt Berry Bowl\",\"description\":\"Greek yogurt, berries, chia seeds\",\"calories\":320,\"protein\":24,\"carbs\":30,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Grilled Chicken Salad\",\"description\":\"Chicken breast with mixed greens and olive oil\",\"calories\":450,\"protein\":42,\"carbs\":18,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Baked Fish & Veggies\",\"description\":\"White fish, broccoli, carrots\",\"calories\":430,\"protein\":38,\"carbs\":24,\"fat\":18},{\"meal_type\":\"snack\",\"meal_name\":\"Boiled Eggs + Cucumber\",\"description\":\"2 eggs with cucumber sticks\",\"calories\":180,\"protein\":13,\"carbs\":4,\"fat\":11}]}', '2026-04-20 19:59:24'),
(4, 3, 'Weight Loss - Low Carb Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Veggie Omelette\",\"description\":\"Eggs, spinach, mushroom, tomato\",\"calories\":300,\"protein\":22,\"carbs\":9,\"fat\":19},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Lettuce Wraps\",\"description\":\"Minced chicken and veggies in lettuce leaves\",\"calories\":410,\"protein\":35,\"carbs\":14,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Paneer & Stir Fry Veg\",\"description\":\"Low oil paneer stir fry with vegetables\",\"calories\":460,\"protein\":28,\"carbs\":16,\"fat\":30},{\"meal_type\":\"snack\",\"meal_name\":\"Mixed Nuts Portion\",\"description\":\"Almonds and walnuts\",\"calories\":170,\"protein\":6,\"carbs\":5,\"fat\":14}]}', '2026-04-20 19:59:24'),
(5, 3, 'Maintain - Balanced Classic Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Oats With Banana\",\"description\":\"Rolled oats, milk, banana slices\",\"calories\":360,\"protein\":14,\"carbs\":57,\"fat\":8},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice, Dal, Chicken Curry\",\"description\":\"Balanced home-style meal\",\"calories\":620,\"protein\":36,\"carbs\":68,\"fat\":20},{\"meal_type\":\"dinner\",\"meal_name\":\"Roti, Mixed Veg, Fish\",\"description\":\"2 roti, fish and vegetables\",\"calories\":560,\"protein\":35,\"carbs\":52,\"fat\":22},{\"meal_type\":\"snack\",\"meal_name\":\"Fruit + Peanut Butter\",\"description\":\"Apple with peanut butter\",\"calories\":220,\"protein\":6,\"carbs\":24,\"fat\":11}]}', '2026-04-20 19:59:24'),
(6, 3, 'Maintain - Vegetarian Balanced Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Poha With Peanuts\",\"description\":\"Flattened rice with vegetables\",\"calories\":340,\"protein\":9,\"carbs\":52,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Brown Rice + Rajma\",\"description\":\"Kidney beans and brown rice\",\"calories\":580,\"protein\":20,\"carbs\":88,\"fat\":12},{\"meal_type\":\"dinner\",\"meal_name\":\"Roti + Paneer Bhurji\",\"description\":\"Whole wheat roti with paneer\",\"calories\":550,\"protein\":26,\"carbs\":50,\"fat\":26},{\"meal_type\":\"snack\",\"meal_name\":\"Yogurt + Seeds\",\"description\":\"Curd with pumpkin seeds\",\"calories\":190,\"protein\":10,\"carbs\":11,\"fat\":11}]}', '2026-04-20 19:59:24'),
(7, 3, 'Lean Gain - Muscle Builder Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Egg & Toast Power Plate\",\"description\":\"Eggs, whole grain toast, avocado\",\"calories\":520,\"protein\":29,\"carbs\":36,\"fat\":28},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Rice Bowl\",\"description\":\"Grilled chicken, rice, beans\",\"calories\":760,\"protein\":48,\"carbs\":82,\"fat\":25},{\"meal_type\":\"dinner\",\"meal_name\":\"Beef\\/Paneer + Potato\",\"description\":\"High energy lean-gain dinner\",\"calories\":740,\"protein\":43,\"carbs\":66,\"fat\":30},{\"meal_type\":\"snack\",\"meal_name\":\"Protein Shake + Banana\",\"description\":\"Post-workout snack\",\"calories\":310,\"protein\":26,\"carbs\":33,\"fat\":8}]}', '2026-04-20 19:59:24'),
(8, 3, 'Lean Gain - Vegetarian Bulk Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Paneer Stuffed Paratha\",\"description\":\"Paratha with curd\",\"calories\":560,\"protein\":23,\"carbs\":52,\"fat\":28},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice + Chole + Salad\",\"description\":\"Chickpea curry meal\",\"calories\":760,\"protein\":26,\"carbs\":104,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Soya Chunk Stir Fry + Roti\",\"description\":\"Protein-focused vegetarian dinner\",\"calories\":700,\"protein\":44,\"carbs\":68,\"fat\":24},{\"meal_type\":\"snack\",\"meal_name\":\"Milk Smoothie + Nuts\",\"description\":\"Calorie-dense shake\",\"calories\":360,\"protein\":15,\"carbs\":32,\"fat\":19}]}', '2026-04-20 19:59:24'),
(9, 3, 'Diabetes Friendly - Moderate Carb Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Besan Chilla + Yogurt\",\"description\":\"Low GI breakfast\",\"calories\":320,\"protein\":16,\"carbs\":30,\"fat\":13},{\"meal_type\":\"lunch\",\"meal_name\":\"Multigrain Roti + Dal + Veg\",\"description\":\"Fiber-rich plate\",\"calories\":520,\"protein\":22,\"carbs\":56,\"fat\":20},{\"meal_type\":\"dinner\",\"meal_name\":\"Grilled Fish + Saute Veg\",\"description\":\"Protein and non-starchy vegetables\",\"calories\":460,\"protein\":36,\"carbs\":20,\"fat\":24},{\"meal_type\":\"snack\",\"meal_name\":\"Roasted Chana\",\"description\":\"Low sugar crunchy snack\",\"calories\":170,\"protein\":9,\"carbs\":18,\"fat\":6}]}', '2026-04-20 19:59:24'),
(10, 3, 'PCOS Support - Anti Inflammatory Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Chia Oats Pudding\",\"description\":\"Oats, chia, berries, almond milk\",\"calories\":340,\"protein\":12,\"carbs\":39,\"fat\":15},{\"meal_type\":\"lunch\",\"meal_name\":\"Quinoa Chickpea Bowl\",\"description\":\"Quinoa, chickpeas, leafy greens\",\"calories\":540,\"protein\":21,\"carbs\":62,\"fat\":21},{\"meal_type\":\"dinner\",\"meal_name\":\"Salmon\\/Tofu + Veg\",\"description\":\"Omega-3 rich dinner\",\"calories\":500,\"protein\":33,\"carbs\":26,\"fat\":27},{\"meal_type\":\"snack\",\"meal_name\":\"Pumpkin Seeds + Fruit\",\"description\":\"Micronutrient-rich snack\",\"calories\":200,\"protein\":8,\"carbs\":17,\"fat\":11}]}', '2026-04-20 19:59:24'),
(11, 3, 'Heart Healthy - Low Sodium Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Fruit Yogurt Parfait\",\"description\":\"Low-fat yogurt and fruits\",\"calories\":310,\"protein\":15,\"carbs\":41,\"fat\":9},{\"meal_type\":\"lunch\",\"meal_name\":\"Lentil Soup + Whole Bread\",\"description\":\"Low sodium lentil soup meal\",\"calories\":500,\"protein\":22,\"carbs\":65,\"fat\":14},{\"meal_type\":\"dinner\",\"meal_name\":\"Baked Chicken + Sweet Potato\",\"description\":\"Heart-friendly complete plate\",\"calories\":560,\"protein\":38,\"carbs\":48,\"fat\":21},{\"meal_type\":\"snack\",\"meal_name\":\"Unsalted Nuts\",\"description\":\"Small handful nuts\",\"calories\":180,\"protein\":5,\"carbs\":6,\"fat\":15}]}', '2026-04-20 19:59:24'),
(12, 3, 'Quick Office Day - Easy Prep', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Overnight Oats Jar\",\"description\":\"Prep in 5 minutes\",\"calories\":350,\"protein\":14,\"carbs\":50,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Wrap + Salad\",\"description\":\"Portable lunch option\",\"calories\":560,\"protein\":34,\"carbs\":51,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"One Pan Rice & Veg\",\"description\":\"Simple weekday dinner\",\"calories\":590,\"protein\":22,\"carbs\":77,\"fat\":19},{\"meal_type\":\"snack\",\"meal_name\":\"Protein Bar\",\"description\":\"Grab-and-go option\",\"calories\":210,\"protein\":16,\"carbs\":18,\"fat\":8}]}', '2026-04-20 19:59:24'),
(13, 3, 'Budget Friendly Family Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Vegetable Upma\",\"description\":\"Affordable wholesome breakfast\",\"calories\":330,\"protein\":9,\"carbs\":49,\"fat\":11},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice, Egg Curry, Veg\",\"description\":\"Cost-effective balanced meal\",\"calories\":610,\"protein\":23,\"carbs\":72,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Khichdi + Curd\",\"description\":\"Comfort and nutrition\",\"calories\":520,\"protein\":19,\"carbs\":69,\"fat\":16},{\"meal_type\":\"snack\",\"meal_name\":\"Seasonal Fruit\",\"description\":\"Simple fruit snack\",\"calories\":120,\"protein\":2,\"carbs\":29,\"fat\":0}]}', '2026-04-20 19:59:24'),
(14, 3, 'High Fiber Gut Friendly Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Bran Cereal + Milk\",\"description\":\"Fiber-rich start\",\"calories\":320,\"protein\":13,\"carbs\":47,\"fat\":8},{\"meal_type\":\"lunch\",\"meal_name\":\"Barley Veg Bowl\",\"description\":\"Barley, beans and veggies\",\"calories\":540,\"protein\":21,\"carbs\":76,\"fat\":14},{\"meal_type\":\"dinner\",\"meal_name\":\"Lentil Pasta + Veg Sauce\",\"description\":\"High fiber dinner option\",\"calories\":600,\"protein\":29,\"carbs\":78,\"fat\":18},{\"meal_type\":\"snack\",\"meal_name\":\"Pear + Flax Seeds\",\"description\":\"Digestive support snack\",\"calories\":160,\"protein\":3,\"carbs\":27,\"fat\":4}]}', '2026-04-20 19:59:24'),
(15, 1, 'Weight Loss - High Protein Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Greek Yogurt Berry Bowl\",\"description\":\"Greek yogurt, berries, chia seeds\",\"calories\":320,\"protein\":24,\"carbs\":30,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Grilled Chicken Salad\",\"description\":\"Chicken breast with mixed greens and olive oil\",\"calories\":450,\"protein\":42,\"carbs\":18,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Baked Fish & Veggies\",\"description\":\"White fish, broccoli, carrots\",\"calories\":430,\"protein\":38,\"carbs\":24,\"fat\":18},{\"meal_type\":\"snack\",\"meal_name\":\"Boiled Eggs + Cucumber\",\"description\":\"2 eggs with cucumber sticks\",\"calories\":180,\"protein\":13,\"carbs\":4,\"fat\":11}]}', '2026-04-20 20:16:55'),
(16, 1, 'Weight Loss - Low Carb Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Veggie Omelette\",\"description\":\"Eggs, spinach, mushroom, tomato\",\"calories\":300,\"protein\":22,\"carbs\":9,\"fat\":19},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Lettuce Wraps\",\"description\":\"Minced chicken and veggies in lettuce leaves\",\"calories\":410,\"protein\":35,\"carbs\":14,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Paneer & Stir Fry Veg\",\"description\":\"Low oil paneer stir fry with vegetables\",\"calories\":460,\"protein\":28,\"carbs\":16,\"fat\":30},{\"meal_type\":\"snack\",\"meal_name\":\"Mixed Nuts Portion\",\"description\":\"Almonds and walnuts\",\"calories\":170,\"protein\":6,\"carbs\":5,\"fat\":14}]}', '2026-04-20 20:16:55'),
(17, 1, 'Maintain - Balanced Classic Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Oats With Banana\",\"description\":\"Rolled oats, milk, banana slices\",\"calories\":360,\"protein\":14,\"carbs\":57,\"fat\":8},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice, Dal, Chicken Curry\",\"description\":\"Balanced home-style meal\",\"calories\":620,\"protein\":36,\"carbs\":68,\"fat\":20},{\"meal_type\":\"dinner\",\"meal_name\":\"Roti, Mixed Veg, Fish\",\"description\":\"2 roti, fish and vegetables\",\"calories\":560,\"protein\":35,\"carbs\":52,\"fat\":22},{\"meal_type\":\"snack\",\"meal_name\":\"Fruit + Peanut Butter\",\"description\":\"Apple with peanut butter\",\"calories\":220,\"protein\":6,\"carbs\":24,\"fat\":11}]}', '2026-04-20 20:16:55'),
(18, 1, 'Maintain - Vegetarian Balanced Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Poha With Peanuts\",\"description\":\"Flattened rice with vegetables\",\"calories\":340,\"protein\":9,\"carbs\":52,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Brown Rice + Rajma\",\"description\":\"Kidney beans and brown rice\",\"calories\":580,\"protein\":20,\"carbs\":88,\"fat\":12},{\"meal_type\":\"dinner\",\"meal_name\":\"Roti + Paneer Bhurji\",\"description\":\"Whole wheat roti with paneer\",\"calories\":550,\"protein\":26,\"carbs\":50,\"fat\":26},{\"meal_type\":\"snack\",\"meal_name\":\"Yogurt + Seeds\",\"description\":\"Curd with pumpkin seeds\",\"calories\":190,\"protein\":10,\"carbs\":11,\"fat\":11}]}', '2026-04-20 20:16:55'),
(19, 1, 'Lean Gain - Muscle Builder Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Egg & Toast Power Plate\",\"description\":\"Eggs, whole grain toast, avocado\",\"calories\":520,\"protein\":29,\"carbs\":36,\"fat\":28},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Rice Bowl\",\"description\":\"Grilled chicken, rice, beans\",\"calories\":760,\"protein\":48,\"carbs\":82,\"fat\":25},{\"meal_type\":\"dinner\",\"meal_name\":\"Beef\\/Paneer + Potato\",\"description\":\"High energy lean-gain dinner\",\"calories\":740,\"protein\":43,\"carbs\":66,\"fat\":30},{\"meal_type\":\"snack\",\"meal_name\":\"Protein Shake + Banana\",\"description\":\"Post-workout snack\",\"calories\":310,\"protein\":26,\"carbs\":33,\"fat\":8}]}', '2026-04-20 20:16:55'),
(20, 1, 'Lean Gain - Vegetarian Bulk Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Paneer Stuffed Paratha\",\"description\":\"Paratha with curd\",\"calories\":560,\"protein\":23,\"carbs\":52,\"fat\":28},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice + Chole + Salad\",\"description\":\"Chickpea curry meal\",\"calories\":760,\"protein\":26,\"carbs\":104,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Soya Chunk Stir Fry + Roti\",\"description\":\"Protein-focused vegetarian dinner\",\"calories\":700,\"protein\":44,\"carbs\":68,\"fat\":24},{\"meal_type\":\"snack\",\"meal_name\":\"Milk Smoothie + Nuts\",\"description\":\"Calorie-dense shake\",\"calories\":360,\"protein\":15,\"carbs\":32,\"fat\":19}]}', '2026-04-20 20:16:55'),
(21, 1, 'Diabetes Friendly - Moderate Carb Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Besan Chilla + Yogurt\",\"description\":\"Low GI breakfast\",\"calories\":320,\"protein\":16,\"carbs\":30,\"fat\":13},{\"meal_type\":\"lunch\",\"meal_name\":\"Multigrain Roti + Dal + Veg\",\"description\":\"Fiber-rich plate\",\"calories\":520,\"protein\":22,\"carbs\":56,\"fat\":20},{\"meal_type\":\"dinner\",\"meal_name\":\"Grilled Fish + Saute Veg\",\"description\":\"Protein and non-starchy vegetables\",\"calories\":460,\"protein\":36,\"carbs\":20,\"fat\":24},{\"meal_type\":\"snack\",\"meal_name\":\"Roasted Chana\",\"description\":\"Low sugar crunchy snack\",\"calories\":170,\"protein\":9,\"carbs\":18,\"fat\":6}]}', '2026-04-20 20:16:55'),
(22, 1, 'PCOS Support - Anti Inflammatory Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Chia Oats Pudding\",\"description\":\"Oats, chia, berries, almond milk\",\"calories\":340,\"protein\":12,\"carbs\":39,\"fat\":15},{\"meal_type\":\"lunch\",\"meal_name\":\"Quinoa Chickpea Bowl\",\"description\":\"Quinoa, chickpeas, leafy greens\",\"calories\":540,\"protein\":21,\"carbs\":62,\"fat\":21},{\"meal_type\":\"dinner\",\"meal_name\":\"Salmon\\/Tofu + Veg\",\"description\":\"Omega-3 rich dinner\",\"calories\":500,\"protein\":33,\"carbs\":26,\"fat\":27},{\"meal_type\":\"snack\",\"meal_name\":\"Pumpkin Seeds + Fruit\",\"description\":\"Micronutrient-rich snack\",\"calories\":200,\"protein\":8,\"carbs\":17,\"fat\":11}]}', '2026-04-20 20:16:55'),
(23, 1, 'Heart Healthy - Low Sodium Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Fruit Yogurt Parfait\",\"description\":\"Low-fat yogurt and fruits\",\"calories\":310,\"protein\":15,\"carbs\":41,\"fat\":9},{\"meal_type\":\"lunch\",\"meal_name\":\"Lentil Soup + Whole Bread\",\"description\":\"Low sodium lentil soup meal\",\"calories\":500,\"protein\":22,\"carbs\":65,\"fat\":14},{\"meal_type\":\"dinner\",\"meal_name\":\"Baked Chicken + Sweet Potato\",\"description\":\"Heart-friendly complete plate\",\"calories\":560,\"protein\":38,\"carbs\":48,\"fat\":21},{\"meal_type\":\"snack\",\"meal_name\":\"Unsalted Nuts\",\"description\":\"Small handful nuts\",\"calories\":180,\"protein\":5,\"carbs\":6,\"fat\":15}]}', '2026-04-20 20:16:55'),
(24, 1, 'Quick Office Day - Easy Prep', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Overnight Oats Jar\",\"description\":\"Prep in 5 minutes\",\"calories\":350,\"protein\":14,\"carbs\":50,\"fat\":10},{\"meal_type\":\"lunch\",\"meal_name\":\"Chicken Wrap + Salad\",\"description\":\"Portable lunch option\",\"calories\":560,\"protein\":34,\"carbs\":51,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"One Pan Rice & Veg\",\"description\":\"Simple weekday dinner\",\"calories\":590,\"protein\":22,\"carbs\":77,\"fat\":19},{\"meal_type\":\"snack\",\"meal_name\":\"Protein Bar\",\"description\":\"Grab-and-go option\",\"calories\":210,\"protein\":16,\"carbs\":18,\"fat\":8}]}', '2026-04-20 20:16:55'),
(25, 1, 'Budget Friendly Family Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Vegetable Upma\",\"description\":\"Affordable wholesome breakfast\",\"calories\":330,\"protein\":9,\"carbs\":49,\"fat\":11},{\"meal_type\":\"lunch\",\"meal_name\":\"Rice, Egg Curry, Veg\",\"description\":\"Cost-effective balanced meal\",\"calories\":610,\"protein\":23,\"carbs\":72,\"fat\":22},{\"meal_type\":\"dinner\",\"meal_name\":\"Khichdi + Curd\",\"description\":\"Comfort and nutrition\",\"calories\":520,\"protein\":19,\"carbs\":69,\"fat\":16},{\"meal_type\":\"snack\",\"meal_name\":\"Seasonal Fruit\",\"description\":\"Simple fruit snack\",\"calories\":120,\"protein\":2,\"carbs\":29,\"fat\":0}]}', '2026-04-20 20:16:55'),
(26, 1, 'High Fiber Gut Friendly Day', '{\"1\":[{\"meal_type\":\"breakfast\",\"meal_name\":\"Bran Cereal + Milk\",\"description\":\"Fiber-rich start\",\"calories\":320,\"protein\":13,\"carbs\":47,\"fat\":8},{\"meal_type\":\"lunch\",\"meal_name\":\"Barley Veg Bowl\",\"description\":\"Barley, beans and veggies\",\"calories\":540,\"protein\":21,\"carbs\":76,\"fat\":14},{\"meal_type\":\"dinner\",\"meal_name\":\"Lentil Pasta + Veg Sauce\",\"description\":\"High fiber dinner option\",\"calories\":600,\"protein\":29,\"carbs\":78,\"fat\":18},{\"meal_type\":\"snack\",\"meal_name\":\"Pear + Flax Seeds\",\"description\":\"Digestive support snack\",\"calories\":160,\"protein\":3,\"carbs\":27,\"fat\":4}]}', '2026-04-20 20:16:55');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `sender_type` enum('user','dietitian') NOT NULL,
  `receiver_id` bigint(20) UNSIGNED NOT NULL,
  `receiver_type` enum('user','dietitian') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `sender_type`, `receiver_id`, `receiver_type`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'user', 1, 'dietitian', 'I am feeling hungry at night. Any snack suggestion?', 1, '2026-04-14 20:00:00'),
(2, 1, 'dietitian', 1, 'user', 'Try Greek yogurt with chia or cucumber with hummus.', 1, '2026-04-14 20:10:00'),
(3, 2, 'user', 2, 'dietitian', 'Can I replace beef pasta with chicken rice?', 0, '2026-04-15 18:30:00'),
(4, 2, 'dietitian', 2, 'user', 'Yes, keep calories and protein equivalent.', 0, '2026-04-15 18:40:00'),
(5, 3, 'user', 1, 'dietitian', 'When does my maintenance plan start?', 1, '2026-04-15 09:00:00'),
(6, 2, 'user', 2, 'dietitian', 'hi', 0, '2026-04-21 18:35:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `age` tinyint(3) UNSIGNED NOT NULL,
  `weight` decimal(6,2) NOT NULL,
  `height` decimal(6,2) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `activity_level` enum('sedentary','lightly_active','moderately_active','very_active','extra_active') NOT NULL,
  `goal` enum('weight_loss','gain','maintain') NOT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `daily_calorie_goal` smallint(5) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `profile_pic`, `age`, `weight`, `height`, `gender`, `activity_level`, `goal`, `bmi`, `daily_calorie_goal`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ayesha Rahman', 'ayesha@example.com', 'user123', '+8801711111111', 'uploads/users/ayesha.jpg', 29, 78.00, 165.00, 'female', 'lightly_active', 'weight_loss', 28.65, 1650, 'active', '2026-04-01 07:30:00', '2026-04-15 07:30:00'),
(2, 'Test User', 'test@gmail.com', 'user123', '+8801722222222', 'users/img_69e64936a3d6c2.53608776.jpg', 25, 62.00, 175.00, 'male', 'moderately_active', 'gain', 20.24, 2600, 'active', '2026-04-02 10:00:00', '2026-04-20 18:35:10'),
(3, 'Nusrat Jahan', 'nusrat@example.com', 'user123', '+8801733333333', 'uploads/users/nusrat.jpg', 34, 68.00, 168.00, 'female', 'sedentary', 'maintain', 24.09, 1950, 'active', '2026-04-03 11:00:00', '2026-04-15 11:00:00'),
(4, 'Anjon Biswas', 'abanjon123@gmail.com', '$2y$10$StaFtfPnsRh.VYjTYssAIeYEaiZ770h24Mad/f.D4T8oV8hvibt5q', '01635607817', 'users/img_69e6644b5163e7.78158844.png', 24, 72.00, 172.00, 'male', 'lightly_active', 'maintain', 24.34, 2395, 'active', '2026-04-20 17:37:15', '2026-04-20 17:37:15'),
(5, 'abc', 'abc@gmail.com', '$2y$10$0VS5lGZ87eeAdqjCz.dnJOIdAdi.8Xr2gNctaNLHUFLj0brDPdLT2', '01635607817', NULL, 33, 67.00, 170.00, 'male', 'lightly_active', 'maintain', 23.18, 2220, 'active', '2026-04-21 18:44:51', '2026-04-21 18:44:51');

-- --------------------------------------------------------

--
-- Table structure for table `user_diet_plans`
--

CREATE TABLE `user_diet_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `diet_plan_id` bigint(20) UNSIGNED NOT NULL,
  `dietitian_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','pending') NOT NULL DEFAULT 'pending',
  `dietitian_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_diet_plans`
--

INSERT INTO `user_diet_plans` (`id`, `user_id`, `diet_plan_id`, `dietitian_id`, `assigned_date`, `end_date`, `status`, `dietitian_notes`) VALUES
(1, 1, 1, 1, '2026-04-01', '2026-05-01', 'active', 'Focus on hydration and nightly sleep > 7 hours.'),
(2, 2, 2, 2, '2026-04-02', '2026-05-17', 'active', 'Track protein intake daily and progressive overload in gym.'),
(3, 3, 3, 1, '2026-04-03', '2026-05-03', 'pending', 'Start with day-1 meals for first 3 days, then continue.'),
(4, 4, 3, 1, '2026-04-21', '2026-05-21', 'active', 'Plan assigned by dietitian dashboard'),
(5, 4, 4, 1, '2026-04-20', '2026-04-21', 'active', 'Assigned directly from template \"Weight Loss - High Protein Day\"'),
(6, 4, 5, 1, '2026-04-20', '2026-04-21', 'active', 'Assigned directly from template \"Weight Loss - Low Carb Day\"'),
(7, 4, 6, 1, '2026-04-20', '2026-04-21', 'active', 'Assigned directly from template \"High Fiber Gut Friendly Day\"');

-- --------------------------------------------------------

--
-- Table structure for table `user_favorite_meals`
--

CREATE TABLE `user_favorite_meals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `meal_name` varchar(160) NOT NULL,
  `calories` smallint(5) UNSIGNED NOT NULL,
  `protein` decimal(6,2) DEFAULT 0.00,
  `carbs` decimal(6,2) DEFAULT 0.00,
  `fat` decimal(6,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_favorite_meals`
--

INSERT INTO `user_favorite_meals` (`id`, `user_id`, `meal_name`, `calories`, `protein`, `carbs`, `fat`, `created_at`) VALUES
(1, 1, 'Greek Yogurt', 180, 14.00, 12.00, 7.00, '2026-04-10 19:00:00'),
(2, 2, 'Chicken Rice Plate', 760, 45.00, 85.00, 24.00, '2026-04-10 19:10:00'),
(3, 3, 'Veg Omelette', 420, 24.00, 28.00, 20.00, '2026-04-10 19:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `water_log`
--

CREATE TABLE `water_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount_ml` int(10) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `water_log`
--

INSERT INTO `water_log` (`id`, `user_id`, `amount_ml`, `log_date`, `created_at`) VALUES
(1, 1, 2500, '2026-04-15', '2026-04-15 21:00:00'),
(2, 2, 3200, '2026-04-15', '2026-04-15 21:05:00'),
(3, 3, 2200, '2026-04-15', '2026-04-15 21:10:00'),
(4, 2, 100, '2026-04-20', '2026-04-20 15:40:47'),
(5, 2, 500, '2026-04-20', '2026-04-20 15:40:49'),
(6, 2, 250, '2026-04-20', '2026-04-20 15:40:51'),
(7, 2, 250, '2026-04-20', '2026-04-20 15:40:51'),
(8, 2, 250, '2026-04-20', '2026-04-20 15:40:51'),
(9, 2, 250, '2026-04-20', '2026-04-20 15:40:51'),
(12, 2, 250, '2026-04-22', '2026-04-21 18:31:46'),
(13, 5, 200, '2026-04-22', '2026-04-22 06:30:36');

-- --------------------------------------------------------

--
-- Table structure for table `weight_log`
--

CREATE TABLE `weight_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `weight` decimal(6,2) NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `weight_log`
--

INSERT INTO `weight_log` (`id`, `user_id`, `weight`, `log_date`, `created_at`) VALUES
(1, 1, 79.20, '2026-04-01', '2026-04-01 07:40:00'),
(2, 1, 78.60, '2026-04-08', '2026-04-08 07:40:00'),
(3, 1, 78.00, '2026-04-15', '2026-04-15 07:40:00'),
(4, 2, 61.40, '2026-04-02', '2026-04-02 10:10:00'),
(5, 2, 61.80, '2026-04-09', '2026-04-09 10:10:00'),
(6, 2, 62.00, '2026-04-15', '2026-04-15 10:10:00'),
(7, 3, 68.20, '2026-04-03', '2026-04-03 11:10:00'),
(8, 3, 68.00, '2026-04-10', '2026-04-10 11:10:00'),
(9, 3, 68.00, '2026-04-15', '2026-04-15 11:10:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_actor` (`user_type`,`user_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `dietitians`
--
ALTER TABLE `dietitians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_dietitians_status` (`status`);

--
-- Indexes for table `dietitian_requests`
--
ALTER TABLE `dietitian_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dietitian_request_pair` (`user_id`,`dietitian_id`),
  ADD KEY `idx_dietitian_requests_status` (`status`),
  ADD KEY `fk_dietitian_requests_dietitian` (`dietitian_id`);

--
-- Indexes for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_diet_plans_dietitian` (`dietitian_id`),
  ADD KEY `idx_diet_plans_goal_status` (`goal_type`,`status`);

--
-- Indexes for table `food_calories`
--
ALTER TABLE `food_calories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_food_name` (`food_name`);

--
-- Indexes for table `food_calorie_reference`
--
ALTER TABLE `food_calorie_reference`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `food_name` (`food_name`);

--
-- Indexes for table `food_log`
--
ALTER TABLE `food_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_food_log_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `ingredient_meal_suggestions`
--
ALTER TABLE `ingredient_meal_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meal_name` (`meal_name`);

--
-- Indexes for table `meals`
--
ALTER TABLE `meals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meals_plan_day_type` (`diet_plan_id`,`day_number`,`meal_type`);

--
-- Indexes for table `meal_ingredients`
--
ALTER TABLE `meal_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meal_id` (`meal_id`);

--
-- Indexes for table `meal_suggestions`
--
ALTER TABLE `meal_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_meal_name` (`meal_name`);

--
-- Indexes for table `meal_templates`
--
ALTER TABLE `meal_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meal_templates_dietitian` (`dietitian_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sender` (`sender_type`,`sender_id`),
  ADD KEY `idx_messages_receiver` (`receiver_type`,`receiver_id`),
  ADD KEY `idx_messages_read` (`is_read`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_goal` (`goal`),
  ADD KEY `idx_users_activity` (`activity_level`);

--
-- Indexes for table `user_diet_plans`
--
ALTER TABLE `user_diet_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_udp_user_status` (`user_id`,`status`),
  ADD KEY `idx_udp_dietitian_status` (`dietitian_id`,`status`),
  ADD KEY `fk_udp_plan` (`diet_plan_id`);

--
-- Indexes for table `user_favorite_meals`
--
ALTER TABLE `user_favorite_meals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_favorites_user` (`user_id`);

--
-- Indexes for table `water_log`
--
ALTER TABLE `water_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_water_log_user_date` (`user_id`,`log_date`);

--
-- Indexes for table `weight_log`
--
ALTER TABLE `weight_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_weight_log_user_date` (`user_id`,`log_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dietitians`
--
ALTER TABLE `dietitians`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `dietitian_requests`
--
ALTER TABLE `dietitian_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `diet_plans`
--
ALTER TABLE `diet_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `food_calories`
--
ALTER TABLE `food_calories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `food_calorie_reference`
--
ALTER TABLE `food_calorie_reference`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `food_log`
--
ALTER TABLE `food_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ingredient_meal_suggestions`
--
ALTER TABLE `ingredient_meal_suggestions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `meals`
--
ALTER TABLE `meals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `meal_ingredients`
--
ALTER TABLE `meal_ingredients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- AUTO_INCREMENT for table `meal_suggestions`
--
ALTER TABLE `meal_suggestions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `meal_templates`
--
ALTER TABLE `meal_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_diet_plans`
--
ALTER TABLE `user_diet_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_favorite_meals`
--
ALTER TABLE `user_favorite_meals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `water_log`
--
ALTER TABLE `water_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `weight_log`
--
ALTER TABLE `weight_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dietitian_requests`
--
ALTER TABLE `dietitian_requests`
  ADD CONSTRAINT `fk_dietitian_requests_dietitian` FOREIGN KEY (`dietitian_id`) REFERENCES `dietitians` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dietitian_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD CONSTRAINT `fk_diet_plans_dietitian` FOREIGN KEY (`dietitian_id`) REFERENCES `dietitians` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `food_log`
--
ALTER TABLE `food_log`
  ADD CONSTRAINT `fk_food_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `meals`
--
ALTER TABLE `meals`
  ADD CONSTRAINT `fk_meals_diet_plan` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `meal_ingredients`
--
ALTER TABLE `meal_ingredients`
  ADD CONSTRAINT `fk_meal_ingredients_meal_id` FOREIGN KEY (`meal_id`) REFERENCES `meal_suggestions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meal_templates`
--
ALTER TABLE `meal_templates`
  ADD CONSTRAINT `fk_meal_templates_dietitian` FOREIGN KEY (`dietitian_id`) REFERENCES `dietitians` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_diet_plans`
--
ALTER TABLE `user_diet_plans`
  ADD CONSTRAINT `fk_udp_dietitian` FOREIGN KEY (`dietitian_id`) REFERENCES `dietitians` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_udp_plan` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_udp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_favorite_meals`
--
ALTER TABLE `user_favorite_meals`
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `water_log`
--
ALTER TABLE `water_log`
  ADD CONSTRAINT `fk_water_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `weight_log`
--
ALTER TABLE `weight_log`
  ADD CONSTRAINT `fk_weight_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
