-- =============================================
-- Dr. Zohrabi Nutrition Clinic Database Schema
-- Version: 2.0
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- =============================================
-- Users Table
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `status` ENUM('active', 'banned') DEFAULT 'active',
    `email_verified` TINYINT(1) DEFAULT 0,
    `verification_token` VARCHAR(255) DEFAULT NULL,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `remember_expiry` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_role` (`role`),
    INDEX `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- User Profiles Table
-- =============================================
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `user_id` INT UNSIGNED PRIMARY KEY,
    `bio` TEXT DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Sessions Table (for session management)
-- =============================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `payload` LONGTEXT,
    `last_activity` INT UNSIGNED NOT NULL,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_session_token` (`session_token`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Activity Logs Table
-- =============================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Password Resets Table
-- =============================================
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Site Settings Table
-- =============================================
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Insert Default Admin User
-- Password: Admin@12345 (hashed with bcrypt cost 12)
-- =============================================
INSERT INTO `users` (`fullname`, `email`, `password_hash`, `role`, `status`, `email_verified`) VALUES
('Administrator', 'admin@zohrabi.com', '$2y$12$LJ3m4ks9hK/XTx.rYcL7r.CJvZ1.nW3n5gEJh6F7kL9mN1oP2qR4S', 'admin', 'active', 1);

-- Default password hash: Admin@12345

-- =============================================
-- Insert Default Settings
-- =============================================
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', '"Dr. Zohrabi Nutrition Clinic"'),
('site_email', '"info@zohrabi.com"'),
('site_phone', '"+98 900 000 0000"'),
('site_address', '"Tehran, Iran"'),
('maintenance_mode', 'false');
