-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 12, 2026 at 10:11 AM
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
-- Database: `canteen_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `process_secure_sale` (IN `p_invoice` VARCHAR(50), IN `p_branch` INT, IN `p_product` INT, IN `p_quantity` INT, IN `p_price` DECIMAL(10,2))   BEGIN
    -- 1. ROLLBACK CAPABILITY: Declare an exit handler for errors
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        -- If any error occurs, rollback the entire transaction
        ROLLBACK;
        -- Log the failure
        INSERT INTO system_logs(action, description) 
        VALUES ('SALE ERROR', CONCAT('Transaction rolled back for invoice: ', p_invoice));
    END;

    -- 2. CONCURRENCY CONTROL: Start the explicit transaction
    START TRANSACTION;

    -- 3. LOCKING MECHANISM: Lock the specific stock row so no one else can modify it while we read/update
    SELECT quantity INTO @current_stock 
    FROM stocks 
    WHERE product_id = p_product AND branch_id = p_branch 
    FOR UPDATE; -- <--- This is the explicit lock

    -- Check if we have enough stock before proceeding
    IF @current_stock >= p_quantity THEN
        
        -- Insert the main sale record
        INSERT INTO sales(invoice_no, branch_id, sale_date)
        VALUES(p_invoice, p_branch, CURDATE());
        
        -- Get the ID of the sale we just inserted
        SET @new_sale_id = LAST_INSERT_ID();

        -- Insert the sale item (Your triggers will automatically handle the stock deduction and total update here!)
        INSERT INTO sale_items(sale_id, product_id, quantity, unit_price)
        VALUES(@new_sale_id, p_product, p_quantity, p_price);

        -- Commit the transaction to finalize all changes safely
        COMMIT;
        
    ELSE
        -- Not enough stock, rollback and log it
        ROLLBACK;
        INSERT INTO system_logs(action, description) 
        VALUES ('INSUFFICIENT STOCK', CONCAT('Failed to process invoice: ', p_invoice));
    END IF;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half Day') DEFAULT 'Present',
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `staff_id`, `date`, `time_in`, `time_out`, `status`, `notes`, `recorded_by`, `created_at`) VALUES
(16, 8, '2026-03-11', NULL, NULL, 'Present', '', 12, '2026-03-11 02:30:52'),
(25, 7, '2026-03-11', NULL, NULL, 'Absent', '', 12, '2026-03-11 03:11:19'),
(43, 8, '2026-03-12', NULL, NULL, 'Present', '', 17, '2026-03-12 08:37:14'),
(44, 7, '2026-03-12', NULL, NULL, 'Present', '', 17, '2026-03-12 08:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `code`, `address`) VALUES
(1, 'DPT Canteen', 'DPT', NULL),
(2, 'BE Study Hall', 'BE', NULL),
(3, 'UM Food Hall', 'UM', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prefix` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `prefix`) VALUES
(1, 'Snacks', '2026-03-01 12:22:01', 'A'),
(2, 'Drinks', '2026-03-01 12:22:01', 'B'),
(3, 'Meals', '2026-03-01 12:22:01', 'C'),
(4, 'Desserts', '2026-03-01 12:22:01', 'D'),
(5, 'Fruits', '2026-03-01 12:22:01', 'E'),
(6, 'Others', '2026-03-01 12:22:01', 'F');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_id`, `name`, `category_id`, `cost_price`, `selling_price`, `created_at`) VALUES
(1, 'A01', 'Chippy', 1, 8.00, 15.00, '2026-03-01 12:22:01'),
(2, 'A02', 'Skyflakes', 1, 6.00, 12.00, '2026-03-01 12:22:01'),
(3, 'B01', 'Mineral Water', 2, 10.00, 20.00, '2026-03-01 12:22:01'),
(4, 'B02', 'Coke 500ml', 2, 22.00, 35.00, '2026-03-01 12:22:01'),
(5, 'C01', 'Pancit Canton', 3, 12.00, 25.00, '2026-03-01 12:22:01'),
(6, 'D01', 'Banana Cue', 4, 8.00, 15.00, '2026-03-01 12:22:01'),
(8, 'C02', 'Riceball', 3, 15.00, 25.00, '2026-03-11 20:32:45'),
(9, 'A03', 'Wafello', 1, 12.00, 15.00, '2026-03-12 08:35:36'),
(10, 'C03', 'Chicken Mami', 3, 15.00, 25.00, '2026-03-12 08:36:48');

-- --------------------------------------------------------

--
-- Table structure for table `product_branches`
--

CREATE TABLE `product_branches` (
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_branches`
--

