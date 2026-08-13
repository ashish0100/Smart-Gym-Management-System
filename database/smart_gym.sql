-- =========================================================
-- Smart Gym Management System
-- World Fitness Australia
-- ICT308 Project 2
-- Initial Database Schema
-- =========================================================

USE smart_gym;

-- =========================================================
-- 1. USERS
-- Central authentication table
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM(
        'member',
        'trainer',
        'admin'
    ) NOT NULL DEFAULT 'member',

    status ENUM(
        'active',
        'inactive',
        'suspended'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 2. MEMBERS
-- Additional information for member accounts
-- =========================================================

CREATE TABLE IF NOT EXISTS members (
    member_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    address VARCHAR(255) NOT NULL,

    registration_date DATE NOT NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 3. TRAINERS
-- Trainer-specific information
-- =========================================================

CREATE TABLE IF NOT EXISTS trainers (
    trainer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    phone VARCHAR(20),

    specialisation VARCHAR(100),
    qualification VARCHAR(255),
    availability VARCHAR(255),

    employment_status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 4. MEMBERSHIPS
-- Membership records and visit packages
-- =========================================================

CREATE TABLE IF NOT EXISTS memberships (
    membership_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    member_id INT UNSIGNED NOT NULL,

    membership_type ENUM(
        'weekly',
        'monthly',
        'annual',
        'pay_per_visit',
        '10_visit',
        '20_visit'
    ) NOT NULL,

    access_type ENUM(
        'all_access',
        'peak',
        'off_peak'
    ) NOT NULL DEFAULT 'all_access',

    start_date DATE NOT NULL,
    end_date DATE NULL,

    status ENUM(
        'pending',
        'active',
        'paused',
        'expired',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    remaining_visits INT UNSIGNED NULL,

    paused_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id)
        REFERENCES members(member_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 5. CLASSES
-- Gym classes and trainer sessions
-- =========================================================

CREATE TABLE IF NOT EXISTS classes (
    class_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    trainer_id INT UNSIGNED NULL,

    class_name VARCHAR(100) NOT NULL,

    description TEXT,

    class_date DATE NOT NULL,

    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    capacity INT UNSIGNED NOT NULL DEFAULT 20,

    location VARCHAR(100),

    status ENUM(
        'available',
        'full',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (trainer_id)
        REFERENCES trainers(trainer_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 6. BOOKINGS
-- Connects members with gym classes
-- =========================================================

CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    member_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,

    booking_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'confirmed',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'confirmed',

    cancelled_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT unique_member_class
        UNIQUE (member_id, class_id),

    FOREIGN KEY (member_id)
        REFERENCES members(member_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (class_id)
        REFERENCES classes(class_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 7. PAYMENTS
-- Membership and visit payments
-- =========================================================

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    member_id INT UNSIGNED NOT NULL,

    membership_id INT UNSIGNED NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_method ENUM(
        'cash',
        'card',
        'online'
    ) NOT NULL,

    transaction_reference VARCHAR(100)
        NOT NULL UNIQUE,

    payment_status ENUM(
        'pending',
        'completed',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'pending',

    payment_date DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id)
        REFERENCES members(member_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (membership_id)
        REFERENCES memberships(membership_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 8. ATTENDANCE
-- Records member attendance
-- =========================================================

CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    member_id INT UNSIGNED NOT NULL,

    class_id INT UNSIGNED NULL,

    booking_id INT UNSIGNED NULL,

    attendance_date DATE NOT NULL,

    check_in_time TIME NULL,

    status ENUM(
        'present',
        'absent',
        'late'
    ) NOT NULL DEFAULT 'present',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id)
        REFERENCES members(member_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (class_id)
        REFERENCES classes(class_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 9. NOTIFICATIONS
-- Notifications can be sent to any system user
-- =========================================================

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    notification_type ENUM(
        'membership',
        'booking',
        'payment',
        'class',
        'general'
    ) NOT NULL DEFAULT 'general',

    is_read TINYINT(1)
        NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;