-- -------------------------------------------------------------
-- TablePlus 7.1.0(710)
--
-- https://tableplus.com/
--
-- Database: rupiachat
-- Generation Time: 2026-05-29 19:54:34.4410
-- -------------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) /*T![clustered_index] CLUSTERED */,
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) /*T![clustered_index] CLUSTERED */,
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `call_logs`;
CREATE TABLE `call_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `caller_id` bigint unsigned NOT NULL,
  `receiver_id` bigint unsigned DEFAULT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `group_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('voice','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'voice',
  `status` enum('missed','answered','declined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'missed',
  `duration` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `call_logs_caller_id_foreign` (`caller_id`),
  KEY `call_logs_receiver_id_foreign` (`receiver_id`),
  KEY `call_logs_caller_id_created_at_index` (`caller_id`,`created_at`),
  KEY `call_logs_receiver_id_created_at_index` (`receiver_id`,`created_at`),
  CONSTRAINT `call_logs_caller_id_foreign` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_logs_receiver_id_foreign` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=150001;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `group_members`;
CREATE TABLE `group_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('admin','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_cleared_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `group_members_group_id_user_id_unique` (`group_id`,`user_id`),
  KEY `group_members_user_id_foreign` (`user_id`),
  CONSTRAINT `group_members_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

DROP TABLE IF EXISTS `group_messages`;
CREATE TABLE `group_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_size` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `group_messages_group_id_foreign` (`group_id`),
  KEY `group_messages_sender_id_foreign` (`sender_id`),
  KEY `group_messages_group_id_index` (`group_id`),
  CONSTRAINT `group_messages_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `groups_creator_id_foreign` (`creator_id`),
  CONSTRAINT `groups_creator_id_foreign` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_size` bigint unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_room_id_index` (`room_id`),
  KEY `messages_room_id_sender_id_is_read_index` (`room_id`,`sender_id`,`is_read`),
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=266105;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=246362;

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=4060001;

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `feature_slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `feature_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `purchases_user_id_foreign` (`user_id`),
  CONSTRAINT `purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=60001;

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `last_cleared_at` timestamp NULL DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `rooms_room_id_user_id_unique` (`room_id`,`user_id`),
  KEY `rooms_user_id_foreign` (`user_id`),
  CONSTRAINT `rooms_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

DROP TABLE IF EXISTS `user_features`;
CREATE TABLE `user_features` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `feature_slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_id` bigint unsigned DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `user_features_user_id_foreign` (`user_id`),
  UNIQUE KEY `user_features_user_id_feature_slug_unique` (`user_id`,`feature_slug`),
  CONSTRAINT `user_features_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=60001;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '0',
  `fcm_token` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'topup',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `wallet_transactions_user_id_foreign` (`user_id`),
  UNIQUE KEY `wallet_transactions_midtrans_order_id_unique` (`order_id`),
  CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=120001;

DROP TABLE IF EXISTS `wallets`;
CREATE TABLE `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `wallets_user_id_foreign` (`user_id`),
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=60001;

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('rupiachat-cache-otp_backupcobacoba@gmail.com', 'a:5:{s:4:\"code\";s:6:\"221189\";s:4:\"name\";s:8:\"Test OTP\";s:5:\"phone\";s:12:\"081299999999\";s:5:\"email\";s:24:\"backupcobacoba@gmail.com\";s:8:\"password\";s:6:\"123456\";}', 1780034479),
('rupiachat-cache-otp_dimaswijil7@gmail.com', 'a:5:{s:4:\"code\";s:6:\"200803\";s:4:\"name\";s:11:\"dimaswijill\";s:5:\"phone\";s:13:\"0896089178712\";s:5:\"email\";s:21:\"dimaswijil7@gmail.com\";s:8:\"password\";s:6:\"123456\";}', 1780035594),
('rupiachat-cache-otp_dimaswijilpamungkas@gmail.com', 'a:5:{s:4:\"code\";s:6:\"048813\";s:4:\"name\";s:21:\"Dimas Wijil Pamungkas\";s:5:\"phone\";s:12:\"089608179170\";s:5:\"email\";s:29:\"dimaswijilpamungkas@gmail.com\";s:8:\"password\";s:6:\"123456\";}', 1780029342);

INSERT INTO `call_logs` (`id`, `caller_id`, `receiver_id`, `group_id`, `group_name`, `channel_name`, `type`, `status`, `duration`, `created_at`, `updated_at`) VALUES
(1, 1, 2, NULL, NULL, 'call_1_2', 'video', 'missed', 0, '2026-05-28 04:58:40', '2026-05-28 04:58:40'),
(2, 2, 1, NULL, NULL, 'call_1_2', 'video', 'missed', 0, '2026-05-28 04:58:43', '2026-05-28 04:58:43'),
(3, 1, 2, NULL, NULL, 'call_1_2', 'video', 'missed', 0, '2026-05-28 04:58:50', '2026-05-28 04:58:50'),
(4, 1, 2, NULL, NULL, 'call_1_2', 'video', 'answered', 30, '2026-05-28 04:59:41', '2026-05-28 04:59:41'),
(5, 2, 1, NULL, NULL, 'call_1_2', 'video', 'missed', 34, '2026-05-28 04:59:45', '2026-05-28 04:59:45'),
(30001, 1, 2, NULL, NULL, 'call_1_2', 'video', 'missed', 0, '2026-05-28 14:24:10', '2026-05-28 14:24:10'),
(60001, 2, 1, NULL, NULL, 'call_1_2', 'video', 'answered', 13, '2026-05-28 16:49:17', '2026-05-28 16:49:17'),
(60002, 1, 2, NULL, NULL, 'call_1_2', 'video', 'missed', 16, '2026-05-28 16:49:20', '2026-05-28 16:49:20'),
(90001, 2, NULL, 1, 'MTI', 'group_1', 'video', 'answered', 32, '2026-05-28 20:15:32', '2026-05-28 20:15:32'),
(90002, 2, NULL, 1, 'MTI', 'group_1', 'video', 'missed', 46, '2026-05-28 20:16:37', '2026-05-28 20:16:37'),
(90003, 2, NULL, 1, 'MTI', 'group_1', 'video', 'missed', 169, '2026-05-28 20:44:53', '2026-05-28 20:44:53'),
(120001, 1, 2, NULL, NULL, 'call_1_2', 'video', 'answered', 27, '2026-05-29 03:31:24', '2026-05-29 03:31:24'),
(120002, 2, 1, NULL, NULL, 'call_1_2', 'video', 'missed', 30, '2026-05-29 03:31:26', '2026-05-29 03:31:26');

INSERT INTO `group_members` (`id`, `group_id`, `user_id`, `role`, `is_pinned`, `joined_at`, `last_cleared_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'admin', 0, '2026-05-28 20:14:49', NULL, '2026-05-28 20:14:49', '2026-05-28 20:14:49'),
(2, 1, 2, 'member', 0, '2026-05-28 20:14:49', NULL, '2026-05-28 20:14:49', '2026-05-28 20:14:49');

INSERT INTO `group_messages` (`id`, `group_id`, `sender_id`, `text`, `type`, `amount`, `media_url`, `media_type`, `media_name`, `media_size`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', 'call', NULL, NULL, NULL, NULL, NULL, '2026-05-28 20:14:51', '2026-05-28 20:14:51'),
(2, 1, 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', 'call', NULL, NULL, NULL, NULL, NULL, '2026-05-28 20:15:46', '2026-05-28 20:15:46'),
(3, 1, 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', 'call', NULL, NULL, NULL, NULL, NULL, '2026-05-28 20:41:57', '2026-05-28 20:41:57');

INSERT INTO `groups` (`id`, `name`, `description`, `photo`, `creator_id`, `created_at`, `updated_at`) VALUES
(1, 'MTI', NULL, NULL, 1, '2026-05-28 20:14:49', '2026-05-28 20:14:49');

INSERT INTO `messages` (`id`, `room_id`, `sender_id`, `text`, `media_url`, `media_type`, `media_name`, `media_size`, `is_read`, `type`, `amount`, `created_at`, `updated_at`) VALUES
(1, '1_2', 2, 'storage/messages_documents/6kdgF8n90xkM9adeSUQGWr51GLWyheYVsWjSBVaO.pdf', 'storage/messages_documents/6kdgF8n90xkM9adeSUQGWr51GLWyheYVsWjSBVaO.pdf', 'application/pdf', 'LHU_1734321052383.pdf', 822330, 1, 'pdf', NULL, '2026-05-28 04:41:48', '2026-05-28 04:41:59'),
(30001, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 04:58:18', '2026-05-28 04:58:48'),
(30002, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 04:58:20', '2026-05-28 04:58:48'),
(30003, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 04:58:59', '2026-05-28 04:59:01'),
(30004, '1_2', 1, 'storage/messages_documents/8yHYogT1QClnGTNcw01U8gSz0p98TnojDV4A5U53.pdf', 'storage/messages_documents/8yHYogT1QClnGTNcw01U8gSz0p98TnojDV4A5U53.pdf', 'application/pdf', 'bsi_estatment-2026-05-26 12:36:16.pdf', 162222, 1, 'pdf', NULL, '2026-05-28 05:04:35', '2026-05-28 20:25:06'),
(86105, '1_2', 1, 'alo', NULL, NULL, NULL, NULL, 1, 'text', NULL, '2026-05-28 14:23:48', '2026-05-28 20:25:06'),
(86106, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 14:23:53', '2026-05-28 20:25:06'),
(86107, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 14:24:26', '2026-05-28 20:25:06'),
(86108, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 14:26:52', '2026-05-28 20:25:06'),
(116105, '1_2', 1, 'hi', NULL, NULL, NULL, NULL, 1, 'text', NULL, '2026-05-28 16:11:05', '2026-05-28 20:25:06'),
(116106, '1_2', 1, 'alo', NULL, NULL, NULL, NULL, 1, 'text', NULL, '2026-05-28 16:48:48', '2026-05-28 20:25:06'),
(116107, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-28 16:48:50', '2026-05-28 20:25:06'),
(116108, '1_2', 1, 'storage/messages/1bZl9kmYPXs6qdUydEPmJZa1aaWZIEoNySlanwtY.png', 'storage/messages/1bZl9kmYPXs6qdUydEPmJZa1aaWZIEoNySlanwtY.png', 'image/png', 'image_picker_C2AA1508-BEC2-4206-8459-30E66E5AC5AE-2845-0000005D67014C9A.png', 214397, 1, 'image', NULL, '2026-05-28 17:24:12', '2026-05-28 20:25:06'),
(146105, '1_2', 1, 'storage/messages/cnG1i6JIecP0H1IbwoEepgBJRnKpfYDV7P22fdMU.png', 'storage/messages/cnG1i6JIecP0H1IbwoEepgBJRnKpfYDV7P22fdMU.png', 'image/png', 'image_picker_2FE862CE-E46A-4E16-9B46-AEF4E6B11257-3896-0000008E521840E4.png', 259603, 1, 'image', NULL, '2026-05-28 19:54:23', '2026-05-28 20:25:06'),
(146106, '1_2', 1, 'storage/messages/3KvduW1FiOqNJcHuCPbPVNXV4zSXUeMs4RHddZ1z.png', 'storage/messages/3KvduW1FiOqNJcHuCPbPVNXV4zSXUeMs4RHddZ1z.png', 'image/png', 'image_picker_FBDD9B5C-F452-4642-8E71-8E619DF8099D-3896-0000008E6AC85859.png', 1037241, 1, 'image', NULL, '2026-05-28 19:54:40', '2026-05-28 20:25:06'),
(146107, '1_2', 1, 'storage/messages/QKbGl09oBhTCdVssOERien5PISOvhmfv6YSCsTjW.jpg', 'storage/messages/QKbGl09oBhTCdVssOERien5PISOvhmfv6YSCsTjW.jpg', 'image/jpeg', 'image_picker_FF576A13-C40C-4F47-8A00-18E5394DA8E0-3896-0000008E7BC9733C.jpg', 2231130, 1, 'image', NULL, '2026-05-28 19:54:53', '2026-05-28 20:25:06'),
(146108, '1_2', 1, 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1779998110743_bsi_estatment-2026-05-26%2012:36:16.pdf', 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1779998110743_bsi_estatment-2026-05-26%2012:36:16.pdf', 'application/pdf', '1779998110743_bsi_estatment-2026-05-26%2012:36:16.pdf', NULL, 1, 'pdf', NULL, '2026-05-28 19:55:12', '2026-05-28 20:25:06'),
(146109, '1_2', 1, 'deym', 'storage/messages/T8yqvmSb2iu3V0MVRxK6mb2iCY7Yo0sJgBQOxqRa.png', 'image/png', 'image_picker_29DD860D-676F-40CC-8045-13CC3C229133-4396-000000932CC464B4.png', 259932, 1, 'image', NULL, '2026-05-28 20:08:56', '2026-05-28 20:25:06'),
(146110, '1_2', 2, 'storage/messages/fQCQSDT3kTdx860Mb41Z00YFKXzXrwFbAq1UExFv.jpg', 'storage/messages/fQCQSDT3kTdx860Mb41Z00YFKXzXrwFbAq1UExFv.jpg', 'image/jpeg', 'scaled_Lia & Ozy-136.jpg', 582250, 1, 'image', NULL, '2026-05-28 20:25:40', '2026-05-28 20:26:09'),
(146111, '1_2', 1, 'deym', 'storage/messages/aDcVoxNXISvBCpSU26lTjIx8QffjVtskLs7t7fsG.png', 'image/png', 'image_picker_0F6332B7-5ED6-490C-9919-2FD55BEF24CB-4601-000000990E3BBC4E.png', 1449258, 1, 'image', NULL, '2026-05-28 20:26:29', '2026-05-28 20:26:29'),
(146112, '1_2', 2, 'deym', NULL, NULL, NULL, NULL, 1, 'text', NULL, '2026-05-28 20:33:06', '2026-05-28 20:40:53'),
(146113, '1_2', 2, 'bisa dong', NULL, NULL, NULL, NULL, 1, 'text', NULL, '2026-05-28 20:33:09', '2026-05-28 20:40:53'),
(146114, '1_2', 1, 'deym', 'storage/messages/D8UTZ6ZRkuRj8efQ8ETWtH1mgYFD7cZoLiefzm8U.png', 'image/png', 'image_picker_D6DF28D0-7444-420A-8082-7271F090E836-4735-0000009DF5EB5DE6.png', 889798, 1, 'image', NULL, '2026-05-28 20:41:05', '2026-05-28 20:41:11'),
(146115, '1_2', 1, 'storage/messages/i8NYvU1vFnzDGrDQqGv5JDMMdAkM9m6x7Tv5SUyV.jpg', 'storage/messages/i8NYvU1vFnzDGrDQqGv5JDMMdAkM9m6x7Tv5SUyV.jpg', 'image/jpeg', 'image_picker_63F26374-C1F6-47BF-92BC-1431B5A9FE1F-4776-000000A05B0C5E8B.jpg', 32491, 1, 'image', NULL, '2026-05-28 20:48:11', '2026-05-28 20:49:08'),
(146116, '1_2', 1, 'storage/messages/aCCdMKF61rcIiHpsGUK1jKYBKQEcx7Ue3rpg4sel.png', 'storage/messages/aCCdMKF61rcIiHpsGUK1jKYBKQEcx7Ue3rpg4sel.png', 'image/png', 'image_picker_7DABB4B4-39E2-4B95-962B-C47BAD1EDD43-4776-000000A178A699C3.png', 504946, 1, 'image', NULL, '2026-05-28 20:51:32', '2026-05-29 02:22:55'),
(146117, '1_2', 1, 'uovouv', 'storage/messages/IX78TdCLKVfeM0QQRyywhxsHJPGT85RFqAprNMsn.png', 'image/png', 'image_picker_DA9FE73E-A94A-44B0-9E92-8C061D4C74A8-4776-000000A21D4FFD59.png', 504946, 1, 'image', NULL, '2026-05-28 20:53:28', '2026-05-29 02:22:55'),
(146118, '1_2', 1, 'storage/messages/ErC1zYOpFH0rTiQoxpnPCnq18dA3mrRMzAdUA40q.png', 'storage/messages/ErC1zYOpFH0rTiQoxpnPCnq18dA3mrRMzAdUA40q.png', 'image/png', 'image_picker_C341C506-54E4-44EB-ACCE-E8D681528BE7-4952-000000A3E9E30F0B.png', 312182, 1, 'image', NULL, '2026-05-28 20:58:49', '2026-05-29 02:22:55'),
(146119, '1_2', 2, 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1780021477594_LHU_1734321052383.pdf', 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1780021477594_LHU_1734321052383.pdf', 'application/pdf', '1780021477594_LHU_1734321052383.pdf', NULL, 1, 'pdf', NULL, '2026-05-29 02:24:39', '2026-05-29 02:37:57'),
(176105, '1_2', 1, '{\"call_type\":\"video\",\"status\":\"missed\",\"duration\":0}', NULL, NULL, NULL, NULL, 1, 'call', NULL, '2026-05-29 03:30:51', '2026-05-29 03:30:52'),
(176106, '1_2', 1, 'storage/messages/OtnBD6Yu30NzFDrhW7sZp32mfhCfFmicmjqE3i3q.png', 'storage/messages/OtnBD6Yu30NzFDrhW7sZp32mfhCfFmicmjqE3i3q.png', 'image/png', 'image_picker_C47D0F1B-E56F-4137-A899-E34496DC7CB1-556-00000017F8239851.png', 904303, 1, 'image', NULL, '2026-05-29 03:32:46', '2026-05-29 03:33:34'),
(206105, '1_2', 1, 'aman brow?', NULL, NULL, NULL, NULL, 0, 'text', NULL, '2026-05-29 04:52:44', '2026-05-29 04:52:44'),
(236105, '1_2', 1, 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1780033777574_bsi_estatment-2026-05-26%2012:36:16.pdf', 'https://udojvwycokcaoiqffmob.supabase.co/storage/v1/object/public/messages_documents/1780033777574_bsi_estatment-2026-05-26%2012:36:16.pdf', 'application/pdf', '1780033777574_bsi_estatment-2026-05-26%2012:36:16.pdf', NULL, 0, 'pdf', NULL, '2026-05-29 05:49:39', '2026-05-29 05:49:39'),
(236106, '1_2', 1, 'subs', NULL, NULL, NULL, NULL, 0, 'text', NULL, '2026-05-29 05:49:51', '2026-05-29 05:49:51'),
(236107, '1_2', 1, 'storage/messages/12f23DODF1tfmmtfNrqneoMtOTkdxhDgglxxvlE9.png', 'storage/messages/12f23DODF1tfmmtfNrqneoMtOTkdxhDgglxxvlE9.png', 'image/png', 'image_picker_8FA20091-E377-44EE-AB6C-1AA15EFFC7A2-2601-0000004599350C81.png', 172545, 0, 'image', NULL, '2026-05-29 05:50:05', '2026-05-29 05:50:05'),
(236108, '1_2', 1, 'deym', 'storage/messages/W6p8ixcLpEF8YbsuufMqv5bugG9IDi8TlA5HeZf1.png', 'image/png', 'image_picker_C253A634-712E-4B4E-97C3-B3A07A2D3A9B-2601-00000045A945D831.png', 172545, 0, 'image', NULL, '2026-05-29 05:50:18', '2026-05-29 05:50:18');

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_03_16_010955_create_personal_access_tokens_table', 1),
(5, '2026_03_16_011004_add_is_online_to_users_table', 1),
(6, '2026_03_16_011204_create_messages_table', 1),
(7, '2026_04_05_131534_add_profile_photo_to_users_table', 1),
(8, '2026_04_06_043212_add_phone_to_users_table', 1),
(9, '2026_04_07_034341_add_media_columns_to_messages_table', 1),
(10, '2026_04_07_064922_add_is_read_to_messages_table', 1),
(11, '2026_04_09_014018_create_rooms_table', 1),
(12, '2026_04_09_041323_add_is_pinned_to_rooms_table', 1),
(13, '2026_04_09_044341_add_fcm_token_to_users_table', 1),
(14, '2026_04_09_080000_create_wallets_table', 1),
(15, '2026_04_09_080001_create_wallet_transactions_table', 1),
(16, '2026_04_10_000001_create_groups_table', 2),
(17, '2026_04_10_000002_create_group_members_table', 2),
(18, '2026_04_10_000003_create_group_messages_table', 2),
(19, '2026_04_10_000004_add_is_pinned_to_group_members_table', 2),
(20, '2026_04_13_081153_add_last_cleared_at_to_rooms_and_group_members', 2),
(21, '2026_04_17_000000_add_description_to_wallet_transactions_table', 2),
(22, '2026_04_27_010536_create_call_logs_table', 2),
(23, '2026_04_27_064441_add_group_fields_to_call_logs_table', 2),
(24, '2026_05_25_000001_create_purchases_table', 2),
(25, '2026_05_25_000002_create_user_features_table', 2),
(26, '2026_05_25_000003_create_settings_table', 2),
(216362, '2026_05_28_154858_rename_midtrans_order_id_in_wallet_transactions_table', 3);

INSERT INTO `purchases` (`id`, `user_id`, `feature_slug`, `feature_name`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'vip_member', 'VIP Member', 50000.00, 'completed', '2026-05-28 04:35:33', '2026-05-28 04:35:33'),
(30001, 2, 'theme_pro', 'Tema Pro', 5000.00, 'completed', '2026-05-29 02:23:33', '2026-05-29 02:23:33'),
(30002, 2, 'vip_member', 'VIP Member', 50000.00, 'completed', '2026-05-29 02:26:29', '2026-05-29 02:26:29');

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('raLimfGBx9VTVa9Y0gSa1fOlCp5N9XOtMbKfiVjF', NULL, '100.64.0.8', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYzVzdGlUR0RiOER1dFlEOXQ4Y25IS0YzdHNGaXVtY2xUQ3VsT1dDVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDY6Imh0dHA6Ly9ydXBpYWNoYXQtYXBpLXByb2R1Y3Rpb24udXAucmFpbHdheS5hcHAiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6Q0c5dmVDRUVkZGdwWGwzQiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1779982548);

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'exchange_rate_usd_idr', '{\"rate\":17851,\"date\":\"2026-05-28\"}', '2026-05-28 04:35:09', '2026-05-29 04:52:53');

INSERT INTO `user_features` (`id`, `user_id`, `feature_slug`, `purchase_id`, `activated_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'vip_member', 1, '2026-05-28 04:35:33', '2026-05-28 04:35:33', '2026-05-28 04:35:33'),
(30001, 2, 'theme_pro', 30001, '2026-05-29 02:23:33', '2026-05-29 02:23:33', '2026-05-29 02:23:33'),
(30002, 2, 'vip_member', 30002, '2026-05-29 02:26:29', '2026-05-29 02:26:29', '2026-05-29 02:26:29');

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `profile_photo`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `is_online`, `fcm_token`) VALUES
(1, 'Dimas Wijil Pamungkas', 'dimaswijill@gmail.com', '0896089176470', 'http://rupiachat-api-production.up.railway.app/storage/photos/6NOgdH7ggXBAgj0pZdBxvbkQy0lqK12fSm1NkHhB.jpg', NULL, '$2y$12$4YtZYg0N9lXIcCZcuBNjzOD20Fwf83UlMmrV8rIa19VVJr5iAKBE2', NULL, '2026-05-28 04:34:57', '2026-05-29 05:50:58', 0, NULL),
(2, 'mcflyon', 'magangmcflyon@gmail.com', '089608179179', NULL, NULL, '$2y$12$zZJKIBfUPUXGUf3/oj5MBOT.zC3bz.PqH0gqKFVcT8haiCAZo6LmS', NULL, '2026-05-28 04:41:18', '2026-05-29 05:30:27', 0, NULL);

INSERT INTO `wallet_transactions` (`id`, `user_id`, `order_id`, `amount`, `description`, `reference_user_id`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'TOPUP-1-1779978332', 1000000.00, NULL, NULL, 'topup', 'pending', '2026-05-28 14:25:32', '2026-05-28 14:25:32'),
(30001, 2, 'TOPUP-2-1779981008', 1000000.00, NULL, NULL, 'topup', 'pending', '2026-05-28 15:10:08', '2026-05-28 15:10:08'),
(30002, 2, 'TOPUP-2-1779981043', 100000.00, NULL, NULL, 'topup', 'pending', '2026-05-28 15:10:43', '2026-05-28 15:10:43'),
(30003, 2, 'TOPUP-2-1779981079', 100000.00, NULL, NULL, 'topup', 'pending', '2026-05-28 15:11:19', '2026-05-28 15:11:19'),
(60001, 1, 'TOPUP-1-1779982410', 100000.00, NULL, NULL, 'topup', 'success', '2026-05-28 15:33:30', '2026-05-28 17:33:50'),
(60002, 1, 'TOPUP-1-1779982560', 1000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 15:36:00', '2026-05-28 17:36:50'),
(60003, 1, 'TOPUP-1-1779983847', 1000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 15:57:27', '2026-05-28 16:42:50'),
(60004, 1, 'TOPUP-1-1779983880', 1000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 15:58:00', '2026-05-28 16:43:50'),
(60005, 1, 'TOPUP-1-1779984943', 1000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 16:15:43', '2026-05-28 17:01:50'),
(60006, 1, 'TOPUP-1-1779986018', 1000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 16:33:38', '2026-05-28 16:48:50'),
(60007, 1, 'TOPUP-1-1779986453', 100000.00, NULL, NULL, 'topup', 'success', '2026-05-28 16:40:53', '2026-05-28 16:41:02'),
(60008, 1, 'TOPUP-1-1779986481', 1000000000000.00, NULL, NULL, 'topup', 'pending', '2026-05-28 16:41:21', '2026-05-28 16:41:21'),
(60009, 1, 'TOPUP-1-1779986497', 100000000.00, NULL, NULL, 'topup', 'success', '2026-05-28 16:41:37', '2026-05-28 16:41:43'),
(90001, 1, 'TOPUP-1-1779996252', 9999999.00, NULL, NULL, 'topup', 'success', '2026-05-28 19:24:12', '2026-05-28 19:24:24'),
(90002, 2, 'TOPUP-2-1780000419', 100000.00, NULL, NULL, 'topup', 'success', '2026-05-28 20:33:39', '2026-05-28 20:33:55'),
(90003, 1, 'TOPUP-1-1780024235', 11111.00, NULL, NULL, 'topup', 'success', '2026-05-29 03:10:35', '2026-05-29 03:10:45');

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 1, 115261110.00, '2026-05-28 04:35:07', '2026-05-29 03:10:45'),
(30001, 2, 145000.00, '2026-05-28 15:10:01', '2026-05-29 02:26:29');



/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;