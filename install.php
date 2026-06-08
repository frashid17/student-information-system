<?php
/**
 * Run once after importing schooldb.sql
 * Visit: http://localhost/student-managment-system/install.php
 */
require_once __DIR__ . '/config/app.php';

$message = '';
$success = false;

try {
    $pdo = getDBConnection();
    $hash = password_hash('admin1', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' AND role = 'super_admin'");
    $stmt->execute();
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE users SET password = ?, full_name = 'System Administrator' WHERE id = ?");
        $update->execute([$hash, $existing['id']]);
        $message = 'Admin account updated. Login with Super Administrator / admin / admin1';
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES ('admin', ?, 'super_admin', 'System Administrator', 'admin@kemu.ac.ke')");
        $insert->execute([$hash]);
        $message = 'Admin account created. Login with Super Administrator / admin / admin1';
    }
    $success = true;
} catch (Exception $e) {
    $message = 'Setup failed: ' . $e->getMessage() . '. Import database/schooldb.sql first.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Setup</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--login">
                <h1>System Setup</h1>
            </div>
            <div class="alert alert-<?= $success ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
            <?php if ($success): ?>
                <a href="<?= BASE_URL ?>/" class="btn btn-primary" style="width:100%;">Go to Portal Home</a>
                <p style="margin-top:16px;font-size:0.8rem;color:var(--text-muted);text-align:center;">
                    Delete install.php after setup for security.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
