-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 05, 2026 at 12:59 PM
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
-- Database: `student_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `absence_notifications`
--

CREATE TABLE `absence_notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `parent_contact` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advisers`
--

CREATE TABLE `advisers` (
  `id` int(11) NOT NULL,
  `teacher_name` varchar(255) NOT NULL,
  `grade` varchar(100) NOT NULL,
  `pin` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `advisers`
--

INSERT INTO `advisers` (`id`, `teacher_name`, `grade`, `pin`) VALUES
(7, 'TIGREAL', 'Grade-12', '123456'),
(8, 'Zsyza', 'Grade-12', '123456');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `date` date NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `scan_status` varchar(50) DEFAULT 'Time-In Pending',
  `first_scan_time` time DEFAULT NULL,
  `second_scan_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `status`, `date`, `timestamp`, `time_in`, `time_out`, `scan_status`, `first_scan_time`, `second_scan_time`) VALUES
(55, 66, 'Present', '2026-08-25', '2026-08-25 07:44:17', '15:44:17', '16:09:53', 'Time-In Pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_log`
--

CREATE TABLE `attendance_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Present',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `no_classes`
--

CREATE TABLE `no_classes` (
  `id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin` varchar(100) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `advisers` int(11) NOT NULL,
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '09:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`id`, `section_name`, `created_at`, `admin`, `adviser_id`, `advisers`, `start_time`, `end_time`) VALUES
(59, 'dwaddwa', '2026-08-21 10:19:14', NULL, 1, 0, '08:00:00', '09:00:00'),
(65, 'PASSCODE', '2026-08-23 05:57:28', NULL, 7, 0, '15:48:00', '20:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `section` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'Present',
  `time_in` datetime NOT NULL,
  `registration_stage` varchar(20) DEFAULT 'Staging',
  `section_id` int(11) DEFAULT NULL,
  `admin` varchar(100) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `parent_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `last_name`, `first_name`, `middle_initial`, `section`, `status`, `time_in`, `registration_stage`, `section_id`, `admin`, `adviser_id`, `parent_number`) VALUES
(61, 'BENSI', 'JACOB', '', 'PASSCODE', 'Present', '2026-08-23 17:26:22', 'Staging', NULL, NULL, 7, NULL),
(62, 'aa', 'Danie Mae', '', 'PASSCODE', 'Present', '2026-08-23 17:31:49', 'Staging', NULL, NULL, 7, NULL),
(66, 'wdaw', 'waf', '', 'PASSCODE', 'Present', '2026-08-25 15:42:03', 'Staging', NULL, NULL, 7, '09083511971'),
(69, 'Dungog', 'Zsyza', '', 'PASSCODE', 'Present', '2026-08-25 15:48:12', 'Staging', NULL, NULL, 7, '09560396409'),
(70, 'Dungog', 'Zsyza', '', '', 'Present', '2026-09-05 01:05:50', 'Staging', NULL, NULL, 8, '09942873147');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absence_notifications`
--
ALTER TABLE `absence_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `advisers`
--
ALTER TABLE `advisers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_log`
--
ALTER TABLE `attendance_log`
  ADD KEY `fk_attlog_student` (`student_id`);

--
-- Indexes for table `no_classes`
--
ALTER TABLE `no_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_no_class` (`adviser_id`,`section_name`,`date`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_name` (`section_name`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_section` (`section_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absence_notifications`
--
ALTER TABLE `absence_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advisers`
--
ALTER TABLE `advisers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `no_classes`
--
ALTER TABLE `no_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_log`
--
ALTER TABLE `attendance_log`
  ADD CONSTRAINT `fk_attlog_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `fk_student_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
