- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 30, 2026 at 03:26 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `alu`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `resource_id` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `resource_id`, `is_read`, `created_at`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `table_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `people` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending',
  `grace_end_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `name`, `email`, `phone`, `table_id`, `booking_date`, `booking_time`, `start_time`, `end_time`, `people`, `message`, `created_at`, `status`, `grace_end_at`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `feedback_name` varchar(100) NOT NULL,
  `feedback_email` varchar(100) NOT NULL,
  `feedback_rating` int(1) NOT NULL,
  `feedback_message` text NOT NULL,
  `feedback_category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `feedback_name`, `feedback_email`, `feedback_rating`, `feedback_message`, `feedback_category`, `created_at`) VALUES


-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--
CREATE TABLE `menu_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `menu_id` int(11) NOT NULL,
  `menu_name` varchar(255) NOT NULL,
  `menu_description` text NOT NULL,
  `menu_price` int(10) NOT NULL,
  `menu_category` enum('starter','dinner','lunch','breakfast') NOT NULL,
  `menu_status` enum('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock',
  `stock_quantity` int NOT NULL DEFAULT 40,
  `max_order_quantity` int NOT NULL DEFAULT 10,
  `menu_image` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`menu_id`, `menu_name`, `menu_description`, `menu_price`, `menu_category`, `menu_status`, `menu_image`, `created_at`) VALUES
(59, 'Burger', 'Juicy, big, loaded with toppings of my choice', 350, 'lunch', 'In Stock', 'assets/img/menu/menu_68e7b771efafa.jpg', '2025-10-09 13:24:01'),
(60, 'Berry Cheesecake', 'Dessert consisting of a dense, creamy cheese-based filling set on a base (usually a crust) and often finished with a topping or glaze', 450, 'starter', 'Out of Stock', 'assets/img/menu/menu_68e7b7fa52e86.jpg', '2025-10-09 13:26:18'),
(61, 'Pani Puri', 'A small, hollow, fried wheat and/or semolina shell filled with spiced mashed potatoes and served with either a green or a brown-coloured dipping water; or both', 100, 'starter', 'In Stock', 'assets/img/menu/menu_68e7bc9cb993f.jpg', '2025-10-09 13:46:04'),
(62, 'Waffles and fruits', 'light and crispy waffles enhanced with cinnamon or nutmeg', 11, 'breakfast', 'Low Stock', 'assets/img/menu/menu_68e7bce3ede23.jpg', '2025-10-09 13:47:15'),
(63, 'Butter Chicken With Butter Nan', 'a type of curry made from chicken cooked in a spiced tomato and butter-based  gravy served with a soft and fluffy bread that is traditionally cooked in a tandoor oven, but can also be made on a stovetop or in an oven', 650, 'dinner', 'In Stock', 'assets/img/menu/menu_68e7bd75e420d.jpg', '2025-10-09 13:49:41'),
(64, 'Momo', 'a dumpling made of all-purpose flour and filled with either meat or vegetables', 299, 'dinner', 'In Stock', 'assets/img/menu/menu_68e7bdcf256e6.jpg', '2025-10-09 13:51:11'),
(65, 'Fry Momo', 'a dumpling made of all-purpose flour and filled with either meat or vegetables Fried in Pure sunflower oil', 399, 'dinner', 'In Stock', 'assets/img/menu/menu_68e7be0351cf2.jpg', '2025-10-09 13:52:03'),
(66, 'Chowmein', 'a stir-fried dish consisting of noodles, meat (chicken being most common but pork, beef, shrimp or tofu sometimes being substituted), onions and celery', 150, 'lunch', 'In Stock', 'assets/img/menu/menu_68e7c025ac6bc.jpg', '2025-10-09 14:01:09'),
(67, 'Sandwich with French Fries', 'Your choice of freshly made sandwich—stuffed with premium meats, crisp vegetables, and melted cheese—served on toasted artisan bread. Comes with a generous side of golden, crispy French fries. Perfectly satisfying, every bite.', 450, 'lunch', 'In Stock', 'assets/img/menu/menu_68e7c584edce3.jpg', '2025-10-09 14:24:04'),
(68, 'Yogurt oats Bowl', 'A wholesome blend of creamy yogurt and chilled oats, layered with fresh seasonal fruits, crunchy granola, and a drizzle of honey. Light, nourishing, and perfect for a healthy start or midday boost.', 450, 'breakfast', 'In Stock', 'assets/img/menu/menu_68e7c5dc3a05b.jpg', '2025-10-09 14:25:32'),
(70, 'Fried Egg Toast', 'Crispy golden toast topped with a perfectly fried egg—sunny side up or to your liking. Served with a sprinkle of herbs and a touch of seasoning for a classic, satisfying bite', 19, 'breakfast', 'In Stock', 'assets/img/menu/menu_68e7c75c181eb.jpg', '2025-10-09 14:31:56'),
(74, 'Selroti', 'Traditional sweet rice bread served with yogurt and achar\r\nServe with: Aalu ko achar, curd, milk tea', 150, 'breakfast', 'In Stock', 'assets/img/menu/menu_699701c298f10.jpg', '2026-02-19 12:27:46'),
(75, 'Puri Tarkari', 'Deep fried bread with potato curry', 200, 'breakfast', 'In Stock', 'assets/img/menu/menu_6997030baa4ed.png', '2026-02-19 12:33:15'),
(76, 'Newari Khaja Set (Samay Baji)', 'Chiura, buff chhoila, boiled egg, soybeans, achar', 250, 'breakfast', 'In Stock', 'assets/img/menu/menu_699703de1b844.jpg', '2026-02-19 12:36:46'),
(77, 'Dal Bhat Tarkari Set (National Dish)', 'Rice, lentil soup, vegetable curry, achar, papad, salad', 450, 'lunch', 'In Stock', 'assets/img/menu/menu_699704935ebab.webp', '2026-02-19 12:39:47'),
(78, 'Thakali Khana Set', 'Rice, dal, gundruk, tarkari, meat curry, achar', 500, 'lunch', 'In Stock', 'assets/img/menu/menu_69970512b9a02.jpg', '2026-02-19 12:41:54'),
(79, 'Chatamari', 'Rice flour base topped with egg, meat, vegetables', 100, 'lunch', 'In Stock', 'assets/img/menu/menu_699705a9e1a09.jpg', '2026-02-19 12:44:25'),
(80, 'Thukpa', 'Traditional Sherpa noodle soup', 200, 'lunch', 'In Stock', 'assets/img/menu/menu_699706112358b.avif', '2026-02-19 12:46:09'),
(81, 'Dhido Set', 'Traditional millet or buckwheat porridge with gundruk and curry', 450, 'dinner', 'In Stock', 'assets/img/menu/menu_699708e96ef9b.jpg', '2026-02-19 12:58:17'),
(82, 'Choila (Buff / Chicken)', 'Spicy grilled meat mixed with mustard oil, garlic, and spices', 199, 'starter', 'In Stock', 'assets/img/menu/menu_6997095c0d4d2.jpg', '2026-02-19 13:00:12'),
(83, 'Sekuwa (Grilled Meat)', 'Fire-grilled marinated meat cooked on charcoal', 299, 'starter', 'In Stock', 'assets/img/menu/menu_699709eb4bc49.jpg', '2026-02-19 13:02:35'),
(84, 'Wai Wai Sadeko', 'Nepali instant noodles mixed with onion, spices, mustard oil', 199, 'starter', 'In Stock', 'assets/img/menu/menu_69970a3b10dbd.png', '2026-02-19 13:03:55');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `menu_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `order_type` varchar(50) DEFAULT 'Delivery',
  `table_number` varchar(20) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash on Delivery',
  `payment_status` enum('Pending','Paid','Failed') NOT NULL DEFAULT 'Pending',
  `transaction_uuid` varchar(100) DEFAULT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `menu_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` int(10) NOT NULL,
  `total_price` int(10) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `status` enum('Pending','Confirmed','Preparing','Ready','Delivering','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `status_updated_at` datetime DEFAULT NULL,
  `order_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_date` date NOT NULL DEFAULT curdate(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--



--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`id`, `table_name`, `capacity`) VALUES
(1, 'Table 1', 4),
(2, 'Table 2', 4),
(3, 'Table 3', 6),
(4, 'Table 4', 6),
(5, 'Table 5', 6),
(6, 'Table 6', 6),
(7, 'Table 7', 8);

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscribers`
--

