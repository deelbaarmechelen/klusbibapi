-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 23 dec 2020 om 15:05
-- Serverversie: 10.4.11-MariaDB
-- PHP-versie: 7.2.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `klusbib`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `activity_report`
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
-- Tabelstructuur voor tabel `consumers`
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
-- Tabelstructuur voor tabel `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `pick_up_address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `drop_off_address` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `pick_up_date` date DEFAULT NULL,
  `drop_off_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reservation_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `delivery_item`
--

CREATE TABLE `delivery_item` (
  `delivery_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `delivery_item`
--

INSERT INTO `delivery_item` (`delivery_id`, `inventory_item_id`) VALUES
(1, 3);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `events`
--

CREATE TABLE `events` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `version` int(11) NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `currency` varchar(50) COLLATE utf8_unicode_ci DEFAULT 'euro',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `inventory_item`
--

CREATE TABLE `inventory_item` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `item_type` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `current_location_id` int(10) UNSIGNED DEFAULT NULL,
  `item_condition` int(10) UNSIGNED DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `keywords` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `brand` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `care_information` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `component_information` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `loan_fee` decimal(10,2) DEFAULT NULL,
  `max_loan_days` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_website` tinyint(1) NOT NULL DEFAULT 1,
  `serial` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `note` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `price_cost` decimal(10,2) DEFAULT NULL,
  `price_sell` decimal(10,2) DEFAULT NULL,
  `image_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `short_url` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_sector` int(10) UNSIGNED DEFAULT NULL,
  `is_reservable` tinyint(1) NOT NULL DEFAULT 1,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `donated_by` int(10) UNSIGNED DEFAULT NULL,
  `owned_by` int(10) UNSIGNED DEFAULT NULL,
  `last_sync_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `inventory_item`
--

