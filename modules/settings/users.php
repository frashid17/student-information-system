<?php
$pageTitle = 'User Accounts';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$isSuperAdmin = hasRole('super_admin');
$allowedRoles = $isSuperAdmin ? ['admin', 'staff'] : ['staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        setFlash('error', 'You cannot create that type of account.');
        redirect(BASE_URL . '/modules/settings/users.php');
    }

    $result = createUserAccount(
        $pdo,
        $_POST['username'],
        $_POST['password'] ?: 'staff123',
        $role,
        trim($_POST['full_name']),
        trim($_POST['email'] ?? '') ?: null
    );

    if ($result === true) {
        setFlash('success', 'User account created. Username: ' . strtolower(trim($_POST['username'])));
    } else {
        setFlash('error', (string) $result);
    }
    redirect(BASE_URL . '/modules/settings/users.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$_GET['toggle']]);
    $target = $stmt->fetch();

    if ($target && $target['id'] != $_SESSION['user_id']) {
        if (!$isSuperAdmin && in_array($target['role'], ['super_admin', 'admin'], true)) {
            setFlash('error', 'You cannot change that account.');
        } else {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$_GET['toggle']]);
            setFlash('success', 'Account status updated.');
        }
    }
    redirect(BASE_URL . '/modules/settings/users.php');
}

$users = $pdo->query("
    SELECT id, username, role, full_name, email, is_active, created_at
    FROM users
    WHERE role IN ('super_admin', 'admin', 'staff')
    ORDER BY role, full_name
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Create Staff / Admin Login</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. jane.staff" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <?php if ($isSuperAdmin): ?>
                        <option value="admin">Administrator</option>
                        <?php endif; ?>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Default: staff123" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>System User Accounts</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['full_name']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize(getRoleLabel($u['role'])) ?></span></td>
                        <td><?= sanitize($u['email'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id'] && ($isSuperAdmin || !in_array($u['role'], ['super_admin', 'admin'], true))): ?>
                            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