INSERT INTO `subscribers` (`id`, `email`, `subscribed_at`) VALUES
(12, 'ok@gmail.com', '2026-02-25 12:36:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_img` varchar(255) NOT NULL DEFAULT '../assets/img/usersprofiles/profilepic.jpg',
  `google_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `user_type`, `created_at`, `user_img`, `google_id`) VALUES
(27, 'subodh paudel', 'subodhpaudel0000@gmail.com', '$2y$10$VddGBi8G315saOQgnXtqIOwc8gtN10c54VPpdvR3OOps7i2fSdssm', 'admin', '2025-10-06 15:06:17', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(52, '', 'admin@admin.com', '$2y$10$bJ9X4N1jFm5imGM8z2HzmO3gLeUtf8/rvWgm.JMJFmVkJIe2rqvz6', 'user', '2026-04-16 07:08:53', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(53, '', 'so@gmail.com', '$2y$10$S5M8B5Q7gvH89A.Gj9vBouy7hYWAtyLqDmrWZvJsNJNhLTRTMSCnS', 'user', '2026-06-30 07:21:00', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(55, '', 'op@gmail.com', '$2y$10$TVrNYwtOVck9M97UBco2HeWx5XJsqSUooZcAbX2xuy.f.nc0dS1T.', 'user', '2026-07-06 12:54:00', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(56, '', 'subodh@gmail.com', '$2y$10$neD2PbXUO41rtfnIbb.VzeV1jldJhccWornG/bzLEoQcuDmi15rjy', 'user', '2026-08-01 09:10:11', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(57, '', 'htmlc47@gmail.com', '$2y$10$xvwAsVPLbFWbRkiigdhi.u59smvfn1JSFjLo/uPcA81EsC5DMzd5C', 'user', '2026-08-08 08:21:56', '../assets/img/usersprofiles/profilepic.jpg', NULL),
(58, '', 'subodhp@gmail.com', '$2y$10$My0d7r1Csam1kkjerYhf9uXBKiMaXC5UaurdW9xSwnBBQT2GO/H..', 'admin', '2026-08-21 11:49:22', '../assets/img/usersprofiles/profilepic.jpg', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_resource` (`type`,`resource_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_rating` (`feedback_rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`menu_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_orders_user_id` (`user_id`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28652;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `menu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

CREATE TABLE IF NOT EXISTS `ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(120) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'unit', `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `low_threshold` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Available','Low','Out') NOT NULL DEFAULT 'Available',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL, `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL, `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL, `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','ordered','received','canceled') NOT NULL DEFAULT 'draft',
  `payment_status` enum('unpaid','partially_paid','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL, `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uniq_po_number` (`po_number`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `po_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL, `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL, `total_cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_poi_po` (`po_id`),
  CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL,
  `user_role` varchar(50) NOT NULL, `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL, `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL, `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
