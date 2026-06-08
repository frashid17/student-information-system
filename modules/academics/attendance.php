<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'faculty']);

$pdo = getDBConnection();
$students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_attendance'])) {
        foreach ($_POST['attendance'] as $studentId => $status) {
            $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND attendance_date = ? AND course_name = ?");
            $check->execute([$studentId, $_POST['attendance_date'], $_POST['course_name']]);

            if ($check->fetch()) {
                $pdo->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND attendance_date = ? AND course_name = ?")
                    ->execute([$status, $studentId, $_POST['attendance_date'], $_POST['course_name']]);
            } else {
                $pdo->prepare("INSERT INTO attendance (student_id, course_name, attendance_date, status, recorded_by) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$studentId, $_POST['course_name'], $_POST['attendance_date'], $status, $_SESSION['user_id']]);
            }
        }
        setFlash('success', 'Attendance recorded for all students.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_name, attendance_date, status, recorded_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['student_id'], trim($_POST['course_name']),
            $_POST['attendance_date'], $_POST['status'], $_SESSION['user_id']
        ]);
        setFlash('success', 'Attendance recorded.');
    }
    redirect(BASE_URL . '/modules/academics/attendance.php');
}

$reportStudent = null;
$reportAttendance = [];
if (isset($_GET['report']) && is_numeric($_GET['report'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$_GET['report']]);
    $reportStudent = $stmt->fetch();

    if ($reportStudent) {
        $aStmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC");
        $aStmt->execute([$_GET['report']]);
        $reportAttendance = $aStmt->fetchAll();
    }
}

$recent = $pdo->query("
    SELECT a.*, s.student_number, s.first_name, s.last_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    ORDER BY a.attendance_date DESC, a.created_at DESC LIMIT 50
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($reportStudent): ?>
<?php
    $present = count(array_filter($reportAttendance, fn($a) => $a['status'] === 'present'));
    $total = count($reportAttendance);
    $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
?>
<div class="card">
    <div class="card-header">
        <h3>Attendance Report - <?= sanitize($reportStudent['student_number']) ?></h3>
        <button onclick="printSection('attendanceReport')" class="btn btn-primary btn-sm">Print</button>
    </div>
    <div class="card-body" id="attendanceReport">
        <div class="detail-grid" style="margin-bottom:20px;">
            <div class="detail-item"><label>Student</label><span><?= sanitize($reportStudent['first_name'] . ' ' . $reportStudent['last_name']) ?></span></div>
            <div class="detail-item"><label>Attendance Rate</label><span><?= $rate ?>%</span></div>
            <div class="detail-item"><label>Present</label><span><?= $present ?> / <?= $total ?></span></div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Course</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($reportAttendance as $a): ?>
                    <tr>
                        <td><?= formatDate($a['attendance_date']) ?></td>
                        <td><?= sanitize($a['course_name'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $a['status'] === 'present' ? 'success' : ($a['status'] === 'late' ? 'warning' : 'danger') ?>"><?= sanitize($a['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Bulk Attendance Marking</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="bulk_attendance" value="1">
            <div class="form-row">
                <div class="form-group">
                    <label>Course Name *</label>
                    <input type="text" name="course_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="table-responsive" style="margin-top:16px;">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= sanitize($s['student_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td>
                                <select name="attendance[<?= $s['id'] ?>]" class="form-control" style="max-width:150px;">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Save Attendance</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Recent Attendance Records</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Course</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $a): ?>
                    <tr>
                        <td><?= sanitize($a['student_number'] . ' - ' . $a['first_name']) ?></td>
                        <td><?= sanitize($a['course_name'] ?? '-') ?></td>
                        <td><?= formatDate($a['attendance_date']) ?></td>
                        <td><span class="badge badge-<?= $a['status'] === 'present' ? 'success' : ($a['status'] === 'late' ? 'warning' : 'danger') ?>"><?= sanitize($a['status']) ?></span></td>
                        <td><a href="?report=<?= $a['student_id'] ?>" class="btn btn-secondary btn-sm">Full Report</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
