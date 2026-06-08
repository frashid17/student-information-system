<?php

const UNIT_REGISTRATION_FEE_PERCENT = 40;
const MAX_UNITS_PER_SEMESTER = 6;
const UNITS_PER_PROGRAM = 120;
const COMMON_UNITS_COUNT = 25;

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getStudentSemesterNumber(array $student): int
{
    return max(1, (int) ($student['semester_number'] ?? 1));
}

function getFeePaymentPercent(PDO $pdo, int $studentId, string $trimester, string $academicYear): float
{
    $balance = getStudentFeeBalance($pdo, $studentId, $trimester, $academicYear);
    $totalFee = (float) $balance['total_fee'];

    if ($totalFee <= 0) {
        return 100.0;
    }

    return min(100.0, ((float) $balance['total_paid'] / $totalFee) * 100);
}

function getRegisteredUnitCount(PDO $pdo, int $studentId, string $trimester, string $academicYear): int
{
    return count(getStudentRegisteredUnits($pdo, $studentId, $trimester, $academicYear));
}

function getRemainingUnitSlots(PDO $pdo, int $studentId, string $trimester, string $academicYear): int
{
    return max(0, MAX_UNITS_PER_SEMESTER - getRegisteredUnitCount($pdo, $studentId, $trimester, $academicYear));
}

function canRegisterForUnits(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    if (!tableExists($pdo, 'units')) {
        return ['allowed' => false, 'reason' => 'Units module is not set up. Ask the administrator to run the database update.'];
    }

    $registeredCount = getRegisteredUnitCount($pdo, $studentId, $trimester, $academicYear);
    if ($registeredCount >= MAX_UNITS_PER_SEMESTER) {
        return [
            'allowed' => false,
            'reason' => 'You have already registered the maximum of ' . MAX_UNITS_PER_SEMESTER . ' units this semester.',
            'registered_count' => $registeredCount,
            'remaining_slots' => 0,
        ];
    }

    $percent = getFeePaymentPercent($pdo, $studentId, $trimester, $academicYear);

    if ($percent < UNIT_REGISTRATION_FEE_PERCENT) {
        return [
            'allowed' => false,
            'reason' => 'You must pay at least ' . UNIT_REGISTRATION_FEE_PERCENT . '% of your semester fees before registering for units. Current payment: ' . number_format($percent, 1) . '%.',
            'percent' => $percent,
            'registered_count' => $registeredCount,
            'remaining_slots' => max(0, MAX_UNITS_PER_SEMESTER - $registeredCount),
        ];
    }

    return [
        'allowed' => true,
        'percent' => $percent,
        'registered_count' => $registeredCount,
        'remaining_slots' => MAX_UNITS_PER_SEMESTER - $registeredCount,
    ];
}

function canPrintExamCard(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    $balance = getStudentFeeBalance($pdo, $studentId, $trimester, $academicYear);

    if ((float) $balance['balance'] > 0) {
        return [
            'allowed' => false,
            'reason' => 'Exam card printing requires full payment of school fees. Outstanding balance: ' . formatCurrency((float) $balance['balance']) . '.',
            'balance' => $balance,
        ];
    }

    $units = getStudentRegisteredUnits($pdo, $studentId, $trimester, $academicYear);
    if (empty($units)) {
        return [
            'allowed' => false,
            'reason' => 'You have not registered for any units this semester.',
            'balance' => $balance,
        ];
    }

    return ['allowed' => true, 'balance' => $balance, 'units' => $units];
}

