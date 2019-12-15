-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 07, 2019 at 08:19 PM
-- Server version: 5.7.28-0ubuntu0.18.04.4
-- PHP Version: 7.2.24-0ubuntu0.18.04.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `klusbibapi`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_report`
--

CREATE TABLE `activity_report` (
  `id` int(10) UNSIGNED NOT NULL,
  `day` date DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `start_balance` decimal(8,2) DEFAULT NULL,
  `end_balance` decimal(8,2) DEFAULT NULL,
  `enrolment_count` int(11) DEFAULT NULL,
  `loan_count` int(11) DEFAULT NULL,
  `return_count` int(11) DEFAULT NULL,
  `donation_count` int(11) DEFAULT NULL,
  `volunteer_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comments` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumers`
--

CREATE TABLE `consumers` (
  `consumer_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `brand` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reference` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `unit` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `low_stock` int(11) DEFAULT NULL,
  `packed_per` int(11) DEFAULT NULL,
  `provider` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `public` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `version` int(11) NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `currency` varchar(50) COLLATE utf8_unicode_ci DEFAULT 'euro',
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lendings`
--

CREATE TABLE `lendings` (
  `lending_id` int(10) UNSIGNED NOT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `returned_date` timestamp NULL DEFAULT NULL,
  `tool_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_by` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comments` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `mode` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'NEW',
  `order_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `currency` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phinxlog`
--

CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `phinxlog`
--

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`) VALUES
(20170115194754, 'CreateTools', '2019-05-12 12:01:41', '2019-05-12 12:01:41'),
(20170206203251, 'CreateReservations', '2019-05-12 12:01:42', '2019-05-12 12:01:42'),
(20170207220328, 'CreateUsers', '2019-05-12 12:01:42', '2019-05-12 12:01:43'),
(20170312202026, 'UpdateTools', '2019-05-12 12:01:43', '2019-05-12 12:01:43'),
(20170315223653, 'CreateConsumers', '2019-05-12 12:01:43', '2019-05-12 12:01:44'),
(20170401113552, 'UpdateToolsAddCodeOwner', '2019-05-12 12:01:44', '2019-05-12 12:01:44'),
(20170414144715, 'UsersExtraData', '2019-05-12 12:01:44', '2019-05-12 12:01:45'),
(20170416143711, 'UpdateUserPrimaryKey', '2019-05-12 12:01:45', '2019-05-12 12:01:46'),
(20170509200808, 'UserState', '2019-05-12 12:01:47', '2019-05-12 12:01:47'),
(20170518204508, 'UpdateUserAddressSize', '2019-05-12 12:01:47', '2019-05-12 12:01:47'),
(20170518204803, 'AddToolExperienceLevel', '2019-05-12 12:01:47', '2019-05-12 12:01:47'),
(20170625205328, 'UpdateUserRegistrationNumber', '2019-05-12 12:01:47', '2019-05-12 12:01:48'),
(20170802153219, 'UpdateUserPaymentMode', '2019-05-12 12:01:48', '2019-05-12 12:01:48'),
(20170807202156, 'UpdateReservationAddState', '2019-05-12 12:01:48', '2019-05-12 12:01:49'),
(20170923174916, 'UsersTermsOfUse', '2019-05-12 12:01:49', '2019-05-12 12:01:49'),
(20171015150456, 'UpdateReservationDescription', '2019-05-12 12:01:49', '2019-05-12 12:01:50'),
(20171115214239, 'UpdateToolVisible', '2019-05-12 12:01:50', '2019-05-12 12:01:50'),
(20180406205935, 'UpdateReservationsCommentSize', '2019-05-12 12:01:50', '2019-05-12 12:01:51'),
(20180406210240, 'UpdateUsersSizes', '2019-05-12 12:01:51', '2019-05-12 12:01:52'),
(20180406210521, 'CreateEvents', '2019-05-12 12:01:52', '2019-05-12 12:01:52'),
(20180406215732, 'CreateActivityReport', '2019-05-12 12:01:52', '2019-05-12 12:01:52'),
(20181107211504, 'UpdateUserEmailState', '2019-05-12 12:01:52', '2019-05-12 12:01:53'),
(20181107211716, 'CreatePayments', '2019-05-12 12:01:53', '2019-05-12 12:01:53'),
(20190319220711, 'UpdateUserExtId', '2019-05-12 12:01:53', '2019-05-12 12:01:53'),
(20190406113139, 'UpdateToolExtId', '2019-05-12 12:01:53', '2019-05-12 12:01:54'),
(20190710141634, 'CreateLending', '2019-07-10 14:45:30', '2019-07-10 14:45:30');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `tool_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `startsAt` date DEFAULT NULL,
  `endsAt` date DEFAULT NULL,
  `type` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'REQUESTED',
  `comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tools`
--

CREATE TABLE `tools` (
  `tool_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `img` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `brand` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `serial` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `manufacturing_year` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `manufacturer_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `doc_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `replacement_value` int(11) DEFAULT NULL,
  `code` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `reception_date` date DEFAULT NULL,
  `experience_level` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `safety_risk` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'NEW',
  `tool_ext_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `firstname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lastname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `membership_start_date` date DEFAULT NULL,
  `membership_end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postal_code` varchar(5) COLLATE utf8_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mobile` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'DISABLED',
  `registration_number` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `payment_mode` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `accept_terms_date` date DEFAULT NULL,
  `email_state` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_ext_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_report`
--
ALTER TABLE `activity_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consumers`
--
ALTER TABLE `consumers`
  ADD PRIMARY KEY (`consumer_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `lendings`
--
ALTER TABLE `lendings`
  ADD PRIMARY KEY (`lending_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `reservations_tool_id_index` (`tool_id`),
  ADD KEY `reservations_user_id_index` (`user_id`);

--
-- Indexes for table `tools`
--
ALTER TABLE `tools`
  ADD PRIMARY KEY (`tool_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_report`
--
ALTER TABLE `activity_report`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `consumers`
--
ALTER TABLE `consumers`
  MODIFY `consumer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `lendings`
--
ALTER TABLE `lendings`
  MODIFY `lending_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `tools`
--
ALTER TABLE `tools`
  MODIFY `tool_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
