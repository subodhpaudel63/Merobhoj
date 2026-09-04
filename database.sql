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
(1, 'order', 'ORD-20260829-7676', 1, '2026-08-29 11:57:42'),
(2, 'order', 'ORD-20260829-8809', 1, '2026-08-29 11:57:01'),
(3, 'order', 'ORD-20260829-9190', 1, '2026-08-29 11:54:26'),
(4, 'order', 'ORD-20260829-5489', 1, '2026-08-29 11:54:04'),
(5, 'order', 'ORD-20260828-5785', 1, '2026-08-28 08:26:16'),
(6, 'order', 'ORD-20260828-3234', 1, '2026-08-28 05:48:15'),
(7, 'order', 'ORD-20260826-3563', 1, '2026-08-26 05:40:24'),
(8, 'order', 'ORD-20260826-5784', 1, '2026-08-26 05:30:29'),
(9, 'order', 'ORD-20260826-7503', 1, '2026-08-26 05:20:39'),
(10, 'order', 'ORD-20260826-9597', 1, '2026-08-26 05:18:36'),
(11, 'order', 'ORD-20260826-6559', 1, '2026-08-26 05:15:11'),
(12, 'order', 'ORD-20260826-7382', 1, '2026-08-26 05:07:04'),
(13, 'order', 'ORD-20260826-3596', 1, '2026-08-26 05:06:28'),
(14, 'order', 'ORD-20260826-4757', 1, '2026-08-26 05:05:24'),
(15, 'order', 'ORD-20260821-2180', 1, '2026-08-21 11:47:53'),
(16, 'order', 'ORD-20260808-7105', 1, '2026-08-08 09:05:36'),
(17, 'order', 'ORD-20260808-7359', 1, '2026-08-08 08:25:15'),
(18, 'order', 'ORD-20260802-2041', 1, '2026-08-02 11:41:46'),
(19, 'order', 'ORD-20260802-3099', 1, '2026-08-02 11:39:14'),
(20, 'order', 'ORD-20260802-4630', 1, '2026-08-02 11:37:08'),
(21, 'order', 'ORD-0174', 1, '2026-08-02 11:08:22'),
(22, 'order', 'ORD-0172', 1, '2026-08-02 11:08:22'),
(23, 'order', 'ORD-0171', 1, '2026-08-02 11:06:26'),
(24, 'order', 'ORD-0168', 1, '2026-08-01 09:12:04'),
(25, 'order', 'ORD-0165', 1, '2026-08-01 09:12:04'),
(26, 'order', 'ORD-0170', 1, '2026-08-01 09:12:04'),
(27, 'order', 'ORD-0167', 1, '2026-08-01 09:12:04'),
(28, 'order', 'ORD-0169', 1, '2026-08-01 09:12:04'),
(29, 'order', 'ORD-0166', 1, '2026-08-01 09:12:04'),
(30, 'order', 'ORD-0161', 1, '2026-07-26 08:06:37'),
(31, 'order', 'ORD-0163', 1, '2026-07-26 08:06:37'),
(32, 'order', 'ORD-0159', 1, '2026-07-26 07:48:42'),
(33, 'booking', '38', 1, '2026-08-27 15:57:25'),
(34, 'booking', '37', 1, '2026-08-25 13:24:10'),
(35, 'booking', '36', 1, '2026-08-25 02:35:03'),
(36, 'booking', '35', 1, '2026-08-24 14:46:52'),
(37, 'booking', '34', 1, '2026-08-24 13:32:48'),
(38, 'booking', '33', 1, '2026-08-24 13:30:17'),
(39, 'booking', '32', 1, '2026-08-12 11:51:38'),
(40, 'booking', '31', 1, '2026-08-12 11:44:45'),
(41, 'booking', '30', 1, '2026-08-12 11:41:13'),
(42, 'booking', '29', 1, '2026-08-11 07:41:20'),
(43, 'booking', '28', 1, '2026-07-06 14:30:37'),
(44, 'feedback', '4', 1, '2026-06-30 07:32:13'),
(617, 'order', 'ORD-20260829-8479', 1, '2026-08-29 14:11:34'),
(3137, 'order', 'ORD-20260829-5553', 1, '2026-08-29 14:33:32'),
(6633, 'order', 'ORD-20260829-1910', 1, '2026-08-29 14:49:33'),
(18712, 'order', 'ORD-20260830-9400', 1, '2026-08-30 10:07:25'),
(19228, 'booking', '39', 1, '2026-08-30 10:09:10'),
(25807, 'order', 'ORD-20260830-5153', 1, '2026-08-30 13:01:23'),
(26394, 'booking', '40', 1, '2026-08-30 13:03:12');

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
(28, 'sham', 'p@gmail.com', '9898989898', 0, '2026-07-06', '10:15:00', '10:15:00', '12:15:00', 1, 'buhh', '2026-07-06 14:30:37', 'Cancelled', NULL),
(29, 'subodh dai', 'htmlc47@gmail.com', '9878787898', 7, '2026-08-11', '17:28:00', '17:28:00', '19:28:00', 8, 'i want this table pls', '2026-08-11 07:41:20', 'Completed', NULL),
(30, 'subodh', 'subodh@gmail.com', '9878787898', 7, '2026-08-14', '08:27:00', '08:27:00', '10:27:00', 7, 'nbh', '2026-08-12 11:41:13', 'Cancelled', NULL),
(31, 'hari', 'htmlc47@gmail.com', '9878787898', 7, '2026-08-12', '17:30:00', '17:30:00', '19:30:00', 8, 'xcv', '2026-08-12 11:44:45', 'Completed', NULL),
(32, 'html css', 'htmlc47@gmail.com', '9878787890', 3, '2026-08-12', '17:38:00', '17:38:00', '19:38:00', 6, 'i wnat a table', '2026-08-12 11:51:38', 'No-show', '2026-08-12 17:58:00'),
(33, 'hari', 'hari@gmail.com', '9878787898', 7, '2026-08-25', '21:17:00', '21:17:00', '23:17:00', 5, 'i wnat table', '2026-08-24 13:30:17', 'No-show', '2026-08-25 21:37:00'),
(35, 'sample', 'subodh@gmail.com', '9778787898', 4, '2026-08-27', '20:31:00', '20:31:00', '22:31:00', 6, 'xc', '2026-08-24 14:46:52', 'No-show', '2026-08-27 20:51:00'),
(36, 'ok sir', 'subodh@gmail.com', '9878787898', 7, '2026-09-01', '10:22:00', '10:22:00', '12:22:00', 7, 'i want a table', '2026-08-25 02:35:03', 'Confirmed', '2026-09-01 10:42:00'),
(37, 'subodh dxxxx', 'subodh@gmail.com', '9878787898', 3, '2026-09-02', '19:09:00', '19:09:00', '21:09:00', 4, 'i want a table', '2026-08-25 13:24:10', 'Confirmed', '2026-09-02 19:29:00'),
(38, 'subodh dai', 'htmlc47@gmail.com', '9878787898', 6, '2026-08-28', '07:42:00', '07:42:00', '09:42:00', 3, 'hello', '2026-08-27 15:57:25', 'Completed', NULL),
(39, 'subodh dai', 'subodh@gmail.com', '9878787898', 4, '2026-08-31', '10:00:00', '10:00:00', '13:30:00', 1, 'hey', '2026-08-30 10:09:10', 'Confirmed', '2026-08-31 10:20:00'),
(40, 'subodh da', 'subodh@gmail.com', '9878787898', 3, '2026-09-01', '10:00:00', '10:00:00', '15:30:00', 6, 'hi', '2026-08-30 13:03:12', 'Pending', NULL);

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
(4, 'subodh paudel', 'subodhpaudel0000@gmail.com', 5, 'xefrggggt', 'Service', '2026-06-30 07:32:13');