function getStudentRegisteredUnits(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    if (!tableExists($pdo, 'student_unit_registrations')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT sur.*, u.unit_code, u.unit_name, u.credit_hours, u.category
        FROM student_unit_registrations sur
        JOIN units u ON u.id = sur.unit_id
        WHERE sur.student_id = ? AND sur.trimester = ? AND sur.academic_year = ?
          AND sur.status = 'registered'
        ORDER BY u.unit_code
    ");
    $stmt->execute([$studentId, $trimester, $academicYear]);

    return $stmt->fetchAll();
}

function getAvailableUnitsForStudent(PDO $pdo, array $student): array
{
    if (!tableExists($pdo, 'program_units') || empty($student['program_id'])) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT u.*, pu.year_of_study
        FROM program_units pu
        JOIN units u ON u.id = pu.unit_id
        WHERE pu.program_id = ? AND u.is_active = 1
          AND u.id NOT IN (
              SELECT unit_id FROM student_unit_registrations
              WHERE student_id = ? AND trimester = ? AND academic_year = ? AND status = 'registered'
          )
        ORDER BY pu.year_of_study, u.unit_code
    ");
    $stmt->execute([
        (int) $student['program_id'],
        (int) $student['id'],
        $student['trimester'],
        $student['academic_year'],
    ]);

    return $stmt->fetchAll();
}

function getProgramUnits(PDO $pdo, int $programId): array
{
    $stmt = $pdo->prepare("
        SELECT u.*, pu.year_of_study
        FROM program_units pu
        JOIN units u ON u.id = pu.unit_id
        WHERE pu.program_id = ?
        ORDER BY pu.year_of_study, u.unit_code
    ");
    $stmt->execute([$programId]);

    return $stmt->fetchAll();
}

function nextTrimester(string $trimester, string $academicYear): array
{
    $map = [
        'Trimester 1' => 'Trimester 2',
        'Trimester 2' => 'Trimester 3',
        'Trimester 3' => 'Trimester 1',
    ];

    $nextTrimester = $map[$trimester] ?? 'Trimester 1';
    $nextYear = $academicYear;

    if ($trimester === 'Trimester 3' && preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $matches)) {
        $nextYear = ((int) $matches[1] + 1) . '/' . ((int) $matches[2] + 1);
    }

    return [
        'trimester' => $nextTrimester,
        'academic_year' => $nextYear,
    ];
}

function getUngradedRegistrations(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    $stmt = $pdo->prepare("
        SELECT sur.id, u.unit_code, u.unit_name
        FROM student_unit_registrations sur
        JOIN units u ON u.id = sur.unit_id
        LEFT JOIN grades g ON g.student_id = sur.student_id
            AND g.trimester = sur.trimester
            AND g.academic_year = sur.academic_year
            AND (g.unit_id = sur.unit_id OR g.course_code = u.unit_code)
        WHERE sur.student_id = ? AND sur.trimester = ? AND sur.academic_year = ?
          AND sur.status = 'registered'
          AND g.id IS NULL
        ORDER BY u.unit_code
    ");
    $stmt->execute([$studentId, $trimester, $academicYear]);

    return $stmt->fetchAll();
}

function canReportNewSemester(PDO $pdo, array $student): array
{
    if (!tableExists($pdo, 'semester_reporting')) {
        return ['allowed' => false, 'reason' => 'Semester reporting is not set up. Ask the administrator to run the database update.'];
    }

    $studentId = (int) $student['id'];
    $trimester = $student['trimester'];
    $academicYear = $student['academic_year'];

    $balance = getStudentFeeBalance($pdo, $studentId, $trimester, $academicYear);
    if ((float) $balance['balance'] > 0) {
        return [
            'allowed' => false,
            'reason' => 'Clear your fee balance before reporting for a new semester. Outstanding: ' . formatCurrency((float) $balance['balance']) . '.',
            'balance' => $balance,
        ];
    }

    $registrations = getStudentRegisteredUnits($pdo, $studentId, $trimester, $academicYear);
    if (empty($registrations)) {
        return [
            'allowed' => false,
            'reason' => 'Register for units this semester before reporting for the next semester.',
            'balance' => $balance,
        ];
    }

    $ungraded = getUngradedRegistrations($pdo, $studentId, $trimester, $academicYear);
    if (!empty($ungraded)) {
        $codes = implode(', ', array_column($ungraded, 'unit_code'));
        return [
            'allowed' => false,
            'reason' => 'Results must be awarded for all registered units before reporting. Pending: ' . $codes . '.',
            'balance' => $balance,
            'ungraded' => $ungraded,
        ];
    }

    return [
        'allowed' => true,
        'balance' => $balance,
        'registrations' => $registrations,
        'next' => nextTrimester($trimester, $academicYear),
        'current_semester' => getStudentSemesterNumber($student),
        'next_semester' => getStudentSemesterNumber($student) + 1,
    ];
}

function reportNewSemester(PDO $pdo, array $student): array
{
    $check = canReportNewSemester($pdo, $student);
    if (!$check['allowed']) {
        return ['ok' => false, 'error' => $check['reason']];
    }

    $next = $check['next'];
    $fromSemester = $check['current_semester'];
    $toSemester = $check['next_semester'];

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE students
            SET trimester = ?, academic_year = ?, semester_number = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $next['trimester'],
            $next['academic_year'],
            $toSemester,
            (int) $student['id'],
        ]);

        $log = $pdo->prepare("
            INSERT INTO semester_reporting
            (student_id, from_trimester, from_academic_year, to_trimester, to_academic_year, from_semester_number, to_semester_number)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log->execute([
            (int) $student['id'],
            $student['trimester'],
            $student['academic_year'],
            $next['trimester'],
            $next['academic_year'],
            $fromSemester,
            $toSemester,
        ]);

        $pdo->commit();

        return [
            'ok' => true,
            'message' => "Welcome to Semester {$toSemester} ({$next['trimester']}, {$next['academic_year']}). Your fee balance has been reset for the new semester.",
            'next' => $next,
            'semester_number' => $toSemester,
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Failed to report for new semester. Please try again.'];
    }
}

function registerStudentUnits(PDO $pdo, array $student, array $unitIds): array
{
    $check = canRegisterForUnits($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
    if (!$check['allowed']) {
        return ['ok' => false, 'error' => $check['reason']];
    }

    $unitIds = array_unique(array_filter(array_map('intval', $unitIds)));
    if (empty($unitIds)) {
        return ['ok' => false, 'error' => 'Select at least one unit to register.'];
    }

    $currentCount = getRegisteredUnitCount($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
    $remaining = MAX_UNITS_PER_SEMESTER - $currentCount;

    if ($remaining <= 0) {
        return ['ok' => false, 'error' => 'You have already registered the maximum of ' . MAX_UNITS_PER_SEMESTER . ' units this semester.'];
    }

    if (count($unitIds) > $remaining) {
        return [
            'ok' => false,
            'error' => 'You can only register ' . $remaining . ' more unit(s) this semester (maximum ' . MAX_UNITS_PER_SEMESTER . ').',
        ];
    }

    $available = getAvailableUnitsForStudent($pdo, $student);
    $availableIds = array_column($available, 'id');
    $invalid = array_diff($unitIds, $availableIds);

    if (!empty($invalid)) {
        return ['ok' => false, 'error' => 'One or more selected units are not available for your program or are already registered.'];
    }

    $insert = $pdo->prepare("
        INSERT INTO student_unit_registrations (student_id, unit_id, trimester, academic_year)
        VALUES (?, ?, ?, ?)
    ");

    $registered = 0;
    foreach ($unitIds as $unitId) {
        if ($registered >= $remaining) {
            break;
        }

        try {
            $insert->execute([
                (int) $student['id'],
                $unitId,
                $student['trimester'],
                $student['academic_year'],
            ]);
            $registered++;
        } catch (PDOException $e) {
            // Skip duplicates
        }
    }

    if ($registered === 0) {
        return ['ok' => false, 'error' => 'No units were registered. They may already be on your list.'];
    }

    return ['ok' => true, 'message' => "$registered unit(s) registered successfully."];
}

function seedProgramUnits(PDO $pdo): array
{
    if (!tableExists($pdo, 'units')) {
        return ['ok' => false, 'error' => 'Run database/schooldb_update_v3.sql first.'];
    }

    $programs = $pdo->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1")->fetchAll();
    if (empty($programs)) {
        return ['ok' => false, 'error' => 'No active programs found. Add programs first.'];
    }

    $existingLinks = (int) $pdo->query('SELECT COUNT(*) FROM program_units')->fetchColumn();
    if ($existingLinks >= UNITS_PER_PROGRAM) {
        return ['ok' => false, 'error' => 'Units appear to be seeded already. Delete program_units rows to re-seed.'];
    }

    $commonTopics = [
        'Communication Skills', 'Introduction to Computing', 'Mathematics I', 'Academic Writing',
        'Research Methods', 'Statistics', 'Ethics and Integrity', 'Entrepreneurship',
        'Critical Thinking', 'Information Literacy', 'Professional Development', 'Leadership Skills',
        'Project Management Basics', 'Financial Literacy', 'Environmental Studies',
        'Health and Wellness', 'Kenyan Constitution', 'Community Service Learning',
        'Digital Citizenship', 'Presentation Skills', 'Teamwork and Collaboration',
        'Problem Solving', 'Innovation and Creativity', 'Career Planning', 'Library and Study Skills',
    ];

    $pdo->beginTransaction();

    try {
        $unitInsert = $pdo->prepare("
            INSERT INTO units (unit_code, unit_name, credit_hours, category, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $linkInsert = $pdo->prepare("
            INSERT IGNORE INTO program_units (program_id, unit_id, year_of_study)
            VALUES (?, ?, ?)
        ");

        $commonUnitIds = [];

        for ($i = 1; $i <= COMMON_UNITS_COUNT; $i++) {
            $code = 'COM' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $name = $commonTopics[$i - 1] ?? ('Common Unit ' . $i);

            $check = $pdo->prepare('SELECT id FROM units WHERE unit_code = ?');
            $check->execute([$code]);
            $existing = $check->fetch();

            if ($existing) {
                $unitId = (int) $existing['id'];
            } else {
                $unitInsert->execute([$code, $name, 3.0, 'common', 'Shared across all programs']);
                $unitId = (int) $pdo->lastInsertId();
            }

            $commonUnitIds[] = $unitId;

            foreach ($programs as $program) {
                $year = (int) ceil($i / 8);
                $linkInsert->execute([(int) $program['id'], $unitId, min(4, max(1, $year))]);
            }
        }

        $programSpecificCount = UNITS_PER_PROGRAM - COMMON_UNITS_COUNT;

        foreach ($programs as $program) {
            $programId = (int) $program['id'];
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $program['program_code']));
            if ($prefix === '') {
                $prefix = 'PRG' . $programId;
            }

            for ($i = 1; $i <= $programSpecificCount; $i++) {
                $code = $prefix . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $name = $program['program_name'] . ' — Unit ' . $i;
                $year = (int) ceil($i / 24);

                $check = $pdo->prepare('SELECT id FROM units WHERE unit_code = ?');
                $check->execute([$code]);
                $existing = $check->fetch();

                if ($existing) {
                    $unitId = (int) $existing['id'];
                } else {
                    $unitInsert->execute([$code, $name, 3.0, 'core', 'Program-specific unit']);
                    $unitId = (int) $pdo->lastInsertId();
                }

                $linkInsert->execute([$programId, $unitId, min(4, max(1, $year))]);
            }
        }

        $pdo->commit();

        $totalUnits = (int) $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
        $totalLinks = (int) $pdo->query('SELECT COUNT(*) FROM program_units')->fetchColumn();

        return [
            'ok' => true,
            'message' => "Seeded units successfully. {$totalUnits} units in catalogue, {$totalLinks} program-unit links (" . UNITS_PER_PROGRAM . " per program including shared units).",
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Seeding failed: ' . $e->getMessage()];
    }
}

function getUnitsCatalogue(PDO $pdo, ?int $programId = null): array
{
    if ($programId) {
        return getProgramUnits($pdo, $programId);
    }

    return $pdo->query("SELECT * FROM units ORDER BY unit_code")->fetchAll();
}

function getStudentUnitsWithGrades(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    $units = getStudentRegisteredUnits($pdo, $studentId, $trimester, $academicYear);
    if (empty($units)) {
        return [];
    }

    $gradeStmt = $pdo->prepare("
        SELECT id, marks, grade_letter, remarks
        FROM grades
        WHERE student_id = ? AND trimester = ? AND academic_year = ?
          AND (unit_id = ? OR course_code = ?)
        LIMIT 1
    ");

    $result = [];
    foreach ($units as $unit) {
        $gradeStmt->execute([
            $studentId,
            $trimester,
            $academicYear,
            (int) $unit['unit_id'],
            $unit['unit_code'],
        ]);
        $grade = $gradeStmt->fetch();

        $result[] = [
            'unit_id' => (int) $unit['unit_id'],
            'unit_code' => $unit['unit_code'],
            'unit_name' => $unit['unit_name'],
            'credit_hours' => $unit['credit_hours'],
            'category' => $unit['category'],
            'grade_id' => $grade ? (int) $grade['id'] : null,
            'marks' => $grade ? (float) $grade['marks'] : null,
            'grade_letter' => $grade['grade_letter'] ?? null,
            'remarks' => $grade['remarks'] ?? null,
        ];
    }

    return $result;
}

function saveStudentUnitGrade(PDO $pdo, int $studentId, int $unitId, string $trimester, string $academicYear, float $marks, ?string $remarks = null): array
{
    if ($marks < 0 || $marks > 100) {
        return ['ok' => false, 'error' => 'Marks must be between 0 and 100.'];
    }

    $regStmt = $pdo->prepare("
        SELECT sur.id
        FROM student_unit_registrations sur
        WHERE sur.student_id = ? AND sur.unit_id = ? AND sur.trimester = ? AND sur.academic_year = ?
          AND sur.status = 'registered'
        LIMIT 1
    ");
    $regStmt->execute([$studentId, $unitId, $trimester, $academicYear]);
    if (!$regStmt->fetch()) {
        return ['ok' => false, 'error' => 'This unit is not registered for the student in the selected semester.'];
    }

    $unitStmt = $pdo->prepare('SELECT unit_code, unit_name FROM units WHERE id = ?');
    $unitStmt->execute([$unitId]);
    $unit = $unitStmt->fetch();
    if (!$unit) {
        return ['ok' => false, 'error' => 'Unit not found.'];
    }

    $gradeLetter = calculateGrade($marks);
    $remarks = trim($remarks ?? '') ?: null;

    $existingStmt = $pdo->prepare("
        SELECT id FROM grades
        WHERE student_id = ? AND trimester = ? AND academic_year = ?
          AND (unit_id = ? OR course_code = ?)
        LIMIT 1
    ");
    $existingStmt->execute([$studentId, $trimester, $academicYear, $unitId, $unit['unit_code']]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE grades
            SET course_name = ?, course_code = ?, marks = ?, grade_letter = ?, remarks = ?, unit_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $unit['unit_name'],
            $unit['unit_code'],
            $marks,
            $gradeLetter,
            $remarks,
            $unitId,
            (int) $existing['id'],
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO grades (student_id, course_name, course_code, trimester, academic_year, marks, grade_letter, remarks, unit_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $studentId,
            $unit['unit_name'],
            $unit['unit_code'],
            $trimester,
            $academicYear,
            $marks,
            $gradeLetter,
            $remarks,
            $unitId,
        ]);
    }

    return [
        'ok' => true,
        'message' => 'Grade saved for ' . $unit['unit_code'] . ' (' . $unit['unit_name'] . '): ' . $gradeLetter . ' (' . $marks . '%)',
        'grade_letter' => $gradeLetter,
        'marks' => $marks,
    ];
}
