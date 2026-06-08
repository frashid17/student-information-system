<?php

const GPA_SCALE_MAX = 4.0;

function getGradePoint(string $gradeLetter): float
{
    return match (strtoupper(trim($gradeLetter))) {
        'A' => 4.0,
        'B' => 3.0,
        'C' => 2.0,
        'D' => 1.0,
        default => 0.0,
    };
}

function formatGpa(?float $gpa): string
{
    if ($gpa === null) {
        return '—';
    }

    return number_format($gpa, 2);
}

function getGradePointLabel(string $gradeLetter): string
{
    $point = getGradePoint($gradeLetter);
    return $gradeLetter . ' = ' . number_format($point, 1);
}

function getStudentGradesWithCredits(PDO $pdo, int $studentId, ?string $trimester = null, ?string $academicYear = null): array
{
    $sql = "
        SELECT g.id, g.course_name, g.course_code, g.trimester, g.academic_year,
               g.marks, g.grade_letter, g.unit_id,
               COALESCE(
                   (SELECT credit_hours FROM units WHERE id = g.unit_id LIMIT 1),
                   (SELECT credit_hours FROM units WHERE unit_code = g.course_code LIMIT 1),
                   3
               ) AS credit_hours
        FROM grades g
        WHERE g.student_id = ?
    ";
    $params = [$studentId];

    if ($trimester !== null && $academicYear !== null) {
        $sql .= ' AND g.trimester = ? AND g.academic_year = ?';
        $params[] = $trimester;
        $params[] = $academicYear;
    }

    $sql .= ' ORDER BY g.academic_year, g.trimester, g.course_code, g.course_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function calculateGpa(array $gradesWithCredits): ?array
{
    if (empty($gradesWithCredits)) {
        return null;
    }

    $qualityPoints = 0.0;
    $totalCredits = 0.0;

    foreach ($gradesWithCredits as $grade) {
        $credits = (float) ($grade['credit_hours'] ?? 3);
        $letter = $grade['grade_letter'] ?? calculateGrade((float) ($grade['marks'] ?? 0));
        $qualityPoints += getGradePoint($letter) * $credits;
        $totalCredits += $credits;
    }

    if ($totalCredits <= 0) {
        return null;
    }

    return [
        'gpa' => round($qualityPoints / $totalCredits, 2),
        'total_credits' => $totalCredits,
        'units_count' => count($gradesWithCredits),
        'quality_points' => round($qualityPoints, 2),
    ];
}

function getStudentGpaSummary(PDO $pdo, array $student): array
{
    $studentId = (int) $student['id'];
    $trimester = $student['trimester'] ?? '';
    $academicYear = $student['academic_year'] ?? '';

    $semesterGrades = getStudentGradesWithCredits($pdo, $studentId, $trimester, $academicYear);
    $allGrades = getStudentGradesWithCredits($pdo, $studentId);

    return [
        'semester' => calculateGpa($semesterGrades),
        'cumulative' => calculateGpa($allGrades),
        'semester_label' => trim($trimester . ' (' . $academicYear . ')'),
        'semester_number' => getStudentSemesterNumber($student),
    ];
}

function getGpaClass(?float $gpa): string
{
    if ($gpa === null) {
        return 'secondary';
    }
    if ($gpa >= 3.5) {
        return 'success';
    }
    if ($gpa >= 2.5) {
        return 'info';
    }
    if ($gpa >= 1.5) {
        return 'warning';
    }

    return 'danger';
}
