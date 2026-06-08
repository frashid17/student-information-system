<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/init.php';
requireLogin();

$pdo = getDBConnection();
$role = $_SESSION['user_role'];
$announcements = getAnnouncementsForUser($pdo);

$linkedStudent = null;
$linkedFaculty = null;
$feeBalance = [];
$recentGrades = [];
$registeredUnits = [];
$studentGpa = null;
$borrowedBooks = 0;
$stats = ['students' => 0, 'faculty' => 0, 'programs' => 0];
$totalFees = 0;
$recentStudents = [];
$gradesRecorded = 0;
$attendanceRecorded = 0;
$libraryToday = 0;
$staffAssignedUnits = 0;
$staffStudentCount = 0;
$staffTimetableCount = 0;
$staffPayslipCount = 0;
$staffUnitList = [];

if ($role === 'student') {
    $linkedStudent = getLinkedStudent($pdo);
    if ($linkedStudent) {
        $feeBalance = getStudentFeeBalance(
            $pdo,
            (int) $linkedStudent['id'],
            $linkedStudent['trimester'],
            $linkedStudent['academic_year']
        );
        $gStmt = $pdo->prepare("SELECT * FROM grades WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
        $gStmt->execute([(int) $linkedStudent['id']]);
        $recentGrades = $gStmt->fetchAll();

        $bStmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE student_id = ? AND status = 'issued'");
        $bStmt->execute([(int) $linkedStudent['id']]);
        $borrowedBooks = (int) $bStmt->fetchColumn();

        $registeredUnits = getStudentRegisteredUnits(
            $pdo,
            (int) $linkedStudent['id'],
            $linkedStudent['trimester'],
            $linkedStudent['academic_year']
        );

        $studentGpa = getStudentGpaSummary($pdo, $linkedStudent);
    }
} elseif ($role === 'faculty') {
    $linkedFaculty = getLinkedFaculty($pdo);
    if ($linkedFaculty) {
        $facultyId = (int) $linkedFaculty['id'];
        $term = getCurrentTeachingTerm($pdo, $facultyId);
        $staffUnitList = getAssignedUnitsForTeacher($pdo, $facultyId, $term['trimester'], $term['academic_year']);
        $staffAssignedUnits = count($staffUnitList);
        $staffStudentCount = count(getStudentsForTeacherUnits($pdo, $facultyId, $term['trimester'], $term['academic_year']));
        $staffTimetableCount = count(getTeacherTimetable($pdo, $facultyId, $term['trimester'], $term['academic_year']));
        $staffPayslipCount = count(getTeacherPayslips($pdo, $facultyId));
    }
} elseif ($role === 'staff') {
    $linkedFaculty = getLinkedFaculty($pdo);
    if ($linkedFaculty) {
        $facultyId = (int) $linkedFaculty['id'];
        $term = getCurrentTeachingTerm($pdo, $facultyId);
        $staffUnitList = getAssignedUnitsForTeacher($pdo, $facultyId, $term['trimester'], $term['academic_year']);
        $staffAssignedUnits = count($staffUnitList);
        $staffStudentCount = count(getStudentsForTeacherUnits($pdo, $facultyId, $term['trimester'], $term['academic_year']));
        $staffTimetableCount = count(getTeacherTimetable($pdo, $facultyId, $term['trimester'], $term['academic_year']));
        $staffPayslipCount = count(getTeacherPayslips($pdo, $facultyId));
    }
} else {
    $stats = [
        'students' => $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(),
        'faculty' => $pdo->query("SELECT COUNT(*) FROM faculty WHERE status = 'active'")->fetchColumn(),
        'programs' => $pdo->query("SELECT COUNT(*) FROM programs WHERE is_active = 1")->fetchColumn(),
        'books' => $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    ];
    $totalFees = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_payments")->fetchColumn();
    $recentStudents = $pdo->query("
        SELECT s.*, p.program_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        ORDER BY s.created_at DESC LIMIT 5
    ")->fetchAll();
}

$flash = getFlash();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
        <?= sanitize($flash['message']) ?>
    </div>
<?php endif; ?>

<?php
if ($role === 'student') {
    require __DIR__ . '/includes/dashboards/student.php';
} elseif ($role === 'staff') {
    require __DIR__ . '/includes/dashboards/staff.php';
} elseif ($role === 'faculty') {
    require __DIR__ . '/includes/dashboards/faculty.php';
} else {
    require __DIR__ . '/includes/dashboards/admin.php';
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
