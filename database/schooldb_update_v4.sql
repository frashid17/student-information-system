-- Password reset requests (forgot password workflow)
-- Run once after schooldb_update_v3.sql

USE schooldb;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    role ENUM('super_admin', 'admin', 'staff', 'student', 'faculty') NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    message TEXT,
    status ENUM('pending', 'resolved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
