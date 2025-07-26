-- =====================================================
-- Birzeit Flat Rent - Complete Database Setup
-- Database: {prefix}_stdID
-- This file creates the complete database with test data
-- =====================================================

-- Create and use database
CREATE DATABASE IF NOT EXISTS birzeit_rent;
USE birzeit_rent;

-- =====================================================
-- TABLE CREATION
-- =====================================================

-- Users table (base table for all user types)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    national_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    flat_house_no VARCHAR(20),
    street_name VARCHAR(100),
    city VARCHAR(50),
    postal_code VARCHAR(10),
    date_of_birth DATE,
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile_number VARCHAR(20),
    telephone_number VARCHAR(20),
    user_type ENUM('customer', 'owner', 'manager') NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 

-- Customers table (extends users)
CREATE TABLE customers (
    customer_id VARCHAR(9) PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Owners table (extends users)
CREATE TABLE owners (
    owner_id VARCHAR(9) PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    bank_name VARCHAR(100),
    bank_branch VARCHAR(100),
    account_number VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Managers table (extends users)
CREATE TABLE managers (
    manager_id INT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Flats table
CREATE TABLE flats (
    flat_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_reference VARCHAR(6) UNIQUE,
    owner_id VARCHAR(9) NOT NULL,
    location VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    monthly_cost DECIMAL(10,2) NOT NULL,
    available_from DATE NOT NULL,
    available_to DATE NOT NULL,
    bedrooms INT NOT NULL,
    bathrooms INT NOT NULL,
    size_sqm DECIMAL(8,2),
    furnished BOOLEAN DEFAULT FALSE,
    has_heating BOOLEAN DEFAULT FALSE,
    has_air_conditioning BOOLEAN DEFAULT FALSE,
    has_access_control BOOLEAN DEFAULT FALSE,
    has_parking BOOLEAN DEFAULT FALSE,
    has_backyard ENUM('none', 'individual', 'shared') DEFAULT 'none',
    has_playground BOOLEAN DEFAULT FALSE,
    has_storage BOOLEAN DEFAULT FALSE,
    rental_conditions TEXT,
     status ENUM('pending_approval', 'approved', 'rented', 'rejected', 'withdrawn') DEFAULT 'pending_approval',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_date TIMESTAMP NULL,
    rejected_by INT NULL,
    rejected_date TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id),
    FOREIGN KEY (approved_by) REFERENCES managers(manager_id),
    FOREIGN KEY (rejected_by) REFERENCES managers(manager_id)
);

-- Flat photos table
CREATE TABLE flat_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    is_main_photo BOOLEAN DEFAULT FALSE,
    photo_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flat_id) REFERENCES flats(flat_id) ON DELETE CASCADE
);
-- Marketing information table
CREATE TABLE flat_marketing (
    marketing_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_id INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    url_link VARCHAR(255),
    FOREIGN KEY (flat_id) REFERENCES flats(flat_id) ON DELETE CASCADE
);

-- Preview timetables
CREATE TABLE preview_timetables (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_id INT NOT NULL,
    available_date DATE NOT NULL,
    available_time TIME NOT NULL,
    contact_number VARCHAR(20),
    is_booked BOOLEAN DEFAULT FALSE,
    booked_by VARCHAR(9) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flat_id) REFERENCES flats(flat_id) ON DELETE CASCADE,
    FOREIGN KEY (booked_by) REFERENCES customers(customer_id)
);

