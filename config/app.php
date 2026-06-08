<?php
/**
 * Application Configuration
 */

define('APP_NAME', 'Student Information Management System');
define('APP_INSTITUTION', 'Kenya Methodist University');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/student-managment-system');
define('LOGO_PATH', BASE_URL . '/assets/images/kemu-logo.png');
define('UPLOAD_PATH', __DIR__ . '/../uploads/homepage');
define('UPLOAD_URL', BASE_URL . '/uploads/homepage');

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
