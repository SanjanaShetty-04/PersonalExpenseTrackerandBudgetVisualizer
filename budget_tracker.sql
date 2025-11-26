-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 10:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `budget`
--

CREATE TABLE `budget` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `limit_amount` decimal(10,2) NOT NULL,
  `month_year` varchar(7) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget`
--

INSERT INTO `budget` (`id`, `user_id`, `category`, `limit_amount`, `month_year`, `created_at`, `category_id`) VALUES
(1, 1, 'Groceries', 1000.00, '2025-11', '2025-11-08 15:35:09', NULL),
(2, 10, 'Transport', 1000.00, '2025-11', '2025-11-25 16:55:18', 1),
(3, 10, 'Grocery', 3000.00, '2025-11', '2025-11-25 16:56:29', 3),
(4, 1, 'Food', 600.00, '2025-11', '2025-11-25 17:26:02', 1),
(5, 1, 'Transport', 300.00, '2025-11', '2025-11-25 17:26:02', 2),
(6, 10, 'Fashion', 10000.00, '2025-11', '2025-11-25 18:22:14', 6);

--
-- Triggers `budget`
--
DELIMITER $$
CREATE TRIGGER `check_positive_budget_limit` BEFORE INSERT ON `budget` FOR EACH ROW BEGIN
    IF NEW.limit_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Budget limit must be positive';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `name`, `type`, `parent_id`, `created_at`) VALUES
(1, 10, 'Transport', 'expense', NULL, '2025-11-25 16:54:49'),
(2, 10, 'Bus', 'expense', 1, '2025-11-25 16:54:49'),
(3, 10, 'Grocery', 'expense', NULL, '2025-11-25 16:56:29'),
(4, 10, 'Vegetable', 'expense', 3, '2025-11-25 16:57:17'),
(5, 10, 'Car rent', 'expense', 1, '2025-11-25 17:24:14'),
(6, 10, 'Fashion', 'expense', NULL, '2025-11-25 18:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `category`, `amount`, `date`, `created_at`, `category_id`) VALUES
(1, 1, 'Groceries', 500.00, '2025-11-08', '2025-11-08 15:34:51', NULL),
(2, 1, 'Transport', 200.00, '2025-11-10', '2025-11-09 13:21:41', NULL),
(3, 1, 'Groceries', 150.75, '2025-11-02', '2025-11-09 13:35:29', NULL),
(4, 1, 'Movie', 250.00, '2025-11-09', '2025-11-09 13:38:46', NULL),
(5, 1, 'Grocery', 800.00, '2025-11-09', '2025-11-09 13:40:54', NULL),
(6, 10, 'Transport', 250.00, '2025-11-25', '2025-11-25 16:54:49', 2),
(7, 10, 'Grocery', 400.00, '2025-11-25', '2025-11-25 16:57:17', 4),
(8, 10, 'Transport', 700.00, '2025-11-25', '2025-11-25 17:24:14', 5),
(9, 1, 'Food', 300.00, '2025-11-05', '2025-11-25 17:26:02', 1),
(10, 1, 'Transport', 150.00, '2025-11-10', '2025-11-25 17:26:02', 2),
(11, 1, 'Food', 250.00, '2025-10-08', '2025-11-25 17:26:02', 1),
(12, 1, 'Entertainment', 200.00, '2025-10-15', '2025-11-25 17:26:02', 3),
(13, 1, 'Transport', 120.00, '2025-10-22', '2025-11-25 17:26:02', 2);

--
-- Triggers `expenses`
--
DELIMITER $$
CREATE TRIGGER `after_expense_budget_check` AFTER INSERT ON `expenses` FOR EACH ROW BEGIN
    DECLARE budget_limit DECIMAL(10,2);
    DECLARE total_expenses DECIMAL(10,2);
    DECLARE current_month VARCHAR(7);
    
    SET current_month = DATE_FORMAT(NEW.date, '%Y-%m');
    
    SELECT limit_amount INTO budget_limit
    FROM budget
    WHERE user_id = NEW.user_id
      AND category = NEW.category
      AND month_year = current_month;
    
    IF budget_limit IS NOT NULL THEN
        SELECT COALESCE(SUM(amount), 0) INTO total_expenses
        FROM expenses
        WHERE user_id = NEW.user_id
          AND category = NEW.category
          AND DATE_FORMAT(date, '%Y-%m') = current_month;
        
        IF total_expenses > budget_limit THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Budget limit exceeded for this category';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_expense_insert` AFTER INSERT ON `expenses` FOR EACH ROW BEGIN
    INSERT INTO transactions (user_id, type, category, amount, date)
    VALUES (NEW.user_id, 'expense', NEW.category, NEW.amount, NEW.date);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `check_positive_amount_expense` BEFORE INSERT ON `expenses` FOR EACH ROW BEGIN
    IF NEW.amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expense amount must be positive';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `source` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `income`