-- Rentals table
CREATE TABLE rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_id INT NOT NULL,
    customer_id VARCHAR(9) NOT NULL,
    rental_start_date DATE NOT NULL,
    rental_end_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    credit_card_number VARCHAR(9) NOT NULL,
    credit_card_expiry DATE NOT NULL,
    credit_card_name VARCHAR(100) NOT NULL,
    status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (flat_id) REFERENCES flats(flat_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

-- Messages table
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NULL,
    sender_type ENUM('system', 'user', 'owner', 'customer', 'manager') NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message_body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Shopping basket table (for ongoing rentals)
CREATE TABLE shopping_basket (
    basket_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(9) NOT NULL,
    flat_id INT NOT NULL,
    rental_start_date DATE,
    rental_end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (flat_id) REFERENCES flats(flat_id)
);

-- User sessions table (for multi-step registration)
CREATE TABLE user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    session_data TEXT,
    step INT DEFAULT 1,
    user_type ENUM('customer', 'owner') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- =====================================================
-- INITIAL DATA INSERTION
-- =====================================================

-- Insert default manager
INSERT INTO users (national_id, name, email, user_type, password_hash, city, mobile_number) 
VALUES ('123456789', 'System Manager', 'manager@birzeitflat.com', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ramallah', '0599000000');

INSERT INTO managers (manager_id, user_id) 
VALUES (1, LAST_INSERT_ID());

-- =====================================================
-- COMPREHENSIVE TEST DATA
-- =====================================================

-- Customers (10 total)
INSERT INTO users (national_id, name, flat_house_no, street_name, city, postal_code, date_of_birth, email, mobile_number, telephone_number, user_type, password_hash) VALUES
('987654321', 'Test Customer', '10', 'Test Street', 'Ramallah', '12345', '1990-01-01', 'customer@test.com', '0599123456', '022951111', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('111111111', 'Ahmed Hassan', '15', 'Al-Manara Street', 'Ramallah', '12345', '1990-03-15', 'ahmed.hassan@email.com', '0599111111', '022811111', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('222222222', 'Fatima Al-Zahra', '8', 'University Street', 'Birzeit', '54321', '1992-07-22', 'fatima.zahra@email.com', '0599222222', '022822222', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('333333333', 'Mohammad Saleh', '23', 'Omar Ibn Al-Khattab St', 'Jerusalem', '67890', '1988-11-10', 'mohammad.saleh@email.com', '0599333333', '022833333', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('444444444', 'Layla Qasemi', '7', 'Al-Bireh Main St', 'Al-Bireh', '11111', '1995-05-18', 'layla.qasemi@email.com', '0599444444', '022844444', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('555555555', 'Khaled Mansour', '12', 'Salaheddine St', 'Nablus', '22222', '1991-09-03', 'khaled.mansour@email.com', '0599555555', '022855555', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('666666666', 'Nour Abdallah', '5', 'King Faisal St', 'Hebron', '33333', '1993-12-25', 'nour.abdallah@email.com', '0599666666', '022866666', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('777777777', 'Omar Khatib', '18', 'Palestine Street', 'Bethlehem', '44444', '1989-04-12', 'omar.khatib@email.com', '0599777777', '022877777', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('888888888', 'Rana Shehab', '9', 'Al-Quds Street', 'Ramallah', '12346', '1994-08-07', 'rana.shehab@email.com', '0599888888', '022888888', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('999999999', 'Samer Daoud', '14', 'Ein Musbah St', 'Ramallah', '12347', '1987-01-30', 'samer.daoud@email.com', '0599999999', '022899999', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Create customer entries
INSERT INTO customers (customer_id, user_id) VALUES
('123456789', (SELECT user_id FROM users WHERE email = 'customer@test.com')),
('111111111', (SELECT user_id FROM users WHERE national_id = '111111111')),
('222222222', (SELECT user_id FROM users WHERE national_id = '222222222')),
('333333333', (SELECT user_id FROM users WHERE national_id = '333333333')),
('444444444', (SELECT user_id FROM users WHERE national_id = '444444444')),
('555555555', (SELECT user_id FROM users WHERE national_id = '555555555')),
('666666666', (SELECT user_id FROM users WHERE national_id = '666666666')),
('777777777', (SELECT user_id FROM users WHERE national_id = '777777777')),
('888888888', (SELECT user_id FROM users WHERE national_id = '888888888')),
('999999999', (SELECT user_id FROM users WHERE national_id = '999999999'));

-- Owners (6 total)
INSERT INTO users (national_id, name, flat_house_no, street_name, city, postal_code, date_of_birth, email, mobile_number, telephone_number, user_type, password_hash) VALUES
('456789123', 'Test Owner', '20', 'Owner Street', 'Ramallah', '12345', '1980-01-01', 'owner@test.com', '0599654321', '022952222', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('101010101', 'Mariam Khalil', '25', 'Al-Irsal Street', 'Ramallah', '12348', '1980-06-15', 'mariam.khalil@email.com', '0598101010', '022801010', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('202020202', 'Yusuf Nasser', '33', 'Al-Masyoun', 'Ramallah', '12349', '1975-09-22', 'yusuf.nasser@email.com', '0598202020', '022802020', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('303030303', 'Iman Qaddura', '11', 'Main Street', 'Birzeit', '54322', '1983-02-11', 'iman.qaddura@email.com', '0598303030', '022803030', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('404040404', 'Saeed Barghouti', '19', 'University Road', 'Birzeit', '54323', '1978-12-05', 'saeed.barghouti@email.com', '0598404040', '022804040', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('505050505', 'Lina Salim', '6', 'Al-Nahda Street', 'Al-Bireh', '11112', '1985-04-18', 'lina.salim@email.com', '0598505050', '022805050', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Create owner entries
INSERT INTO owners (owner_id, user_id, bank_name, bank_branch, account_number) VALUES
('987654321', (SELECT user_id FROM users WHERE email = 'owner@test.com'), 'Arab Bank', 'Ramallah Branch', '1234567890'),
('101010101', (SELECT user_id FROM users WHERE national_id = '101010101'), 'Palestine Bank', 'Ramallah Main', '2345678901'),
('202020202', (SELECT user_id FROM users WHERE national_id = '202020202'), 'Bank of Palestine', 'Al-Masyoun Branch', '3456789012'),
('303030303', (SELECT user_id FROM users WHERE national_id = '303030303'), 'Arab Bank', 'Birzeit Branch', '4567890123'),
('404040404', (SELECT user_id FROM users WHERE national_id = '404040404'), 'Palestine Investment Bank', 'Birzeit Branch', '5678901234'),
('505050505', (SELECT user_id FROM users WHERE national_id = '505050505'), 'Quds Bank', 'Al-Bireh Branch', '6789012345');

-- Flats with different statuses (15 flats total)
    INSERT INTO flats (flat_reference, owner_id, location, address, monthly_cost, available_from, available_to, bedrooms, bathrooms, size_sqm, furnished, has_heating, has_air_conditioning, has_access_control, has_parking, has_backyard, has_playground, has_storage, rental_conditions, status, created_date) VALUES
    -- Approved flats (available for rent)
    ('123456', '987654321', 'Ramallah', '15 Al-Manara Street, Building A, Apt 3', 800.00, '2025-01-01', '2025-12-31', 2, 1, 85.5, TRUE, TRUE, TRUE, TRUE, TRUE, 'individual', FALSE, TRUE, 'No pets allowed. Non-smoking apartment.', 'approved', '2024-12-15 10:30:00'),
    ('234567', '101010101', 'Birzeit', '22 University Street, Floor 2', 650.00, '2025-02-01', '2025-12-31', 1, 1, 65.0, FALSE, TRUE, FALSE, FALSE, FALSE, 'none', FALSE, FALSE, 'Students preferred. Quiet area.', 'approved', '2024-12-20 14:20:00'),
    ('345678', '202020202', 'Ramallah', '8 Al-Masyoun, Building B, Apt 5', 1200.00, '2025-01-15', '2025-12-31', 3, 2, 120.0, TRUE, TRUE, TRUE, TRUE, TRUE, 'shared', TRUE, TRUE, 'Family oriented building. No loud music after 10 PM.', 'approved', '2024-12-18 09:15:00'),
    ('456789', '303030303', 'Birzeit', '45 Main Street, Villa', 1500.00, '2025-03-01', '2025-12-31', 4, 3, 180.0, TRUE, TRUE, TRUE, TRUE, TRUE, 'individual', TRUE, TRUE, 'Whole villa. Garden included.', 'approved', '2024-12-22 16:45:00'),
    ('567890', '404040404', 'Al-Bireh', '12 Al-Nahda Street, Apt 7', 700.00, '2025-01-20', '2025-12-31', 2, 1, 75.0, FALSE, TRUE, TRUE, FALSE, TRUE, 'none', FALSE, TRUE, 'Close to transportation. Elevator building.', 'approved', '2024-12-25 11:30:00'),
    ('678901', '505050505', 'Ramallah', '33 Omar Ibn Al-Khattab St, Floor 3', 950.00, '2025-02-15', '2025-12-31', 2, 2, 95.0, TRUE, TRUE, TRUE, TRUE, FALSE, 'none', FALSE, TRUE, 'Modern apartment with balcony.', 'approved', '2024-12-28 13:20:00'),

    -- Rented flats (currently occupied)
    ('789012', '987654321', 'Ramallah', '25 Al-Irsal Street, Apt 4', 850.00, '2024-06-01', '2025-06-01', 2, 1, 80.0, TRUE, TRUE, FALSE, TRUE, TRUE, 'individual', FALSE, TRUE, 'Long term rental preferred.', 'rented', '2024-11-10 08:00:00'),
    ('890123', '101010101', 'Birzeit', '18 University Road, Studio', 500.00, '2024-09-01', '2025-09-01', 1, 1, 45.0, TRUE, FALSE, FALSE, FALSE, FALSE, 'none', FALSE, FALSE, 'Perfect for students.', 'rented', '2024-11-15 12:30:00'),
    ('901234', '202020202', 'Al-Bireh', '9 Al-Bireh Main St, Apt 2', 900.00, '2024-08-01', '2025-08-01', 3, 2, 110.0, TRUE, TRUE, TRUE, TRUE, TRUE, 'shared', TRUE, TRUE, 'Family apartment with playground access.', 'rented', '2024-11-20 15:45:00'),

    -- Pending approval flats (waiting for manager approval)
    (NULL, '303030303', 'Birzeit', '67 Campus Street, Apt 1', 600.00, '2025-04-01', '2025-12-31', 1, 1, 60.0, FALSE, TRUE, FALSE, FALSE, FALSE, 'none', FALSE, FALSE, 'Near university campus.', 'pending_approval', '2025-01-15 10:00:00'),
    (NULL, '404040404', 'Ramallah', '14 Al-Quds Street, Floor 4', 1100.00, '2025-03-15', '2025-12-31', 3, 2, 125.0, TRUE, TRUE, TRUE, TRUE, TRUE, 'individual', FALSE, TRUE, 'Luxury apartment with city view.', 'pending_approval', '2025-01-20 14:30:00'),
    (NULL, '505050505', 'Al-Bireh', '21 New Street, Apt 6', 750.00, '2025-02-20', '2025-12-31', 2, 1, 85.0, FALSE, TRUE, TRUE, FALSE, TRUE, 'none', FALSE, TRUE, 'Newly renovated apartment.', 'pending_approval', '2025-01-25 09:20:00'),
    (NULL, '987654321', 'Ramallah', '44 Al-Manara Extension, Villa', 2000.00, '2025-05-01', '2025-12-31', 5, 4, 250.0, TRUE, TRUE, TRUE, TRUE, TRUE, 'individual', TRUE, TRUE, 'Luxury villa with pool.', 'pending_approval', '2025-01-28 16:15:00'),
    (NULL, '101010101', 'Birzeit', '77 Student Street, Apt 8', 550.00, '2025-03-01', '2025-12-31', 1, 1, 50.0, FALSE, FALSE, FALSE, FALSE, FALSE, 'none', FALSE, FALSE, 'Budget friendly for students.', 'pending_approval', '2025-01-30 11:45:00'),
    (NULL, '202020202', 'Ramallah', '39 Commercial Street, Office/Apt', 1300.00, '2025-04-15', '2025-12-31', 2, 1, 100.0, FALSE, TRUE, TRUE, TRUE, TRUE, 'none', FALSE, TRUE, 'Can be used as office or apartment.', 'pending_approval', '2025-02-01 13:10:00');

-- Flat Photos
INSERT INTO flat_photos (flat_id, photo_path, is_main_photo, photo_order) VALUES
-- Photos for approved flats (flats with references 123456, 234567, 345678)
(7, 'flat7_main.jpg', TRUE, 1),
(7, 'flat7_interior.jpg', FALSE, 2),
(7, 'flat7_backyard.jpeg', FALSE, 3),

(8, 'flat8_main.jpeg', TRUE, 1),
(8, 'flat8_studio.jpeg', FALSE, 2),

(9, 'flat9_main.jpg', TRUE, 1),
(9, 'flat9_family_room.jpeg', FALSE, 2),
(9, 'flat9_playground.jpeg', FALSE, 3),

-- Photos for rented flats (flats with references 789012, 890123)
(10, 'flat10_main.webp', TRUE, 1),
(10, 'flat10_campus_view.jpg', FALSE, 2),

(11, 'flat11_main.jpg', TRUE, 1),
(11, 'flat11_luxury.webp', FALSE, 2),
(11,'flat11_city_view.jpeg', FALSE, 3);

-- Marketing Information
INSERT INTO flat_marketing (flat_id, title, description, url_link) VALUES
-- flat_id 4
(4, 'Ramallah Grand Mall', 'Shopping center just 5 minutes walk', 'https://ramallahgrandmall.com'),
(4, 'Al-Manara Square', 'City center with restaurants and cafes', NULL),

-- flat_id 5
(5, 'Birzeit University', 'Walking distance to campus', 'https://www.birzeit.edu'),
(5, 'Student Services', 'Nearby cafes and services for students', NULL),

-- flat_id 6
(6, 'Al-Masyoun Park', 'Green area for family activities', NULL),
(6, 'Ramallah Cultural Palace', 'Cultural events and exhibitions', NULL),

-- flat_id 7
(7, 'Birzeit Old Town', 'Historical village core and markets', NULL),
(7, 'Scenic Nature Trails', 'Great for walking and hiking', NULL),

-- flat_id 8
(8, 'Al-Bireh Municipality', 'Public services and administration buildings', NULL),
(8, 'Transportation Hub', 'Central access to Ramallah, Birzeit, and more', NULL),

-- flat_id 9
(9, 'Downtown Ramallah', 'Vibrant area with shops and restaurants', NULL),
(9, 'Fitness Center', 'Modern gym nearby', NULL),

-- flat_id 10
(10, 'Al-Irsal Business District', 'Offices and co-working spaces', NULL),

-- flat_id 11
(11, 'University Studio Life', 'Affordable studio in student area', NULL),

-- flat_id 12
(12, 'Family Park Access', 'Large green areas for families', NULL),

-- flat_id 13
(13, 'Campus Street Cafes', 'Lively cafes popular with students', NULL);


-- Preview Timetables
INSERT INTO preview_timetables (flat_id, available_date, available_time, contact_number, is_booked, booked_by) VALUES
-- Available slots for flat_id 4
(4, '2025-06-10', '10:00:00', '0599101010', FALSE, NULL),
(4, '2025-06-10', '14:00:00', '0599101010', FALSE, NULL),
(4, '2025-06-11', '09:00:00', '0599101010', FALSE, NULL),
(4, '2025-06-12', '15:00:00', '0599101010', FALSE, NULL),

-- Available slots for flat_id 5
(5, '2025-06-08', '11:00:00', '0598303030', FALSE, NULL),
(5, '2025-06-09', '16:00:00', '0598303030', FALSE, NULL),
(5, '2025-06-10', '10:30:00', '0598303030', FALSE, NULL),

-- Available slots for flat_id 6
(6, '2025-06-12', '09:30:00', '0598202020', FALSE, NULL),
(6, '2025-06-13', '14:30:00', '0598202020', FALSE, NULL),
(6, '2025-06-14', '11:00:00', '0598202020', FALSE, NULL),

-- Booked slots
(7, '2025-06-08', '13:00:00', '0598404040', TRUE, '333333333'),
(8, '2025-06-09', '12:00:00', '0598505050', TRUE, '444444444'),
(9, '2025-06-10', '15:30:00', '0599101010', TRUE, '555555555'),

-- Additional available slots
(4, '2025-06-15', '09:00:00', '0599101010', FALSE, NULL),
(4, '2025-06-15', '13:00:00', '0599101010', FALSE, NULL),
(5, '2025-06-14', '10:00:00', '0598303030', FALSE, NULL),
(6, '2025-06-16', '12:00:00', '0598202020', FALSE, NULL),
(7, '2025-06-18', '10:30:00', '0598404040', FALSE, NULL),
(8, '2025-06-19', '13:30:00', '0598505050', FALSE, NULL);


-- Rentals (both active and completed)
INSERT INTO rentals (flat_id, customer_id, rental_start_date, rental_end_date, total_amount, credit_card_number, credit_card_expiry, credit_card_name, status, created_at, confirmed_at) VALUES
-- Active rentals
(7, '123456789', '2024-06-01', '2025-06-01', 10200.00, '123456789', '2027-12-31', 'Test Customer', 'active', '2024-05-15 10:00:00', '2024-05-16 09:30:00'),
(8, '111111111', '2024-09-01', '2025-09-01', 6000.00, '111111111', '2026-08-31', 'Ahmed Hassan', 'active', '2024-08-10 14:20:00', '2024-08-11 11:15:00'),
(9, '222222222', '2024-08-01', '2025-08-01', 10800.00, '222222222', '2028-06-30', 'Fatima Al-Zahra', 'active', '2024-07-20 16:45:00', '2024-07-21 10:30:00'),

-- Completed rentals
(1, '333333333', '2024-01-01', '2024-06-01', 4000.00, '333333333', '2026-12-31', 'Mohammad Saleh', 'completed', '2023-12-15 09:00:00', '2023-12-16 08:30:00'),
(2, '444444444', '2023-09-01', '2024-03-01', 3900.00, '444444444', '2027-05-31', 'Layla Qasemi', 'completed', '2023-08-20 13:15:00', '2023-08-21 10:45:00'),
(3, '555555555', '2023-06-01', '2024-01-01', 8400.00, '555555555', '2025-11-30', 'Khaled Mansour', 'completed', '2023-05-25 11:30:00', '2023-05-26 14:20:00');

-- Messages
INSERT INTO messages (
  recipient_id, sender_type, sender_id, title, message_body, is_read, created_at
) VALUES

-- ✅ Manager messages
(56, 'system', NULL, 'New Flat Submission', 'A new flat listing has been submitted and requires your approval. Location: Birzeit Campus Street', 0, '2025-01-15 10:05:00'),
(56, 'system', NULL, 'Rental Notification', 'Flat #789012 has been rented by Test Customer. Rental period: June 1, 2024 - June 1, 2025', 1, '2024-05-16 09:35:00'),
(56, 'system', NULL, 'Multiple Pending Approvals', 'You have 6 flat listings waiting for approval. Please review them as soon as possible.', 0, '2025-02-01 14:00:00'),

-- ✅ Owner messages
(45, 'system', NULL, 'Flat Approved', 'Your flat listing at 15 Al-Manara Street has been approved. Reference number: 123456', 1, '2024-12-15 10:35:00'),
(45, 'system', NULL, 'Rental Request', 'Customer Test Customer has rented your flat #789012. Contact: 0599123456', 1, '2024-05-16 09:40:00'),
(46, 'system', NULL, 'Flat Approved', 'Your flat listing at 22 University Street has been approved. Reference number: 234567', 1, '2024-12-20 14:25:00'),
(47, 'system', NULL, 'Preview Request', 'Customer Fatima Al-Zahra has requested to preview your flat at Al-Masyoun. Contact: 0599222222', 0, '2025-06-02 10:30:00'),
(48, 'system', NULL, 'Flat Pending Review', 'Your flat listing at Campus Street is under review by our management team.', 0, '2025-01-15 10:15:00'),

-- ✅ Customer messages
(36, 'system', NULL, 'Rental Confirmation', 'Your rental for flat #789012 has been confirmed. You can collect keys from owner. Contact: 0599654321', 1, '2024-05-16 09:45:00'),
(36, 'system', NULL, 'Rental Confirmation', 'Your rental for flat #890123 has been confirmed. Move-in date: September 1, 2024', 1, '2024-08-11 11:20:00'),
(37, 'system', NULL, 'Preview Approved', 'Your preview request for flat at Al-Masyoun has been approved. Date: June 13, 2025 at 2:30 PM', 0, '2025-06-02 11:00:00'),
(38, 'system', NULL, 'Preview Confirmation', 'Your preview appointment for the villa in Birzeit is confirmed for June 8, 2025 at 1:00 PM', 1, '2025-06-02 08:30:00'),
(39, 'system', NULL, 'Welcome to Birzeit Flat Rent', 'Thank you for registering with us. Browse our available flats and find your perfect home!', 0, '2025-01-01 12:00:00'),
(40, 'system', NULL, 'New Flats Available', 'New flats have been added in Ramallah area. Check them out now!', 0, '2025-01-28 15:00:00');

-- Shopping Basket (ongoing rental processes)
INSERT INTO shopping_basket (customer_id, flat_id, rental_start_date, rental_end_date, created_at) VALUES
('666666666', 4, '2025-07-01', '2026-07-01', '2025-06-01 10:30:00'),
('777777777', 5, '2025-08-15', '2026-02-15', '2025-06-02 14:45:00'),
('888888888', 6, '2025-06-20', '2025-12-20', '2025-06-02 16:20:00');