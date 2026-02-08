/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

-- =========================================
-- Nexus-IaaS Database Schema
-- =========================================

-- Drop existing tables (for clean installation)
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `task_queue`;
DROP TABLE IF EXISTS `instances`;
DROP TABLE IF EXISTS `ip_pool`;
DROP TABLE IF EXISTS `users`;

-- =========================================
-- Users Table
-- =========================================
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `is_admin` TINYINT(1) DEFAULT 0,
    `is_banned` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_is_admin` (`is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- IP Pool Table
-- =========================================
CREATE TABLE `ip_pool` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `subnet_mask` VARCHAR(45) DEFAULT '255.255.255.0',
    `gateway` VARCHAR(45) NOT NULL,
    `is_allocated` TINYINT(1) DEFAULT 0,
    `allocated_to` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_is_allocated` (`is_allocated`),
    INDEX `idx_ip_address` (`ip_address`),
    FOREIGN KEY (`allocated_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Instances Table
-- =========================================
CREATE TABLE `instances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `vmid` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `vcpu` INT DEFAULT 1,
    `ram` INT DEFAULT 1024 COMMENT 'RAM in MB',
    `disk` INT DEFAULT 20 COMMENT 'Disk in GB',
    `os_template` VARCHAR(100) DEFAULT 'ubuntu-22.04',
    `status` ENUM('pending', 'creating', 'running', 'stopped', 'error', 'deleted') DEFAULT 'pending',
    `proxmox_node` VARCHAR(50) DEFAULT 'pve',
    `price_per_hour` DECIMAL(10,4) DEFAULT 0.5000,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_vmid` (`vmid`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Task Queue Table
-- =========================================
CREATE TABLE `task_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL COMMENT 'create, start, stop, delete, rebuild',
    `payload_json` JSON NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `result_json` JSON NULL,
    `error_message` TEXT NULL,
    `retry_count` INT DEFAULT 0,
    `max_retries` INT DEFAULT 3,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL,
    `finished_at` TIMESTAMP NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Audit Logs Table
-- =========================================
CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) NULL COMMENT 'instance, user, ip_pool',
    `resource_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` JSON NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_action` (`action`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- Create Default Admin User
-- Password: Admin@123456 (CHANGE THIS IMMEDIATELY!)
-- =========================================
INSERT INTO `users` (`email`, `password_hash`, `balance`, `is_admin`) VALUES
('admin@nexus-iaas.local', '$argon2id$v=19$m=65536,t=4,p=1$ZjBhY3JudzJzb2VoZGVjZg$xqJ5F8xqZ3Q9vL6xV8yH3p2R1wK4nM7tG9sU5eA2cD8', 1000.00, 1);

-- =========================================
-- Seed Sample IP Pool
-- =========================================
INSERT INTO `ip_pool` (`ip_address`, `gateway`) VALUES
('192.168.100.10', '192.168.100.1'),
('192.168.100.11', '192.168.100.1'),
('192.168.100.12', '192.168.100.1'),
('192.168.100.13', '192.168.100.1'),
('192.168.100.14', '192.168.100.1'),
('192.168.100.15', '192.168.100.1'),
('192.168.100.16', '192.168.100.1'),
('192.168.100.17', '192.168.100.1'),
('192.168.100.18', '192.168.100.1'),
('192.168.100.19', '192.168.100.1');