INSERT INTO `product_branches` (`product_id`, `branch_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 1),
(2, 2),
(3, 1),
(3, 2),
(3, 3),
(4, 1),
(4, 2),
(4, 3),
(5, 2),
(5, 3),
(6, 2),
(8, 3),
(9, 1),
(9, 3),
(10, 1),
(10, 3);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_by_staff` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `branch_id`, `total_amount`, `sale_date`, `created_at`, `submitted_by_staff`) VALUES
(23, 'INV-69AE7B24098F9', 1, 675.00, '2026-03-09', '2026-03-09 07:47:48', NULL),
(24, 'INV-69AE82A3DCAD9', 3, 5175.00, '2026-03-09', '2026-03-09 08:19:47', NULL),
(25, 'INV-69AE82C221786', 1, 3510.00, '2026-03-09', '2026-03-09 08:20:18', NULL),
(27, 'INV-69B0DCBAB3FF9', 3, 40.00, '2026-03-11', '2026-03-11 03:08:42', NULL),
(29, 'INV-69B14FF5A68DB', 1, 36.00, '2026-03-11', '2026-03-11 11:20:21', NULL),
(30, 'INV-69B1D0C9E3C04', 1, 200.00, '2026-03-11', '2026-03-11 20:30:01', NULL),
(32, 'INV-69B1D1F115676', 3, 250.00, '2026-03-11', '2026-03-11 20:34:57', 8),
(34, 'INV-69B27D4A63253', 2, 700.00, '2026-03-12', '2026-03-12 08:46:02', NULL),
(35, 'INV-69B27DE36BA1A', 1, 325.00, '2026-03-12', '2026-03-12 08:48:35', NULL);

