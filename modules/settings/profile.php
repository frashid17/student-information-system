<?php
$pageTitle = 'Profile Settings';
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$pdo = getDBConnection();
$userId = (int) $_SESSION['user_id'];
$user = getUserById($pdo, $userId);

if (!$user) {
    setFlash('error', 'Account not found.');
    redirect(BASE_URL . '/logout.php');
}

$linkedStudent = isStudentUser() ? getLinkedStudent($pdo) : null;
$linkedFaculty = isFacultyUser() ? getLinkedFaculty($pdo) : null;
$canChangeUsername = !in_array($user['role'], ['student', 'faculty'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($fullName === '') {
            setFlash('error', 'Full name is required.');
            redirect(BASE_URL . '/modules/settings/profile.php');
        }

        $username = $user['username'];
        if ($canChangeUsername) {
            $newUsername = strtolower(trim($_POST['username'] ?? ''));
            if ($newUsername === '') {
                setFlash('error', 'Username is required.');
                redirect(BASE_URL . '/modules/settings/profile.php');
            }
            if ($newUsername !== $user['username']) {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$newUsername, $userId]);
                if ($check->fetch()) {
                    setFlash('error', 'That username is already taken.');
                    redirect(BASE_URL . '/modules/settings/profile.php');
                }
                $username = $newUsername;
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?");
        $stmt->execute([$fullName, $email ?: null, $username, $userId]);

        if ($linkedStudent) {
            $pdo->prepare("UPDATE students SET email = ? WHERE id = ?")->execute([$email ?: null, $linkedStudent['id']]);
        }
        if ($linkedFaculty) {
            $pdo->prepare("UPDATE faculty SET email = ? WHERE id = ?")->execute([$email ?: null, $linkedFaculty['id']]);
        }

        $user = getUserById($pdo, $userId);
        syncSessionUser($user);
        setFlash('success', 'Profile updated successfully.');
        redirect(BASE_URL . '/modules/settings/profile.php');
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            setFlash('error', 'Current password is incorrect.');
            redirect(BASE_URL . '/modules/settings/profile.php');
        }
        if (strlen($newPassword) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
            redirect(BASE_URL . '/modules/settings/profile.php');
        }
        if ($newPassword !== $confirmPassword) {
            setFlash('error', 'New passwords do not match.');
            redirect(BASE_URL . '/modules/settings/profile.php');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        setFlash('success', 'Password changed successfully.');
        redirect(BASE_URL . '/modules/settings/profile.php');
    }
}

$user = getUserById($pdo, $userId);
$linkedStudent = isStudentUser() ? getLinkedStudent($pdo) : null;
$linkedFaculty = isFacultyUser() ? getLinkedFaculty($pdo) : null;
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Account Overview</h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Role</label><span><?= sanitize(getRoleLabel($user['role'])) ?></span></div>
            <div class="detail-item"><label>Username</label><span><?= sanitize($user['username']) ?></span></div>
            <div class="detail-item"><label>Member Since</label><span><?= formatDate($user['created_at']) ?></span></div>
            <div class="detail-item"><label>Account Status</label><span><span class="badge badge-success">Active</span></span></div>
            <?php if ($linkedStudent): ?>
            <div class="detail-item"><label>Student Number</label><span><?= sanitize($linkedStudent['student_number']) ?></span></div>
            <div class="detail-item"><label>Program</label><span><?= sanitize($linkedStudent['program_name'] ?? '-') ?></span></div>
            <?php endif; ?>
            <?php if ($linkedFaculty): ?>
            <div class="detail-item"><label>Staff ID</label><span><?= sanitize($linkedFaculty['staff_id']) ?></span></div>
            <div class="detail-item"><label>Department</label><span><?= sanitize($linkedFaculty['department'] ?? '-') ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Profile Information</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($user['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username <?= $canChangeUsername ? '*' : '' ?></label>
                    <?php if ($canChangeUsername): ?>
                    <input type="text" name="username" class="form-control" value="<?= sanitize($user['username']) ?>" required>
                    <?php else: ?>
                    <input type="text" class="form-control" value="<?= sanitize($user['username']) ?>" disabled>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Username is linked to your student number or staff ID and cannot be changed.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" value="<?= sanitize(getRoleLabel($user['role'])) ?>" disabled>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Change Password</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-row">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">Password must be at least 6 characters.</p>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