--

INSERT INTO `income` (`id`, `user_id`, `source`, `amount`, `date`, `created_at`) VALUES
(1, 1, 'Salary', 50000.00, '2025-11-08', '2025-11-08 15:31:52'),
(2, 1, 'Stock', 10000.00, '2025-11-09', '2025-11-09 12:06:41'),
(3, 1, 'gift', 3000.00, '2025-11-03', '2025-11-09 13:20:42'),
(4, 1, 'Salary', 5000.00, '2025-11-01', '2025-11-09 13:35:29'),
(5, 1, 'Bonus', 2500.00, '2025-05-15', '2025-11-09 13:35:29'),
(6, 10, 'Salary', 60000.00, '2025-11-25', '2025-11-25 16:41:35'),
(7, 1, 'Salary', 5000.00, '2025-11-01', '2025-11-25 17:26:02'),
(8, 1, 'Freelance', 1500.00, '2025-11-15', '2025-11-25 17:26:02'),
(9, 1, 'Salary', 5000.00, '2025-10-01', '2025-11-25 17:26:02'),
(10, 1, 'Investment', 800.00, '2025-10-20', '2025-11-25 17:26:02'),
(11, 10, 'Stock', 40000.00, '2025-10-15', '2025-11-25 17:27:00'),
(12, 10, 'Gift', 500.00, '2025-11-26', '2025-11-26 05:51:00');

--
-- Triggers `income`
--
DELIMITER $$
CREATE TRIGGER `after_income_insert` AFTER INSERT ON `income` FOR EACH ROW BEGIN
    INSERT INTO transactions (user_id, type, category, amount, date)
    VALUES (NEW.user_id, 'income', NEW.source, NEW.amount, NEW.date);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `check_positive_amount_income` BEFORE INSERT ON `income` FOR EACH ROW BEGIN
    IF NEW.amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Income amount must be positive';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('budget_exceeded','recurring_reminder','general') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(1, 10, '', 'New budget created for \'Fashion\' with limit of $10,000.00 for 2025-11.', 1, '2025-11-25 18:22:14'),
(2, 10, '', 'Income of $500.00 from Gift added successfully.', 1, '2025-11-26 05:51:00');

-- --------------------------------------------------------

--
-- Table structure for table `recurring_transactions`
--