-- --------------------------------------------------------

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

INSERT INTO `orders` (`order_id`, `order_number`, `menu_id`, `email`, `full_name`, `order_type`, `table_number`, `special_instructions`, `payment_method`, `payment_status`, `transaction_uuid`, `transaction_code`, `menu_name`, `quantity`, `price`, `total_price`, `mobile`, `address`, `status`, `order_time`, `order_date`, `created_at`, `admin_note`) VALUES
(159, 'ORD-0159', 65, 'op@gmail.com', 'joy', 'Dine In', '24', 'no spicy', 'Pay at Restaurant', 'Pending', NULL, NULL, 'Fry Momo', 1, 399, 399, '9800000123', 'resturent', '', '2026-07-26 07:48:42', '2026-07-26', '2026-07-26 13:33:42', NULL),
(161, 'ORD-0161', 76, '', 'ram ram ram', 'Delivery', '', 'less spicy', 'Pay at Restaurant', 'Pending', NULL, NULL, 'Newari Khaja Set (Samay Baji)', 1, 250, 250, '9800000123', 'pokhara 20 lakeside', 'Delivering', '2026-07-26 08:06:37', '2026-07-26', '2026-07-26 13:51:37', NULL),
(163, 'ORD-0163', 78, '', 'ram ram ram', 'Delivery', '', 'less spicy', 'Pay at Restaurant', 'Pending', NULL, NULL, 'Thakali Khana Set', 1, 500, 500, '9800000123', 'pokhara 20 lakeside', '', '2026-07-26 08:06:37', '2026-07-26', '2026-07-26 13:51:37', NULL),
(165, 'ORD-0165', 61, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(166, 'ORD-0166', 82, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Choila (Buff / Chicken)', 1, 199, 199, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(167, 'ORD-0167', 68, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Yogurt oats Bowl', 1, 450, 450, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(168, 'ORD-0168', 76, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Newari Khaja Set (Samay Baji)', 1, 250, 250, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(169, 'ORD-0169', 67, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Sandwich with French Fries', 1, 450, 450, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(170, 'ORD-0170', 65, '', 'subodh paudel', 'Delivery', '', 'dint make food spicy', 'Cash on Delivery', 'Pending', NULL, NULL, 'Fry Momo', 1, 399, 399, '9800000129', 'pokhara 17 tall birauta fistall school rod no 13', '', '2026-08-01 09:12:04', '2026-08-01', '2026-08-01 14:57:04', NULL),
(171, 'ORD-0171', 64, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Momo', 1, 299, 299, '9800000123', 'ugsaq', 'Delivering', '2026-08-02 11:06:26', '2026-08-02', '2026-08-02 16:51:26', NULL),
(172, 'ORD-0172', 59, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Burger', 1, 350, 350, '9800000123', 'ac', 'Delivering', '2026-08-02 11:08:22', '2026-08-02', '2026-08-02 16:53:22', NULL),
(174, 'ORD-0174', 67, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Sandwich with French Fries', 1, 450, 450, '9800000123', 'ac', 'Delivering', '2026-08-02 11:08:22', '2026-08-02', '2026-08-02 16:53:22', NULL),
(175, 'ORD-20260802-4630', 82, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Choila (Buff / Chicken)', 1, 199, 199, '9800000123', 'zas', '', '2026-08-02 11:37:08', '2026-08-02', '2026-08-02 17:22:08', NULL),
(176, 'ORD-20260802-4630', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'zas', '', '2026-08-02 11:37:08', '2026-08-02', '2026-08-02 17:22:08', NULL),
(177, 'ORD-20260802-4630', 60, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Berry Cheesecake', 1, 450, 450, '9800000123', 'zas', '', '2026-08-02 11:37:08', '2026-08-02', '2026-08-02 17:22:08', NULL),
(178, 'ORD-20260802-4630', 84, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Wai Wai Sadeko', 1, 199, 199, '9800000123', 'zas', '', '2026-08-02 11:37:08', '2026-08-02', '2026-08-02 17:22:08', NULL),
(179, 'ORD-20260802-4630', 83, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Sekuwa (Grilled Meat)', 1, 299, 299, '9800000123', 'zas', '', '2026-08-02 11:37:08', '2026-08-02', '2026-08-02 17:22:08', NULL),
(180, 'ORD-20260802-3099', 78, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Thakali Khana Set', 32, 500, 16000, '9800000123', 'sdfc', 'Delivering', '2026-08-02 11:39:14', '2026-08-02', '2026-08-02 17:24:14', NULL),
(181, 'ORD-20260802-2041', 67, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Sandwich with French Fries', 1, 450, 450, '9800000123', 'pkr', 'Cancelled', '2026-08-02 11:41:46', '2026-08-02', '2026-08-02 17:26:46', NULL),
(182, 'ORD-20260802-2041', 59, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Burger', 1, 350, 350, '9800000123', 'pkr', 'Cancelled', '2026-08-02 11:41:46', '2026-08-02', '2026-08-02 17:26:46', NULL),
(183, 'ORD-20260802-2041', 77, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Dal Bhat Tarkari Set (National Dish)', 1, 450, 450, '9800000123', 'pkr', 'Cancelled', '2026-08-02 11:41:46', '2026-08-02', '2026-08-02 17:26:46', NULL),
(184, 'ORD-20260802-2041', 80, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Thukpa', 1, 200, 200, '9800000123', 'pkr', 'Cancelled', '2026-08-02 11:41:46', '2026-08-02', '2026-08-02 17:26:46', NULL),
(185, 'ORD-20260808-7359', 60, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Berry Cheesecake', 1, 450, 450, '9846613920', 'pokhara17', 'Cancelled', '2026-08-08 08:25:15', '2026-08-08', '2026-08-08 14:10:15', NULL),
(186, 'ORD-20260808-7359', 83, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Sekuwa (Grilled Meat)', 1, 299, 299, '9846613920', 'pokhara17', 'Cancelled', '2026-08-08 08:25:15', '2026-08-08', '2026-08-08 14:10:15', NULL),
(187, 'ORD-20260808-7359', 84, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Wai Wai Sadeko', 1, 199, 199, '9846613920', 'pokhara17', 'Cancelled', '2026-08-08 08:25:15', '2026-08-08', '2026-08-08 14:10:15', NULL),
(188, 'ORD-20260808-7105', 60, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Berry Cheesecake', 1, 450, 450, '9800000123', 'pkr', 'Delivering', '2026-08-08 09:05:36', '2026-08-08', '2026-08-08 14:50:36', NULL),
(189, 'ORD-20260821-2180', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'bkl', 'Cancelled', '2026-08-21 11:47:53', '2026-08-21', '2026-08-21 17:32:53', NULL),
(190, 'ORD-20260826-4757', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'pke', 'Cancelled', '2026-08-26 05:05:24', '2026-08-26', '2026-08-26 10:50:24', NULL),
(191, 'ORD-20260826-3596', 61, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Pending', NULL, NULL, 'Pani Puri', 3, 100, 300, '9800000123', 'ktms', 'Confirmed', '2026-08-26 05:06:28', '2026-08-26', '2026-08-26 10:51:28', NULL),
(192, 'ORD-20260826-7382', 82, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Failed', NULL, NULL, 'Choila (Buff / Chicken)', 1, 199, 199, '9800000123', 'pkr', 'Confirmed', '2026-08-26 05:07:04', '2026-08-26', '2026-08-26 10:52:04', NULL),
(193, 'ORD-20260826-7382', 83, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Failed', NULL, NULL, 'Sekuwa (Grilled Meat)', 1, 299, 299, '9800000123', 'pkr', 'Confirmed', '2026-08-26 05:07:04', '2026-08-26', '2026-08-26 10:52:04', NULL),
(194, 'ORD-20260826-6559', 70, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Failed', NULL, NULL, 'Fried Egg Toast', 1, 19, 19, '9800000123', '', 'Confirmed', '2026-08-26 05:15:11', '2026-08-26', '2026-08-26 11:00:11', NULL),
(195, 'ORD-20260826-9597', 70, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Paid', 'ORDER-20260826071836-a6af6a15', '000GW6A', 'Fried Egg Toast', 1, 19, 19, '9800000123', '', 'Confirmed', '2026-08-26 05:18:36', '2026-08-26', '2026-08-26 11:03:36', NULL),
(196, 'ORD-20260826-7503', 70, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Paid', 'ORDER-20260826072039-cf4fe9ab', '000GW6D', 'Fried Egg Toast', 1, 19, 19, '9800000128', '', 'Delivering', '2026-08-26 05:20:39', '2026-08-26', '2026-08-26 11:05:39', NULL),
(197, 'ORD-20260826-5784', 70, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Paid', 'ORDER-20260826073030-6ef8e75d', '000GW6N', 'Fried Egg Toast', 1, 19, 19, '9800000149', 'pokhara birauta sir', 'Cancelled', '2026-08-26 05:30:29', '2026-08-26', '2026-08-26 11:15:29', NULL),
(198, 'ORD-20260826-3563', 61, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'Pay at Restaurant', 'Paid', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', '', 'Completed', '2026-08-26 05:40:24', '2026-08-26', '2026-08-26 11:25:24', NULL),
(199, 'ORD-20260828-3234', 62, 'htmlc47@gmail.com', NULL, 'Delivery', NULL, NULL, 'eSewa', 'Paid', 'ORDER-20260828074815-a5826317', '000GWPJ', 'Waffles and fruits', 1, 11, 11, '9800000876', '', 'Completed', '2026-08-28 05:48:15', '2026-08-28', '2026-08-28 11:33:15', NULL),
(200, 'ORD-20260828-5785', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'pokhara 13', 'Delivering', '2026-08-28 08:26:16', '2026-08-28', '2026-08-28 14:11:16', NULL),
(201, 'ORD-20260828-5785', 82, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Choila (Buff / Chicken)', 1, 199, 199, '9800000123', 'pokhara 13', 'Delivering', '2026-08-28 08:26:16', '2026-08-28', '2026-08-28 14:11:16', NULL),
(202, 'ORD-20260828-5785', 84, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Wai Wai Sadeko', 1, 199, 199, '9800000123', 'pokhara 13', 'Delivering', '2026-08-28 08:26:16', '2026-08-28', '2026-08-28 14:11:16', NULL),
(203, 'ORD-20260829-5489', 64, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Pay at Restaurant', 'Paid', NULL, NULL, 'Momo', 1, 299, 299, '9800000123', '', 'Pending', '2026-08-29 11:54:04', '2026-08-29', '2026-08-29 17:39:04', NULL),
(204, 'ORD-20260829-9190', 64, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Pay at Restaurant', 'Paid', NULL, NULL, 'Momo', 1, 299, 299, '9800000123', '', 'Pending', '2026-08-29 11:54:26', '2026-08-29', '2026-08-29 17:39:26', NULL),
(205, 'ORD-20260829-8809', 64, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Pay at Restaurant', 'Paid', NULL, NULL, 'Momo', 1, 299, 299, '9800000123', '', 'Pending', '2026-08-29 11:57:01', '2026-08-29', '2026-08-29 17:42:01', NULL),
(206, 'ORD-20260829-7676', 64, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Momo', 1, 299, 299, '9800000123', '', 'Pending', '2026-08-29 11:57:42', '2026-08-29', '2026-08-29 17:42:42', NULL),
(207, 'ORD-20260829-7676', 65, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Fry Momo', 1, 399, 399, '9800000123', '', 'Pending', '2026-08-29 11:57:42', '2026-08-29', '2026-08-29 17:42:42', NULL),
(208, 'ORD-20260829-8479', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'pokhara', 'Pending', '2026-08-29 14:11:34', '2026-08-29', '2026-08-29 19:56:34', NULL),
(209, 'ORD-20260829-5553', 61, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Pani Puri', 1, 100, 100, '9800000123', 'pokhara', 'Pending', '2026-08-29 14:33:32', '2026-08-29', '2026-08-29 20:18:32', NULL),
(210, 'ORD-20260829-1910', 84, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Wai Wai Sadeko', 1, 199, 199, '9800000123', '', 'Pending', '2026-08-29 14:49:33', '2026-08-29', '2026-08-29 20:34:33', NULL),
(211, 'ORD-20260830-9400', 59, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Burger', 1, 350, 350, '9800000123', '', 'Completed', '2026-08-30 10:07:25', '2026-08-30', '2026-08-30 15:52:25', NULL),
(212, 'ORD-20260830-5153', 76, 'subodh@gmail.com', NULL, 'Delivery', NULL, NULL, 'Cash on Delivery', 'Paid', NULL, NULL, 'Newari Khaja Set (Samay Baji)', 1, 250, 250, '9800000123', '', 'Pending', '2026-08-30 13:01:23', '2026-08-30', '2026-08-30 18:46:23', NULL);

-- --------------------------------------------------------

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
  ADD KEY `idx_order_number` (`order_number`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;