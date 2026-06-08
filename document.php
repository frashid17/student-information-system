<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Document not found.');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM homepage_documents WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

$isPublic = !empty($doc['show_on_homepage']);
$isAdmin = isLoggedIn() && canManageHomepage();

if (!$isPublic && !$isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

$filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . $doc['file_path'];
if (!is_file($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
