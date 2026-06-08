-- Database Schema for Investment Platform

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tradeonix_db`
--
CREATE DATABASE IF NOT EXISTS `tradeonix_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tradeonix_db`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_sent_at` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `balance` decimal(30,15) NOT NULL DEFAULT 0.00,
  `language` varchar(10) NOT NULL DEFAULT 'en_US',
  `country` varchar(2) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `kyc_status` enum('not_submitted','pending','approved','rejected') NOT NULL DEFAULT 'not_submitted',
  `status` enum('active','banned') NOT NULL DEFAULT 'active',
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `role` (`role`),
  KEY `status` (`status`),
  KEY `referred_by` (`referred_by`),
  KEY `email_verification_token` (`email_verification_token`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

CREATE TABLE `investment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `roi_percentage` decimal(5,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `min_amount` decimal(30,15) NOT NULL,
  `max_amount` decimal(30,15) NOT NULL,
  `payout_interval` enum('hourly','daily','end_of_term','custom') NOT NULL,
  `payout_interval_type` enum('minutes','hours','days','weeks','months') DEFAULT NULL COMMENT 'Time unit for custom intervals',
  `payout_interval_value` int(11) DEFAULT NULL COMMENT 'Numeric value for custom intervals',
  `capital_return` tinyint(1) NOT NULL DEFAULT 1,
  `is_compounding` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable compound interest for end_of_term plans',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `waiting_period_value` INT NOT NULL DEFAULT 0,
  `waiting_period_unit` ENUM('seconds','minutes','hours','days','weeks') NOT NULL DEFAULT 'days',
  `country` varchar(2) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2 country code; NULL = global plan',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `is_featured` (`is_featured`),
  KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(30,15) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `next_payout_date` datetime NOT NULL,
  `payout_interval_type` enum('minutes','hours','days','weeks','months') DEFAULT NULL COMMENT 'Snapshot of plan interval type',
  `payout_interval_value` int(11) DEFAULT NULL COMMENT 'Snapshot of plan interval value',
  `total_profit_earned` decimal(30,15) NOT NULL DEFAULT 0.00,
  `is_compounding` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Snapshot of plan compounding setting',
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `waiting_period_value` INT NOT NULL DEFAULT 0,
  `waiting_period_unit` ENUM('seconds','minutes','hours','days','weeks') NOT NULL DEFAULT 'days',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `status` (`status`),
  KEY `next_payout_date` (`next_payout_date`),
  KEY `status_next_payout` (`status`,`next_payout_date`),
  KEY `idx_custom_interval` (`payout_interval_type`,`payout_interval_value`),
  CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `investment_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdrawal','profit','referral','investment','refund','cancellation_penalty') NOT NULL,
  `amount` decimal(30,15) NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `user_created` (`user_id`,`created_at`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(30,15) NOT NULL,
  `fee_amount` decimal(30,15) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(30,15) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) NOT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `local_currency_amount` DECIMAL(30,15) DEFAULT NULL,
  `local_currency_code` VARCHAR(3) DEFAULT NULL,
  `exchange_rate_used` DECIMAL(20,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(30,15) NOT NULL,
  `fee_amount` decimal(30,15) NOT NULL,
  `net_amount` decimal(30,15) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `account_details` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `withdrawals_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kyc_documents`
--

CREATE TABLE `kyc_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_passport_path` varchar(255) DEFAULT NULL,
  `proof_address_path` varchar(255) DEFAULT NULL,
  `selfie_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_kyc_unique` (`user_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `kyc_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kyc_documents_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `bonus_amount` decimal(30,15) NOT NULL,
  `trigger_event` enum('registration','first_deposit','first_investment','every_deposit','every_investment') NOT NULL,
  `status` enum('pending','credited') NOT NULL DEFAULT 'credited',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`),
  KEY `trigger_event` (`trigger_event`),
  KEY `status` (`status`),
  UNIQUE KEY `unique_referral` (`referrer_id`,`referred_id`,`trigger_event`,`created_at`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Investment Platform'),
('site_logo', ''),
('contact_email', 'admin@example.com'),
('secondary_language', 'fr_FR'),
('kyc_required', 'no'),
('kyc_timing', 'before_withdrawal'),
('withdrawal_fee_percentage', '2'),
('minimum_withdrawal', '1'),
('referral_bonus_type', 'flat'),
('referral_bonus_amount', '10'),
('referral_bonus_trigger', 'first_deposit'),
('cancellation_penalty_mode', 'percentage'),
('cancellation_penalty_percentage', '10'),
('cancellation_penalty_flat', '5.00'),
('cancellation_forfeit_profits', 'no'),
('require_email_verification', 'no'),
-- Email Notification Settings
('email_user_registration', 'yes'),
('email_user_verification', 'yes'),
('email_password_reset', 'yes'),
('email_deposit_submitted_user', 'yes'),
('email_deposit_approved_user', 'yes'),
('email_deposit_rejected_user', 'yes'),
('email_deposit_submitted_admin', 'yes'),
('email_withdrawal_submitted_user', 'yes'),
('email_withdrawal_approved_user', 'yes'),
('email_withdrawal_rejected_user', 'yes'),
('email_withdrawal_submitted_admin', 'yes'),
('email_investment_created_user', 'yes'),
('email_investment_completed_user', 'yes'),
('email_investment_cancelled_user', 'yes'),
('email_profit_payout_user', 'yes'),
('email_kyc_approved_user', 'yes'),
('email_kyc_rejected_user', 'yes'),
('email_kyc_submitted_admin', 'yes'),
('email_referral_bonus_user', 'yes'),
('accepted_countries', '[]'),
('default_country', '');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;