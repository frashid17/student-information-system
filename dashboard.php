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
    $stats['students'] = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $gradesRecorded = (int) $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn();
    $attendanceRecorded = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    $libraryToday = (int) $pdo->query("SELECT COUNT(*) FROM library_checkins WHERE DATE(checkin_time) = CURDATE()")->fetchColumn();
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
} elseif ($role === 'faculty') {
    require __DIR__ . '/includes/dashboards/faculty.php';
} else {
    require __DIR__ . '/includes/dashboards/admin.php';
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
