-- Units, registration, exam cards, semester reporting
-- Run once after schooldb.sql (and v1/v2 updates if applied)
--
-- If you see "Duplicate column name 'semester_number'" or "Duplicate column name 'unit_id'",
-- those columns are already added — skip the ALTER lines at the bottom and continue.

USE schooldb;

CREATE TABLE IF NOT EXISTS units (    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_code VARCHAR(20) NOT NULL UNIQUE,
    unit_name VARCHAR(150) NOT NULL,
    credit_hours DECIMAL(4,1) DEFAULT 3.0,
    category ENUM('common', 'core', 'elective') DEFAULT 'core',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS program_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    unit_id INT NOT NULL,
    year_of_study TINYINT DEFAULT 1,
    UNIQUE KEY uq_program_unit (program_id, unit_id),
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_unit_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    unit_id INT NOT NULL,
    trimester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    status ENUM('registered', 'dropped', 'completed') DEFAULT 'registered',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_unit_term (student_id, unit_id, trimester, academic_year),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS semester_reporting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_trimester VARCHAR(20) NOT NULL,
    from_academic_year VARCHAR(20) NOT NULL,
    to_trimester VARCHAR(20) NOT NULL,
    to_academic_year VARCHAR(20) NOT NULL,
    from_semester_number INT NOT NULL,
    to_semester_number INT NOT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Semester counter on students (Sem 1, 2, 3...)
-- MariaDB / XAMPP: safe to re-run. On plain MySQL 8, skip if column exists.
ALTER TABLE students
    ADD COLUMN IF NOT EXISTS semester_number INT NOT NULL DEFAULT 1 AFTER academic_year;

-- Link grades to registered units
ALTER TABLE grades
    ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER course_code;