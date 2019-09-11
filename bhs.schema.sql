-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 11, 2019 at 02:35 PM
-- Server version: 10.3.15-MariaDB-1
-- PHP Version: 7.3.4-2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `blackholesun`
--
CREATE DATABASE IF NOT EXISTS `blackholesun` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `blackholesun`;

-- --------------------------------------------------------

--
-- Table structure for table `bh_customers`
--

CREATE TABLE `bh_customers` (
  `bh_customer_id` int(11) NOT NULL,
  `bh_customer_name` char(255) NOT NULL,
  `bh_customer_blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `bh_routes`
--

CREATE TABLE `bh_routes` (
  `bh_index` int(11) NOT NULL,
  `bh_route` varchar(128) NOT NULL,
  `bh_lifespan` int(16) NOT NULL,
  `bh_starttime` datetime NOT NULL,
  `bh_requestor` varchar(32) NOT NULL,
  `bh_active` int(1) NOT NULL DEFAULT 1,
  `bh_customer_id` int(4) NOT NULL COMMENT 'Indicates which institution the route applies to',
  `bh_owner_id` int(4) NOT NULL COMMENT 'Used to indicate the institution that created this route',
  `bh_comment` text DEFAULT NULL,
  `bh_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `bh_users`
--

CREATE TABLE `bh_users` (
  `bh_user_id` int(11) NOT NULL,
  `bh_user_name` varchar(64) NOT NULL,
  `bh_user_pass` varchar(64) NOT NULL,
  `bh_user_fname` varchar(64) NOT NULL,
  `bh_user_lname` varchar(64) NOT NULL,
  `bh_user_email` varchar(64) NOT NULL,
  `bh_user_affiliation` int(4) NOT NULL,
  `bh_user_role` tinyint(4) NOT NULL,
  `bh_user_active` int(1) NOT NULL DEFAULT 0,
  `bh_user_force_password` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `bh_users`
--

INSERT INTO `bh_users` (`bh_user_id`, `bh_user_name`, `bh_user_pass`, `bh_user_fname`, `bh_user_lname`, `bh_user_email`, `bh_user_affiliation`, `bh_user_role`, `bh_user_active`, `bh_user_force_password`) VALUES
(1, 'admin', '$2y$10$qcoBCIWxuScWE20vVAFOhupmjduN0spddlnWXbNlS4WYTUi.M/qA6', 'BHS', 'Admin', 'email@example.com', 1, 4, 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bh_customers`
--
ALTER TABLE `bh_customers`
  ADD PRIMARY KEY (`bh_customer_id`);

--
-- Indexes for table `bh_routes`
--
ALTER TABLE `bh_routes`
  ADD PRIMARY KEY (`bh_index`);

--
-- Indexes for table `bh_users`
--
ALTER TABLE `bh_users`
  ADD PRIMARY KEY (`bh_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bh_customers`
--
ALTER TABLE `bh_customers`
  MODIFY `bh_customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bh_routes`
--
ALTER TABLE `bh_routes`
  MODIFY `bh_index` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bh_users`
--
ALTER TABLE `bh_users`
  MODIFY `bh_user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
