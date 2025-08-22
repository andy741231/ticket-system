-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 22, 2025 at 08:59 PM
-- Server version: 8.0.43
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `test_ticket`
--

-- --------------------------------------------------------

--
-- Table structure for table `apps`
--

CREATE TABLE `apps` (
  `id` bigint UNSIGNED NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `apps`
--

INSERT INTO `apps` (`id`, `slug`, `name`, `created_at`, `updated_at`) VALUES
(1, 'hub', 'Hub', '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(2, 'tickets', 'Tickets', '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(3, 'directory', 'Directory', '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(4, 'newsletter', 'Newsletter', '2025-08-23 00:51:23', '2025-08-23 00:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-perm:can:v0:1:global:admin.rbac.overrides.manage', 'b:0;', 1755536911),
('laravel-cache-perm:can:v0:1:global:admin.rbac.permissions.manage', 'b:0;', 1755536911),
('laravel-cache-perm:can:v0:1:global:admin.rbac.roles.manage', 'b:0;', 1755536910),
('laravel-cache-perm:can:v0:1:global:tickets.ticket.update', 'b:0;', 1755536911),
('laravel-cache-perm:can:v0:1:global:users.user.manage', 'b:0;', 1755536910),
('laravel-cache-perm:perms:v0:1:global:*', 'a:0:{}', 1755536910),
('laravel-cache-spatie.permission.cache', 'a:3:{s:5:\"alias\";a:9:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:3:\"key\";s:1:\"d\";s:11:\"description\";s:1:\"e\";s:10:\"guard_name\";s:1:\"f\";s:10:\"is_mutable\";s:1:\"r\";s:5:\"roles\";s:1:\"j\";s:7:\"team_id\";s:1:\"k\";s:4:\"slug\";}s:11:\"permissions\";a:25:{i:0;a:7:{s:1:\"a\";i:1;s:1:\"b\";s:12:\"view tickets\";s:1:\"c\";s:12:\"view.tickets\";s:1:\"d\";s:24:\"Permission: View Tickets\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:1;a:7:{s:1:\"a\";i:2;s:1:\"b\";s:14:\"create tickets\";s:1:\"c\";s:14:\"create.tickets\";s:1:\"d\";s:26:\"Permission: Create Tickets\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:2;a:7:{s:1:\"a\";i:3;s:1:\"b\";s:12:\"edit tickets\";s:1:\"c\";s:12:\"edit.tickets\";s:1:\"d\";s:24:\"Permission: Edit Tickets\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:3;a:7:{s:1:\"a\";i:4;s:1:\"b\";s:14:\"delete tickets\";s:1:\"c\";s:14:\"delete.tickets\";s:1:\"d\";s:26:\"Permission: Delete Tickets\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:4;a:7:{s:1:\"a\";i:5;s:1:\"b\";s:10:\"view users\";s:1:\"c\";s:10:\"view.users\";s:1:\"d\";s:22:\"Permission: View Users\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:5;a:7:{s:1:\"a\";i:6;s:1:\"b\";s:12:\"create users\";s:1:\"c\";s:12:\"create.users\";s:1:\"d\";s:24:\"Permission: Create Users\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:6;a:7:{s:1:\"a\";i:7;s:1:\"b\";s:10:\"edit users\";s:1:\"c\";s:10:\"edit.users\";s:1:\"d\";s:22:\"Permission: Edit Users\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:7;a:7:{s:1:\"a\";i:8;s:1:\"b\";s:12:\"delete users\";s:1:\"c\";s:12:\"delete.users\";s:1:\"d\";s:24:\"Permission: Delete Users\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:8;a:7:{s:1:\"a\";i:9;s:1:\"b\";s:14:\"manage tickets\";s:1:\"c\";s:14:\"manage.tickets\";s:1:\"d\";s:26:\"Permission: Manage Tickets\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:9;a:7:{s:1:\"a\";i:10;s:1:\"b\";s:12:\"manage users\";s:1:\"c\";s:12:\"manage.users\";s:1:\"d\";s:24:\"Permission: Manage Users\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:1;}}i:10;a:7:{s:1:\"a\";i:11;s:1:\"b\";s:15:\"users.user.view\";s:1:\"c\";s:15:\"users.user.view\";s:1:\"d\";s:26:\"Can view user in users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:3;i:1;i:4;}}i:11;a:7:{s:1:\"a\";i:12;s:1:\"b\";s:17:\"users.user.create\";s:1:\"c\";s:17:\"users.user.create\";s:1:\"d\";s:28:\"Can create user in users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:3;}}i:12;a:7:{s:1:\"a\";i:13;s:1:\"b\";s:17:\"users.user.update\";s:1:\"c\";s:17:\"users.user.update\";s:1:\"d\";s:28:\"Can update user in users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:3;}}i:13;a:7:{s:1:\"a\";i:14;s:1:\"b\";s:17:\"users.user.delete\";s:1:\"c\";s:17:\"users.user.delete\";s:1:\"d\";s:28:\"Can delete user in users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:3;}}i:14;a:7:{s:1:\"a\";i:15;s:1:\"b\";s:17:\"users.user.manage\";s:1:\"c\";s:17:\"users.user.manage\";s:1:\"d\";s:28:\"Can manage user in users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:3;}}i:15;a:7:{s:1:\"a\";i:16;s:1:\"b\";s:19:\"tickets.ticket.view\";s:1:\"c\";s:19:\"tickets.ticket.view\";s:1:\"d\";s:30:\"Can view ticket in tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:5;i:1;i:6;}}i:16;a:7:{s:1:\"a\";i:17;s:1:\"b\";s:21:\"tickets.ticket.create\";s:1:\"c\";s:21:\"tickets.ticket.create\";s:1:\"d\";s:32:\"Can create ticket in tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:5;i:1;i:6;}}i:17;a:7:{s:1:\"a\";i:18;s:1:\"b\";s:21:\"tickets.ticket.update\";s:1:\"c\";s:21:\"tickets.ticket.update\";s:1:\"d\";s:21:\"Update ticket details\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:5;}}i:18;a:7:{s:1:\"a\";i:19;s:1:\"b\";s:21:\"tickets.ticket.delete\";s:1:\"c\";s:21:\"tickets.ticket.delete\";s:1:\"d\";s:32:\"Can delete ticket in tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:5;}}i:19;a:7:{s:1:\"a\";i:20;s:1:\"b\";s:21:\"tickets.ticket.manage\";s:1:\"c\";s:21:\"tickets.ticket.manage\";s:1:\"d\";s:32:\"Can manage ticket in tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:5;}}i:20;a:7:{s:1:\"a\";i:21;s:1:\"b\";s:19:\"tickets.file.upload\";s:1:\"c\";s:19:\"tickets.file.upload\";s:1:\"d\";s:30:\"Can upload file in tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:5;}}i:21;a:7:{s:1:\"a\";i:22;s:1:\"b\";s:22:\"directory.profile.view\";s:1:\"c\";s:22:\"directory.profile.view\";s:1:\"d\";s:33:\"Can view profile in directory app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:2:{i:0;i:7;i:1;i:8;}}i:22;a:7:{s:1:\"a\";i:23;s:1:\"b\";s:24:\"directory.profile.update\";s:1:\"c\";s:24:\"directory.profile.update\";s:1:\"d\";s:35:\"Can update profile in directory app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:7;}}i:23;a:7:{s:1:\"a\";i:24;s:1:\"b\";s:21:\"directory.user.lookup\";s:1:\"c\";s:21:\"directory.user.lookup\";s:1:\"d\";s:32:\"Can lookup user in directory app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;s:1:\"r\";a:1:{i:0;i:7;}}i:24;a:6:{s:1:\"a\";i:25;s:1:\"b\";s:23:\"admin.rbac.roles.manage\";s:1:\"c\";N;s:1:\"d\";N;s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}}s:5:\"roles\";a:8:{i:0;a:7:{s:1:\"a\";i:1;s:1:\"j\";N;s:1:\"b\";s:5:\"admin\";s:1:\"k\";s:5:\"admin\";s:1:\"d\";s:22:\"Full access within app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}i:1;a:7:{s:1:\"a\";i:2;s:1:\"j\";N;s:1:\"b\";s:4:\"user\";s:1:\"k\";s:4:\"user\";s:1:\"d\";s:18:\"User role (global)\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}i:2;a:7:{s:1:\"a\";i:3;s:1:\"j\";i:1;s:1:\"b\";s:11:\"users admin\";s:1:\"k\";s:5:\"admin\";s:1:\"d\";s:22:\"Full access within app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:0;}i:3;a:7:{s:1:\"a\";i:4;s:1:\"j\";i:1;s:1:\"b\";s:4:\"user\";s:1:\"k\";s:4:\"user\";s:1:\"d\";s:23:\"User role for users app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}i:4;a:7:{s:1:\"a\";i:5;s:1:\"j\";i:2;s:1:\"b\";s:13:\"tickets admin\";s:1:\"k\";s:5:\"admin\";s:1:\"d\";s:22:\"Full access within app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:0;}i:5;a:7:{s:1:\"a\";i:6;s:1:\"j\";i:2;s:1:\"b\";s:4:\"user\";s:1:\"k\";s:4:\"user\";s:1:\"d\";s:25:\"User role for tickets app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}i:6;a:7:{s:1:\"a\";i:7;s:1:\"j\";i:3;s:1:\"b\";s:15:\"directory admin\";s:1:\"k\";s:5:\"admin\";s:1:\"d\";s:22:\"Full access within app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:0;}i:7;a:7:{s:1:\"a\";i:8;s:1:\"j\";i:3;s:1:\"b\";s:4:\"user\";s:1:\"k\";s:4:\"user\";s:1:\"d\";s:27:\"User role for directory app\";s:1:\"e\";s:3:\"web\";s:1:\"f\";i:1;}}}', 1755622977);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_08_02_020548_create_permission_tables', 1),
(5, '2025_08_02_020857_create_tickets_table', 1),
(6, '2025_08_05_201556_make_username_nullable_in_users_table', 1),
(7, '2025_08_06_221042_add_description_to_users_table', 1),
(8, '2025_08_07_222127_create_ticket_files_table', 1),
(9, '2025_08_08_000001_create_temp_files_table', 1),
(10, '2025_08_08_205344_add_due_date_to_tickets_table', 1),
(11, '2025_08_14_163952_create_apps_table', 1),
(12, '2025_08_14_163953_add_teams_support_to_spatie_permission', 1),
(13, '2025_08_14_163954_create_user_permission_overrides_table', 1),
(14, '2025_08_14_163955_add_is_mutable_to_roles_and_permissions', 1),
(15, '2025_08_15_110300_add_slug_key_and_descriptions_to_roles_and_permissions', 2),
(17, '2025_08_15_224933_add_assigned_to_to_tickets_table', 3),
(18, '2025_08_18_231400_create_ticket_user_table', 4),
(19, '2025_08_18_234000_drop_assigned_to_id_from_tickets_table', 5),
(20, '2025_08_19_015500_add_updated_by_to_tickets_table', 6),
(21, '2025_08_19_031100_create_ticket_comments_table', 7),
(22, '2025_08_19_120000_add_description_text_to_tickets_table', 8);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`, `team_id`) VALUES
(3, 'App\\Models\\User', 1, 1),
(3, 'App\\Models\\User', 2, 1),
(5, 'App\\Models\\User', 2, 2),
(5, 'App\\Models\\User', 10, 2),
(6, 'App\\Models\\User', 1, 2),
(6, 'App\\Models\\User', 2, 2),
(7, 'App\\Models\\User', 1, 3),
(7, 'App\\Models\\User', 2, 3),
(9, 'App\\Models\\User', 3, 1),
(9, 'App\\Models\\User', 3, 2),
(9, 'App\\Models\\User', 3, 3),
(17, 'App\\Models\\User', 2, 4);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_mutable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `key`, `description`, `guard_name`, `is_mutable`, `created_at`, `updated_at`) VALUES
(1, 'view tickets', 'view.tickets', 'Permission: View Tickets', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(2, 'create tickets', 'create.tickets', 'Permission: Create Tickets', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(3, 'edit tickets', 'edit.tickets', 'Permission: Edit Tickets', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(4, 'delete tickets', 'delete.tickets', 'Permission: Delete Tickets', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(5, 'view users', 'view.users', 'Permission: View Users', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(6, 'create users', 'create.users', 'Permission: Create Users', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(7, 'edit users', 'edit.users', 'Permission: Edit Users', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(8, 'delete users', 'delete.users', 'Permission: Delete Users', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(9, 'manage tickets', 'manage.tickets', 'Permission: Manage Tickets', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(10, 'manage users', 'manage.users', 'Permission: Manage Users', 'web', 1, '2025-08-15 03:35:17', '2025-08-15 21:26:44'),
(11, 'users.user.view', 'users.user.view', 'Can view user in users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(12, 'users.user.create', 'users.user.create', 'Can create user in users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(13, 'users.user.update', 'users.user.update', 'Can update user in users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(14, 'users.user.delete', 'users.user.delete', 'Can delete user in users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(15, 'users.user.manage', 'users.user.manage', 'Can manage user in users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(16, 'tickets.ticket.view', 'tickets.ticket.view', 'Can view ticket in tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(17, 'tickets.ticket.create', 'tickets.ticket.create', 'Can create ticket in tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(18, 'tickets.ticket.update', 'tickets.ticket.update', 'Update ticket details', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:23:58'),
(19, 'tickets.ticket.delete', 'tickets.ticket.delete', 'Can delete ticket in tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(20, 'tickets.ticket.manage', 'tickets.ticket.manage', 'Can manage ticket in tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(21, 'tickets.file.upload', 'tickets.file.upload', 'Can upload file in tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(22, 'directory.profile.view', 'directory.profile.view', 'Can view profile in directory app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(23, 'directory.profile.update', 'directory.profile.update', 'Can update profile in directory app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(24, 'directory.user.lookup', 'directory.user.lookup', 'Can lookup user in directory app', 'web', 1, '2025-08-15 03:40:10', '2025-08-15 21:26:44'),
(25, 'admin.rbac.roles.manage', NULL, 'Admin rbac roles manage', 'web', 1, '2025-08-18 21:57:31', '2025-08-19 02:37:56'),
(26, 'admin.rbac.permissions.manage', NULL, 'Admin rbac permissions manage', 'web', 1, '2025-08-19 02:37:56', '2025-08-19 02:37:56'),
(27, 'admin.rbac.overrides.manage', NULL, 'Admin rbac overrides manage', 'web', 1, '2025-08-19 02:37:56', '2025-08-19 02:37:56'),
(28, 'hub.user.view', NULL, 'Hub user view', 'web', 1, '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(29, 'hub.user.create', NULL, 'Hub user create', 'web', 1, '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(30, 'hub.user.update', NULL, 'Hub user update', 'web', 1, '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(31, 'hub.user.delete', NULL, 'Hub user delete', 'web', 1, '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(32, 'hub.user.manage', NULL, 'Hub user manage', 'web', 1, '2025-08-20 03:57:21', '2025-08-20 03:57:21'),
(33, 'directory.app.access', NULL, 'Directory app access', 'web', 1, '2025-08-20 04:54:31', '2025-08-20 04:54:31'),
(34, 'directory.profile.manage', NULL, 'Directory profile manage', 'web', 1, '2025-08-20 23:48:28', '2025-08-20 23:48:28'),
(35, 'newsletter.app.access', NULL, 'Newsletter app access', 'web', 1, '2025-08-23 01:05:59', '2025-08-23 01:05:59');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_mutable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `team_id`, `name`, `slug`, `description`, `guard_name`, `is_mutable`, `created_at`, `updated_at`) VALUES
(3, 1, 'hub admin', 'admin', 'Full access within app', 'web', 0, '2025-08-15 03:40:10', '2025-08-16 02:22:58'),
(4, 1, 'hub user', 'user', 'User role for users app', 'web', 1, '2025-08-15 03:40:10', '2025-08-21 00:21:05'),
(5, 2, 'tickets admin', 'admin', 'Full access within app', 'web', 0, '2025-08-15 03:40:10', '2025-08-16 02:23:46'),
(6, 2, 'ticket-user', 'user', 'User role for tickets app', 'web', 1, '2025-08-15 03:40:10', '2025-08-19 03:21:30'),
(7, 3, 'directory admin', 'admin', 'Full access within app', 'web', 0, '2025-08-15 03:40:11', '2025-08-16 02:23:19'),
(8, 3, 'directory-user', 'user', 'User role for directory app', 'web', 1, '2025-08-15 03:40:11', '2025-08-19 03:21:21'),
(9, NULL, 'super_admin', 'super-admin', 'Super Admin role (global)', 'web', 0, '2025-08-15 03:40:11', '2025-08-15 21:26:44'),
(13, NULL, 'admin', 'admin', 'Administrator (Global)', 'web', 0, '2025-08-20 03:59:08', '2025-08-20 03:59:08'),
(14, NULL, 'user', 'user', 'directory.app.access', 'web', 1, '2025-08-20 03:59:08', '2025-08-20 05:16:15'),
(17, 4, 'admin', 'admin', 'Administrator', 'web', 0, '2025-08-23 00:51:23', '2025-08-23 00:51:23'),
(18, 4, 'user', 'user', 'Standard User', 'web', 1, '2025-08-23 00:51:23', '2025-08-23 00:51:23'),
(19, 5, 'admin', 'admin', 'Administrator', 'web', 0, '2025-08-23 00:52:23', '2025-08-23 00:52:23'),
(20, 5, 'user', 'user', 'Standard User', 'web', 1, '2025-08-23 00:52:23', '2025-08-23 00:52:23');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(25, 3),
(26, 3),
(27, 3),
(28, 3),
(29, 3),
(30, 3),
(31, 3),
(32, 3),
(28, 4),
(16, 5),
(17, 5),
(18, 5),
(19, 5),
(20, 5),
(21, 5),
(16, 6),
(17, 6),
(22, 7),
(23, 7),
(24, 7),
(33, 7),
(34, 7),
(22, 8),
(35, 17);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('getERoJP3ziC18V27rX7wbEGADbd5v6XlHWyTqir', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiMU9rM3pkU3BScTRZZzhJcGR6TWN4WjJKc3hIN2hVYklJaVJwNExUcCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTAyOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvdGlja2V0cz9zY29wZT1hc3NpZ25lZCZzZWFyY2g9JnNvcnRfZGlyZWN0aW9uPWRlc2Mmc29ydF9maWVsZD1jcmVhdGVkX2F0JnN0YXR1cz0iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1755896078),
('GFRIQ1zPPgDofO0fdPciA27wgPZsvzyDlqF7bWrX', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoieHliSG9lcXhqMFdCbmxUT2lQekFLczZIekNWdW9EVDlkNmxxQkRVQyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC90aWNrZXRzLzk0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9', 1755893426),
('TxTFrGkxGdF4jiJBCEsmKRDoo0rnIhNHRjS4Op3w', 3, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoieEFzZjVBeWJlVEhYa05Iclc0U2hOTmd0dlpXZG1JaGt6ejFHTVBNUyI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MztzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMjoiaHR0cDovL2xvY2FsaG9zdDo4MDAwL3RpY2tldHMvOTUiO319', 1755896174);

-- --------------------------------------------------------

--
-- Table structure for table `temp_files`
--

CREATE TABLE `temp_files` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `temp_files`
--

INSERT INTO `temp_files` (`id`, `user_id`, `file_path`, `original_name`, `mime_type`, `size`, `created_at`, `updated_at`) VALUES
(3, 3, 'temp/3/GjARvcW2XNBAngPjOsSY21zruGMDw2bZMD2pCjB3.png', 'a04cea05-8697-4b1c-a6dd-d9e7ca6d69ea.png', 'image/png', 22396, '2025-08-20 00:16:28', '2025-08-20 00:16:28'),
(4, 3, 'temp/3/fwaowH3WeVPKaAKAVddDyWLAvZOcFUSHgmitsF2F.png', 'a04cea05-8697-4b1c-a6dd-d9e7ca6d69ea.png', 'image/png', 22396, '2025-08-20 00:19:10', '2025-08-20 00:19:10'),
(6, 3, 'temp/3/F2Y1hohj99nngLCosrLDmOdYfrVLs12BZIshxb0u.png', 'a04cea05-8697-4b1c-a6dd-d9e7ca6d69ea.png', 'image/png', 22396, '2025-08-20 00:24:00', '2025-08-20 00:24:00'),
(7, 3, 'temp/3/rgqA4oHHsw6rBkyTiNzvlR6yhZMzteYzw7lDtCwz.png', '20250815.png', 'image/png', 813449, '2025-08-20 00:24:14', '2025-08-20 00:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_text` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Received','Approved','Rejected','Completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
  `priority` enum('Low','Medium','High') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `user_id`, `title`, `description`, `description_text`, `status`, `priority`, `due_date`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 1, 'First Sample Ticket', 'This is a sample ticket created by the seeder.', 'This is a sample ticket created by the seeder.', 'Received', 'Medium', NULL, '2025-08-15 03:40:10', '2025-08-20 04:26:38', NULL),
(2, 1, 'Second Sample Ticket', 'Another sample ticket with high priority.', 'Another sample ticket with high priority.', 'Approved', 'High', NULL, '2025-08-15 03:40:10', '2025-08-20 04:26:38', NULL),
(3, 1, 'Completed Ticket', 'This ticket has been completed.', 'This ticket has been completed.', 'Completed', 'Low', NULL, '2025-08-15 03:40:10', '2025-08-20 04:26:38', NULL),
(4, 1, 'First Sample Ticket', 'This is a sample ticket created by the seeder.', 'This is a sample ticket created by the seeder.', 'Received', 'Medium', NULL, '2025-08-15 03:42:34', '2025-08-20 04:26:38', NULL),
(5, 1, 'Second Sample Ticket', 'Another sample ticket with high priority.', 'Another sample ticket with high priority.', 'Approved', 'High', NULL, '2025-08-15 03:42:34', '2025-08-20 04:26:38', NULL),
(6, 1, 'Completed Ticket', 'This ticket has been completed.', 'This ticket has been completed.', 'Completed', 'Low', NULL, '2025-08-15 03:42:34', '2025-08-20 04:26:38', NULL),
(15, 3, 'an example task', '<p>create a brochure for AIM AHEAD. need to follow <a target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-blue-600 hover:underline\" href=\"https://abc.com\">UH branding.</a></p>', 'create a brochure for AIM AHEAD. need to follow UH branding.', 'Approved', 'Medium', NULL, '2025-08-19 04:45:12', '2025-08-21 22:33:06', 3),
(16, 3, 'Add glossary to CTPH glossary site', '<p><a target=\"_blank\" rel=\"noopener noreferrer nofollow\" class=\"text-blue-600 hover:underline\" href=\"https://ctph.dept-eit.com/\">https://ctph.dept-eit.com/</a></p><p>add</p><h2>Stakeholder Engagement</h2><p>Consensus Definition</p><p>The process of involving relevant parties (e.g., researchers, clinicians, patients, policymakers) in various stages of the translational process.</p><h3>Conversational / Short Definition</h3><p></p><p>The process of involving relevant parties (e.g., researchers, clinicians, patients, policymakers) in various stages of the translational process.</p><p>ab</p>', 'https://ctph.dept-eit.com/addStakeholder EngagementConsensus DefinitionThe process of involving relevant parties (e.g., researchers, clinicians, patients, policymakers) in various stages of the translational process.Conversational / Short DefinitionThe process of involving relevant parties (e.g., researchers, clinicians, patients, policymakers) in various stages of the translational process.ab', 'Approved', 'Medium', '2025-08-10', '2025-08-19 22:36:11', '2025-08-22 20:05:21', 3),
(94, 3, 'fdaf', '<p>abc</p>', 'abc', 'Received', 'Medium', '2025-08-11', '2025-08-23 00:40:33', '2025-08-23 00:41:41', 3),
(95, 1, 'fdasf', '<p>fadfdaf</p>', 'fadfdaf', 'Approved', 'Medium', '2025-08-06', '2025-08-23 01:54:35', '2025-08-23 01:54:57', 3);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` bigint UNSIGNED NOT NULL,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_comments`
--

INSERT INTO `ticket_comments` (`id`, `ticket_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES
(1, 15, 3, 'abv', '2025-08-19 07:32:20', '2025-08-19 07:32:20'),
(4, 16, 3, 'abc', '2025-08-19 22:37:02', '2025-08-19 22:37:02'),
(14, 16, 1, 'abc', '2025-08-22 04:54:46', '2025-08-22 04:54:46'),
(16, 16, 1, 'fdasfjdj afkldj fkjdafk jdafdj asklfj dakl;fjdakl;fjeio ajfkd afkle;j eioafj kdlas;feiajfiod jasklfj eaklfjeioawfj dklsaj;flej aiofje klajfd;ka', '2025-08-22 21:02:28', '2025-08-22 21:02:28'),
(17, 94, 3, 'abc', '2025-08-23 00:41:19', '2025-08-23 00:41:19'),
(24, 94, 1, 'abc', '2025-08-23 00:49:17', '2025-08-23 00:49:17'),
(25, 95, 1, 'abc', '2025-08-23 01:54:38', '2025-08-23 01:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_files`
--

CREATE TABLE `ticket_files` (
  `id` bigint UNSIGNED NOT NULL,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_files`
--

INSERT INTO `ticket_files` (`id`, `ticket_id`, `file_path`, `original_name`, `mime_type`, `size`, `created_at`, `updated_at`) VALUES
(2, 15, 'tickets/15/BHAvWwldlkucnTudRky6JOgEitejG46RVWHspCCd.jpg', '20250811.jpg', 'image/jpeg', 819079, '2025-08-19 04:45:12', '2025-08-19 04:45:12');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_user`
--

CREATE TABLE `ticket_user` (
  `id` bigint UNSIGNED NOT NULL,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_user`
--

INSERT INTO `ticket_user` (`id`, `ticket_id`, `user_id`, `created_at`, `updated_at`) VALUES
(6, 15, 3, '2025-08-19 04:45:12', '2025-08-19 04:45:12'),
(8, 16, 3, '2025-08-19 22:36:11', '2025-08-19 22:36:11'),
(9, 16, 1, '2025-08-19 22:36:11', '2025-08-19 22:36:11'),
(26, 15, 10, '2025-08-21 22:32:37', '2025-08-21 22:32:37'),
(37, 16, 10, '2025-08-22 20:05:21', '2025-08-22 20:05:21'),
(38, 94, 3, '2025-08-23 00:40:33', '2025-08-23 00:40:33'),
(39, 94, 1, '2025-08-23 00:41:41', '2025-08-23 00:41:41'),
(40, 95, 3, '2025-08-23 01:54:35', '2025-08-23 01:54:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Regular User', 'user', 'user@example.com', '2025-08-15 03:35:18', '$2y$12$yJVUFc1SbMPSA7REutiqyewZoOQ0U9r5vIDRks7UpDbrvlKXVIeVS', NULL, '2025-08-15 03:35:18', '2025-08-15 03:35:18'),
(2, 'Admin', 'admin', 'admin@example.com', '2025-08-15 03:42:34', '$2y$12$gQ/mPTh5Zl1Cm9B0ob17Ru0mYLp8Em5ZtQMw/A4K/QfQVjA5SQ1by', NULL, '2025-08-15 03:40:10', '2025-08-19 02:46:26'),
(3, 'Andy Chan', 'mchan3', 'mchan3@central.uh.edu', NULL, '$2y$12$UD4ECpwj1SNdCoDckLcmF.TCxF7Fl3fA77R1bDJyNTDaVIThztvge', NULL, '2025-08-15 03:40:11', '2025-08-19 05:58:55'),
(9, 'Test Admin', 'testadmin', 'testadmin@example.com', '2025-08-20 05:18:08', '$2y$12$7LIsWzNiz7by8SEVlBxPfeUmHagjE0LIz2ZuXgiwdGU3LVEt/HmrG', NULL, '2025-08-20 05:18:08', '2025-08-20 05:18:08'),
(10, 'ticket admin', 'ticket', 'andy741231@hotmail.com', NULL, '$2y$12$7oLF7YzPjOufcBGV9EbiVemzXZ4wvdEM23lcENGhlPx9o/aANCyX2', NULL, '2025-08-21 04:22:09', '2025-08-21 04:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_permission_overrides`
--

CREATE TABLE `user_permission_overrides` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED DEFAULT NULL,
  `effect` enum('allow','deny') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apps`
--
ALTER TABLE `apps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `apps_slug_unique` (`slug`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`team_id`,`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  ADD KEY `model_has_permissions_permission_id_foreign` (`permission_id`),
  ADD KEY `model_has_permissions_team_foreign_key_index` (`team_id`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`team_id`,`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  ADD KEY `model_has_roles_role_id_foreign` (`role_id`),
  ADD KEY `model_has_roles_team_foreign_key_index` (`team_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`),
  ADD UNIQUE KEY `permissions_key_unique` (`key`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_team_id_name_guard_name_unique` (`team_id`,`name`,`guard_name`),
  ADD UNIQUE KEY `roles_team_slug_unique` (`team_id`,`slug`),
  ADD KEY `roles_team_foreign_key_index` (`team_id`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `temp_files`
--
ALTER TABLE `temp_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temp_files_user_id_index` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tickets_user_id_foreign` (`user_id`),
  ADD KEY `tickets_status_index` (`status`),
  ADD KEY `tickets_priority_index` (`priority`),
  ADD KEY `tickets_created_at_index` (`created_at`),
  ADD KEY `tickets_due_date_index` (`due_date`),
  ADD KEY `tickets_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_comments_user_id_foreign` (`user_id`),
  ADD KEY `ticket_comments_ticket_id_created_at_index` (`ticket_id`,`created_at`);

--
-- Indexes for table `ticket_files`
--
ALTER TABLE `ticket_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_files_ticket_id_index` (`ticket_id`);

--
-- Indexes for table `ticket_user`
--
ALTER TABLE `ticket_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_user_ticket_id_user_id_unique` (`ticket_id`,`user_id`),
  ADD KEY `ticket_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_username_unique` (`username`);

--
-- Indexes for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_perm_override_unique` (`user_id`,`permission_id`,`team_id`),
  ADD KEY `user_perm_overrides_user_team_index` (`user_id`,`team_id`),
  ADD KEY `user_perm_overrides_permission_index` (`permission_id`),
  ADD KEY `user_permission_overrides_team_id_foreign` (`team_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apps`
--
ALTER TABLE `apps`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `temp_files`
--
ALTER TABLE `temp_files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ticket_files`
--
ALTER TABLE `ticket_files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ticket_user`
--
ALTER TABLE `ticket_user`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `temp_files`
--
ALTER TABLE `temp_files`
  ADD CONSTRAINT `temp_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD CONSTRAINT `ticket_comments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_files`
--
ALTER TABLE `ticket_files`
  ADD CONSTRAINT `ticket_files_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_user`
--
ALTER TABLE `ticket_user`
  ADD CONSTRAINT `ticket_user_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD CONSTRAINT `user_permission_overrides_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_overrides_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_overrides_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
