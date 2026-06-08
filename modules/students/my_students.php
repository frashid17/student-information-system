<?php
$pageTitle = 'My Students';
require_once __DIR__ . '/../../includes/init.php';
requireModuleAccess('students_scoped');

$pdo = getDBConnection();
$facultyId = requireTeachingProfile($pdo);
$linkedFaculty = getLinkedFaculty($pdo);
$term = getCurrentTeachingTerm($pdo, $facultyId);

$trimester = $_GET['trimester'] ?? $_POST['trimester'] ?? $term['trimester'];
$academicYear = $_GET['academic_year'] ?? $_POST['academic_year'] ?? $term['academic_year'];

$assignedUnits = getAssignedUnitsForTeacher($pdo, $facultyId, $trimester, $academicYear);
$students = getStudentsForTeacherUnits($pdo, $facultyId, $trimester, $academicYear);

$viewStudent = null;
$viewUnits = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    if (teacherCanAccessStudent($pdo, $facultyId, $viewId, $trimester, $academicYear)) {
        $stmt = $pdo->prepare("
            SELECT s.*, p.program_name, c.branch_name
            FROM students s
            LEFT JOIN programs p ON s.program_id = p.id
            LEFT JOIN campus_branches c ON s.campus_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$viewId]);
        $viewStudent = $stmt->fetch();

        if ($viewStudent && !empty($assignedUnits)) {
            $unitIds = getAssignedUnitIdsForTeacher($pdo, $facultyId, $trimester, $academicYear);
            $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
            $params = array_merge([$viewId], $unitIds, [$trimester, $academicYear]);
            $uStmt = $pdo->prepare("
                SELECT u.unit_code, u.unit_name, u.credit_hours
                FROM student_unit_registrations sur
                JOIN units u ON u.id = sur.unit_id
                WHERE sur.student_id = ? AND sur.unit_id IN ($placeholders)
                  AND sur.trimester = ? AND sur.academic_year = ? AND sur.status = 'registered'
                ORDER BY u.unit_code
            ");
            $uStmt->execute($params);
            $viewUnits = $uStmt->fetchAll();
        }
    } else {
        setFlash('error', 'You can only view students enrolled in your assigned units.');
        redirect(BASE_URL . '/modules/students/my_students.php');
    }
}

$flash = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>My Teaching Assignment</h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Lecturer</label><span><?= sanitize($linkedFaculty['first_name'] . ' ' . $linkedFaculty['last_name']) ?></span></div>
            <div class="detail-item"><label>Staff ID</label><span><?= sanitize($linkedFaculty['staff_id']) ?></span></div>
            <div class="detail-item"><label>Department</label><span><?= sanitize($linkedFaculty['department'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Assigned Units</label><span><?= count($assignedUnits) ?></span></div>
        </div>
        <form method="GET" class="form-row" style="margin-top:16px;">
            <div class="form-group">
                <label>Trimester</label>
                <select name="trimester" class="form-control" onchange="this.form.submit()">
                    <?php foreach (['Trimester 1', 'Trimester 2', 'Trimester 3'] as $t): ?>
                    <option value="<?= $t ?>" <?= $trimester === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="form-control" value="<?= sanitize($academicYear) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($viewStudent): ?>
<div class="card">
    <div class="card-header">
        <h3><?= sanitize($viewStudent['student_number']) ?> — Student Profile</h3>
        <a href="<?= BASE_URL ?>/modules/students/my_students.php?trimester=<?= urlencode($trimester) ?>&academic_year=<?= urlencode($academicYear) ?>" class="btn btn-secondary btn-sm">Back</a>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Name</label><span><?= sanitize($viewStudent['first_name'] . ' ' . $viewStudent['last_name']) ?></span></div>
            <div class="detail-item"><label>Program</label><span><?= sanitize($viewStudent['program_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Campus</label><span><?= sanitize($viewStudent['branch_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($viewStudent) ?></span></div>
        </div>
        <div class="form-section-title">Your Units Enrolled by This Student</div>
        <?php if (empty($viewUnits)): ?>
        <p style="color:var(--text-muted);">No matching unit enrolments.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Unit</th><th>Credits</th></tr></thead>
                <tbody>
                    <?php foreach ($viewUnits as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="btn-group" style="margin-top:16px;">
            <a href="<?= BASE_URL ?>/modules/academics/grades.php?record=<?= (int) $viewStudent['id'] ?>" class="btn btn-primary btn-sm">Record Grades</a>
            <a href="<?= BASE_URL ?>/modules/academics/attendance.php?student=<?= (int) $viewStudent['id'] ?>" class="btn btn-secondary btn-sm">Attendance</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>My Units (<?= sanitize($trimester) ?>, <?= sanitize($academicYear) ?>)</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($assignedUnits)): ?>
        <div class="empty-state">
            <h4>No units assigned</h4>
            <p>The administrator has not assigned any units to you for this period yet.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Unit Name</th><th>Credits</th><th>Program</th></tr></thead>
                <tbody>
                    <?php foreach ($assignedUnits as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                        <td><?= sanitize($u['program_name'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Students in My Units (<?= count($students) ?>)</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($students)): ?>
        <div class="empty-state">
            <h4>No students found</h4>
            <p>Students appear here once they register for your assigned units.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Student No.</th><th>Name</th><th>Program</th><th>Your Units</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= sanitize($s['student_number']) ?></td>
                        <td><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= sanitize($s['program_name'] ?? '-') ?></td>
                        <td><?= sanitize($s['enrolled_units']) ?></td>
                        <td><a href="?view=<?= (int) $s['id'] ?>&trimester=<?= urlencode($trimester) ?>&academic_year=<?= urlencode($academicYear) ?>" class="btn btn-secondary btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
