-- =======================================================
-- UNIVERSITY ENROLLMENT & MULTI-STEP CLEARANCE SYSTEM
-- Database Schema & Comprehensive Demo Seed Data
-- =======================================================

CREATE DATABASE IF NOT EXISTS `enrollment_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `enrollment_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `deficiencies`;
DROP TABLE IF EXISTS `student_enrollments`;
DROP TABLE IF EXISTS `student_subject_loads`;
DROP TABLE IF EXISTS `accounting_assessments`;
DROP TABLE IF EXISTS `clearance_stages`;
DROP TABLE IF EXISTS `clearance_requests`;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `programs`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `academic_terms`;

-- -------------------------------------------------------
-- 1. ACADEMIC TERMS
-- -------------------------------------------------------
CREATE TABLE `academic_terms` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `academic_year` VARCHAR(20) NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `academic_terms` (`id`, `academic_year`, `semester`, `is_active`) VALUES
(1, '2026-2027', '1st Semester', 1),
(2, '2026-2027', '2nd Semester', 0),
(3, '2025-2026', '2nd Semester', 0);

-- -------------------------------------------------------
-- 2. DEPARTMENTS
-- -------------------------------------------------------
CREATE TABLE `departments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `dean_name` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departments` (`id`, `code`, `name`, `dean_name`) VALUES
(1, 'CCS', 'College of Computer Studies', 'Dr. Alan Turing'),
(2, 'CBA', 'College of Business Administration', 'Dr. Warren Buffett'),
(3, 'COE', 'College of Engineering', 'Dr. Nikola Tesla'),
(4, 'CAS', 'College of Arts and Sciences', 'Dr. Marie Curie');

-- -------------------------------------------------------
-- 3. PROGRAMS
-- -------------------------------------------------------
CREATE TABLE `programs` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `total_years` INT DEFAULT 4,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `programs` (`id`, `department_id`, `code`, `name`, `total_years`) VALUES
(1, 1, 'BSCS', 'Bachelor of Science in Computer Science', 4),
(2, 1, 'BSIT', 'Bachelor of Science in Information Technology', 4),
(3, 2, 'BSBA', 'Bachelor of Science in Business Administration', 4),
(4, 3, 'BSCE', 'Bachelor of Science in Civil Engineering', 4);

-- -------------------------------------------------------
-- 4. USERS
-- -------------------------------------------------------
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'student', 'department', 'library', 'accounting', 'registrar') NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `department_id` INT DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 5. STUDENTS
-- -------------------------------------------------------
CREATE TABLE `students` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `student_no` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(60) NOT NULL,
    `middle_name` VARCHAR(60) DEFAULT NULL,
    `last_name` VARCHAR(60) NOT NULL,
    `birth_date` DATE DEFAULT NULL,
    `age` INT DEFAULT NULL,
    `sex` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
    `religion` VARCHAR(80) DEFAULT NULL,
    `civil_status` ENUM('Single', 'Married', 'Widowed', 'Separated') DEFAULT 'Single',
    `birth_place` VARCHAR(150) DEFAULT NULL,
    `program_id` INT NOT NULL,
    `year_level` INT NOT NULL DEFAULT 1,
    `enrolling_semester` ENUM('1st Semester', '2nd Semester') DEFAULT '1st Semester',
    `academic_status` ENUM('Regular', 'Irregular', 'Probationary', 'Graduating') DEFAULT 'Regular',
    `contact_no` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `guardian_name` VARCHAR(120) DEFAULT NULL,
    `guardian_contact` VARCHAR(30) DEFAULT NULL,
    `enrollment_status` ENUM('Not Enrolled', 'In Clearance', 'Cleared', 'Enrolled') DEFAULT 'In Clearance',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 6. SUBJECTS & CURRICULUM
