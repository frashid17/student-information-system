<?php
$pageTitle = 'User Accounts';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$isSuperAdmin = hasRole('super_admin');
$allowedRoles = $isSuperAdmin ? ['admin', 'staff'] : ['staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'link_teaching') {
        if (!$isSuperAdmin) {
            setFlash('error', 'Only the Super Administrator can link staff to teaching profiles.');
            redirect(BASE_URL . '/modules/settings/users.php');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $facultyId = !empty($_POST['faculty_id']) ? (int) $_POST['faculty_id'] : null;

        $stmt = $pdo->prepare('SELECT id, role, username FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser || $targetUser['role'] !== 'staff') {
            setFlash('error', 'Teaching links can only be set for staff accounts.');
            redirect(BASE_URL . '/modules/settings/users.php');
        }

        if ($facultyId) {
            $facultyCheck = $pdo->prepare('SELECT id, staff_id, first_name, last_name FROM faculty WHERE id = ? AND status = ?');
            $facultyCheck->execute([$facultyId, 'active']);
            $faculty = $facultyCheck->fetch();

            if (!$faculty) {
                setFlash('error', 'Selected faculty record was not found.');
                redirect(BASE_URL . '/modules/settings/users.php');
            }

            $duplicate = $pdo->prepare('SELECT id, username FROM users WHERE related_id = ? AND id != ? AND role IN (?, ?)');
            $duplicate->execute([$facultyId, $userId, 'staff', 'faculty']);
            $existing = $duplicate->fetch();

            if ($existing) {
                setFlash('error', 'That faculty record is already linked to user "' . $existing['username'] . '".');
                redirect(BASE_URL . '/modules/settings/users.php');
            }
        }

        $pdo->prepare('UPDATE users SET related_id = ? WHERE id = ?')->execute([$facultyId, $userId]);

        if ($facultyId) {
            setFlash('success', 'Staff account "' . $targetUser['username'] . '" linked to teaching profile successfully.');
        } else {
            setFlash('success', 'Teaching link removed from staff account "' . $targetUser['username'] . '".');
        }
        redirect(BASE_URL . '/modules/settings/users.php');
    }

    $role = $_POST['role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        setFlash('error', 'You cannot create that type of account.');
        redirect(BASE_URL . '/modules/settings/users.php');
    }

    $relatedId = null;
    if ($isSuperAdmin && $role === 'staff' && !empty($_POST['faculty_id'])) {
        $relatedId = (int) $_POST['faculty_id'];

        $duplicate = $pdo->prepare('SELECT username FROM users WHERE related_id = ? AND role IN (?, ?)');
        $duplicate->execute([$relatedId, 'staff', 'faculty']);
        if ($duplicate->fetch()) {
            setFlash('error', 'That faculty record is already linked to another login account.');
            redirect(BASE_URL . '/modules/settings/users.php');
        }
    }

    $result = createUserAccount(
        $pdo,
        $_POST['username'],
        $_POST['password'] ?: 'staff123',
        $role,
        trim($_POST['full_name']),
        trim($_POST['email'] ?? '') ?: null,
        $relatedId
    );

    if ($result === true) {
        $msg = 'User account created. Username: ' . strtolower(trim($_POST['username']));
        if ($relatedId) {
            $msg .= ' (linked to teaching profile)';
        }
        setFlash('success', $msg);
    } else {
        setFlash('error', (string) $result);
    }
    redirect(BASE_URL . '/modules/settings/users.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $stmt->execute([$_GET['toggle']]);
    $target = $stmt->fetch();

    if ($target && $target['id'] != $_SESSION['user_id']) {
        if (!$isSuperAdmin && in_array($target['role'], ['super_admin', 'admin'], true)) {
            setFlash('error', 'You cannot change that account.');
        } else {
            $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$_GET['toggle']]);
            setFlash('success', 'Account status updated.');
        }
    }
    redirect(BASE_URL . '/modules/settings/users.php');
}

