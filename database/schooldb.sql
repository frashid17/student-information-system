-- Student Information Management System Database
-- Database: schooldb
-- Import this file into phpMyAdmin

CREATE DATABASE IF NOT EXISTS schooldb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE schooldb;

-- Users (Admin, Staff, Students, Faculty)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'staff', 'student', 'faculty') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    related_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Campus Branches
CREATE TABLE campus_branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Academic Programs
CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    program_name VARCHAR(150) NOT NULL,
    campus_id INT,
    duration_years INT DEFAULT 4,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campus_id) REFERENCES campus_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    gender ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
    date_of_birth DATE,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    national_id VARCHAR(20),
    program_id INT,
    campus_id INT,
    trimester VARCHAR(20) DEFAULT 'Trimester 1',
    academic_year VARCHAR(20) DEFAULT '2025/2026',
    enrollment_date DATE,
    status ENUM('active', 'inactive', 'graduated', 'suspended') DEFAULT 'active',
    kin_name VARCHAR(100),
    kin_relationship VARCHAR(50),
    kin_phone VARCHAR(20),
    kin_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
    FOREIGN KEY (campus_id) REFERENCES campus_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Faculty / Staff
CREATE TABLE faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
    email VARCHAR(100),
    phone VARCHAR(20),
    faculty_type ENUM('teaching', 'non_teaching') NOT NULL,
    department VARCHAR(100),
    campus_id INT,
    hire_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    kin_name VARCHAR(100),
    kin_relationship VARCHAR(50),
    kin_phone VARCHAR(20),
    kin_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campus_id) REFERENCES campus_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Fee Structure Setup
CREATE TABLE fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT,
    trimester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fee Payments / Records
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    trimester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    payment_method ENUM('cash', 'bank', 'mobile_money', 'cheque') DEFAULT 'cash',
    receipt_number VARCHAR(50),
    payment_date DATE NOT NULL,
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Payroll / Salaries
CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    allowances DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    pay_period VARCHAR(20),
    effective_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pay Slips
CREATE TABLE payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    payroll_id INT,
    basic_salary DECIMAL(12,2) NOT NULL,
    allowances DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    pay_period VARCHAR(20),
    pay_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Library Books
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100),
    publisher VARCHAR(100),
    category VARCHAR(50),
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(50),
    campus_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campus_id) REFERENCES campus_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Library Check-ins
CREATE TABLE library_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    checkout_time DATETIME NULL,
    campus_id INT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (campus_id) REFERENCES campus_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Book Issue / Return
CREATE TABLE book_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE,
    return_date DATE NULL,
    status ENUM('issued', 'returned', 'overdue') DEFAULT 'issued',
    fine_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Academic Grades / Results
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    course_code VARCHAR(20),
    trimester VARCHAR(20),
    academic_year VARCHAR(20),
    marks DECIMAL(5,2),
    grade_letter VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Attendance Records
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_name VARCHAR(100),
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Announcements / Communications
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    target_audience ENUM('all', 'students', 'staff', 'faculty') DEFAULT 'all',
    show_on_homepage TINYINT(1) DEFAULT 0,
    homepage_category ENUM('general', 'results', 'exam_calendar', 'intake', 'portal') DEFAULT 'general',
    homepage_sort INT DEFAULT 0,
    posted_by INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Homepage PDF documents (calendars, timetables)
CREATE TABLE homepage_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    doc_type ENUM('events_calendar', 'timetable', 'exam_timetable') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    academic_year VARCHAR(20),
    trimester VARCHAR(20),
    description TEXT,
    show_on_homepage TINYINT(1) DEFAULT 1,
    uploaded_by INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- System Settings (Database configuration)
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT
) ENGINE=InnoDB;

-- Registration Log
CREATE TABLE registration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default Super Administrator (password: admin1)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin', '$2y$10$dz9PncJFTEGqu51Y9rHqsO1rD/Rtv5ytlgs1th.3.pXR2B/t6wes.', 'super_admin', 'System Administrator', 'admin@kemu.ac.ke');

-- Sample Campus
INSERT INTO campus_branches (branch_name, location, phone, email) VALUES
('Main Campus', 'Meru, Kenya', '+254700000001', 'main@kemu.ac.ke'),
('Nairobi Campus', 'Nairobi, Kenya', '+254700000002', 'nairobi@kemu.ac.ke');

-- Sample Programs
INSERT INTO programs (program_code, program_name, campus_id, duration_years) VALUES
('DBIT', 'Diploma in Business Information Technology', 1, 2),
('BSCS', 'Bachelor of Science in Computer Science', 1, 4),
('MBA', 'Master of Business Administration', 2, 2);

-- Sample Fee Structures
INSERT INTO fee_structures (program_id, trimester, academic_year, amount, description) VALUES
(1, 'Trimester 1', '2025/2026', 45000.00, 'Tuition and Registration'),
(1, 'Trimester 2', '2025/2026', 40000.00, 'Tuition'),
(2, 'Trimester 1', '2025/2026', 65000.00, 'Tuition and Registration');

-- Default System Settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('db_host', '127.0.0.1'),
('db_port', '3306'),
('db_name', 'schooldb'),
('institution_name', 'Kenya Methodist University'),
('institution_short', 'KeMU');
