<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_helpers.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$success = '';
$role = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if ($username === '' || $role === '') {
        $error = 'Please enter your username and role.';
    } else {
        $pdo = getDBConnection();
        $result = createPasswordResetRequest($pdo, $username, $role, $message);

        if ($result['ok']) {
            $success = $result['message'];
            $username = '';
            $role = '';
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= LOGO_PATH ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--login">
                <h1>Forgot Password</h1>
                <p>Request a password reset from the administrator</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= sanitize($success) ?></div>
            <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="role">Login Role *</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="" <?= $role === '' ? 'selected' : '' ?>>Select Role</option>
                        <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>Super Administrator</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="faculty" <?= $role === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Your login username" value="<?= sanitize($username) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="message">Message (optional)</label>
                    <textarea name="message" id="message" class="form-control" rows="3" placeholder="e.g. Locked out after too many attempts"></textarea>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Submit Request</button>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-secondary">Back to Login</a>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="btn-group" style="margin-top: 24px;">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="flex:1;">Back to Login</a>
                <a href="<?= BASE_URL ?>/" class="btn btn-secondary">Home</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