$users = $pdo->query("
    SELECT u.id, u.username, u.role, u.full_name, u.email, u.is_active, u.created_at, u.related_id,
           f.staff_id, f.first_name AS faculty_first, f.last_name AS faculty_last, f.department
    FROM users u
    LEFT JOIN faculty f ON f.id = u.related_id
    WHERE u.role IN ('super_admin', 'admin', 'staff')
    ORDER BY u.role, u.full_name
")->fetchAll();

$facultyList = $pdo->query("
    SELECT f.id, f.staff_id, f.first_name, f.last_name, f.department,
           u.username AS linked_username, u.role AS linked_role
    FROM faculty f
    LEFT JOIN users u ON u.related_id = f.id AND u.role IN ('staff', 'faculty')
    WHERE f.status = 'active'
    ORDER BY f.first_name
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($isSuperAdmin): ?>
<div class="alert alert-info">
    As Super Administrator, you can link <strong>Staff</strong> logins to a <strong>faculty teaching profile</strong>.
    Linked staff can access My Students, grades, timetable, and payslips. Assign units under
    <a href="<?= BASE_URL ?>/modules/institution/unit_assignments.php">Institution → Unit Assignments</a>.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Create Staff / Admin Login</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">
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
                    <select name="role" id="createRole" class="form-control" required>
                        <?php if ($isSuperAdmin): ?>
                        <option value="admin">Administrator</option>
                        <?php endif; ?>
                        <option value="staff" selected>Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Default: staff123" required>
                </div>
                <?php if ($isSuperAdmin): ?>
                <div class="form-group" id="teachingLinkGroup">
                    <label>Link to Teaching Profile (faculty record)</label>
                    <select name="faculty_id" class="form-control">
                        <option value="">No teaching link</option>
                        <?php foreach ($facultyList as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $f['linked_username'] ? 'disabled' : '' ?>>
                            <?= sanitize($f['staff_id'] . ' — ' . $f['first_name'] . ' ' . $f['last_name']) ?>
                            <?= $f['linked_username'] ? ' (linked to ' . $f['linked_username'] . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Optional. Required for staff who will teach units.</p>
                </div>
                <?php endif; ?>
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
                        <th>Teaching Profile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['full_name']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize(getRoleLabel($u['role'])) ?></span></td>
                        <td>
                            <?php if ($u['related_id'] && $u['staff_id']): ?>
                            <span class="badge badge-success"><?= sanitize($u['staff_id']) ?></span>
                            <small style="display:block;color:var(--text-muted);"><?= sanitize($u['faculty_first'] . ' ' . $u['faculty_last']) ?></small>
                            <?php elseif ($u['role'] === 'staff'): ?>
                            <span class="badge badge-warning">Not linked</span>
                            <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= sanitize($u['email'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <?php if ($isSuperAdmin && $u['role'] === 'staff'): ?>
                            <form method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;min-width:220px;margin-bottom:6px;">
                                <input type="hidden" name="action" value="link_teaching">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <select name="faculty_id" class="form-control" style="min-width:140px;flex:1;">
                                    <option value="">No link</option>
                                    <?php foreach ($facultyList as $f): ?>
                                    <?php
                                    $taken = $f['linked_username'] && (int) $u['related_id'] !== (int) $f['id'];
                                    $selected = (int) $u['related_id'] === (int) $f['id'];
                                    ?>
                                    <option value="<?= $f['id'] ?>" <?= $selected ? 'selected' : '' ?> <?= $taken ? 'disabled' : '' ?>>
                                        <?= sanitize($f['staff_id']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Save Link</button>
                            </form>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                            <?php endif; ?>
                            <?php elseif ($u['id'] != $_SESSION['user_id'] && ($isSuperAdmin || !in_array($u['role'], ['super_admin', 'admin'], true))): ?>
                            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<script>
document.getElementById('createRole')?.addEventListener('change', function () {
    const group = document.getElementById('teachingLinkGroup');
    if (group) {
        group.style.display = this.value === 'staff' ? '' : 'none';
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
