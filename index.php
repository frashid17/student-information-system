<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;
$homepageAnnouncements = getHomepageAnnouncements($pdo);
$homepageDocuments = getHomepageDocuments($pdo);

require __DIR__ . '/includes/homepage_view.php';