--
-- Triggers `sales`
--
DELIMITER $$
CREATE TRIGGER `log_new_sale` AFTER INSERT ON `sales` FOR EACH ROW BEGIN
    INSERT INTO system_logs(action,description)
    VALUES(
        'NEW SALE',
        CONCAT('Invoice ',NEW.invoice_no,' created for branch ',NEW.branch_id)
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`) VALUES
(2, 23, 1, 45, 15.00),
(3, 24, 1, 345, 15.00),
(4, 25, 6, 234, 15.00),
(6, 27, 3, 2, 20.00),
(9, 29, 2, 3, 12.00),
(10, 30, 3, 10, 20.00),
(12, 32, 8, 10, 25.00),
(13, 34, 3, 20, 20.00),
(14, 34, 6, 10, 15.00),
(15, 34, 1, 10, 15.00),
(16, 35, 10, 10, 25.00),
(17, 35, 1, 5, 15.00);

--
-- Triggers `sale_items`
--
DELIMITER $$
CREATE TRIGGER `deduct_stock_after_sale` AFTER INSERT ON `sale_items` FOR EACH ROW BEGIN
    UPDATE stocks s
    JOIN sales sa ON sa.id = NEW.sale_id
    SET s.quantity = s.quantity - NEW.quantity
    WHERE s.product_id = NEW.product_id
    AND s.branch_id = sa.branch_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_sales_total` AFTER INSERT ON `sale_items` FOR EACH ROW BEGIN
    UPDATE sales
    SET total_amount = total_amount + (NEW.quantity * NEW.unit_price)
    WHERE id = NEW.sale_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('Cashier','Manager','Cook','Helper') DEFAULT 'Cashier',
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_id`, `full_name`, `role`, `branch_id`, `status`, `email`, `phone`, `created_at`) VALUES
(7, 'SF002', 'Dj ImCookEd', 'Cook', 3, 'Active', '', '', '2026-03-09 12:42:55'),
(8, 'SF003', 'dj Cash', 'Cashier', 1, 'Active', '', '', '2026-03-09 12:50:30');

-- --------------------------------------------------------

--
-- Table structure for table `stocks`
--

CREATE TABLE `stocks` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stocks`
--

INSERT INTO `stocks` (`id`, `product_id`, `branch_id`, `quantity`, `reorder_level`, `last_updated`) VALUES
(1, 1, 1, 13, 0, '2026-03-12 08:48:35'),
(2, 1, 2, 5, 0, '2026-03-12 08:46:02'),
(3, 1, 3, 10, 0, '2026-03-12 08:30:13'),
(4, 2, 1, 17, 0, '2026-03-11 11:20:21'),
(5, 2, 2, 0, 0, '2026-03-12 08:31:01'),
(7, 3, 1, 0, 0, '2026-03-09 07:10:57'),
(8, 3, 2, 30, 0, '2026-03-12 08:46:02'),
(9, 3, 3, 18, 0, '2026-03-11 03:08:42'),
(11, 4, 2, 12, 0, '2026-03-01 12:22:01'),
(19, 8, 3, 20, 0, '2026-03-12 08:30:08'),
(31, 6, 2, 20, 0, '2026-03-12 08:46:02'),
(32, 4, 1, 3, 0, '2026-03-12 08:31:10'),
(34, 4, 3, 50, 0, '2026-03-12 08:30:02'),
(35, 9, 1, 0, 0, '2026-03-12 08:35:36'),
(36, 9, 3, 30, 0, '2026-03-12 08:38:40'),
(37, 5, 3, 100, 0, '2026-03-12 08:38:34'),
(38, 10, 1, 20, 0, '2026-03-12 08:48:35'),
(39, 10, 3, 30, 0, '2026-03-12 08:38:30'),
(40, 5, 2, 0, 0, '2026-03-12 08:57:27');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `action`, `description`, `user_id`, `created_at`) VALUES
(2, 'SALE FAILED', 'Invoice INV-69B27D20A3E59 failed: Insufficient stock for Mineral Water. (Available quantity: 50)', NULL, '2026-03-12 08:45:20'),
(3, 'NEW SALE', 'Invoice INV-69B27D4A63253 created for branch 2', NULL, '2026-03-12 08:46:02'),
(4, 'NEW SALE', 'Invoice INV-69B27DE36BA1A created for branch 1', NULL, '2026-03-12 08:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('staff','admin','super_admin') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `created_at`) VALUES
(12, 'djcortez', '$2y$10$9WQt8vvcq0BcBFBIXZKEee.uU5lNHMfc1B46W2fa8cK1RKBHnUcbW', 'DJ CORTEZ', 'super_admin', '2026-03-09 12:22:46'),
(13, 'dijiicortez', '$2y$10$c6VVwNjmLTHRVqu8B8D1NeJi28Zup1thgFqbKJ9793unvG3fBBDSO', 'Di Jii Cortez', 'admin', '2026-03-09 12:23:29'),
(15, 'sf002', '$2y$10$lJr.y4YzUJxE0jSvuHdl4OJOssfierVG0qT/6Se8oUK2kNQYavB5K', 'Dj ImCookEd', 'staff', '2026-03-09 12:42:55'),
(16, 'sf003', '$2y$10$pVJ5QNxXnpNScmnBTXuEMOf2HAQkF7az8wVu66SFi..GEE7NExL2C', 'dj Cash', 'staff', '2026-03-09 12:50:30'),
(17, 'nat_abella', '$2y$10$DKhcc6MWAaX03h8X2wixdei.QoDw0a0J5PbRrjPZ6kn646EAShZzu', 'Amber Bonilla', 'super_admin', '2026-03-11 10:25:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`staff_id`,`date`),
  ADD KEY `attendance_ibfk_2` (`recorded_by`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_branches`
--
ALTER TABLE `product_branches`
  ADD PRIMARY KEY (`product_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `submitted_by_staff` (`submitted_by_staff`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sale_product` (`sale_id`,`product_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_branch` (`product_id`,`branch_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_branches`
--
ALTER TABLE `product_branches`
  ADD CONSTRAINT `product_branches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_branches_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`submitted_by_staff`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stocks`
--
ALTER TABLE `stocks`
  ADD CONSTRAINT `stocks_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stocks_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
