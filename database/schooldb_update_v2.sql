-- Run once after schooldb.sql (and schooldb_update.sql if applied)
-- Adds homepage PDF documents support

USE schooldb;

CREATE TABLE IF NOT EXISTS homepage_documents (
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
