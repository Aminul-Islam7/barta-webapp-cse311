-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2025 at 02:56 PM
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
-- Database: `barta_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bartauser`
--

CREATE TABLE `bartauser` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(100) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `birth_date` date NOT NULL,
  `role` enum('tween','parent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bartauser`
--

INSERT INTO `bartauser` (`id`, `email`, `password_hash`, `full_name`, `birth_date`, `role`, `created_at`) VALUES
(1, 'aminulfardin7@gmail.com', '$2y$10$k0qXSjrxLOaUwYrwbxjElObMAX2xngNr6OgVBYsrlQm9aea25hwn6', 'Aminul', '2002-09-18', 'tween', '2025-12-01 12:28:02');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_word`
--

CREATE TABLE `blocked_word` (
  `word_id` int(11) NOT NULL,
  `tween_id` int(11) NOT NULL,
  `word` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `connection`
--

CREATE TABLE `connection` (
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `type` enum('added','blocked') NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `connection_request`
--

CREATE TABLE `connection_request` (
  `requester_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `requester_parent_approved` tinyint(1) NOT NULL DEFAULT 0,
  `receiver_parent_approved` tinyint(1) NOT NULL DEFAULT 0,
  `receiver_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_member`
--

CREATE TABLE `group_member` (
  `group_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `added_by` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_message`
--

CREATE TABLE `group_message` (
  `message_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `individual_message`
--

CREATE TABLE `individual_message` (
  `message_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `text_content` varchar(4096) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_clean` tinyint(1) NOT NULL DEFAULT 1,
  `parent_approval` enum('not required','pending','approved','rejected') NOT NULL DEFAULT 'not required'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_user`
--

CREATE TABLE `parent_user` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `personal_id_type` enum('Passport','NID','Drivers license') NOT NULL,
  `personal_id_number` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tween_link_request`
--

CREATE TABLE `tween_link_request` (
  `tween_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tween_user`
--

CREATE TABLE `tween_user` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `bio` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `daily_msg_limit` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_group`
--

CREATE TABLE `user_group` (
  `id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `color` varchar(7) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bartauser`
--
ALTER TABLE `bartauser`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `blocked_word`
--
ALTER TABLE `blocked_word`
  ADD PRIMARY KEY (`word_id`),
  ADD KEY `fk_BW_tween` (`tween_id`);

--
-- Indexes for table `connection`
--
ALTER TABLE `connection`
  ADD KEY `fk_connection_sender` (`sender_id`),
  ADD KEY `fk_connection_receiver` (`receiver_id`);

--
-- Indexes for table `connection_request`
--
ALTER TABLE `connection_request`
  ADD KEY `fk_CR_requester` (`requester_id`),
  ADD KEY `fk_CR_receiver` (`receiver_id`);

--
-- Indexes for table `group_member`
--
ALTER TABLE `group_member`
  ADD KEY `fk_GM_group` (`group_id`),
  ADD KEY `fk_GM_member` (`member_id`),
  ADD KEY `fk_GM_added_by` (`added_by`);

--
-- Indexes for table `group_message`
--
ALTER TABLE `group_message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_GMS_group_ref` (`group_id`);

--
-- Indexes for table `individual_message`
--
ALTER TABLE `individual_message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_IM_receiver` (`receiver_id`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_message_sender` (`sender_id`);

--
-- Indexes for table `parent_user`
--
ALTER TABLE `parent_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_parent_user_bartaUser` (`user_id`);

--
-- Indexes for table `tween_link_request`
--
ALTER TABLE `tween_link_request`
  ADD KEY `fk_TLR_tween` (`tween_id`),
  ADD KEY `fk_TLR_parent` (`parent_id`);

--
-- Indexes for table `tween_user`
--
ALTER TABLE `tween_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_tween_user_bartaUser` (`user_id`),
  ADD KEY `fk_tween_user_parent_user` (`parent_id`);

--
-- Indexes for table `user_group`
--
ALTER TABLE `user_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_group_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bartauser`
--
ALTER TABLE `bartauser`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `blocked_word`
--
ALTER TABLE `blocked_word`
  MODIFY `word_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_user`
--
ALTER TABLE `parent_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tween_user`
--
ALTER TABLE `tween_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_group`
--
ALTER TABLE `user_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blocked_word`
--
ALTER TABLE `blocked_word`
  ADD CONSTRAINT `fk_BW_tween` FOREIGN KEY (`tween_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `connection`
--
ALTER TABLE `connection`
  ADD CONSTRAINT `fk_connection_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_connection_sender` FOREIGN KEY (`sender_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `connection_request`
--
ALTER TABLE `connection_request`
  ADD CONSTRAINT `fk_CR_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_CR_requester` FOREIGN KEY (`requester_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `group_member`
--
ALTER TABLE `group_member`
  ADD CONSTRAINT `fk_GM_added_by` FOREIGN KEY (`added_by`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_GM_group` FOREIGN KEY (`group_id`) REFERENCES `user_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_GM_member` FOREIGN KEY (`member_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `group_message`
--
ALTER TABLE `group_message`
  ADD CONSTRAINT `fk_GMS_group_ref` FOREIGN KEY (`group_id`) REFERENCES `user_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_GMS_message` FOREIGN KEY (`message_id`) REFERENCES `message` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `individual_message`
--
ALTER TABLE `individual_message`
  ADD CONSTRAINT `fk_IM_message` FOREIGN KEY (`message_id`) REFERENCES `message` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_IM_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `tween_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `tween_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `parent_user`
--
ALTER TABLE `parent_user`
  ADD CONSTRAINT `fk_parent_user_bartaUser` FOREIGN KEY (`user_id`) REFERENCES `bartauser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tween_link_request`
--
ALTER TABLE `tween_link_request`
  ADD CONSTRAINT `fk_TLR_parent` FOREIGN KEY (`parent_id`) REFERENCES `parent_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_TLR_tween` FOREIGN KEY (`tween_id`) REFERENCES `tween_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tween_user`
--
ALTER TABLE `tween_user`
  ADD CONSTRAINT `fk_tween_user_bartaUser` FOREIGN KEY (`user_id`) REFERENCES `bartauser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tween_user_parent_user` FOREIGN KEY (`parent_id`) REFERENCES `bartauser` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_group`
--
ALTER TABLE `user_group`
  ADD CONSTRAINT `fk_user_group_created_by` FOREIGN KEY (`created_by`) REFERENCES `tween_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
