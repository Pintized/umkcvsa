-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: jourdain.iad1-mysql-e2-4b.dreamhost.com
-- Generation Time: Jul 16, 2026 at 09:23 PM
-- Server version: 8.0.41-0ubuntu0.24.04.1
-- PHP Version: 8.5.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `umkcvsa_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_achievements`
--

CREATE TABLE `app_achievements` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `points` int UNSIGNED NOT NULL DEFAULT '0',
  `icon` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_achievements`
--

INSERT INTO `app_achievements` (`id`, `name`, `description`, `points`, `icon`, `active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'First Event Attended', 'Awarded for attending your first UMKC VSA event.', 50, NULL, 1, NULL, '2026-06-09 04:36:56', '2026-06-09 04:36:56');

-- --------------------------------------------------------

--
-- Table structure for table `app_achievement_awards`
--

CREATE TABLE `app_achievement_awards` (
  `id` int UNSIGNED NOT NULL,
  `achievement_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `awarded_by` int UNSIGNED DEFAULT NULL,
  `points_awarded` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_achievement_awards`
--

INSERT INTO `app_achievement_awards` (`id`, `achievement_id`, `user_id`, `awarded_by`, `points_awarded`, `created_at`) VALUES
(1, 1, 3, 1, 50, '2026-06-09 04:37:43'),
(2, 1, 3, 1, 50, '2026-06-09 04:45:46'),
(3, 1, 1, 1, 50, '2026-06-09 06:55:36'),
(4, 1, 1, 1, 50, '2026-06-09 06:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `app_audit_log`
--

CREATE TABLE `app_audit_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `user_email` varchar(190) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity` varchar(60) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_audit_log`
--

INSERT INTO `app_audit_log` (`id`, `user_id`, `user_email`, `action`, `entity`, `details`, `created_at`) VALUES
(1, 1, 'example@umkcvsa.org', 'create', 'event', 'Created event: Audit Wiring Test', '2026-06-07 12:53:59'),
(2, 1, 'example@umkcvsa.org', 'create', 'event', 'Created event: Audit Lifecycle Test', '2026-06-07 13:04:00'),
(3, 1, 'example@umkcvsa.org', 'update', 'event', 'Updated event: Audit Lifecycle Test (edited) (#6)', '2026-06-07 13:04:29'),
(4, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #4', '2026-06-07 13:10:28'),
(5, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #2', '2026-06-07 13:15:10'),
(6, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #3', '2026-06-07 13:15:10'),
(7, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #4', '2026-06-07 13:15:10'),
(8, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #5', '2026-06-07 13:15:10'),
(9, 1, 'example@umkcvsa.org', 'delete', 'event', 'Deleted event #6', '2026-06-07 13:15:10'),
(10, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: Test Task A', '2026-06-08 09:23:57'),
(11, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: Test Task A (edited) (#1)', '2026-06-08 09:24:07'),
(12, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: Test Task A (edited) (#1)', '2026-06-08 09:24:14'),
(13, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: Plan Fall Welcome Night', '2026-06-08 09:27:10'),
(14, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: Plan Fall Welcome Night (#2)', '2026-06-08 09:30:13'),
(15, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: Plan Fall Welcome Night (#2)', '2026-06-08 11:32:24'),
(16, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 1', '2026-06-08 11:53:02'),
(17, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: SYNC TEST CARD', '2026-06-08 12:04:20'),
(18, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: AUTO REFRESH PROOF', '2026-06-08 12:06:46'),
(19, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: SYNC TEST CARD (#4)', '2026-06-08 12:07:57'),
(20, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: AUTO REFRESH PROOF (#5)', '2026-06-08 12:07:58'),
(21, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: VISIBILITY TEST', '2026-06-08 12:35:19'),
(22, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: VISIBILITY TEST (#6)', '2026-06-08 12:35:28'),
(23, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: POLL LIST TEST', '2026-06-08 12:36:31'),
(24, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: POLL LIST TEST (#7)', '2026-06-08 12:36:51'),
(25, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: edge test node', '2026-06-08 13:58:35'),
(26, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: edge test node (#8)', '2026-06-08 13:59:18'),
(27, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: Subtask A', '2026-06-08 14:04:56'),
(28, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: Subtask A (#9)', '2026-06-08 14:07:53'),
(29, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 2', '2026-06-08 14:09:37'),
(30, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-08 14:45:12'),
(31, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-08 15:06:37'),
(32, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-08 15:07:06'),
(33, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 3', '2026-06-08 16:22:34'),
(34, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#11)', '2026-06-08 16:23:10'),
(35, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: task 3 (#11)', '2026-06-08 16:23:56'),
(36, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-08 16:24:07'),
(37, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-08 16:31:38'),
(38, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-08 16:31:42'),
(39, 1, 'example@umkcvsa.org', 'reward_create', 'reward', 'VSA T-Shirt (150 pts)', '2026-06-09 00:36:44'),
(40, 1, 'example@umkcvsa.org', 'grant_points', 'user', 'Granted 50 pts to User 1 (new total 50) — Test grant - verifying rewards system', '2026-06-09 00:37:10'),
(41, 1, 'example@umkcvsa.org', 'grant_points', 'user', 'Granted 25 pts to User 1 (new total 75)', '2026-06-09 01:04:06'),
(42, 1, 'example@umkcvsa.org', 'grant_points', 'user', 'Granted 25 pts to User 2 (new total 25)', '2026-06-09 01:04:06'),
(43, 1, 'example@umkcvsa.org', 'achievement_award', 'achievement', 'Awarded \"First Event Attended\" (+50 pts) to Test Test1 — new total 50 pts', '2026-06-09 04:37:43'),
(44, 1, 'example@umkcvsa.org', 'achievement_award', 'achievement', 'Awarded \"First Event Attended\" (+50 pts) to Test Test1 — new total 100 pts', '2026-06-09 04:45:46'),
(45, 1, 'example@umkcvsa.org', 'achievement_award', 'achievement', 'Awarded \"First Event Attended\" (+50 pts) to User 1 — new total 125 pts', '2026-06-09 06:55:36'),
(46, 1, 'example@umkcvsa.org', 'achievement_award', 'achievement', 'Awarded \"First Event Attended\" (+50 pts) to User 1 — new total 175 pts', '2026-06-09 06:55:44'),
(47, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 3', '2026-06-09 09:02:23'),
(48, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 4', '2026-06-09 09:02:27'),
(49, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: Live Update Test Task', '2026-06-09 09:08:46'),
(50, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: Live Update Test Task (#14)', '2026-06-09 09:09:09'),
(51, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 5', '2026-06-09 09:10:33'),
(52, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-09 11:25:54'),
(53, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 11:35:14'),
(54, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 11:35:49'),
(55, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 11:39:37'),
(56, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 11:39:46'),
(57, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 12:48:17'),
(58, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 12:48:24'),
(59, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-09 12:48:36'),
(60, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-09 16:34:02'),
(61, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-09 16:34:40'),
(62, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: task 5', '2026-06-09 17:02:56'),
(63, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#15)', '2026-06-09 17:03:08'),
(64, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#15)', '2026-06-09 17:03:09'),
(65, 1, 'example@umkcvsa.org', 'delete', 'task', 'Deleted task: task 5 (#15)', '2026-06-09 17:14:43'),
(66, 1, 'example@umkcvsa.org', 'create', 'task', 'Created task: 2', '2026-06-09 17:15:09'),
(67, 1, 'example@umkcvsa.org', 'inventory.create', 'inventory#1', 'Added \"__TEST_ITEM__\" (qty 5) in Storage A', '2026-06-10 13:24:32'),
(68, 1, 'example@umkcvsa.org', 'inventory.delete', 'inventory#1', 'Removed \"__TEST_ITEM__\"', '2026-06-10 13:24:45'),
(69, 1, 'example@umkcvsa.org', 'inventory.create', 'inventory#2', 'Added \"Club T-Shirts\" (qty 3) in Closet B, Shelf 2', '2026-06-10 13:29:02'),
(70, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#2', 'Qty Club T-Shirts: 3 -> 2', '2026-06-10 13:29:15'),
(71, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#2', 'Qty Club T-Shirts: 2 -> 1', '2026-06-10 13:29:16'),
(72, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#2', 'Qty Club T-Shirts: 1 -> 0', '2026-06-10 13:29:17'),
(73, 1, 'example@umkcvsa.org', 'inventory.delete', 'inventory#2', 'Removed \"Club T-Shirts\"', '2026-06-10 13:34:54'),
(74, 1, 'example@umkcvsa.org', 'inventory.create', 'inventory#3', 'Added \"Modal Test Item\" (qty 2) in Test Shelf', '2026-06-10 13:48:36'),
(75, 1, 'example@umkcvsa.org', 'inventory.delete', 'inventory#3', 'Removed \"Modal Test Item\"', '2026-06-10 13:49:08'),
(76, 1, 'example@umkcvsa.org', 'inventory.create', 'inventory#4', 'Added \"25-26 T-Shirts\" (qty 20) in K\'s House', '2026-06-10 14:01:00'),
(77, 1, 'example@umkcvsa.org', 'inventory.update', 'inventory#4', 'Updated \"25-26 T-Shirts wholee lot\"', '2026-06-10 14:01:34'),
(78, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 20 -> 21', '2026-06-10 14:01:44'),
(79, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 21 -> 22', '2026-06-10 14:01:45'),
(80, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 22 -> 23', '2026-06-10 14:01:45'),
(81, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 23 -> 22', '2026-06-10 14:01:46'),
(82, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 22 -> 21', '2026-06-10 14:01:47'),
(83, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#4', 'Qty 25-26 T-Shirts wholee lot: 21 -> 20', '2026-06-10 14:01:47'),
(84, 1, 'example@umkcvsa.org', 'inventory.create', 'inventory#5', 'Added \"test2\" (qty 2) in 2', '2026-06-10 16:38:33'),
(85, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 3', '2026-06-10 16:38:43'),
(86, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 3 -> 2', '2026-06-10 16:38:52'),
(87, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 3', '2026-06-10 16:38:53'),
(88, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 3 -> 2', '2026-06-10 16:38:53'),
(89, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 1', '2026-06-10 16:38:54'),
(90, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 1 -> 2', '2026-06-10 16:38:55'),
(91, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 1', '2026-06-10 16:38:55'),
(92, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 1 -> 0', '2026-06-10 16:38:55'),
(93, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 0 -> 1', '2026-06-10 16:38:56'),
(94, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 1 -> 2', '2026-06-10 16:38:57'),
(95, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 1', '2026-06-10 17:05:10'),
(96, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 1 -> 2', '2026-06-10 17:05:11'),
(97, 1, 'example@umkcvsa.org', 'inventory.adjust', 'inventory#5', 'Qty test2: 2 -> 3', '2026-06-10 17:05:11'),
(98, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:03:55'),
(99, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:03:57'),
(100, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-12 22:04:13'),
(101, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-12 22:04:21'),
(102, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-12 22:04:22'),
(103, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 3 (#12)', '2026-06-12 22:04:30'),
(104, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:05:22'),
(105, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:05:24'),
(106, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:05:25'),
(107, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:05:26'),
(108, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:08:57'),
(109, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:08:58'),
(110, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:08:59'),
(111, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:09:01'),
(112, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:16:28'),
(113, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:33'),
(114, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:17:34'),
(115, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:36'),
(116, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:17:37'),
(117, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:38'),
(118, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:17:39'),
(119, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:17:39'),
(120, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:17:40'),
(121, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:41'),
(122, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:42'),
(123, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:44'),
(124, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:47'),
(125, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:17:53'),
(126, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:19:41'),
(127, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:19:49'),
(128, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:23:24'),
(129, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:23:27'),
(130, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:23:31'),
(131, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:23:33'),
(132, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:24:10'),
(133, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 4 (#13)', '2026-06-12 22:24:11'),
(134, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:24:12'),
(135, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:24:15'),
(136, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:24:18'),
(137, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:24:19'),
(138, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:40:44'),
(139, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:40:46'),
(140, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:40:47'),
(141, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:40:50'),
(142, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:40:53'),
(143, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:41:00'),
(144, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:41:03'),
(145, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:43:58'),
(146, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:43:58'),
(147, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:00'),
(148, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:01'),
(149, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:44:03'),
(150, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:44:04'),
(151, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:44:06'),
(152, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:44:11'),
(153, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 2 (#10)', '2026-06-12 22:44:12'),
(154, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 5 (#16)', '2026-06-12 22:44:14'),
(155, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:15'),
(156, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:17'),
(157, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:19'),
(158, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:20'),
(159, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 0 (#12)', '2026-06-12 22:44:21'),
(160, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-12 22:44:21'),
(161, 1, 'example@umkcvsa.org', 'update', 'task', 'Updated task: task 1 (#3)', '2026-06-13 00:12:02'),
(162, 1, 'example@umkcvsa.org', 'create', 'note_folder', 'Created folder \"Audit Test Folder\" (#4)', '2026-06-13 01:28:00'),
(163, 1, 'example@umkcvsa.org', 'create', 'note', 'Created note \"Audit Test Note\" (#3) in folder #4', '2026-06-13 01:28:00'),
(164, 1, 'example@umkcvsa.org', 'update', 'note', 'Edited note \"Audit Test Note\" (#3)', '2026-06-13 01:28:01'),
(165, 1, 'example@umkcvsa.org', 'delete', 'note_folder', 'Deleted folder #4 and its notes', '2026-06-13 01:28:31');

-- --------------------------------------------------------

--
-- Table structure for table `app_cart_items`
--

CREATE TABLE `app_cart_items` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `item_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int UNSIGNED NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_documents`
--

CREATE TABLE `app_documents` (
  `id` int UNSIGNED NOT NULL,
  `folder_id` int UNSIGNED NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_documents`
--

INSERT INTO `app_documents` (`id`, `folder_id`, `title`, `content_html`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'test', '<p><br></p>', 'example@umkcvsa.org', 'example@umkcvsa.org', '2026-06-14 06:15:07', '2026-06-14 06:15:07');

-- --------------------------------------------------------

--
-- Table structure for table `app_document_folders`
--

CREATE TABLE `app_document_folders` (
  `id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_document_folders`
--

INSERT INTO `app_document_folders` (`id`, `parent_id`, `name`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'test', 'example@umkcvsa.org', '2026-06-14 06:15:00', '2026-06-14 06:15:00'),
(2, NULL, 'pictures', 'example@umkcvsa.org', '2026-06-15 20:07:55', '2026-06-15 20:07:55'),
(3, 1, 'test', 'example@umkcvsa.org', '2026-06-15 20:08:20', '2026-06-15 20:08:20');

-- --------------------------------------------------------

--
-- Table structure for table `app_events`
--

CREATE TABLE `app_events` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_events`
--

INSERT INTO `app_events` (`id`, `name`, `event_date`, `start_time`, `end_time`, `location`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'VSA General Body Meeting', '2026-09-15', '18:00:00', '19:30:00', 'Student Union, Room 401', 'Kickoff meeting for the fall semester. Free food and games!', 1, '2026-06-06 06:25:08', '2026-06-06 06:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `app_inventory`
--

CREATE TABLE `app_inventory` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(128) NOT NULL DEFAULT '',
  `quantity` int NOT NULL DEFAULT '0',
  `unit` varchar(64) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  `low_stock_threshold` int NOT NULL DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_inventory`
--

INSERT INTO `app_inventory` (`id`, `name`, `category`, `quantity`, `unit`, `location`, `low_stock_threshold`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(4, '25-26 T-Shirts wholee lot', 'Apparel', 20, 'shirts', 'K\'s House', 5, '', 1, '2026-06-10 14:01:00', '2026-06-10 14:01:47'),
(5, 'test2', 'Apparel', 3, '', '2', 2, '', 1, '2026-06-10 16:38:32', '2026-06-10 17:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `app_login_attempts`
--

CREATE TABLE `app_login_attempts` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_login_attempts`
--

INSERT INTO `app_login_attempts` (`id`, `email`, `ip_address`, `success`, `attempted_at`) VALUES
(1, 'example@umkcvsa.org', 0x465e761a, 1, '2026-06-06 00:10:05'),
(2, 'example2@umkcvsa.org', 0x8823a6a7, 1, '2026-06-06 06:44:36'),
(3, 'example@umkcvsa.org', 0x8823a6a7, 1, '2026-06-07 06:38:35'),
(4, 'example@umkcvsa.org', 0x8823a6a7, 1, '2026-06-08 16:14:01'),
(5, 'example@umkcvsa.org', 0x8823a6a7, 1, '2026-06-09 07:20:20'),
(6, 'example@umkcvsa.org', 0xd8c87b9a, 1, '2026-06-09 15:54:40'),
(7, 'example@umkcvsa.org', 0xd8c87b9a, 1, '2026-06-10 20:45:49'),
(8, 'example@umkcvsa.org', 0x8823a6a7, 1, '2026-06-13 04:57:32'),
(9, 'example@umkcvsa.org', 0x8823a6a7, 1, '2026-06-14 05:07:20'),
(10, 'example@umkcvsa.org', 0x68059411, 1, '2026-06-15 20:06:06');

-- --------------------------------------------------------

--
-- Table structure for table `app_notes`
--

CREATE TABLE `app_notes` (
  `id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Untitled note',
  `content` mediumtext,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_note_folders`
--

CREATE TABLE `app_note_folders` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_orders`
--

CREATE TABLE `app_orders` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` enum('stripe','cash','zelle','venmo') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marked_paid_by` int UNSIGNED DEFAULT NULL,
  `marked_paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_order_items`
--

CREATE TABLE `app_order_items` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `item_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int UNSIGNED NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_rewards`
--

CREATE TABLE `app_rewards` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `point_cost` int UNSIGNED NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_rewards`
--

INSERT INTO `app_rewards` (`id`, `name`, `description`, `point_cost`, `active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'VSA T-Shirt', 'Official UMKC VSA t-shirt in your size.', 150, 1, 1, '2026-06-09 07:36:44', '2026-06-09 07:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `app_rsvps`
--

CREATE TABLE `app_rsvps` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `event_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date DEFAULT NULL,
  `STATUS` enum('going','maybe','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'going',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_tasks`
--

CREATE TABLE `app_tasks` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `pos_x` int NOT NULL DEFAULT '40',
  `pos_y` int NOT NULL DEFAULT '40',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `priority` varchar(16) NOT NULL DEFAULT 'medium'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_tasks`
--

INSERT INTO `app_tasks` (`id`, `title`, `description`, `due_date`, `status`, `pos_x`, `pos_y`, `created_by`, `created_at`, `priority`) VALUES
(3, 'task 1', '', NULL, 'open', 1240, 320, 1, '2026-06-08 11:53:02', 'high'),
(10, 'task 2', 'the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon the fox jumped over the big brown moon', '2026-06-11', 'open', 1080, 680, 1, '2026-06-08 14:09:37', 'medium'),
(12, 'task 0', '', NULL, 'open', 520, 600, 1, '2026-06-09 09:02:23', 'medium'),
(13, 'task 4', '', NULL, 'in-progress', 1400, 680, 1, '2026-06-09 09:02:27', 'medium'),
(16, 'task 5', '', NULL, 'open', 520, 320, 1, '2026-06-09 17:02:56', 'medium'),
(17, '2', '', NULL, 'open', 440, 1040, 1, '2026-06-09 17:15:09', 'medium');

-- --------------------------------------------------------

--
-- Table structure for table `app_task_assignees`
--

CREATE TABLE `app_task_assignees` (
  `task_id` int NOT NULL,
  `user_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_task_assignees`
--

INSERT INTO `app_task_assignees` (`task_id`, `user_id`) VALUES
(10, 1),
(12, 1),
(13, 1),
(16, 1),
(17, 1);

-- --------------------------------------------------------

--
-- Table structure for table `app_task_edges`
--

CREATE TABLE `app_task_edges` (
  `id` int NOT NULL,
  `from_id` int NOT NULL,
  `to_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_task_edges`
--

INSERT INTO `app_task_edges` (`id`, `from_id`, `to_id`, `created_at`) VALUES
(10, 3, 10, '2026-06-09 11:39:57'),
(11, 3, 13, '2026-06-09 11:40:11'),
(12, 12, 17, '2026-06-12 22:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `app_users`
--

CREATE TABLE `app_users` (
  `id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_pic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int NOT NULL DEFAULT '0',
  `role` set('member','officer','alumni','intern','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_users`
--

INSERT INTO `app_users` (`id`, `full_name`, `first_name`, `last_name`, `email`, `password_hash`, `profile_pic`, `points`, `role`, `created_at`) VALUES
(1, 'User 1', 'User', '1', 'example@umkcvsa.org', '$2y$12$jNls/PbOvXyamndbNNh7Quk6Bz4jfm7duijss0ciKDFZIONDDKbEm', NULL, 175, 'member,officer', '2026-06-05 09:47:31'),
(2, 'User 2', 'User', '2', 'Example2@umkcvsa.org', '$2y$12$j1okoEgVUaC/DKg3L5r2Met8DEBhY3W8WXJ/HPX/PaZ4ydLlCfd06', NULL, 25, 'member', '2026-06-05 09:48:05'),
(3, 'Test Test1', 'Test', 'Test1', 'test1@umkcvsa.org', '$2y$12$hs8gkyb.TiMRtbM3lXMhP.Ahfu.WfvR.P/8uRI0/EiQqSGZ1WgBcu', NULL, 100, 'member', '2026-06-05 19:40:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_achievements`
--
ALTER TABLE `app_achievements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_achievement_awards`
--
ALTER TABLE `app_achievement_awards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ach` (`achievement_id`);

--
-- Indexes for table `app_audit_log`
--
ALTER TABLE `app_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `entity` (`entity`);

--
-- Indexes for table `app_cart_items`
--
ALTER TABLE `app_cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `app_documents`
--
ALTER TABLE `app_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documents_folder_updated` (`folder_id`,`updated_at`),
  ADD KEY `idx_documents_title` (`title`);

--
-- Indexes for table `app_document_folders`
--
ALTER TABLE `app_document_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_folder_parent` (`parent_id`);

--
-- Indexes for table `app_events`
--
ALTER TABLE `app_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `fk_events_creator` (`created_by`);

--
-- Indexes for table `app_inventory`
--
ALTER TABLE `app_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_login_attempts`
--
ALTER TABLE `app_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempted_at`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `app_notes`
--
ALTER TABLE `app_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`);

--
-- Indexes for table `app_note_folders`
--
ALTER TABLE `app_note_folders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_orders`
--
ALTER TABLE `app_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `fk_order_admin` (`marked_paid_by`);

--
-- Indexes for table `app_order_items`
--
ALTER TABLE `app_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `app_rewards`
--
ALTER TABLE `app_rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_rsvps`
--
ALTER TABLE `app_rsvps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `app_tasks`
--
ALTER TABLE `app_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_task_assignees`
--
ALTER TABLE `app_task_assignees`
  ADD PRIMARY KEY (`task_id`,`user_id`);

--
-- Indexes for table `app_task_edges`
--
ALTER TABLE `app_task_edges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_edge` (`from_id`,`to_id`);

--
-- Indexes for table `app_users`
--
ALTER TABLE `app_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_achievements`
--
ALTER TABLE `app_achievements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `app_achievement_awards`
--
ALTER TABLE `app_achievement_awards`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `app_audit_log`
--
ALTER TABLE `app_audit_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `app_cart_items`
--
ALTER TABLE `app_cart_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_documents`
--
ALTER TABLE `app_documents`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `app_document_folders`
--
ALTER TABLE `app_document_folders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `app_events`
--
ALTER TABLE `app_events`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `app_inventory`
--
ALTER TABLE `app_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `app_login_attempts`
--
ALTER TABLE `app_login_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `app_notes`
--
ALTER TABLE `app_notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `app_note_folders`
--
ALTER TABLE `app_note_folders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `app_orders`
--
ALTER TABLE `app_orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_order_items`
--
ALTER TABLE `app_order_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_rewards`
--
ALTER TABLE `app_rewards`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `app_rsvps`
--
ALTER TABLE `app_rsvps`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_tasks`
--
ALTER TABLE `app_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `app_task_edges`
--
ALTER TABLE `app_task_edges`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `app_users`
--
ALTER TABLE `app_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `app_cart_items`
--
ALTER TABLE `app_cart_items`
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `app_documents`
--
ALTER TABLE `app_documents`
  ADD CONSTRAINT `fk_documents_folder` FOREIGN KEY (`folder_id`) REFERENCES `app_document_folders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `app_events`
--
ALTER TABLE `app_events`
  ADD CONSTRAINT `fk_events_creator` FOREIGN KEY (`created_by`) REFERENCES `app_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `app_orders`
--
ALTER TABLE `app_orders`
  ADD CONSTRAINT `fk_order_admin` FOREIGN KEY (`marked_paid_by`) REFERENCES `app_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `app_order_items`
--
ALTER TABLE `app_order_items`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `app_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `app_rsvps`
--
ALTER TABLE `app_rsvps`
  ADD CONSTRAINT `fk_rsvp_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
