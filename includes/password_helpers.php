<?php

require_once __DIR__ . '/units_helpers.php';

function updateUserPassword(PDO $pdo, int $userId, string $plainPassword): bool
{
    if (strlen($plainPassword) < 4) {
        return false;
    }

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $userId]);

    return $stmt->rowCount() > 0;
}

function findUserByUsernameAndRole(PDO $pdo, string $username, string $role): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND role = ?');
    $stmt->execute([strtolower(trim($username)), $role]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function createPasswordResetRequest(PDO $pdo, string $username, string $role, ?string $message = null): array
{
    if (!tableExists($pdo, 'password_reset_requests')) {
        return ['ok' => false, 'error' => 'Password reset is not configured. Contact the IT office.'];
    }

    $user = findUserByUsernameAndRole($pdo, $username, $role);
    if (!$user) {
        return ['ok' => false, 'error' => 'No account found for that username and role. Check your details and try again.'];
    }

    if (!(int) $user['is_active']) {
        return ['ok' => false, 'error' => 'This account is inactive. Contact the administration office.'];
    }

    $pending = $pdo->prepare("
        SELECT id FROM password_reset_requests
        WHERE user_id = ? AND status = 'pending'
        LIMIT 1
    ");
    $pending->execute([(int) $user['id']]);
    if ($pending->fetch()) {
        return [
            'ok' => true,
            'message' => 'A password reset request is already pending. An administrator will contact you soon.',
        ];
    }

    $stmt = $pdo->prepare("
        INSERT INTO password_reset_requests (user_id, username, role, full_name, email, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user['id'],
        $user['username'],
        $user['role'],
        $user['full_name'],
        $user['email'],
        trim($message ?? '') ?: null,
    ]);

    return [
        'ok' => true,
        'message' => 'Your password reset request has been sent to the administrator. You will be contacted once your password is reset.',
    ];
}

function getPasswordResetRequests(PDO $pdo, string $status = 'pending'): array
{
    if (!tableExists($pdo, 'password_reset_requests')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name AS current_name, ru.full_name AS resolved_by_name
        FROM password_reset_requests pr
        LEFT JOIN users u ON u.id = pr.user_id
        LEFT JOIN users ru ON ru.id = pr.resolved_by
        WHERE pr.status = ?
        ORDER BY pr.requested_at DESC
        LIMIT 25
    ");
    $stmt->execute([$status]);

    return $stmt->fetchAll();
}

function countPendingPasswordRequests(PDO $pdo): int
{
    if (!tableExists($pdo, 'password_reset_requests')) {
        return 0;
    }

    return (int) $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'")->fetchColumn();
}

function canManagePasswordResets(): bool
{
    return hasRole('super_admin');
}

function canViewPasswordRequests(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['super_admin', 'admin'], true);
}

function resolvePasswordResetRequest(PDO $pdo, int $requestId, int $adminId, string $newPassword, string $action = 'resolved', ?string $notes = null): array
{
    if (!canManagePasswordResets()) {
        return ['ok' => false, 'error' => 'Only the Super Administrator can reset passwords.'];
    }

    if (strlen($newPassword) < 4 && $action === 'resolved') {
        return ['ok' => false, 'error' => 'Password must be at least 4 characters.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        return ['ok' => false, 'error' => 'Request not found or already handled.'];
    }

    if ($action === 'resolved') {
        if (empty($request['user_id'])) {
            return ['ok' => false, 'error' => 'Linked user account not found.'];
        }

        if (!updateUserPassword($pdo, (int) $request['user_id'], $newPassword)) {
            return ['ok' => false, 'error' => 'Failed to update password.'];
        }
    }

    $update = $pdo->prepare("
        UPDATE password_reset_requests
        SET status = ?, admin_notes = ?, resolved_at = NOW(), resolved_by = ?
        WHERE id = ?
    ");
    $update->execute([
        $action === 'resolved' ? 'resolved' : 'rejected',
        trim($notes ?? '') ?: null,
        $adminId,
        $requestId,
    ]);

    if ($action === 'resolved') {
        return [
            'ok' => true,
            'message' => 'Password reset for ' . $request['username'] . ' (' . getRoleLabel($request['role']) . ').',
        ];
    }

    return ['ok' => true, 'message' => 'Password reset request rejected.'];
}

function resetUserPasswordByAdmin(PDO $pdo, int $userId, string $newPassword): array
{
    if (!canManagePasswordResets()) {
        return ['ok' => false, 'error' => 'Only the Super Administrator can reset passwords.'];
    }

    if (strlen($newPassword) < 4) {
        return ['ok' => false, 'error' => 'Password must be at least 4 characters.'];
    }

    $stmt = $pdo->prepare('SELECT id, username, role, full_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    if ($user['role'] === 'super_admin' && (int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)) {
        return ['ok' => false, 'error' => 'You cannot reset another Super Administrator password from here.'];
    }

    if (!updateUserPassword($pdo, (int) $user['id'], $newPassword)) {
        return ['ok' => false, 'error' => 'Failed to update password.'];
    }

    if (tableExists($pdo, 'password_reset_requests')) {
        $pdo->prepare("
            UPDATE password_reset_requests
            SET status = 'resolved', resolved_at = NOW(), resolved_by = ?, admin_notes = 'Reset directly by administrator'
            WHERE user_id = ? AND status = 'pending'
        ")->execute([(int) $_SESSION['user_id'], (int) $user['id']]);
    }

    return [
        'ok' => true,
        'message' => 'Password updated for ' . $user['username'] . ' (' . getRoleLabel($user['role']) . ').',
    ];
}

function getAllUsersForPasswordManagement(PDO $pdo): array
{
    return $pdo->query("
        SELECT id, username, role, full_name, email, is_active, created_at
        FROM users
        ORDER BY role, full_name
    ")->fetchAll();
}
