-- ============================================================================
-- USTED UNIVERSITY - CAMPUS CLINIC APPOINTMENT MANAGEMENT SYSTEM (CCAMS)
-- Complete MySQL / MariaDB Database Schema & Fictional Demonstration Data
-- Database Name: campus_clinic
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `campus_clinic`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `campus_clinic`;

-- Clean drop order for fresh import
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `appointment_slots`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- TABLE 1: users
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(25) DEFAULT NULL,
    `role` ENUM('student', 'doctor', 'admin') NOT NULL DEFAULT 'student',
    `account_status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `account_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE 2: doctors
-- ----------------------------------------------------------------------------
CREATE TABLE `doctors` (
    `doctor_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `specialization` VARCHAR(120) NOT NULL,
    `availability_status` ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`doctor_id`),
    UNIQUE KEY `uq_doctors_user_id` (`user_id`),
    KEY `idx_doctors_specialization` (`specialization`),
    KEY `idx_doctors_availability` (`availability_status`),
    CONSTRAINT `fk_doctors_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE 3: appointment_slots
-- ----------------------------------------------------------------------------
CREATE TABLE `appointment_slots` (
    `slot_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doctor_id` INT UNSIGNED NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `status` ENUM('available', 'booked', 'inactive') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`slot_id`),
    KEY `idx_slots_doctor_time` (`doctor_id`, `start_time`),
    KEY `idx_slots_status_time` (`status`, `start_time`),
    CONSTRAINT `fk_slots_doctor`
        FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE 4: appointments
-- Note: No permanent UNIQUE constraint on slot_id so cancelled records are
-- preserved while freed slots can be re-booked. Active booking uniqueness is
-- enforced via InnoDB transactions + SELECT ... FOR UPDATE row locking.
-- ----------------------------------------------------------------------------
CREATE TABLE `appointments` (
    `appointment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `slot_id` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_id`),
    KEY `idx_appointments_student_status` (`student_id`, `status`),
    KEY `idx_appointments_slot_status` (`slot_id`, `status`),
    KEY `idx_appointments_status_created` (`status`, `created_at`),
    CONSTRAINT `fk_appointments_student`
        FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_slot`
        FOREIGN KEY (`slot_id`) REFERENCES `appointment_slots` (`slot_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABLE 5: notifications
-- ----------------------------------------------------------------------------
CREATE TABLE `notifications` (
    `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` INT UNSIGNED NOT NULL,
    `channel` ENUM('in_app', 'email', 'sms', 'mock') NOT NULL DEFAULT 'in_app',
    `message` TEXT NOT NULL,
    `delivery_status` ENUM('pending', 'sent', 'simulated', 'queued', 'failed') NOT NULL DEFAULT 'sent',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`),
    KEY `idx_notifications_appointment` (`appointment_id`),
    KEY `idx_notifications_channel_status` (`channel`, `delivery_status`),
    CONSTRAINT `fk_notifications_appointment`
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FICTIONAL DEMONSTRATION SEED DATA (USTED UNIVERSITY, KUMASI)
-- Demo Passwords (hashed with PHP password_hash compatible bcrypt $2y$10$):
--   Admin   : Admin@2026
--   Doctors : Doctor@2026
--   Students: Student@2026
-- ============================================================================

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `phone`, `role`, `account_status`) VALUES
(1, 'Adelaide Takyi-Mensah', 'admin@usted.edu.gh', '$2y$10$Cz21jwVq6OO0HuAIOQ6znuIKYcC2sNZkxLMV0EqMh/NJjolHIxh.O', '+233 24 000 1001', 'admin', 'active'),
(2, 'Dr. Kwame Mensah', 'kwame.mensah@usted.edu.gh', '$2y$10$QziHPMHmqJCQn8/gBoTzBOCh9U2aO6jUwp0hUiYPzkMO6O3G2a.by', '+233 24 000 2001', 'doctor', 'active'),
(3, 'Dr. Akosua Boateng', 'akosua.boateng@usted.edu.gh', '$2y$10$QziHPMHmqJCQn8/gBoTzBOCh9U2aO6jUwp0hUiYPzkMO6O3G2a.by', '+233 24 000 2002', 'doctor', 'active'),
(4, 'Dr. Kofi Osei-Bonsu', 'kofi.oseibonsu@usted.edu.gh', '$2y$10$QziHPMHmqJCQn8/gBoTzBOCh9U2aO6jUwp0hUiYPzkMO6O3G2a.by', '+233 24 000 2003', 'doctor', 'active'),
(5, 'Dr. Abena Serwaa Darko', 'abena.darko@usted.edu.gh', '$2y$10$QziHPMHmqJCQn8/gBoTzBOCh9U2aO6jUwp0hUiYPzkMO6O3G2a.by', '+233 24 000 2004', 'doctor', 'active'),
(6, 'Dr. Yaw Adu-Gyamfi', 'yaw.adugyamfi@usted.edu.gh', '$2y$10$QziHPMHmqJCQn8/gBoTzBOCh9U2aO6jUwp0hUiYPzkMO6O3G2a.by', '+233 24 000 2005', 'doctor', 'active'),
(7, 'Kwabena Asare', 'kwabena.asare@st.usted.edu.gh', '$2y$10$MPswF8OjjJaigZyRUw3WVeiz6reyIHOl93DbHXT8UvQf.N8.eD8hu', '+233 54 000 3001', 'student', 'active'),
(8, 'Ama Serwaa Appiah', 'ama.appiah@st.usted.edu.gh', '$2y$10$MPswF8OjjJaigZyRUw3WVeiz6reyIHOl93DbHXT8UvQf.N8.eD8hu', '+233 54 000 3002', 'student', 'active'),
(9, 'Kojo Frimpong', 'kojo.frimpong@st.usted.edu.gh', '$2y$10$MPswF8OjjJaigZyRUw3WVeiz6reyIHOl93DbHXT8UvQf.N8.eD8hu', '+233 54 000 3003', 'student', 'active'),
(10, 'Efua Owusu-Ansah', 'efua.owusu@st.usted.edu.gh', '$2y$10$MPswF8OjjJaigZyRUw3WVeiz6reyIHOl93DbHXT8UvQf.N8.eD8hu', '+233 54 000 3004', 'student', 'active');

INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialization`, `availability_status`) VALUES
(1, 2, 'General Medicine', 'available'),
(2, 3, 'Dental & Oral Health', 'available'),
(3, 4, 'Optometry & Eye Care', 'available'),
(4, 5, 'Women & Reproductive Health', 'available'),
(5, 6, 'Student Counseling & Mental Health', 'available');

-- Dynamic future and past slots relative to CURRENT_DATE so demos stay fresh
INSERT INTO `appointment_slots` (`slot_id`, `doctor_id`, `start_time`, `end_time`, `status`) VALUES
-- Past slots (completed / cancelled history)
(1, 1, DATE_SUB(CONCAT(CURDATE(), ' 09:00:00'), INTERVAL 4 DAY), DATE_SUB(CONCAT(CURDATE(), ' 09:30:00'), INTERVAL 4 DAY), 'booked'),
(2, 2, DATE_SUB(CONCAT(CURDATE(), ' 10:00:00'), INTERVAL 2 DAY), DATE_SUB(CONCAT(CURDATE(), ' 10:30:00'), INTERVAL 2 DAY), 'booked'),
-- Today's slots
(3, 1, CONCAT(CURDATE(), ' 14:00:00'), CONCAT(CURDATE(), ' 14:30:00'), 'booked'),
(4, 1, CONCAT(CURDATE(), ' 15:00:00'), CONCAT(CURDATE(), ' 15:30:00'), 'available'),
(5, 3, CONCAT(CURDATE(), ' 16:00:00'), CONCAT(CURDATE(), ' 16:30:00'), 'available'),
-- Tomorrow's slots
(6, 1, DATE_ADD(CONCAT(CURDATE(), ' 09:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 09:30:00'), INTERVAL 1 DAY), 'booked'),
(7, 1, DATE_ADD(CONCAT(CURDATE(), ' 10:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 10:30:00'), INTERVAL 1 DAY), 'available'),
(8, 2, DATE_ADD(CONCAT(CURDATE(), ' 09:30:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 10:00:00'), INTERVAL 1 DAY), 'available'),
(9, 2, DATE_ADD(CONCAT(CURDATE(), ' 11:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 11:30:00'), INTERVAL 1 DAY), 'booked'),
(10, 3, DATE_ADD(CONCAT(CURDATE(), ' 13:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 13:30:00'), INTERVAL 1 DAY), 'available'),
(11, 4, DATE_ADD(CONCAT(CURDATE(), ' 10:30:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 11:00:00'), INTERVAL 1 DAY), 'available'),
(12, 5, DATE_ADD(CONCAT(CURDATE(), ' 14:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 14:45:00'), INTERVAL 1 DAY), 'available'),
-- Day +2 & +3 slots
(13, 1, DATE_ADD(CONCAT(CURDATE(), ' 09:00:00'), INTERVAL 2 DAY), DATE_ADD(CONCAT(CURDATE(), ' 09:30:00'), INTERVAL 2 DAY), 'available'),
(14, 3, DATE_ADD(CONCAT(CURDATE(), ' 10:00:00'), INTERVAL 2 DAY), DATE_ADD(CONCAT(CURDATE(), ' 10:30:00'), INTERVAL 2 DAY), 'available'),
(15, 4, DATE_ADD(CONCAT(CURDATE(), ' 11:00:00'), INTERVAL 2 DAY), DATE_ADD(CONCAT(CURDATE(), ' 11:30:00'), INTERVAL 2 DAY), 'available'),
(16, 5, DATE_ADD(CONCAT(CURDATE(), ' 15:00:00'), INTERVAL 2 DAY), DATE_ADD(CONCAT(CURDATE(), ' 15:45:00'), INTERVAL 2 DAY), 'inactive'),
(17, 2, DATE_ADD(CONCAT(CURDATE(), ' 09:00:00'), INTERVAL 3 DAY), DATE_ADD(CONCAT(CURDATE(), ' 09:30:00'), INTERVAL 3 DAY), 'available');

INSERT INTO `appointments` (`appointment_id`, `student_id`, `slot_id`, `reason`, `status`) VALUES
(1, 7, 1, 'Routine campus wellness screening', 'completed'),
(2, 8, 2, 'Dental checkup and scaling inquiry', 'completed'),
(3, 7, 3, 'Follow-up general consultation', 'confirmed'),
(4, 9, 6, 'General fever and fatigue check', 'pending'),
(5, 10, 9, 'Tooth sensitivity consultation', 'confirmed'),
(6, 7, 7, 'Earlier consultation cancelled due to lecture conflict', 'cancelled');

INSERT INTO `notifications` (`notification_id`, `appointment_id`, `channel`, `message`, `delivery_status`, `is_read`, `sent_at`) VALUES
(1, 1, 'in_app', 'Your appointment with Dr. Kwame Mensah has been marked as completed.', 'sent', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 3, 'in_app', 'Appointment #3 with Dr. Kwame Mensah is confirmed for today at 2:00 PM.', 'sent', 0, NOW()),
(3, 3, 'mock', '[MOCK SANDBOX] Confirmation alert prepared for Kwabena Asare (+233 54 000 3001).', 'simulated', 1, NOW()),
(4, 4, 'in_app', 'Appointment #4 with Dr. Kwame Mensah has been booked and is pending confirmation.', 'sent', 0, NOW()),
(5, 5, 'in_app', 'Appointment #5 with Dr. Akosua Boateng is confirmed for tomorrow at 11:00 AM.', 'sent', 0, NOW()),
(6, 6, 'in_app', 'Appointment #6 was cancelled. The slot has been released for other students.', 'sent', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));
