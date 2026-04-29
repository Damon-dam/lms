-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 09:42 AM
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
-- Database: `library_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `available` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `title`, `author`, `category`, `isbn`, `quantity`, `available`, `created_at`) VALUES
(1, 'Clean Code', 'Robert C. Martin', 'Software Development', 'SD001', 5, 5, '2026-04-26 11:19:28'),
(2, 'The Pragmatic Programmer', 'Andrew Hunt & David Thomas', 'Software Development', 'SD002', 4, 4, '2026-04-26 11:19:28'),
(3, 'Introduction to Algorithms', 'Thomas H. Cormen', 'Software Development', 'SD003', 3, 3, '2026-04-26 11:19:28'),
(4, 'Design Patterns', 'Erich Gamma', 'Software Development', 'SD004', 6, 6, '2026-04-26 11:19:28'),
(5, 'bestprogrammers history', 'dony', 'sod', NULL, 5, 5, '2026-04-26 15:22:30');

-- --------------------------------------------------------

--
-- Table structure for table `borrowing`
--

CREATE TABLE `borrowing` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowing`
--

INSERT INTO `borrowing` (`id`, `user_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`) VALUES
(1, 5, 0, '2026-04-26', '2026-05-03', NULL, 'Borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `message`, `created_at`) VALUES
(1, 'caline aimee', 'calineaimee686@gmail.com', 'lfkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkd', '2026-04-26 13:10:15'),
(2, 'caline aimee', 'nsxsjjjjj@gmail.com', 'gcddddddddddddddddddddddddddddddddddddddd', '2026-04-26 16:05:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`) VALUES
(4, 'aimee caline', 'caline', '$2y$10$1khPgnaFkr4E3erv6RwP1Oxniwxwfx90xHc8IKkrs5lyKkD8Kuuku', 'user'),
(5, 'john', 'john', '$2y$10$sQErLOFXYI8LeOAagv/U9ePbP/sfNYsPiwDXw3Ygy0sTcTAF86G6q', 'user'),
(6, 'mimi', 'mimi', '$2y$10$Zq0AU5POjc.Rif.dD9IZC.RUI7WZX9HQbl0BGCQwq3h4XvGONy2Q6', 'user'),
(10, 'aimee caline', 'caline', '$2y$10$ttyCBXpSKqcPsdmrnadgEe8cIpMwLzqZ0m2gdzIYcQoMgUkNTDk4K', 'user'),
(11, 'System Admin', 'admin', '$2y$10$3cmcE5ZMiN1Roa8YbWr0.e4WvlhePsp1X1XuaTNApSh6NPj.rw.7O', 'admin'),
(12, 'lucy', 'lucy', '$2y$10$nwwd3Bw0tTlrzIzHFRxuYumVcH7mLXMWKV..LSOhEkL6nKjCkMGgK', 'admin'),
(13, 'abby', 'abby', '$2y$10$doThqBovo3sr4FEfLiNU2OwgTxVwoKnhehHPVNsD4.fo.K5RM5lRe', 'user'),
(14, 'jovia', 'joe', '$2y$10$VUOLzczJrHt0keFQzgeyHea.IDSVCTUfq3T4f6fVnSJ1KC3Z9RcIi', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`);

--
-- Indexes for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `borrowing`
--
ALTER TABLE `borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
