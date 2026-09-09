-- ============================================================
-- FITCORE GYM MANAGEMENT SYSTEM — XAMPP / MySQL Database
-- Database: fitcore_gym
-- For XAMPP: import via http://localhost/phpmyadmin -> Import
-- ============================================================
-- XAMPP Setup:
-- 1. Start Apache + MySQL in XAMPP Control Panel
-- 2. Open phpMyAdmin (http://localhost/phpmyadmin)
-- 3. Create database "fitcore_gym" (utf8mb4_unicode_ci)
-- 4. Import this file
-- 5. Copy this project folder to C:/xampp/htdocs/fitcore/
-- 6. Visit http://localhost/fitcore/
-- ============================================================

CREATE DATABASE IF NOT EXISTS `fitcore_gym` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fitcore_gym`;

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ----------------------------------------------------------
-- Table: users (admin, staff, trainer)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(40) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `contact` VARCHAR(30) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','staff','trainer') NOT NULL,
  `status` ENUM('active','pending','locked') NOT NULL DEFAULT 'active',
  `avatar` LONGTEXT DEFAULT NULL,
  `coach_name` VARCHAR(100) DEFAULT NULL,
  `specializations` JSON DEFAULT NULL,
  `available_days` JSON DEFAULT NULL,
  `available_from` VARCHAR(20) DEFAULT NULL,
  `available_to` VARCHAR(20) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `created_at` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: plans
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` VARCHAR(20) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `duration` INT NOT NULL COMMENT 'months',
  `sessions` VARCHAR(20) NOT NULL COMMENT 'number or Unlimited',
  `benefits` TEXT DEFAULT NULL,
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: members
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(40) DEFAULT NULL,
  `contact` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `plan_id` VARCHAR(20) DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `plan_start` DATE DEFAULT NULL,
  `qr_nonce` VARCHAR(20) DEFAULT NULL,
  `qr_token` VARCHAR(100) DEFAULT NULL,
  `avatar` LONGTEXT DEFAULT NULL,
  `age` VARCHAR(10) DEFAULT NULL,
  `sex` ENUM('Male','Female','Other','') DEFAULT '',
  `dob` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `ec_name` VARCHAR(100) DEFAULT NULL,
  `ec_num` VARCHAR(30) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('Active','Expired','Suspended','Expiring Soon','pending_payment','Archived','Pending') NOT NULL DEFAULT 'Active',
  `created_at` DATE DEFAULT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_by_username` VARCHAR(40) DEFAULT NULL,
  `created_by_role` VARCHAR(20) DEFAULT NULL,
  `bg_check_status` VARCHAR(20) DEFAULT 'Pending',
  `bg_check_date` DATE DEFAULT NULL,
  `bg_check_by` VARCHAR(100) DEFAULT NULL,
  `bg_check_notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `plan_id` (`plan_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_members_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: payments
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` VARCHAR(20) NOT NULL,
  `member_id` VARCHAR(20) NOT NULL,
  `member_name` VARCHAR(100) NOT NULL,
  `plan_id` VARCHAR(20) DEFAULT NULL,
  `plan_name` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `date` DATE NOT NULL,
  `new_expiry` DATE DEFAULT NULL,
  `method` ENUM('Cash','GCash','Maya') DEFAULT 'Cash',
  `notes` TEXT DEFAULT NULL,
  `recorded_by` VARCHAR(100) DEFAULT NULL,
  `recorded_by_username` VARCHAR(40) DEFAULT NULL,
  `status` ENUM('Paid','Pending') NOT NULL DEFAULT 'Paid',
  `created_at` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `fk_payments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: sessions (trainer schedule)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(20) NOT NULL,
  `trainer_id` VARCHAR(20) DEFAULT NULL,
  `trainer_name` VARCHAR(100) DEFAULT NULL,
  `member_id` VARCHAR(20) DEFAULT NULL,
  `member_name` VARCHAR(100) DEFAULT NULL,
  `date` DATE NOT NULL,
  `start` VARCHAR(20) NOT NULL,
  `end` VARCHAR(20) NOT NULL,
  `type` VARCHAR(50) DEFAULT 'Personal Training',
  `status` ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_by_username` VARCHAR(40) DEFAULT NULL,
  `created_by_role` VARCHAR(20) DEFAULT NULL,
  `created_at` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `member_id` (`member_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: attendance
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` VARCHAR(20) NOT NULL,
  `member_id` VARCHAR(20) NOT NULL,
  `member_name` VARCHAR(100) DEFAULT NULL,
  `date` DATE NOT NULL,
  `time` VARCHAR(20) DEFAULT NULL,
  `check_in` VARCHAR(20) DEFAULT NULL,
  `check_in_ts` BIGINT DEFAULT NULL,
  `check_out` VARCHAR(20) DEFAULT NULL,
  `check_out_ts` BIGINT DEFAULT NULL,
  `duration` VARCHAR(20) DEFAULT NULL,
  `recorded_by` VARCHAR(100) DEFAULT NULL,
  `scanned_by` VARCHAR(100) DEFAULT NULL,
  `source` ENUM('staff','qr','manual') DEFAULT 'staff',
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `date` (`date`),
  CONSTRAINT `fk_attendance_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: walkins
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `walkins`;
CREATE TABLE `walkins` (
  `id` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(30) DEFAULT NULL,
  `fee` DECIMAL(10,2) NOT NULL,
  `date` DATE NOT NULL,
  `time` VARCHAR(20) DEFAULT NULL,
  `recorded_by` VARCHAR(100) DEFAULT NULL,
  `recorded_by_username` VARCHAR(40) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: notifications (pending_payment etc)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` VARCHAR(20) NOT NULL,
  `member_id` VARCHAR(20) NOT NULL,
  `plan_id` VARCHAR(20) DEFAULT NULL,
  `type` VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
  `status` ENUM('open','resolved') NOT NULL DEFAULT 'open',
  `created_at` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: messages (member <-> staff)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` VARCHAR(20) NOT NULL,
  `member_id` VARCHAR(20) NOT NULL,
  `member_name` VARCHAR(100) DEFAULT NULL,
  `text` TEXT NOT NULL,
  `time` VARCHAR(20) DEFAULT NULL,
  `date` DATE DEFAULT NULL,
  `ts` BIGINT NOT NULL,
  `direction` ENUM('in','out') NOT NULL COMMENT 'in=member to gym, out=gym to member',
  `read_flag` TINYINT(1) NOT NULL DEFAULT 0,
  `read_by_member` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: announcements
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` VARCHAR(20) NOT NULL,
  `type` VARCHAR(30) DEFAULT 'General',
  `date` DATE DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `text` TEXT DEFAULT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATE DEFAULT NULL,
  `time` VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: activity_log
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` VARCHAR(20) NOT NULL,
  `action` VARCHAR(40) NOT NULL,
  `category` VARCHAR(40) NOT NULL,
  `detail` TEXT DEFAULT NULL,
  `extra` TEXT DEFAULT NULL,
  `by_name` VARCHAR(100) DEFAULT NULL,
  `by_username` VARCHAR(40) DEFAULT NULL,
  `by_role` VARCHAR(20) DEFAULT NULL,
  `at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `at` (`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: settings
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` VARCHAR(40) NOT NULL,
  `fee` DECIMAL(10,2) DEFAULT NULL,
  `secret` VARCHAR(64) DEFAULT NULL,
  `value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table: login_attempts
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `username` VARCHAR(40) NOT NULL,
  `count` INT NOT NULL DEFAULT 0,
  `last` BIGINT NOT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Settings
INSERT INTO `settings` (`id`, `fee`, `secret`) VALUES
('walkin', 100.00, NULL),
('qr', NULL, SHA2(CONCAT('fc-secret-', UNIX_TIMESTAMP(), '-', RAND()), 256));

-- Plans
INSERT INTO `plans` (`id`, `name`, `price`, `duration`, `sessions`, `benefits`, `status`) VALUES
('pl1', 'Basic', 500.00, 1, '8', 'Gym access\nLocker use', 'Active'),
('pl2', 'Standard', 900.00, 1, '16', 'Gym access\nLocker use\n1 trainer session', 'Active'),
('pl3', 'Premium', 1500.00, 3, 'Unlimited', 'Full access\nPriority trainer\nFree assessment', 'Active');

-- Users (passwords: admin123, staff123, trainer123 — bcrypt $2y$10$)
INSERT INTO `users` (`id`, `name`, `username`, `password_hash`, `role`, `status`, `contact`, `created_at`, `coach_name`, `specializations`, `available_days`, `available_from`, `available_to`, `bio`) VALUES
('u1', 'System Admin', 'admin', '$2y$10$.b9joZwlXj5CA1LWY1f0q.caYXl3mEE106PptyDTBRtQbwa4HadZG', 'admin', 'active', '09150435696', CURDATE(), NULL, NULL, NULL, NULL, NULL, NULL),
('u2', 'Marie Santos', 'staff', '$2y$10$Dfcaym6ElpgNDkGxBG3Ol.3B.bLHDL/U.5iXS.TG0RaLEUuJ3o6s.', 'staff', 'active', '09171234568', CURDATE(), NULL, NULL, NULL, NULL, NULL, NULL),
('u3', 'Coach Ryan', 'trainer', '$2y$10$SmR/V6EbzJZ68kMqTQhYEu9MHgf5PcffSMPsbChRWpnjp/Nin21r2', 'trainer', 'active', '09171234569', CURDATE(), 'Coach Ryan', '["Personal Training","Strength Training"]', '["Mon","Tue","Wed","Thu","Fri"]', '6:00 AM', '6:00 PM', 'Certified personal trainer.');

-- Members (5 active/expired + 1 pending_payment)
INSERT INTO `members` (`id`, `name`, `contact`, `age`, `sex`, `plan_id`, `start_date`, `expiry_date`, `status`, `created_at`, `created_by`, `created_by_username`, `created_by_role`, `bg_check_status`, `bg_check_date`, `bg_check_by`) VALUES
('MEM-0001', 'Stephen Hugo', '09171000001', '28', 'Male', 'pl2', DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Active', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Marie Santos', 'staff', 'staff', 'Cleared', DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Marie Santos'),
('MEM-0002', 'Mike Delavega', '09171000002', '32', 'Male', 'pl1', DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Active', DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'Marie Santos', 'staff', 'staff', 'Cleared', DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'Marie Santos'),
('MEM-0003', 'Christan Aranez', '09171000003', '25', 'Male', 'pl3', DATE_SUB(CURDATE(), INTERVAL 88 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Active', DATE_SUB(CURDATE(), INTERVAL 88 DAY), 'Marie Santos', 'staff', 'staff', 'Cleared', DATE_SUB(CURDATE(), INTERVAL 88 DAY), 'Marie Santos'),
('MEM-0004', 'Sam Ervin Cuajor', '09171000004', '30', 'Male', 'pl1', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Expired', DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Marie Santos', 'staff', 'staff', 'Cleared', DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Marie Santos'),
('MEM-0005', 'Janwell Nacario', '09171000005', '27', 'Male', 'pl2', DATE_SUB(CURDATE(), INTERVAL 29 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Active', DATE_SUB(CURDATE(), INTERVAL 29 DAY), 'Marie Santos', 'staff', 'staff', 'Cleared', DATE_SUB(CURDATE(), INTERVAL 29 DAY), 'Marie Santos'),
('MEM-0006', 'Nicole Ramos', '09171000006', '22', 'Female', 'pl2', NULL, NULL, 'pending_payment', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Self', 'nicole', 'member', 'Pending', NULL, NULL);

-- Update Nicole with login credentials (username/email/password)
UPDATE `members` SET `username`='nicole', `email`='nicole.ramos@example.com', `password_hash`='$2y$10$xa2hLXTp4cZr.VLmznPs2Ofo.TSqT/rErXLuCwt8fZEqvFa.i79my' WHERE `id`='MEM-0006';

-- Payments
INSERT INTO `payments` (`id`, `member_id`, `member_name`, `plan_id`, `plan_name`, `amount`, `date`, `new_expiry`, `method`, `recorded_by`, `recorded_by_username`, `status`, `created_at`) VALUES
('PAY-0001', 'MEM-0001', 'Stephen Hugo', 'pl2', 'Standard', 900.00, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Cash', 'Marie Santos', 'staff', 'Paid', DATE_SUB(CURDATE(), INTERVAL 25 DAY)),
('PAY-0002', 'MEM-0002', 'Mike Delavega', 'pl1', 'Basic', 500.00, DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Cash', 'Marie Santos', 'staff', 'Paid', DATE_SUB(CURDATE(), INTERVAL 28 DAY)),
('PAY-0003', 'MEM-0003', 'Christan Aranez', 'pl3', 'Premium', 1500.00, DATE_SUB(CURDATE(), INTERVAL 88 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'GCash', 'Marie Santos', 'staff', 'Paid', DATE_SUB(CURDATE(), INTERVAL 88 DAY)),
('PAY-0004', 'MEM-0004', 'Sam Ervin Cuajor', 'pl1', 'Basic', 500.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Cash', 'Marie Santos', 'staff', 'Paid', DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
('PAY-0005', 'MEM-0005', 'Janwell Nacario', 'pl2', 'Standard', 900.00, DATE_SUB(CURDATE(), INTERVAL 29 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Cash', 'Marie Santos', 'staff', 'Paid', DATE_SUB(CURDATE(), INTERVAL 29 DAY));

-- Notifications (pending payment)
INSERT INTO `notifications` (`id`, `member_id`, `plan_id`, `type`, `status`, `created_at`) VALUES
('NTF-0001', 'MEM-0006', 'pl2', 'pending_payment', 'open', DATE_SUB(CURDATE(), INTERVAL 1 DAY));

-- Demo activity log
INSERT INTO `activity_log` (`id`, `action`, `category`, `detail`, `extra`, `by_name`, `by_username`, `by_role`, `at`) VALUES
('ACT0001', 'Signup', 'Member', 'Nicole Ramos', 'ID: MEM-0006 | Plan: Standard | Awaiting front-desk payment', 'Nicole Ramos', 'nicole', 'member', NOW());

SET FOREIGN_KEY_CHECKS=1;