CREATE TABLE `recurring_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('daily','weekly','bi-weekly','monthly','quarterly','yearly') NOT NULL,
  `start_date` date NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `last_added_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recurring_transactions`
--

INSERT INTO `recurring_transactions` (`id`, `user_id`, `type`, `description`, `amount`, `frequency`, `start_date`, `category`, `last_added_date`, `is_active`, `created_at`) VALUES
(1, 1, 'income', 'Salary', 50000.00, 'monthly', '2025-11-09', '', NULL, 1, '2025-11-09 14:03:39'),
(2, 10, 'income', 'Salary', 60000.00, 'monthly', '2025-11-25', '', NULL, 1, '2025-11-25 17:22:33');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `category`, `amount`, `date`, `created_at`) VALUES
(1, 1, 'income', 'Salary', 50000.00, '2025-11-08', '2025-11-08 15:31:52'),
(2, 1, 'expense', 'Groceries', 500.00, '2025-11-08', '2025-11-08 15:34:51'),
(3, 1, 'income', 'Stock', 10000.00, '2025-11-09', '2025-11-09 12:06:41'),
(4, 1, 'income', 'gift', 3000.00, '2025-11-03', '2025-11-09 13:20:42'),
(5, 1, 'expense', 'Transport', 200.00, '2025-11-10', '2025-11-09 13:21:41'),
(6, 1, 'income', 'Salary', 5000.00, '2025-11-01', '2025-11-09 13:35:29'),
(8, 1, 'expense', 'Groceries', 150.75, '2025-11-02', '2025-11-09 13:35:29'),
(10, 1, 'income', 'Bonus', 2500.00, '2025-05-15', '2025-11-09 13:35:29'),
(12, 1, 'expense', 'Movie', 250.00, '2025-11-09', '2025-11-09 13:38:46'),
(13, 1, 'expense', 'Grocery', 800.00, '2025-11-09', '2025-11-09 13:40:54'),
(14, 10, 'income', 'Salary', 60000.00, '2025-11-25', '2025-11-25 16:41:35'),
(16, 10, 'expense', 'Transport', 250.00, '2025-11-25', '2025-11-25 16:54:49'),
(18, 10, 'expense', 'Grocery', 400.00, '2025-11-25', '2025-11-25 16:57:17'),
(20, 10, 'expense', 'Transport', 700.00, '2025-11-25', '2025-11-25 17:24:14'),
(21, 1, 'income', 'Salary', 5000.00, '2025-11-01', '2025-11-25 17:26:02'),
(22, 1, 'income', 'Freelance', 1500.00, '2025-11-15', '2025-11-25 17:26:02'),
(23, 1, 'income', 'Salary', 5000.00, '2025-10-01', '2025-11-25 17:26:02'),
(24, 1, 'income', 'Investment', 800.00, '2025-10-20', '2025-11-25 17:26:02'),
(25, 1, 'expense', 'Food', 300.00, '2025-11-05', '2025-11-25 17:26:02'),
(26, 1, 'expense', 'Transport', 150.00, '2025-11-10', '2025-11-25 17:26:02'),
(27, 1, 'expense', 'Food', 250.00, '2025-10-08', '2025-11-25 17:26:02'),
(28, 1, 'expense', 'Entertainment', 200.00, '2025-10-15', '2025-11-25 17:26:02'),
(29, 1, 'expense', 'Transport', 120.00, '2025-10-22', '2025-11-25 17:26:02'),
(30, 10, 'income', 'Stock', 40000.00, '2025-10-15', '2025-11-25 17:27:00'),
(31, 10, 'income', 'Gift', 500.00, '2025-11-26', '2025-11-26 05:51:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(10, 'Sanjana Shetty', 'sanjanauppunda@gmail.com', '$2y$10$WvB9.fyhgeRFIb0KTauE9.FL4M4X4.oYkZOvyRDSDdW4zriDvkYmm', 'user', '2025-11-08 15:01:26', '2025-11-08 15:01:26'),
(11, 'Keerti', 'kepa@gmail.com', '$2y$10$R1cB4vS0hrL4NBk5ACbaK.qxAG9LZ09EmU5Ja1eVB5eiHathaECtm', 'user', '2025-11-26 07:52:38', '2025-11-26 07:52:38');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `update_user_timestamp` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budget`
--
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_budget` (`user_id`,`category`,`month_year`),
  ADD KEY `idx_budget_user_month` (`user_id`,`month_year`),
  ADD KEY `fk_budget_category` (`category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_category` (`user_id`,`name`,`type`),
  ADD KEY `fk_categories_parent` (`parent_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenses_user_date` (`user_id`,`date`),
  ADD KEY `fk_expenses_category` (`category_id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_income_user_date` (`user_id`,`date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`);

--
-- Indexes for table `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recurring_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transactions_user_date` (`user_id`,`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budget`
--
ALTER TABLE `budget`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `recurring_transactions`
--
ALTER TABLE `recurring_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budget`
--
ALTER TABLE `budget`
  ADD CONSTRAINT `fk_budget_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
