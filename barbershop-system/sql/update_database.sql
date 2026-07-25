-- ============================================================
-- DATABASE UPDATE SCRIPT FOR PROFESSIONAL BARBERSHOP
-- This script adds new columns and tables for new features
-- Run this after installing the base database schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- Add barcode column to products
-- ============================================
ALTER TABLE `products` ADD COLUMN `barcode` varchar(50) DEFAULT NULL AFTER `product_code`;
ALTER TABLE `products` ADD UNIQUE KEY `barcode` (`barcode`);

-- ============================================
-- Add cost_price to products
-- ============================================
ALTER TABLE `products` ADD COLUMN `cost_price` decimal(10,2) DEFAULT 0.00 AFTER `price`;

-- ============================================
-- Add commission_type to barbers
-- ============================================
ALTER TABLE `barbers` ADD COLUMN `commission_type` enum('percentage','fixed','tiered') DEFAULT 'percentage' AFTER `commission_rate`;

-- ============================================
-- Add payment_status to sales
-- ============================================
ALTER TABLE `sales` ADD COLUMN `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending' AFTER `payment_method`;
ALTER TABLE `sales` ADD COLUMN `paystack_reference` varchar(100) DEFAULT NULL AFTER `payment_status`;

-- ============================================
-- Add status to payments
-- ============================================
ALTER TABLE `payments` ADD COLUMN `status` enum('pending','paid','failed','refunded') DEFAULT 'paid' AFTER `transaction_code`;

-- ============================================
-- Add branch_id to bookings
-- ============================================
ALTER TABLE `bookings` ADD COLUMN `branch_id` int(11) DEFAULT 1 AFTER `booking_type`;

-- ============================================
-- Add branch_id to sales
-- ============================================
ALTER TABLE `sales` ADD COLUMN `branch_id` int(11) DEFAULT 1 AFTER `invoice_number`;

-- ============================================
-- Add branch_id to queue
-- ============================================
ALTER TABLE `queue` ADD COLUMN `branch_id` int(11) DEFAULT 1 AFTER `created_at`;

-- ============================================
-- Add paid_by to commissions table
-- ============================================
ALTER TABLE `commissions` ADD COLUMN `paid_by` int(11) DEFAULT NULL AFTER `paid_at`;
ALTER TABLE `commissions` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`;

-- ============================================
-- Add entity columns to activity_logs
-- ============================================
ALTER TABLE `activity_logs` ADD COLUMN `entity` varchar(100) DEFAULT NULL AFTER `action`;
ALTER TABLE `activity_logs` ADD COLUMN `entity_id` int(11) DEFAULT NULL AFTER `entity`;
ALTER TABLE `activity_logs` ADD COLUMN `old_value` text DEFAULT NULL AFTER `entity_id`;
ALTER TABLE `activity_logs` ADD COLUMN `new_value` text DEFAULT NULL AFTER `old_value`;
ALTER TABLE `activity_logs` ADD COLUMN `user_agent` varchar(255) DEFAULT NULL AFTER `new_value`;

-- ============================================
-- Create branches table
-- ============================================
CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` text,
  `city` varchar(100),
  `province` varchar(100),
  `postal_code` varchar(20),
  `country` varchar(100) DEFAULT 'South Africa',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default branch
INSERT IGNORE INTO `branches` (`id`, `name`, `address`, `city`, `province`, `postal_code`, `is_active`) 
VALUES (1, 'The Professional Barbershop', '16 Blaine St, KwaDukuza Central', 'KwaDukuza', 'KwaDukuza', '4449', 1);

-- ============================================
-- Create suppliers table
-- ============================================
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `contact_person` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create expense_categories table
-- ============================================
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default expense categories
INSERT IGNORE INTO `expense_categories` (`id`, `name`) VALUES
(1, 'Rent'),
(2, 'Electricity'),
(3, 'Water'),
(4, 'Salaries'),
(5, 'Products'),
(6, 'Equipment'),
(7, 'Marketing'),
(8, 'Internet'),
(9, 'Maintenance'),
(10, 'Other');

-- ============================================
-- Create expenses table
-- ============================================
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `description` text,
  `receipt_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create purchase_orders table
-- ============================================
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `status` enum('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create po_items table
-- ============================================
CREATE TABLE IF NOT EXISTS `po_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create stock_adjustments table
-- ============================================
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `type` enum('increase','decrease') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255),
  `created_by` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create cash_ups table
-- ============================================
CREATE TABLE IF NOT EXISTS `cash_ups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cashier_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `cash_sales` decimal(10,2) DEFAULT 0.00,
  `card_sales` decimal(10,2) DEFAULT 0.00,
  `eft_sales` decimal(10,2) DEFAULT 0.00,
  `paystack_sales` decimal(10,2) DEFAULT 0.00,
  `yaco_sales` decimal(10,2) DEFAULT 0.00,
  `online_payments` decimal(10,2) DEFAULT 0.00,
  `refunds` decimal(10,2) DEFAULT 0.00,
  `total_expected` decimal(10,2) DEFAULT 0.00,
  `actual_counted` decimal(10,2) DEFAULT 0.00,
  `variance` decimal(10,2) DEFAULT 0.00,
  `notes` text,
  `status` enum('open','submitted','approved','closed') NOT NULL DEFAULT 'open',
  `branch_id` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashier_id` (`cashier_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `cash_ups_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_ups_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create refunds table
-- ============================================
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `refunds_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Create commissions table (enhanced)
-- ============================================
CREATE TABLE IF NOT EXISTS `commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barber_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL,
  `status` enum('earned','paid') NOT NULL DEFAULT 'earned',
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barber_id` (`barber_id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Add Paystack settings
-- ============================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('paystack_public_key', ''),
('paystack_secret_key', ''),
('paystack_payment_url', 'https://api.paystack.co'),
('paystack_use_test_mode', '1');

-- ============================================
-- Add Loyalty Tier settings
-- ============================================
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('loyalty_rate', '0.1'),
('loyalty_tier_bronze', '0-999'),
('loyalty_tier_silver', '1000-2499'),
('loyalty_tier_gold', '2500-4999'),
('loyalty_tier_vip', '5000-999999');

COMMIT;