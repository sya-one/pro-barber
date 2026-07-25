-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 15, 2026 at 07:27 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `professional_barbershop`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barbers`
--

CREATE TABLE `barbers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'default.jpg',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 30.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barbers`
--

INSERT INTO `barbers` (`id`, `user_id`, `full_name`, `email`, `phone`, `photo`, `commission_rate`, `is_active`, `created_at`) VALUES
(4, 6, 'Vukani', 'vukani@probarber.co.za', '', 'default.jpg', 30.00, 1, '2026-05-21 06:06:06'),
(5, 7, 'Bob', 'bobo@probarber.co.za', '', 'default.jpg', 30.00, 1, '2026-05-21 06:06:50'),
(6, 8, 'Nduduzo', 'ndu@probarber.co.za', '', 'default.jpg', 30.00, 1, '2026-05-21 06:07:25'),
(7, 9, 'Ayo', 'ayo@probarber.co.za', '', 'default.jpg', 30.00, 1, '2026-05-21 08:22:41');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `barber_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `booking_type` enum('online','walk-in') NOT NULL DEFAULT 'online',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `visit_count` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `preferred_barber_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `email`, `phone`, `visit_count`, `total_spent`, `loyalty_points`, `preferred_barber_id`, `notes`, `created_at`) VALUES
(1, 'Khetha Chonco', 'kechonco2@gmail.com', '0629773550', 0, 0.00, 0, NULL, '', '2026-05-20 04:28:45'),
(2, 'Siyabonga Chonco', 'siyabongac@horsementech.com', '0643557840', 0, 0.00, 0, NULL, '', '2026-05-20 04:28:45'),
(3, 'Warras', 'warras@chonco.org', '0818988888', 0, 0.00, 0, NULL, NULL, '2026-05-20 13:12:01'),
(4, 'Sthembiso Ndlovu', 'ndlovupromise38@gmail.com', '0653176435', 0, 0.00, 0, NULL, NULL, '2026-05-21 03:16:06'),
(5, 'Xolani', 'xolany.gumede@gmail.com', '0737841410', 0, 0.00, 0, NULL, NULL, '2026-05-21 08:43:03'),
(6, 'Thulani', 'Thulani.bob@cloud.com', '0799586684', 0, 0.00, 0, NULL, NULL, '2026-05-21 08:50:23'),
(7, 'Ayo', 'mabuzankululeko333@gmail.com', '0846544804', 0, 0.00, 0, NULL, NULL, '2026-05-21 16:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

CREATE TABLE `loyalty_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earned','redeemed','manual') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','eft') NOT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `product_size` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT 'default-product.png',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `description`, `price`, `stock_quantity`, `product_size`, `image`, `is_active`, `created_at`) VALUES
(1, 'PROD-00001', 'Pro Beard Oil', 'Description:\r\nA premium beard oil formulated to nourish, soften, and hydrate facial hair while reducing itchiness and dryness. Enriched with natural oils for a smooth finish and healthy shine without leaving a greasy feel. Perfect for daily grooming and beard maintenance.\r\nKey Benefits:\r\nSoftens coarse beard hair\r\nMoisturizes skin underneath\r\nPromotes healthy beard growth appearance\r\nLightweight non-greasy formula\r\nProfessional barbershop scent', 129.00, 42, '30ml', 'product_1780045303_6a1955f7d986b.png', 1, '2026-05-29 09:01:43'),
(2, 'PROD-00002', 'Premium Durag', 'Description:\r\nLuxury satin durag designed for maximum wave compression, moisture retention, and overnight protection. Crafted with a smooth premium finish for comfort, style, and long-lasting durability.\r\nKey Benefits:\r\nHelps maintain waves and hairstyles\r\nComfortable stretch fit\r\nBreathable satin material\r\nReduces hair frizz and breakage\r\nProfessional premium look\r\nSize: One Size Fits All', 99.00, 15, 'one size fit all', 'product_1780054123_6a19786bca431.png', 1, '2026-05-29 09:18:25'),
(3, 'PROD-00003', 'Pro Styling Gel – Aloe', 'Description:\r\nStrong-hold styling gel infused with Aloe Vera to provide lasting hold without flaking. Designed for modern styling while keeping hair moisturized, healthy-looking, and smooth throughout the day.\r\nKey Benefits:\r\nStrong long-lasting hold\r\nNon-flaking formula\r\nAloe Vera infused\r\nAdds shine and definition\r\nSuitable for daily styling', 149.99, 4, '300ml', 'product_1780046368_6a195a2010f82.png', 1, '2026-05-29 09:19:28'),
(4, 'PROD-00004', 'Starter Grooming Kit', 'Upgrade your grooming routine with the Professional Barbers Starter Grooming Kit — a complete premium collection designed for modern men who value style, confidence, and professional care. This bundle combines our best-selling essentials to help you maintain healthy hair, sharp waves, and a well-groomed beard every day.\r\nWhat’s Included:\r\nBeard Oil (30ml)\r\nNourishes, softens, and hydrates beard hair while promoting a healthy shine and smooth finish.\r\nPremium Satin Durag\r\nDesigned for maximum wave compression, moisture retention, and overnight hair protection with a comfortable premium fit.\r\nStyling Gel Aloe (300ml)\r\nStrong-hold, non-flaking styling gel infused with Aloe Vera for long-lasting control and healthy-looking hair.\r\nWhy Choose This Kit?\r\nProfessional barbershop quality\r\nPremium modern packaging\r\nDaily grooming essentials in one bundle\r\nPerfect for waves, beard care, and styling\r\nSuitable for all hair types', 349.99, 8, '', 'product_1780047174_6a195d463b5bd.png', 1, '2026-05-29 09:32:54');

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `walkin_name` varchar(100) DEFAULT NULL,
  `barber_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `status` enum('waiting','called','in_service','completed') NOT NULL DEFAULT 'waiting',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `invoice_number` varchar(20) DEFAULT NULL,
  `payment_method` enum('cash','card','eft') NOT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `item_type` enum('product','service') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'in minutes',
  `image` varchar(255) DEFAULT 'default-service.jpg',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `duration`, `image`, `is_active`, `created_at`) VALUES
(1, 'Edge Up', '', 50.00, 30, 'classic.jpg', 1, '2026-05-20 04:28:45'),
(2, 'Beard Trim & Shape', 'Hot towel and expert beard sculpting.', 40.00, 20, 'beard.jpg', 1, '2026-05-20 04:28:45'),
(3, 'Haircut & Beard Combo', 'Full haircut plus beard trim.', 190.00, 45, 'combo.jpg', 1, '2026-05-20 04:28:45'),
(4, 'Kids Haircut', 'Stylish cut for the little gentlemen.', 100.00, 35, 'kids.jpg', 1, '2026-05-20 04:28:45'),
(5, 'Chieskop', '', 60.00, 30, 'shave.jpg', 1, '2026-05-20 04:28:45'),
(6, 'Adult Cut', '', 150.00, 40, 'service_1779369365_6a0f0595a2a17.jpg', 1, '2026-05-20 09:14:13'),
(7, 'Color', '', 100.00, 30, 'default-service.jpg', 1, '2026-05-20 09:18:10'),
(8, 'Wash and Blow', '', 80.00, 20, 'default-service.jpg', 1, '2026-05-20 09:18:35'),
(9, 'Blond', '', 100.00, 20, 'default-service.jpg', 1, '2026-05-20 09:18:51');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'shop_name', 'The Professional 🟢 Barbershop'),
(2, 'address', '16 Blaine St, KwaDukuza Central, KwaDukuza, 4449, South Africa'),
(3, 'currency', 'R'),
(4, 'timezone', 'Africa/Johannesburg'),
(5, 'logo', 'logo.png'),
(6, 'loyalty_rate', '0.1'),
(7, 'invoice_template', '<div style=\"font-family: Arial, sans-serif; max-width: 650px; margin: auto; padding: 20px; border: 1px solid #ddd; background: #fff;\">\r\n\r\n  <!-- HEADER -->\r\n\r\n  <div style=\"text-align:center; border-bottom: 2px solid #111; padding-bottom: 12px;\">\r\n\r\n\r\n<!-- LOGO -->\r\n<img src=\"/barbershop-system/assets/images/default-avatar.png\"\r\n     alt=\"Shop Logo\"\r\n     style=\"max-height:80px; margin-bottom:10px;\">\r\n\r\n<h2 style=\"margin:0; font-size:24px; letter-spacing:1px;\">\r\n  {{shop_name}}\r\n</h2>\r\n\r\n<p style=\"margin:5px 0; font-size:12px; color:#555;\">\r\n  {{shop_address}} | {{shop_phone}}\r\n</p>\r\n\r\n<p style=\"margin:5px 0; font-size:12px;\">\r\n  <strong>Invoice:</strong> {{invoice_number}}\r\n</p>\r\n\r\n\r\n  </div>\r\n\r\n  <!-- CUSTOMER INFO -->\r\n\r\n  <div style=\"margin-top:15px; font-size:13px;\">\r\n    <p style=\"margin:3px 0;\"><strong>Customer:</strong> {{customer_name}}</p>\r\n    <p style=\"margin:3px 0;\"><strong>Date:</strong> {{date}}</p>\r\n    <p style=\"margin:3px 0;\"><strong>Payment Method:</strong> {{payment_method}}</p>\r\n<p style=\"margin:3px 0;\"><strong>Loyalty Points Earned:</strong> {{loyalty_points}}</p>\r\n    <p style=\"margin:3px 0;\"><strong>Handled By:</strong> {{barber_name}}</p>\r\n  </div>\r\n\r\n  <hr style=\"margin:15px 0; border:0; border-top:1px dashed #999;\">\r\n\r\n  <!-- ITEMS -->\r\n\r\n  <table style=\"width:100%; border-collapse: collapse; font-size:13px;\">\r\n    <thead>\r\n      <tr style=\"background:#000000; color:#fff;\">\r\n        <th style=\"text-align:left; padding:8px;\">Item</th>\r\n        <th style=\"text-align:center; padding:8px;\">Qty</th>\r\n        <th style=\"text-align:right; padding:8px;\">Price</th>\r\n        <th style=\"text-align:right; padding:8px;\">Total</th>\r\n      </tr>\r\n    </thead>\r\n    <tbody>\r\n      {{items_table}}\r\n    </tbody>\r\n  </table>\r\n\r\n  <!-- TOTAL -->\r\n\r\n  <div style=\"margin-top:15px; text-align:right;\">\r\n    <p style=\"font-size:16px; margin:5px 0;\">\r\n      <strong>Total: {{total}}</strong>\r\n    </p>\r\n  </div>\r\n\r\n  <hr style=\"margin:15px 0; border:0; border-top:1px dashed #999;\">\r\n\r\n  <!-- FOOTER -->\r\n\r\n  <div style=\"text-align:center; font-size:12px; color:#555;\">\r\n    <p style=\"margin:5px 0;\">Thank you for choosing us 💈</p>\r\n    <p style=\"margin:5px 0;\">Fresh cuts. Fresh confidence.</p>\r\n    <p style=\"margin:5px 0; font-size:11px;\">\r\n      Powered by {{shop_name}} POS System\r\n    </p>\r\n  </div>\r\n\r\n</div>\r\n'),
(8, 'smtp_host', 'mail.horsementech.com'),
(9, 'smtp_port', '465'),
(10, 'smtp_username', 'notificactions@horsementech.com'),
(11, 'smtp_password', 'Ihhashi@44'),
(12, 'smtp_encryption', 'ssl');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','barber','receptionist') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `last_login`, `remember_token`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$CuVHrfUN7BV7/5A7y482nulZl.CWPcQ6pI7pRDYfz35OcqgJqNpBW', 'admin', 'Admin Master', 'admin@probarber.co.za', '0612345678', '2026-06-15 17:17:00', '1aa7ba18ecf8ea9aae147e65c91517cee227c9ff2bf5a6cfdf6b812a2e7509a6', 1, '2026-05-20 04:28:45'),
(4, 'reception', '$2y$10$.HfCx3Gs7RbNpnnwgFwj0OMLbw6XoJcwIdY1vIzjuXCFVYQaMrJpG', 'receptionist', 'Reception', 'info@probarber.co.za', '0723333333', '2026-06-01 22:07:39', '16a0b0927b13ce579634c9afdd9ff86e60b37e0055a224d2d5f7ecce03f644c5', 1, '2026-05-20 04:28:45'),
(6, 'vukani', '$2y$10$yag2FCcI6HNfu8YoiCLyge/d8Oi5gUrk1KqKslwJTclBVLYibJiB.', 'barber', 'Vukani', 'vukani@probarber.co.za', '', NULL, NULL, 1, '2026-05-21 06:06:06'),
(7, 'bobo', '$2y$10$GbeNhloTefAqb8qBCYFcUeFzhFAChb0MXu/VWqXUYgPvyoll7vA/W', 'barber', 'Thulani Mthembu', 'bobo@probarber.co.za', '', '2026-05-29 09:50:43', NULL, 1, '2026-05-21 06:06:50'),
(8, 'ndu', '$2y$10$1M.h1FL6g.f3q.XEkeQ1n.cC7iMALcqVu1v1IHm/fAa1xk4irOdt6', 'barber', 'Nduduzo', 'ndu@probarber.co.za', '', NULL, NULL, 1, '2026-05-21 06:07:25'),
(9, 'Ayo', '$2y$10$v5rqCbE6hdfdK0TKbquKOu4drzH7uH8G64dA/DfXFbXGDd7hDJdX6', 'barber', 'Ayo', 'ayo@probarber.co.za', '', NULL, NULL, 1, '2026-05-21 08:22:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `barbers`
--
ALTER TABLE `barbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `barber_id` (`barber_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_bookings_customer_date` (`customer_id`,`booking_date`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `preferred_barber_id` (`preferred_barber_id`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `barber_id` (`barber_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_sales_invoice` (`invoice_number`),
  ADD KEY `idx_sales_customer` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_sale_items_sale_id` (`sale_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barbers`
--
ALTER TABLE `barbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `barbers`
--
ALTER TABLE `barbers`
  ADD CONSTRAINT `barbers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`preferred_barber_id`) REFERENCES `barbers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `queue_ibfk_2` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`),
  ADD CONSTRAINT `queue_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
