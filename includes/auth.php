<?php

require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        setFlash('error', 'You do not have permission to access this page.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role'],
        'full_name' => $_SESSION['full_name'],
        'related_id' => $_SESSION['related_id'] ?? null,
    ];
}

function hasRole(string $role): bool
{
    return ($_SESSION['user_role'] ?? '') === $role;
}

function isStudentUser(): bool
{
    return hasRole('student');
}

function isFacultyUser(): bool
{
    return hasRole('faculty');
}

function loginUser(PDO $pdo, string $username, string $password, string $role): bool
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['related_id'] = $user['related_id'];
        return true;
    }

    return false;
}

function logoutUser(): void
{
    session_destroy();
    redirect(BASE_URL . '/index.php');
}

function syncSessionUser(array $user): void
{
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['related_id'] = $user['related_id'];
}

function getUserById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function canAccess(string $module): bool
{
    $role = $_SESSION['user_role'] ?? '';

    $permissions = [
        'super_admin' => ['dashboard', 'students', 'fees', 'fees_manage', 'faculty', 'library', 'institution', 'units', 'academics', 'reports', 'communications', 'settings', 'users', 'password_resets'],
        'admin' => ['dashboard', 'students', 'fees', 'fees_manage', 'faculty', 'library', 'institution', 'units', 'academics', 'reports', 'communications', 'users', 'password_resets'],
        'staff' => ['dashboard', 'students_scoped', 'academics_teaching', 'timetable', 'payslip_own', 'communications'],
        'faculty' => ['dashboard', 'students_scoped', 'academics_teaching', 'timetable', 'payslip_own', 'communications'],
        'student' => ['dashboard', 'fees_balance', 'library_student', 'academics_view', 'unit_registration', 'exam_card', 'semester_reporting', 'communications'],
    ];

    return in_array($module, $permissions[$role] ?? [], true);
}

function requireModuleAccess(string $module): void
{
    requireLogin();
    if (!canAccess($module)) {
        setFlash('error', 'You do not have permission to access this page.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

function requireAnyModuleAccess(array $modules): void
{
    requireLogin();
    foreach ($modules as $module) {
        if (canAccess($module)) {
            return;
        }
    }
    setFlash('error', 'You do not have permission to access this page.');
    redirect(BASE_URL . '/dashboard.php');
}
