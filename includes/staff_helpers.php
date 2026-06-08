<?php

function isStaffUser(): bool
{
    return hasRole('staff');
}

function isTeachingUser(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['staff', 'faculty'], true);
}

function isAdminRole(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['super_admin', 'admin'], true);
}

function getTeachingFacultyId(PDO $pdo): ?int
{
    if (!isTeachingUser() || empty($_SESSION['related_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM faculty WHERE id = ? AND status = 'active'");
    $stmt->execute([(int) $_SESSION['related_id']]);

    return $stmt->fetchColumn() ? (int) $_SESSION['related_id'] : null;
}

function requireTeachingProfile(PDO $pdo): int
{
    $facultyId = getTeachingFacultyId($pdo);
    if (!$facultyId) {
        setFlash('error', 'Your teaching profile is not linked. Contact the administration to link your account to a faculty record.');
        redirect(BASE_URL . '/dashboard.php');
    }

    return $facultyId;
}

function tableExistsStaff(PDO $pdo, string $table): bool
{
    return tableExists($pdo, $table);
}

function getAssignedUnitsForTeacher(PDO $pdo, int $facultyId, string $trimester, string $academicYear): array
{
    if (!tableExistsStaff($pdo, 'unit_assignments')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT ua.*, u.unit_code, u.unit_name, u.credit_hours, p.program_name
        FROM unit_assignments ua
        JOIN units u ON u.id = ua.unit_id
        LEFT JOIN program_units pu ON pu.unit_id = u.id
        LEFT JOIN programs p ON p.id = pu.program_id
        WHERE ua.faculty_id = ? AND ua.trimester = ? AND ua.academic_year = ?
        GROUP BY ua.id, u.id, u.unit_code, u.unit_name, u.credit_hours, p.program_name
        ORDER BY u.unit_code
    ");
    $stmt->execute([$facultyId, $trimester, $academicYear]);

    return $stmt->fetchAll();
}

function getAssignedUnitIdsForTeacher(PDO $pdo, int $facultyId, string $trimester, string $academicYear): array
{
    return array_column(getAssignedUnitsForTeacher($pdo, $facultyId, $trimester, $academicYear), 'unit_id');
}

function teacherCanAccessUnit(PDO $pdo, int $facultyId, int $unitId, string $trimester, string $academicYear): bool
{
    if (isAdminRole()) {
        return true;
    }

    return in_array($unitId, getAssignedUnitIdsForTeacher($pdo, $facultyId, $trimester, $academicYear), true);
}

function getStudentsForTeacherUnits(PDO $pdo, int $facultyId, string $trimester, string $academicYear): array
{
    $unitIds = getAssignedUnitIdsForTeacher($pdo, $facultyId, $trimester, $academicYear);
    if (empty($unitIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge($unitIds, [$trimester, $academicYear]);

    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.student_number, s.first_name, s.last_name, s.trimester, s.academic_year,
               p.program_name, p.program_code,
               GROUP_CONCAT(DISTINCT u.unit_code ORDER BY u.unit_code SEPARATOR ', ') AS enrolled_units
        FROM student_unit_registrations sur
        JOIN students s ON s.id = sur.student_id
        JOIN units u ON u.id = sur.unit_id
        LEFT JOIN programs p ON p.id = s.program_id
        WHERE sur.unit_id IN ($placeholders)
          AND sur.trimester = ?
          AND sur.academic_year = ?
          AND sur.status = 'registered'
          AND s.status = 'active'
        GROUP BY s.id, s.student_number, s.first_name, s.last_name, s.trimester, s.academic_year, p.program_name, p.program_code
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function teacherCanAccessStudent(PDO $pdo, int $facultyId, int $studentId, string $trimester, string $academicYear): bool
{
    if (isAdminRole()) {
        return true;
    }

    $unitIds = getAssignedUnitIdsForTeacher($pdo, $facultyId, $trimester, $academicYear);
    if (empty($unitIds)) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$studentId], $unitIds, [$trimester, $academicYear]);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM student_unit_registrations
        WHERE student_id = ? AND unit_id IN ($placeholders)
          AND trimester = ? AND academic_year = ? AND status = 'registered'
    ");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function getTeacherTimetable(PDO $pdo, int $facultyId, string $trimester, string $academicYear): array
{
    if (!tableExistsStaff($pdo, 'timetable_entries')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT t.*, u.unit_code, u.unit_name
        FROM timetable_entries t
        JOIN units u ON u.id = t.unit_id
        WHERE t.faculty_id = ? AND t.trimester = ? AND t.academic_year = ?
        ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
    $stmt->execute([$facultyId, $trimester, $academicYear]);

    return $stmt->fetchAll();
}

function getTeacherPayslips(PDO $pdo, int $facultyId): array
{
    $stmt = $pdo->prepare("
        SELECT ps.* FROM payslips ps
        WHERE ps.faculty_id = ?
        ORDER BY ps.pay_date DESC
    ");
    $stmt->execute([$facultyId]);

    return $stmt->fetchAll();
}

function getCurrentTeachingTerm(PDO $pdo, int $facultyId): array
{
    if (tableExistsStaff($pdo, 'unit_assignments')) {
        $stmt = $pdo->prepare('
            SELECT trimester, academic_year FROM unit_assignments
            WHERE faculty_id = ? ORDER BY assigned_at DESC LIMIT 1
        ');
        $stmt->execute([$facultyId]);
        $row = $stmt->fetch();
        if ($row) {
            return ['trimester' => $row['trimester'], 'academic_year' => $row['academic_year']];
        }
    }

    return ['trimester' => 'Trimester 1', 'academic_year' => '2025/2026'];
}

function canRecordTeachingGrades(): bool
{
    return isAdminRole() || (isTeachingUser() && canAccess('academics_teaching'));
}

function filterStudentsForCurrentUser(PDO $pdo, array $students, ?string $trimester = null, ?string $academicYear = null): array
{
    if (isAdminRole()) {
        return $students;
    }

    if (!isTeachingUser()) {
        return $students;
    }

    $facultyId = getTeachingFacultyId($pdo);
    if (!$facultyId) {
        return [];
    }

    $term = getCurrentTeachingTerm($pdo, $facultyId);
    $trimester = $trimester ?? $term['trimester'];
    $academicYear = $academicYear ?? $term['academic_year'];

    $allowed = getStudentsForTeacherUnits($pdo, $facultyId, $trimester, $academicYear);
    $allowedIds = array_column($allowed, 'id');

    return array_values(array_filter($students, fn($s) => in_array((int) $s['id'], $allowedIds, true)));
}

function getLecturersForStudentRegisteredUnits(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    if (!tableExistsStaff($pdo, 'unit_assignments') || !tableExists($pdo, 'student_unit_registrations')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT sur.unit_id, u.unit_code, u.unit_name,
               GROUP_CONCAT(
                   DISTINCT CONCAT(f.staff_id, ' — ', f.first_name, ' ', f.last_name)
                   ORDER BY f.first_name SEPARATOR '; '
               ) AS lecturers
        FROM student_unit_registrations sur
        JOIN units u ON u.id = sur.unit_id
        LEFT JOIN unit_assignments ua ON ua.unit_id = sur.unit_id
            AND ua.trimester = sur.trimester AND ua.academic_year = sur.academic_year
        LEFT JOIN faculty f ON f.id = ua.faculty_id AND f.status = 'active'
        WHERE sur.student_id = ? AND sur.trimester = ? AND sur.academic_year = ?
          AND sur.status = 'registered'
        GROUP BY sur.unit_id, u.unit_code, u.unit_name
        ORDER BY u.unit_code
    ");
    $stmt->execute([$studentId, $trimester, $academicYear]);

    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['unit_id']] = $row['lecturers'] ?: null;
    }

    return $map;
}

function getLecturerStudentLinksForTerm(PDO $pdo, string $trimester, string $academicYear): array
{
    if (!tableExistsStaff($pdo, 'unit_assignments')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT ua.id AS assignment_id, ua.faculty_id, ua.unit_id,
               f.staff_id, f.first_name, f.last_name,
               u.unit_code, u.unit_name,
               GROUP_CONCAT(
                   DISTINCT CONCAT(s.student_number, ' — ', s.first_name, ' ', s.last_name)
                   ORDER BY s.first_name SEPARATOR ' | '
               ) AS student_list,
               COUNT(DISTINCT s.id) AS student_count
        FROM unit_assignments ua
        JOIN faculty f ON f.id = ua.faculty_id
        JOIN units u ON u.id = ua.unit_id
        LEFT JOIN student_unit_registrations sur ON sur.unit_id = ua.unit_id
            AND sur.trimester = ua.trimester AND sur.academic_year = ua.academic_year
            AND sur.status = 'registered'
        LEFT JOIN students s ON s.id = sur.student_id AND s.status = 'active'
        WHERE ua.trimester = ? AND ua.academic_year = ?
        GROUP BY ua.id, ua.faculty_id, ua.unit_id, f.staff_id, f.first_name, f.last_name, u.unit_code, u.unit_name
        ORDER BY f.first_name, f.last_name, u.unit_code
    ");
    $stmt->execute([$trimester, $academicYear]);

    return $stmt->fetchAll();
}
