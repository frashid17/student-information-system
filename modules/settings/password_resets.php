<?php
$pageTitle = 'Password Management';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$canReset = canManagePasswordResets();
$resetUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'resolve_request' && $canReset) {
        $result = resolvePasswordResetRequest(
            $pdo,
            (int) $_POST['request_id'],
            (int) $_SESSION['user_id'],
            $_POST['new_password'] ?? '',
            'resolved',
            $_POST['admin_notes'] ?? null
        );
        setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
        redirect(BASE_URL . '/modules/settings/password_resets.php');
    }

    if ($action === 'reject_request' && $canReset) {
        $result = resolvePasswordResetRequest(
            $pdo,
            (int) $_POST['request_id'],
            (int) $_SESSION['user_id'],
            '',
            'rejected',
            $_POST['admin_notes'] ?? null
        );
        setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
        redirect(BASE_URL . '/modules/settings/password_resets.php');
    }

    if ($action === 'reset_user' && $canReset) {
        $result = resetUserPasswordByAdmin($pdo, (int) $_POST['user_id'], $_POST['new_password'] ?? '');
        setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
        redirect(BASE_URL . '/modules/settings/password_resets.php');
    }
}

if (isset($_GET['reset']) && is_numeric($_GET['reset']) && $canReset) {
    $stmt = $pdo->prepare('SELECT id, username, role, full_name, email, is_active FROM users WHERE id = ?');
    $stmt->execute([(int) $_GET['reset']]);
    $resetUser = $stmt->fetch();
}

$pendingRequests = getPasswordResetRequests($pdo, 'pending');
$recentRequests = getPasswordResetRequests($pdo, 'resolved');
$recentRejected = getPasswordResetRequests($pdo, 'rejected');
$allUsers = $canReset ? getAllUsersForPasswordManagement($pdo) : [];

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$canReset): ?>
<div class="alert alert-info">
    You can view password reset requests. Only the <strong>Super Administrator</strong> can reset passwords and resolve requests.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Pending Password Reset Requests</h3>
        <span class="badge badge-<?= count($pendingRequests) ? 'warning' : 'success' ?>"><?= count($pendingRequests) ?> pending</span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($pendingRequests)): ?>
        <div class="empty-state">
            <h4>No pending requests</h4>
            <p>Users can submit requests from the login page using <strong>Forgot Password</strong>.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Requested</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Message</th>
                        <?php if ($canReset): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $req): ?>
                    <tr>
                        <td><?= formatDate($req['requested_at']) ?></td>
                        <td><?= sanitize($req['full_name'] ?: $req['username']) ?><br><small><?= sanitize($req['username']) ?></small></td>
                        <td><span class="badge badge-info"><?= sanitize(getRoleLabel($req['role'])) ?></span></td>
                        <td><?= sanitize($req['email'] ?? '-') ?></td>
                        <td><?= sanitize($req['message'] ?: '-') ?></td>
                        <?php if ($canReset): ?>
                        <td>
                            <form method="POST" style="display:flex;flex-direction:column;gap:6px;min-width:200px;">
                                <input type="hidden" name="action" value="resolve_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input type="password" name="new_password" class="form-control" placeholder="New password" required minlength="4">
                                <input type="text" name="admin_notes" class="form-control" placeholder="Notes (optional)">
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary btn-sm">Reset Password</button>
                                </div>
                            </form>
                            <form method="POST" style="margin-top:6px;">
                                <input type="hidden" name="action" value="reject_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Reject this request?">Reject</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canReset): ?>
<?php if ($resetUser): ?>
<div class="card">
    <div class="card-header">
        <h3>Reset Password — <?= sanitize($resetUser['username']) ?></h3>
        <a href="<?= BASE_URL ?>/modules/settings/password_resets.php" class="btn btn-secondary btn-sm">Cancel</a>
    </div>
    <div class="card-body">
        <div class="detail-grid" style="margin-bottom:16px;">
            <div class="detail-item"><label>Name</label><span><?= sanitize($resetUser['full_name']) ?></span></div>
            <div class="detail-item"><label>Role</label><span><?= sanitize(getRoleLabel($resetUser['role'])) ?></span></div>
            <div class="detail-item"><label>Status</label><span><?= $resetUser['is_active'] ? 'Active' : 'Inactive' ?></span></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_user">
            <input type="hidden" name="user_id" value="<?= (int) $resetUser['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" class="form-control" required minlength="4" placeholder="Minimum 4 characters">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" data-confirm="Reset password for this user?">Update Password</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>All User Accounts — Reset Password</h3></div>
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
                    <?php foreach ($allUsers as $u): ?>
                    <tr>
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['full_name']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize(getRoleLabel($u['role'])) ?></span></td>
                        <td><?= sanitize($u['email'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?>
                            <a href="<?= BASE_URL ?>/modules/settings/profile.php" class="btn btn-secondary btn-sm">Profile</a>
                            <?php elseif ($u['role'] === 'super_admin'): ?>
                            -
                            <?php else: ?>
                            <a href="?reset=<?= (int) $u['id'] ?>" class="btn btn-primary btn-sm">Reset Password</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($recentRequests) || !empty($recentRejected)): ?>
<div class="card">
    <div class="card-header"><h3>Recently Handled Requests</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Handled By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_merge($recentRequests, $recentRejected) as $req): ?>
                    <tr>
                        <td><?= sanitize($req['username']) ?></td>
                        <td><?= sanitize(getRoleLabel($req['role'])) ?></td>
                        <td><span class="badge badge-<?= $req['status'] === 'resolved' ? 'success' : 'secondary' ?>"><?= ucfirst($req['status']) ?></span></td>
                        <td><?= sanitize($req['resolved_by_name'] ?? '-') ?></td>
                        <td><?= formatDate($req['resolved_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