-- -------------------------------------------------------
CREATE TABLE `subjects` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `program_id` INT NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `lecture_units` INT DEFAULT 3,
    `lab_units` INT DEFAULT 0,
    `total_units` INT DEFAULT 3,
    `tuition_rate_per_unit` DECIMAL(10,2) DEFAULT 450.00,
    `lab_fee` DECIMAL(10,2) DEFAULT 0.00,
    `prerequisites` VARCHAR(100) DEFAULT 'None',
    `year_level` INT NOT NULL,
    `semester` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `subjects` (`id`, `program_id`, `code`, `title`, `lecture_units`, `lab_units`, `total_units`, `tuition_rate_per_unit`, `lab_fee`, `prerequisites`, `year_level`, `semester`) VALUES
-- BSCS 1st Year - 1st Sem
(1, 1, 'CS101', 'Introduction to Computing', 2, 1, 3, 450.00, 1200.00, 'None', 1, '1st Semester'),
(2, 1, 'CS102', 'Fundamentals of Programming (C++)', 2, 1, 3, 450.00, 1500.00, 'None', 1, '1st Semester'),
(3, 1, 'MATH101', 'Calculus for Computing I', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(4, 1, 'GE101', 'Understanding the Self', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(5, 1, 'GE102', 'Purposive Communication', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(6, 1, 'PE101', 'Physical Fitness and Wellness', 2, 0, 2, 450.00, 0.00, 'None', 1, '1st Semester'),
(7, 1, 'NSTP101', 'National Service Training Program 1', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),

-- BSCS 2nd Year - 1st Sem
(8, 1, 'CS201', 'Data Structures and Algorithms', 2, 1, 3, 450.00, 1500.00, 'CS102', 2, '1st Semester'),
(9, 1, 'CS202', 'Object-Oriented Programming (Java)', 2, 1, 3, 450.00, 1500.00, 'CS102', 2, '1st Semester'),
(10, 1, 'CS203', 'Discrete Structures', 3, 0, 3, 450.00, 0.00, 'MATH101', 2, '1st Semester'),
(11, 1, 'CS204', 'Database Management Systems', 2, 1, 3, 450.00, 1500.00, 'CS102', 2, '1st Semester'),
(12, 1, 'GE103', 'Art Appreciation', 3, 0, 3, 450.00, 0.00, 'None', 2, '1st Semester'),

-- BSIT 1st Year - 1st Sem
(13, 2, 'IT101', 'IT Fundamentals & Office Automation', 2, 1, 3, 450.00, 1200.00, 'None', 1, '1st Semester'),
(14, 2, 'IT102', 'Computer Programming 1 (Python)', 2, 1, 3, 450.00, 1500.00, 'None', 1, '1st Semester'),
(15, 2, 'MATH101', 'College Algebra & Trigonometry', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(16, 2, 'GE101', 'Understanding the Self', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),

-- BSBA 1st Year - 1st Sem
(17, 3, 'BA101', 'Principles of Management', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(18, 3, 'BA102', 'Financial Accounting 1', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester'),
(19, 3, 'BA103', 'Business Microeconomics', 3, 0, 3, 450.00, 0.00, 'None', 1, '1st Semester');

-- -------------------------------------------------------
-- 7. SCHEDULES / CLASS SECTIONS
-- -------------------------------------------------------
CREATE TABLE `schedules` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `academic_term_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `section` VARCHAR(30) NOT NULL,
    `days` VARCHAR(20) NOT NULL,
    `time_start` TIME NOT NULL,
    `time_end` TIME NOT NULL,
    `room` VARCHAR(50) NOT NULL,
    `instructor` VARCHAR(100) NOT NULL,
    `max_slots` INT DEFAULT 40,
    `enrolled_slots` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schedules` (`id`, `academic_term_id`, `subject_id`, `section`, `days`, `time_start`, `time_end`, `room`, `instructor`, `max_slots`, `enrolled_slots`) VALUES
-- BSCS 2nd Year Subjects (Term 1)
(1, 1, 8, 'BSCS 2A', 'MWF', '08:00:00', '09:00:00', 'CLAB 1', 'Engr. Ada Lovelace', 35, 12),
(2, 1, 8, 'BSCS 2B', 'TTH', '10:00:00', '11:30:00', 'CLAB 2', 'Engr. Ada Lovelace', 35, 8),
(3, 1, 9, 'BSCS 2A', 'MWF', '09:00:00', '10:00:00', 'CLAB 3', 'Prof. James Gosling', 35, 12),
(4, 1, 9, 'BSCS 2B', 'TTH', '01:00:00', '02:30:00', 'CLAB 1', 'Prof. James Gosling', 35, 6),
(5, 1, 10, 'BSCS 2A', 'MWF', '10:00:00', '11:00:00', 'RM 301', 'Dr. John von Neumann', 40, 12),
(6, 1, 10, 'BSCS 2B', 'TTH', '08:30:00', '10:00:00', 'RM 302', 'Dr. John von Neumann', 40, 9),
(7, 1, 11, 'BSCS 2A', 'MWF', '01:00:00', '02:00:00', 'CLAB 2', 'Prof. Edgar Codd', 35, 12),
(8, 1, 11, 'BSCS 2B', 'TTH', '02:30:00', '04:00:00', 'CLAB 3', 'Prof. Edgar Codd', 35, 7),
(9, 1, 12, 'BSCS 2A', 'MWF', '02:00:00', '03:00:00', 'RM 204', 'Prof. Leonardo da Vinci', 45, 12),

-- BSCS 1st Year Subjects (Term 1)
(10, 1, 1, 'BSCS 1A', 'MWF', '08:00:00', '09:00:00', 'CLAB 4', 'Prof. Grace Hopper', 40, 15),
(11, 1, 2, 'BSCS 1A', 'MWF', '09:00:00', '10:00:00', 'CLAB 4', 'Prof. Bjarne Stroustrup', 40, 15),
(12, 1, 3, 'BSCS 1A', 'MWF', '10:00:00', '11:00:00', 'RM 101', 'Dr. Isaac Newton', 40, 15),
(13, 1, 4, 'BSCS 1A', 'TTH', '08:30:00', '10:00:00', 'RM 102', 'Dr. Carl Jung', 45, 15),
(14, 1, 5, 'BSCS 1A', 'TTH', '10:00:00', '11:30:00', 'RM 103', 'Prof. Noam Chomsky', 45, 15),
(16, 1, 7, 'BSCS 1A', 'SAT', '10:00:00', '01:00:00', 'AUDITORIUM', 'Maj. Sun Tzu', 50, 15),

-- Additional Department Section Offerings (BSCS 2C)
(17, 1, 8, 'BSCS 2C', 'TTH', '01:00:00', '02:30:00', 'CLAB 4', 'Engr. Ada Lovelace', 35, 0),
(18, 1, 9, 'BSCS 2C', 'TTH', '02:30:00', '04:00:00', 'CLAB 2', 'Prof. James Gosling', 35, 0),
(19, 1, 10, 'BSCS 2C', 'MWF', '08:00:00', '09:00:00', 'RM 303', 'Dr. John von Neumann', 40, 0),
(20, 1, 11, 'BSCS 2C', 'MWF', '09:00:00', '10:00:00', 'CLAB 1', 'Prof. Edgar Codd', 35, 0),
(21, 1, 12, 'BSCS 2C', 'MWF', '10:00:00', '11:00:00', 'RM 205', 'Prof. Leonardo da Vinci', 45, 0),

-- BSIT 1st Year Section Offerings (BSIT 1A & 1B)
(22, 1, 13, 'BSIT 1A', 'MWF', '08:00:00', '09:00:00', 'CLAB 5', 'Prof. Tim Berners-Lee', 40, 0),
(23, 1, 13, 'BSIT 1B', 'TTH', '10:00:00', '11:30:00', 'CLAB 5', 'Prof. Tim Berners-Lee', 40, 0),
(24, 1, 14, 'BSIT 1A', 'MWF', '09:00:00', '10:00:00', 'CLAB 6', 'Prof. Guido van Rossum', 40, 0),
(25, 1, 14, 'BSIT 1B', 'TTH', '01:00:00', '02:30:00', 'CLAB 6', 'Prof. Guido van Rossum', 40, 0),
(26, 1, 15, 'BSIT 1A', 'MWF', '10:00:00', '11:00:00', 'RM 201', 'Prof. Katherine Johnson', 45, 0),
(27, 1, 15, 'BSIT 1B', 'TTH', '08:30:00', '10:00:00', 'RM 201', 'Prof. Katherine Johnson', 45, 0),
(28, 1, 16, 'BSIT 1A', 'MWF', '01:00:00', '02:00:00', 'RM 202', 'Dr. Carl Jung', 45, 0),

-- BSBA 1st Year Section Offerings (BSBA 1A & 1B)
(29, 1, 17, 'BSBA 1A', 'MWF', '08:00:00', '09:00:00', 'CBA RM 1', 'Dr. Peter Drucker', 45, 0),
(30, 1, 17, 'BSBA 1B', 'TTH', '08:30:00', '10:00:00', 'CBA RM 2', 'Dr. Peter Drucker', 45, 0),
(31, 1, 18, 'BSBA 1A', 'MWF', '09:00:00', '10:00:00', 'CBA RM 1', 'Prof. Luca Pacioli', 45, 0),
(32, 1, 18, 'BSBA 1B', 'TTH', '10:00:00', '11:30:00', 'CBA RM 2', 'Prof. Luca Pacioli', 45, 0),
(33, 1, 19, 'BSBA 1A', 'MWF', '10:00:00', '11:00:00', 'CBA RM 3', 'Dr. Adam Smith', 45, 0),
(34, 1, 19, 'BSBA 1B', 'TTH', '01:00:00', '02:30:00', 'CBA RM 3', 'Dr. Adam Smith', 45, 0);

-- -------------------------------------------------------
-- 8. CLEARANCE REQUESTS
-- -------------------------------------------------------
CREATE TABLE `clearance_requests` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `academic_term_id` INT NOT NULL,
    `current_step` INT DEFAULT 1,
    `overall_status` ENUM('Pending', 'In Progress', 'Action Required', 'Cleared', 'Enrolled') DEFAULT 'In Progress',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student_term` (`student_id`, `academic_term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 9. CLEARANCE STAGES
-- -------------------------------------------------------
CREATE TABLE `clearance_stages` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clearance_id` INT NOT NULL,
    `step_number` INT NOT NULL,
    `stage_code` ENUM('dept_initial', 'library', 'accounting', 'registrar', 'dept_final') NOT NULL,
    `stage_title` VARCHAR(100) NOT NULL,
    `status` ENUM('Pending', 'Cleared', 'Flagged') DEFAULT 'Pending',
    `officer_user_id` INT DEFAULT NULL,
    `officer_name` VARCHAR(100) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `signed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_clearance_step` (`clearance_id`, `step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 10. STUDENT SUBJECT LOADS
-- -------------------------------------------------------
CREATE TABLE `student_subject_loads` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clearance_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `is_allowed` TINYINT(1) DEFAULT 1,
    `evaluated_by_user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 11. STUDENT ENROLLMENTS
-- -------------------------------------------------------
CREATE TABLE `student_enrollments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clearance_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `schedule_id` INT NOT NULL,
    `enrolled_by_user_id` INT DEFAULT NULL,
    `status` ENUM('Enrolled', 'Dropped', 'Withdrawn') DEFAULT 'Enrolled',
    `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 12. ACCOUNTING ASSESSMENTS
-- -------------------------------------------------------
CREATE TABLE `accounting_assessments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clearance_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `academic_term_id` INT NOT NULL,
    `total_units` INT DEFAULT 0,
    `tuition_fee` DECIMAL(10,2) DEFAULT 0.00,
    `lab_fee` DECIMAL(10,2) DEFAULT 0.00,
    `misc_fee` DECIMAL(10,2) DEFAULT 2500.00,
    `registration_fee` DECIMAL(10,2) DEFAULT 500.00,
    `other_fees` DECIMAL(10,2) DEFAULT 350.00,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_assessment` DECIMAL(10,2) DEFAULT 0.00,
    `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `payment_status` ENUM('Unpaid', 'Partial Downpayment', 'Fully Paid') DEFAULT 'Unpaid',
    `or_number` VARCHAR(50) DEFAULT NULL,
    `payment_date` DATETIME DEFAULT NULL,
    `assessed_by_user_id` INT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 13. DEFICIENCIES
-- -------------------------------------------------------
CREATE TABLE `deficiencies` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clearance_stage_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `department_code` VARCHAR(20) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('Open', 'Resolved') DEFAULT 'Open',
    `created_by_user_id` INT NOT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- 14. ACTIVITY LOGS
-- -------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- SEED USERS ACCOUNTS
-- Password hash for 'password123': $2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe
-- =======================================================
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `full_name`, `department_id`, `status`) VALUES
(1, 'admin', 'admin@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'admin', 'System Administrator', NULL, 'active'),
(2, 'dept_ccs', 'ccs_dean@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'department', 'Dr. Alan Turing (CCS Dean)', 1, 'active'),
(3, 'dept_cba', 'cba_dean@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'department', 'Dr. Warren Buffett (CBA Dean)', 2, 'active'),
(4, 'librarian', 'library@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'library', 'Ms. Hermoine Granger (Head Librarian)', NULL, 'active'),
(5, 'accounting', 'accounting@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'accounting', 'Mr. Alexander Hamilton (Comptroller)', NULL, 'active'),
(6, 'registrar', 'registrar@university.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'registrar', 'Atty. Harvey Specter (Chief Registrar)', NULL, 'active'),
(7, '2026-0001', 'juan.delacruz@student.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'student', 'Juan Dela Cruz', 1, 'active'),
(8, '2026-0002', 'maria.santos@student.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'student', 'Maria Santos', 1, 'active'),
(9, '2026-0003', 'mark.reyes@student.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'student', 'Mark Reyes', 1, 'active'),
(10, '2026-0004', 'chloe.garcia@student.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'student', 'Chloe Garcia', 1, 'active'),
(11, '2026-0005', 'david.lim@student.edu', '$2y$10$wNnQhR.E1l6RzI3uF1gL..2p9dIeO5gG9Q3aZ5oX5wY2tO2Q6q7Oe', 'student', 'David Lim', 1, 'active');

-- =======================================================
-- SEED STUDENTS
-- =======================================================
INSERT INTO `students` (`id`, `user_id`, `student_no`, `first_name`, `middle_name`, `last_name`, `birth_date`, `program_id`, `year_level`, `enrolling_semester`, `academic_status`, `contact_no`, `address`, `guardian_name`, `guardian_contact`, `enrollment_status`) VALUES
(1, 7, '2026-0001', 'Juan', 'Protacio', 'Dela Cruz', '2004-06-15', 1, 2, '1st Semester', 'Regular', '0917-123-4567', 'Manila, Philippines', 'Eduardo Dela Cruz', '0917-888-9999', 'In Clearance'),
(2, 8, '2026-0002', 'Maria', 'Clara', 'Santos', '2004-08-20', 1, 2, '1st Semester', 'Regular', '0918-234-5678', 'Quezon City, Philippines', 'Santiago Santos', '0918-777-6666', 'In Clearance'),
(3, 9, '2026-0003', 'Mark', 'Anthony', 'Reyes', '2003-11-10', 1, 2, '1st Semester', 'Regular', '0919-345-6789', 'Makati City, Philippines', 'Corazon Reyes', '0919-555-4444', 'In Clearance'),
(4, 10, '2026-0004', 'Chloe', 'Marie', 'Garcia', '2004-03-25', 1, 2, '1st Semester', 'Regular', '0920-456-7890', 'Pasig City, Philippines', 'Roberto Garcia', '0920-333-2222', 'In Clearance'),
(5, 11, '2026-0005', 'David', 'Kowalski', 'Lim', '2004-01-30', 1, 2, '1st Semester', 'Regular', '0921-567-8901', 'Taguig City, Philippines', 'Elena Lim', '0921-111-0000', 'Enrolled');

-- =======================================================
-- SEED CLEARANCE REQUESTS AT DIFFERENT STAGES (FOR EASY DEMO)
-- =======================================================

-- Student 1 (Juan Dela Cruz): Step 1 Pending (Ready for Dept to sign)
INSERT INTO `clearance_requests` (`id`, `student_id`, `academic_term_id`, `current_step`, `overall_status`) VALUES
(1, 1, 1, 1, 'In Progress');

INSERT INTO `clearance_stages` (`clearance_id`, `step_number`, `stage_code`, `stage_title`, `status`) VALUES
(1, 1, 'dept_initial', 'Department Initial Clearance', 'Pending'),
(1, 2, 'library', 'Library Clearance', 'Pending'),
(1, 3, 'accounting', 'Accounting & Financial Clearance', 'Pending'),
(1, 4, 'registrar', 'Registrar Subject Load Evaluation', 'Pending'),
(1, 5, 'dept_final', 'Department Advising & Scheduling', 'Pending');

-- Student 2 (Maria Santos): Step 2 Pending (Step 1 Cleared by Dept -> Ready for Library)
INSERT INTO `clearance_requests` (`id`, `student_id`, `academic_term_id`, `current_step`, `overall_status`) VALUES
(2, 2, 1, 2, 'In Progress');

INSERT INTO `clearance_stages` (`clearance_id`, `step_number`, `stage_code`, `stage_title`, `status`, `officer_user_id`, `officer_name`, `remarks`, `signed_at`) VALUES
(2, 1, 'dept_initial', 'Department Initial Clearance', 'Cleared', 2, 'Dr. Alan Turing', 'Good academic standing. Cleared for 2nd Year 1st Sem.', NOW()),
(2, 2, 'library', 'Library Clearance', 'Pending', NULL, NULL, NULL, NULL),
(2, 3, 'accounting', 'Accounting & Financial Clearance', 'Pending', NULL, NULL, NULL, NULL),
(2, 4, 'registrar', 'Registrar Subject Load Evaluation', 'Pending', NULL, NULL, NULL, NULL),
(2, 5, 'dept_final', 'Department Advising & Scheduling', 'Pending', NULL, NULL, NULL, NULL);

-- Student 3 (Mark Reyes): Step 3 Pending (Step 1 & 2 Cleared -> Ready for Accounting)
INSERT INTO `clearance_requests` (`id`, `student_id`, `academic_term_id`, `current_step`, `overall_status`) VALUES
(3, 3, 1, 3, 'In Progress');

INSERT INTO `clearance_stages` (`clearance_id`, `step_number`, `stage_code`, `stage_title`, `status`, `officer_user_id`, `officer_name`, `remarks`, `signed_at`) VALUES
(3, 1, 'dept_initial', 'Department Initial Clearance', 'Cleared', 2, 'Dr. Alan Turing', 'Cleared for regular load.', NOW()),
(3, 2, 'library', 'Library Clearance', 'Cleared', 4, 'Ms. Hermoine Granger', 'No unreturned books or unpaid fines.', NOW()),
(3, 3, 'accounting', 'Accounting & Financial Clearance', 'Pending', NULL, NULL, NULL, NULL),
(3, 4, 'registrar', 'Registrar Subject Load Evaluation', 'Pending', NULL, NULL, NULL, NULL),
(3, 5, 'dept_final', 'Department Advising & Scheduling', 'Pending', NULL, NULL, NULL, NULL);

-- Student 4 (Chloe Garcia): Step 4 Pending (Step 1, 2, 3 Cleared -> Ready for Registrar Evaluation)
INSERT INTO `clearance_requests` (`id`, `student_id`, `academic_term_id`, `current_step`, `overall_status`) VALUES
(4, 4, 1, 4, 'In Progress');

INSERT INTO `clearance_stages` (`clearance_id`, `step_number`, `stage_code`, `stage_title`, `status`, `officer_user_id`, `officer_name`, `remarks`, `signed_at`) VALUES
(4, 1, 'dept_initial', 'Department Initial Clearance', 'Cleared', 2, 'Dr. Alan Turing', 'Cleared for enrollment.', NOW()),
(4, 2, 'library', 'Library Clearance', 'Cleared', 4, 'Ms. Hermoine Granger', 'All clear.', NOW()),
(4, 3, 'accounting', 'Accounting & Financial Clearance', 'Cleared', 5, 'Mr. Alexander Hamilton', 'Downpayment of ₱3,500.00 verified. OR# 88921.', NOW()),
(4, 4, 'registrar', 'Registrar Subject Load Evaluation', 'Pending', NULL, NULL, NULL, NULL),
(4, 5, 'dept_final', 'Department Advising & Scheduling', 'Pending', NULL, NULL, NULL, NULL);

-- Pre-seed accounting assessment for Chloe Garcia
INSERT INTO `accounting_assessments` (`clearance_id`, `student_id`, `academic_term_id`, `total_units`, `tuition_fee`, `lab_fee`, `misc_fee`, `registration_fee`, `other_fees`, `total_assessment`, `amount_paid`, `balance`, `payment_status`, `or_number`, `payment_date`) VALUES
(4, 4, 1, 15, 6750.00, 4500.00, 2500.00, 500.00, 350.00, 14600.00, 3500.00, 11100.00, 'Partial Downpayment', 'OR-88921', NOW());

-- Student 5 (David Lim): Fully Cleared & Enrolled (Step 1, 2, 3, 4, 5 Cleared -> Has Official COR)
INSERT INTO `clearance_requests` (`id`, `student_id`, `academic_term_id`, `current_step`, `overall_status`) VALUES
(5, 5, 1, 6, 'Enrolled');

INSERT INTO `clearance_stages` (`clearance_id`, `step_number`, `stage_code`, `stage_title`, `status`, `officer_user_id`, `officer_name`, `remarks`, `signed_at`) VALUES
(5, 1, 'dept_initial', 'Department Initial Clearance', 'Cleared', 2, 'Dr. Alan Turing', 'Cleared for 2nd Year 1st Sem.', NOW()),
(5, 2, 'library', 'Library Clearance', 'Cleared', 4, 'Ms. Hermoine Granger', 'No records found. Cleared.', NOW()),
(5, 3, 'accounting', 'Accounting & Financial Clearance', 'Cleared', 5, 'Mr. Alexander Hamilton', 'Full payment verified. OR# 88900.', NOW()),
(5, 4, 'registrar', 'Registrar Subject Load Evaluation', 'Cleared', 6, 'Atty. Harvey Specter', 'Full 15 units curriculum load granted.', NOW()),
(5, 5, 'dept_final', 'Department Advising & Scheduling', 'Cleared', 2, 'Dr. Alan Turing', 'Enrolled in BSCS 2A section block.', NOW());

-- Evaluated Subject Load for David Lim (Step 4)
INSERT INTO `student_subject_loads` (`clearance_id`, `student_id`, `subject_id`, `is_allowed`, `evaluated_by_user_id`) VALUES
(5, 5, 8, 1, 6),
(5, 5, 9, 1, 6),
(5, 5, 10, 1, 6),
(5, 5, 11, 1, 6),
(5, 5, 12, 1, 6);

-- Enrolled Class Schedules for David Lim (Step 5)
INSERT INTO `student_enrollments` (`clearance_id`, `student_id`, `schedule_id`, `enrolled_by_user_id`, `status`) VALUES
(5, 5, 1, 2, 'Enrolled'),
(5, 5, 3, 2, 'Enrolled'),
(5, 5, 5, 2, 'Enrolled'),
(5, 5, 7, 2, 'Enrolled'),
(5, 5, 9, 2, 'Enrolled');

-- Accounting Assessment for David Lim
INSERT INTO `accounting_assessments` (`clearance_id`, `student_id`, `academic_term_id`, `total_units`, `tuition_fee`, `lab_fee`, `misc_fee`, `registration_fee`, `other_fees`, `total_assessment`, `amount_paid`, `balance`, `payment_status`, `or_number`, `payment_date`) VALUES
(5, 5, 1, 15, 6750.00, 4500.00, 2500.00, 500.00, 350.00, 14600.00, 14600.00, 0.00, 'Fully Paid', 'OR-88900', NOW());

-- Initial Activity Logs
INSERT INTO `activity_logs` (`user_id`, `action`, `details`) VALUES
(1, 'SYSTEM_INIT', 'Enrollment & Multi-Step Clearance Database successfully initialized with demo seed data.'),
(2, 'DEPT_CLEARANCE_SIGNED', 'Signed initial clearance for Maria Santos (2026-0002)'),
(4, 'LIBRARY_CLEARANCE_SIGNED', 'Signed library clearance for Mark Reyes (2026-0003)'),
(5, 'ACCOUNTING_CLEARANCE_SIGNED', 'Processed payment and signed financial clearance for Chloe Garcia (2026-0004)'),
(2, 'FINAL_ENROLLMENT_COMPLETED', 'Assigned schedule and finalized official enrollment for David Lim (2026-0005)');

-- Add Foreign Key Constraints
ALTER TABLE `programs` ADD CONSTRAINT `fk_prog_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
ALTER TABLE `users` ADD CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
ALTER TABLE `students` ADD CONSTRAINT `fk_stud_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `students` ADD CONSTRAINT `fk_stud_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE RESTRICT;
ALTER TABLE `subjects` ADD CONSTRAINT `fk_subj_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE;
ALTER TABLE `schedules` ADD CONSTRAINT `fk_sch_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;
ALTER TABLE `schedules` ADD CONSTRAINT `fk_sch_subj` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
ALTER TABLE `clearance_requests` ADD CONSTRAINT `fk_clr_stud` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
ALTER TABLE `clearance_requests` ADD CONSTRAINT `fk_clr_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;
ALTER TABLE `clearance_stages` ADD CONSTRAINT `fk_stg_clr` FOREIGN KEY (`clearance_id`) REFERENCES `clearance_requests` (`id`) ON DELETE CASCADE;
ALTER TABLE `clearance_stages` ADD CONSTRAINT `fk_stg_off` FOREIGN KEY (`officer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `student_subject_loads` ADD CONSTRAINT `fk_ssl_clr` FOREIGN KEY (`clearance_id`) REFERENCES `clearance_requests` (`id`) ON DELETE CASCADE;
ALTER TABLE `student_subject_loads` ADD CONSTRAINT `fk_ssl_stud` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
ALTER TABLE `student_subject_loads` ADD CONSTRAINT `fk_ssl_subj` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_ste_clr` FOREIGN KEY (`clearance_id`) REFERENCES `clearance_requests` (`id`) ON DELETE CASCADE;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_ste_stud` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_ste_sch` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE;
ALTER TABLE `accounting_assessments` ADD CONSTRAINT `fk_acc_clr` FOREIGN KEY (`clearance_id`) REFERENCES `clearance_requests` (`id`) ON DELETE CASCADE;
ALTER TABLE `accounting_assessments` ADD CONSTRAINT `fk_acc_stud` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
ALTER TABLE `accounting_assessments` ADD CONSTRAINT `fk_acc_term` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;
ALTER TABLE `deficiencies` ADD CONSTRAINT `fk_def_stg` FOREIGN KEY (`clearance_stage_id`) REFERENCES `clearance_stages` (`id`) ON DELETE CASCADE;
ALTER TABLE `deficiencies` ADD CONSTRAINT `fk_def_stud` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
ALTER TABLE `activity_logs` ADD CONSTRAINT `fk_act_usr` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