INSERT INTO `inventory_item` (`id`, `name`, `item_type`, `created_by`, `assigned_to`, `current_location_id`, `item_condition`, `sku`, `description`, `keywords`, `brand`, `care_information`, `component_information`, `loan_fee`, `max_loan_days`, `is_active`, `show_on_website`, `serial`, `note`, `price_cost`, `price_sell`, `image_name`, `short_url`, `item_sector`, `is_reservable`, `deposit_amount`, `donated_by`, `owned_by`, `last_sync_date`, `created_at`, `updated_at`) VALUES
(1, 'Bouwstofzuiger', 'TOOL', NULL, NULL, NULL, NULL, 'KB-000-20-001', NULL, 'general', 'Makita', NULL, NULL, NULL, NULL, 1, 1, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2020-12-09 22:00:07', '2020-11-02 17:06:45', '2020-12-09 22:00:07'),
(2, 'Bouwstofzuiger', 'TOOL', NULL, NULL, NULL, NULL, 'KB-000-20-002', NULL, 'general', 'Makita', NULL, NULL, NULL, NULL, 1, 1, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2020-12-09 22:00:07', '2020-11-02 17:06:45', '2020-12-09 22:00:07'),
(3, 'Bouwstofzuiger', 'TOOL', NULL, NULL, NULL, NULL, 'KB-000-20-123', NULL, 'general', 'Gardena', NULL, NULL, NULL, NULL, 1, 1, '', 'De &#039;schroevendraaier&#039; waar de bitjes aan kunnen bevestigd worden is vermist.\r\nEen van de precisie-schroevendraaiers...', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2020-12-09 22:00:07', '2020-11-02 17:27:09', '2020-12-09 22:00:07'),
(100001, 'test', 'ACCESSORY', NULL, NULL, NULL, NULL, 'test', NULL, NULL, 'Gardena', NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2020-12-09 22:00:07', '2020-11-02 17:33:57', '2020-12-09 22:00:10');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `lendings`
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tool_type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'TOOL'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `lendings`
--

INSERT INTO `lendings` (`lending_id`, `start_date`, `due_date`, `returned_date`, `tool_id`, `user_id`, `created_by`, `comments`, `created_at`, `updated_at`, `tool_type`) VALUES
(1, '2020-06-30 22:00:00', '2020-07-06 22:00:00', NULL, 1, 3, NULL, '', '2020-07-05 21:38:05', '2020-07-05 21:38:05', 'TOOL'),
(2, '2020-07-05 22:00:00', '2020-07-06 22:00:00', '2020-07-05 22:26:17', 2, 1, NULL, '', '2020-07-05 22:18:53', '2020-07-05 22:18:53', 'TOOL'),
(3, '2020-07-04 22:00:00', '2020-07-11 22:00:00', NULL, 2, 1, NULL, '', '2020-07-05 22:18:53', '2020-07-05 22:18:53', 'TOOL');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `membership`
--

CREATE TABLE `membership` (
  `id` int(10) UNSIGNED NOT NULL,
  `start_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'DISABLED',
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_id` int(10) UNSIGNED DEFAULT NULL,
  `last_payment_mode` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `membership`
--

INSERT INTO `membership` (`id`, `start_at`, `expires_at`, `status`, `subscription_id`, `contact_id`, `last_payment_mode`, `comment`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '2020-06-16 22:00:00', '2020-06-23 22:00:00', 'EXPIRED', 1, 1, 'MOLLIE', NULL, NULL, '2020-09-01 12:27:17', NULL),
(2, '2020-06-25 22:00:00', '2021-06-25 22:00:00', 'ACTIVE', 1, 2, 'PAYCONIQ', NULL, '2020-06-24 22:25:19', '2020-07-21 21:59:54', NULL),
(3, '2020-06-25 22:00:00', '2021-06-25 22:00:00', 'ACTIVE', 1, 3, 'TRANSFER', NULL, '2020-06-25 23:09:26', '2020-08-05 23:56:42', NULL),
(14, '2020-06-16 22:00:00', '2021-06-23 20:00:00', 'ACTIVE', 3, 1, 'MOLLIE', NULL, '2020-09-01 12:27:17', '2020-09-01 12:27:37', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `membership_type`
--

CREATE TABLE `membership_type` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `price` decimal(10,2) UNSIGNED DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `discount` decimal(10,2) UNSIGNED DEFAULT NULL,
  `description` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `self_serve` int(11) NOT NULL,
  `credit_limit` decimal(10,2) UNSIGNED DEFAULT NULL,
  `max_items` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `next_subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `membership_type`
--

INSERT INTO `membership_type` (`id`, `name`, `price`, `duration`, `discount`, `description`, `self_serve`, `credit_limit`, `max_items`, `is_active`, `next_subscription_id`, `created_at`, `updated_at`) VALUES
(1, 'Regular', '30.00', 365, NULL, '', 1, NULL, 5, 1, 3, NULL, NULL),
(2, 'Temporary', '0.00', 60, NULL, '', 0, NULL, 5, 1, 1, NULL, NULL),
(3, 'Renewal', '20.00', 365, NULL, '', 0, NULL, 5, 1, NULL, NULL, NULL),
(4, 'Stroom', '0.00', 365, NULL, '', 0, NULL, 5, 1, 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `mode` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'NEW',
  `order_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `currency` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `membership_id` int(10) UNSIGNED DEFAULT NULL,
  `loan_id` int(10) UNSIGNED DEFAULT NULL,
  `payment_ext_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expiration_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `payments`
--

INSERT INTO `payments` (`payment_id`, `user_id`, `mode`, `payment_date`, `state`, `order_id`, `amount`, `currency`, `comment`, `created_at`, `updated_at`, `membership_id`, `loan_id`, `payment_ext_id`, `expiration_date`) VALUES
(1, 3, 'STROOM', '2020-08-05 22:57:27', 'SUCCESS', '3-20200806125725', '0.00', 'EUR', NULL, '2020-08-05 22:57:27', '2020-08-05 22:57:27', 3, NULL, NULL, NULL),
(2, 3, 'STROOM', '2020-08-05 23:37:14', 'SUCCESS', '3-20200806013712', '0.00', 'EUR', NULL, '2020-08-05 23:37:14', '2020-08-05 23:37:14', 3, NULL, NULL, NULL),
(3, 3, 'TRANSFER', '2020-08-05 23:56:42', 'OPEN', '3-20200806015641', '20.00', 'EUR', NULL, '2020-08-05 23:56:42', '2020-08-05 23:56:42', 3, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `phinxlog`
--

CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Gegevens worden geëxporteerd voor tabel `phinxlog`
--

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES
(20170115194754, 'CreateTools', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170206203251, 'CreateReservations', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170207220328, 'CreateUsers', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170312202026, 'UpdateTools', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170315223653, 'CreateConsumers', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170401113552, 'UpdateToolsAddCodeOwner', '2020-06-13 23:13:33', '2020-06-13 23:13:33', 0),
(20170414144715, 'UsersExtraData', '2020-06-13 23:13:33', '2020-06-13 23:13:34', 0),
(20170416143711, 'UpdateUserPrimaryKey', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170509200808, 'UserState', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170518204508, 'UpdateUserAddressSize', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170518204803, 'AddToolExperienceLevel', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170625205328, 'UpdateUserRegistrationNumber', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170802153219, 'UpdateUserPaymentMode', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170807202156, 'UpdateReservationAddState', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20170923174916, 'UsersTermsOfUse', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20171015150456, 'UpdateReservationDescription', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20171115214239, 'UpdateToolVisible', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20180406205935, 'UpdateReservationsCommentSize', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20180406210240, 'UpdateUsersSizes', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20180406210521, 'CreateEvents', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20180406215732, 'CreateActivityReport', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20181107211504, 'UpdateUserEmailState', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20181107211716, 'CreatePayments', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20190319220711, 'UpdateUserExtId', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20190406113139, 'UpdateToolExtId', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20190710141634, 'CreateLending', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20191229101321, 'CreateProjectUser', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20191229230525, 'CreateProject', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20200208143905, 'UpdateLendingToolType', '2020-06-13 23:13:34', '2020-06-13 23:13:34', 0),
(20200516072649, 'Deliveries', '2020-08-26 22:40:14', '2020-08-26 22:40:14', 0),
(20200728193058, 'CreateMemberships', '2020-09-01 12:23:36', '2020-09-01 12:23:37', 0),
(20200825232700, 'UpdateUsersSyncRelations', '2020-09-01 12:23:37', '2020-09-01 12:23:37', 0),
(20200901075908, 'UpdatePaymentRelations', '2020-09-01 12:23:37', '2020-09-01 12:23:37', 0),
(20201025210400, 'UpdateDeliveries', '2020-10-26 22:16:37', '2020-10-26 22:16:37', 0),
(20201025213037, 'CreateInventoryItem', '2020-11-02 17:06:40', '2020-11-02 17:06:40', 0),
(20201025213045, 'CreateDeliveryItem', '2020-11-02 17:06:40', '2020-11-02 17:06:40', 0),
(20201113222417, 'UpdatePayments', '2020-11-13 22:35:17', '2020-11-13 22:35:17', 0);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `info` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `projects`
--

INSERT INTO `projects` (`id`, `name`, `info`, `created_at`, `updated_at`) VALUES
(1, 'STROOM ', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `project_user`
--

CREATE TABLE `project_user` (
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `info` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `project_user`
--

INSERT INTO `project_user` (`project_id`, `user_id`, `info`, `created_at`, `updated_at`) VALUES
(1, 3, NULL, '2020-06-24 22:25:19', '2020-06-24 22:29:05'),
(1, 7, NULL, '2020-10-18 21:23:14', '2020-10-18 21:23:14'),
(1, 5, NULL, '2020-10-24 21:43:44', '2020-10-24 21:43:44');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `reservations`
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
-- Tabelstructuur voor tabel `tools`
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
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'NEW',
  `tool_ext_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users`
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
  `user_ext_id` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_sync_date` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `active_membership` int(10) UNSIGNED DEFAULT NULL,
  `company` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `users`
--

INSERT INTO `users` (`firstname`, `lastname`, `role`, `email`, `hash`, `membership_start_date`, `membership_end_date`, `created_at`, `updated_at`, `birth_date`, `address`, `postal_code`, `city`, `phone`, `mobile`, `user_id`, `state`, `registration_number`, `payment_mode`, `accept_terms_date`, `email_state`, `user_ext_id`, `last_sync_date`, `active_membership`, `company`, `comment`, `last_login`, `deleted_at`) VALUES
('admin', 'admin', 'admin', 'admin@klusbib.be', '$2y$10$KeHwjOHPXLIPbcJaQIAfsurLrn/igT9C1tPgXLkAO9oHCkHReLwoW', '2020-06-17', '2021-06-23', NULL, '2020-12-23 10:23:40', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'ACTIVE', NULL, 'MOLLIE', NULL, 'CONFIRMED', '3', '2020-12-16 11:15:17', 14, NULL, NULL, '2020-12-23 10:23:40', NULL),
('DummyA', 'DummyB', 'member', 'dummyA@klusbib.be', '$2y$10$KeHwjOHPXLIPbcJaQIAfsurLrn/igT9C1tPgXLkAO9oHCkHReLwoW', '2020-06-26', '2021-06-26', '2020-06-24 22:25:19', '2020-11-18 17:40:48', NULL, 'here 124', '2801', 'Muizen', '12345610', '0456123450', 2, 'ACTIVE', '77051836172', 'MBON', '2020-06-26', 'BOUNCED', '2', '2020-11-18 18:40:48', 2, 'Deelbaar Mechelen', NULL, NULL, NULL),
('Dummy', 'Dummy', 'member', 'dummy@klusbib.be', '$2y$10$KeHwjOHPXLIPbcJaQIAfsurLrn/igT9C1tPgXLkAO9oHCkHReLwoW', '2020-06-26', '2021-06-26', '2020-06-25 23:09:26', '2020-11-13 23:04:18', NULL, 'adres', '2800', 'Mechelen', '12345678', '456123456', 3, 'ACTIVE', '77051836172', 'TRANSFER', '2020-06-26', 'CONFIRM_EMAIL', '2', '2020-11-14 00:04:18', 3, NULL, NULL, NULL, NULL);

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `activity_report`
--
ALTER TABLE `activity_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `consumers`
--
ALTER TABLE `consumers`
  ADD PRIMARY KEY (`consumer_id`);

--
-- Indexen voor tabel `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexen voor tabel `inventory_item`
--
ALTER TABLE `inventory_item`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `lendings`
--
ALTER TABLE `lendings`
  ADD PRIMARY KEY (`lending_id`);

--
-- Indexen voor tabel `membership`
--
ALTER TABLE `membership`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `membership_type`
--
ALTER TABLE `membership_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `payments_membership_id_foreign` (`membership_id`);

--
-- Indexen voor tabel `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);

--
-- Indexen voor tabel `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `reservations_tool_id_index` (`tool_id`),
  ADD KEY `reservations_user_id_index` (`user_id`);

--
-- Indexen voor tabel `tools`
--
ALTER TABLE `tools`
  ADD PRIMARY KEY (`tool_id`);

--
-- Indexen voor tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `users_active_membership_foreign` (`active_membership`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `activity_report`
--
ALTER TABLE `activity_report`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `consumers`
--
ALTER TABLE `consumers`
  MODIFY `consumer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `lendings`
--
ALTER TABLE `lendings`
  MODIFY `lending_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT voor een tabel `membership`
--
ALTER TABLE `membership`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT voor een tabel `membership_type`
--
ALTER TABLE `membership_type`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT voor een tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `tools`
--
ALTER TABLE `tools`
  MODIFY `tool_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_membership_id_foreign` FOREIGN KEY (`membership_id`) REFERENCES `membership` (`id`);

--
-- Beperkingen voor tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_active_membership_foreign` FOREIGN KEY (`active_membership`) REFERENCES `membership` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
