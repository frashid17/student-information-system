<?php
$pageTitle = 'Grades / Results';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'faculty', 'student']);

$pdo = getDBConnection();
$linkedStudent = getLinkedStudent($pdo);
$canRecordGrades = canAccess('academics');
$students = $pdo->query("
    SELECT id, student_number, first_name, last_name, trimester, academic_year
    FROM students WHERE status = 'active' ORDER BY first_name
")->fetchAll();
$recordStudentId = isset($_GET['record']) && is_numeric($_GET['record']) ? (int) $_GET['record'] : null;

if ($canRecordGrades && isset($_GET['ajax']) && $_GET['ajax'] === 'student_units') {
    header('Content-Type: application/json');

    $studentId = (int) ($_GET['student_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, student_number, first_name, last_name, trimester, academic_year FROM students WHERE id = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['ok' => false, 'error' => 'Student not found.']);
        exit;
    }

    $trimester = $_GET['trimester'] ?? $student['trimester'];
    $academicYear = $_GET['academic_year'] ?? $student['academic_year'];
    $units = getStudentUnitsWithGrades($pdo, $studentId, $trimester, $academicYear);

    echo json_encode([
        'ok' => true,
        'student' => [
            'id' => (int) $student['id'],
            'student_number' => $student['student_number'],
            'name' => trim($student['first_name'] . ' ' . $student['last_name']),
            'trimester' => $trimester,
            'academic_year' => $academicYear,
        ],
        'units' => $units,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canRecordGrades) {
        setFlash('error', 'You do not have permission to record grades.');
        redirect(BASE_URL . '/modules/academics/grades.php');
    }

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $redirectUrl = BASE_URL . '/modules/academics/grades.php' . ($studentId ? '?record=' . $studentId : '');

    if (($_POST['action'] ?? '') === 'save_unit_grade') {
        $result = saveStudentUnitGrade(
            $pdo,
            $studentId,
            (int) ($_POST['unit_id'] ?? 0),
            $_POST['trimester'] ?? '',
            $_POST['academic_year'] ?? '',
            (float) ($_POST['marks'] ?? 0),
            $_POST['remarks'] ?? null
        );
        setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
        redirect($redirectUrl);
    }

    setFlash('error', 'Invalid grade submission.');
    redirect(BASE_URL . '/modules/academics/grades.php');
}

$reportStudent = null;
$reportGrades = [];
$reportGpa = null;
if (isset($_GET['report']) && is_numeric($_GET['report'])) {
    $reportId = (int) $_GET['report'];
    if (isStudentUser() && (!$linkedStudent || (int) $linkedStudent['id'] !== $reportId)) {
        setFlash('error', 'You can only view your own grades.');
        redirect(BASE_URL . '/modules/academics/grades.php' . ($linkedStudent ? '?report=' . (int) $linkedStudent['id'] : ''));
    }

    $stmt = $pdo->prepare("
        SELECT s.*, p.program_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$reportId]);
    $reportStudent = $stmt->fetch();

    if ($reportStudent) {
        $reportGrades = getStudentGradesWithCredits($pdo, $reportId);
        $reportGpa = getStudentGpaSummary($pdo, $reportStudent);
    }
} elseif (isStudentUser() && $linkedStudent) {
    redirect(BASE_URL . '/modules/academics/grades.php?report=' . (int) $linkedStudent['id']);
}

if (isStudentUser() && $linkedStudent) {
    $gStmt = $pdo->prepare("
        SELECT g.*, s.student_number, s.first_name, s.last_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        WHERE g.student_id = ?
        ORDER BY g.created_at DESC
    ");
    $gStmt->execute([(int) $linkedStudent['id']]);
    $grades = $gStmt->fetchAll();
} else {
    $grades = $pdo->query("
        SELECT g.*, s.student_number, s.first_name, s.last_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        ORDER BY g.created_at DESC LIMIT 50
    ")->fetchAll();
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($reportStudent): ?>
<div class="card">
    <div class="card-header">
        <h3>Report Card - <?= sanitize($reportStudent['student_number']) ?></h3>
        <button onclick="printSection('reportCard')" class="btn btn-primary btn-sm">Print Report Card</button>
    </div>
    <div class="card-body" id="reportCard">
        <div class="receipt-header" style="margin-bottom:24px;">
            <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--receipt">
            <p>Student Report Card</p>
        </div>
        <div class="detail-grid" style="margin-bottom:24px;">
            <div class="detail-item"><label>Student</label><span><?= sanitize($reportStudent['first_name'] . ' ' . $reportStudent['last_name']) ?></span></div>
            <div class="detail-item"><label>Student Number</label><span><?= sanitize($reportStudent['student_number']) ?></span></div>
            <div class="detail-item"><label>Program</label><span><?= sanitize($reportStudent['program_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($reportStudent) ?></span></div>
        </div>

        <div class="gpa-report-summary">
            <div class="gpa-report-box">
                <div class="gpa-report-label">Semester GPA</div>
                <div class="gpa-report-value"><?= formatGpa($reportGpa['semester']['gpa'] ?? null) ?></div>
                <div class="gpa-report-meta"><?= sanitize($reportGpa['semester_label'] ?? '') ?></div>
            </div>
            <div class="gpa-report-box">
                <div class="gpa-report-label">Cumulative GPA</div>
                <div class="gpa-report-value"><?= formatGpa($reportGpa['cumulative']['gpa'] ?? null) ?></div>
                <div class="gpa-report-meta">Overall academic average</div>
            </div>
            <div class="gpa-report-box gpa-report-box--scale">
                <div class="gpa-report-label">Scale (<?= GPA_SCALE_MAX ?>.0)</div>
                <div class="gpa-scale-list">A=4.0 · B=3.0 · C=2.0 · D=1.0 · F=0.0</div>
                <div class="gpa-report-meta">Credit-weighted</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Course</th><th>Code</th><th>Trimester</th><th>Year</th><th>Credits</th><th>Marks</th><th>Grade</th><th>Points</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reportGrades as $g): ?>
                    <?php $gp = getGradePoint($g['grade_letter'] ?? calculateGrade((float) $g['marks'])); ?>
                    <tr>
                        <td><?= sanitize($g['course_name']) ?></td>
                        <td><?= sanitize($g['course_code'] ?? '-') ?></td>
                        <td><?= sanitize($g['trimester']) ?></td>
                        <td><?= sanitize($g['academic_year']) ?></td>
                        <td><?= sanitize((string) $g['credit_hours']) ?></td>
                        <td><?= $g['marks'] ?>%</td>
                        <td><span class="badge badge-info"><?= sanitize($g['grade_letter']) ?></span></td>
                        <td><?= number_format($gp, 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($reportGpa['cumulative'])): ?>
        <div class="gpa-report-footer">
            <strong>Cumulative summary:</strong>
            <?= (int) $reportGpa['cumulative']['units_count'] ?> graded unit(s),
            <?= number_format((float) $reportGpa['cumulative']['total_credits'], 1) ?> total credits,
            <?= number_format((float) $reportGpa['cumulative']['quality_points'], 2) ?> quality points,
            GPA <strong><?= formatGpa($reportGpa['cumulative']['gpa'] ?? null) ?></strong> / <?= GPA_SCALE_MAX ?>.0
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canRecordGrades): ?>
<div class="card">
    <div class="card-header"><h3>Record Grade / Mark</h3></div>
    <div class="card-body">
        <div class="form-row" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Select Student *</label>
                <select id="gradeStudentSelect" class="form-control">
                    <option value="">Choose a student...</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"
                        data-trimester="<?= sanitize($s['trimester']) ?>"
                        data-year="<?= sanitize($s['academic_year']) ?>"
                        <?= $recordStudentId === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= sanitize($s['student_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Trimester</label>
                <select id="gradeTrimester" class="form-control">
                    <option value="Trimester 1">Trimester 1</option>
                    <option value="Trimester 2">Trimester 2</option>
                    <option value="Trimester 3">Trimester 3</option>
                </select>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" id="gradeAcademicYear" class="form-control" value="2025/2026">
            </div>
        </div>

        <div id="gradeStudentInfo" class="alert alert-info" style="display:none;margin-bottom:16px;"></div>
        <div id="gradeUnitsLoading" style="display:none;color:var(--text-muted);margin-bottom:16px;">Loading registered units...</div>
        <div id="gradeUnitsEmpty" class="empty-state" style="display:none;">
            <h4>No registered units</h4>
            <p>This student has not registered for any units in the selected trimester and academic year.</p>
        </div>

        <div id="gradeUnitsPanel" style="display:none;">
            <div class="form-section-title">Registered Units — enter marks for each unit</div>
            <div class="table-responsive">
                <table class="data-table" id="gradeUnitsTable">
                    <thead>
                        <tr>
                            <th>Unit Code</th>
                            <th>Unit Name</th>
                            <th>Credits</th>
                            <th>Current Grade</th>
                            <th>Marks (%)</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="gradeUnitsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const baseUrl = <?= json_encode(BASE_URL) ?>;
    const studentSelect = document.getElementById('gradeStudentSelect');
    const trimesterSelect = document.getElementById('gradeTrimester');
    const yearInput = document.getElementById('gradeAcademicYear');
    const infoBox = document.getElementById('gradeStudentInfo');
    const loadingEl = document.getElementById('gradeUnitsLoading');
    const emptyEl = document.getElementById('gradeUnitsEmpty');
    const panelEl = document.getElementById('gradeUnitsPanel');
    const tbody = document.getElementById('gradeUnitsBody');

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function setVisible(el, show) {
        if (el) el.style.display = show ? '' : 'none';
    }

    function loadStudentUnits() {
        const studentId = studentSelect.value;
        if (!studentId) {
            setVisible(infoBox, false);
            setVisible(loadingEl, false);
            setVisible(emptyEl, false);
            setVisible(panelEl, false);
            tbody.innerHTML = '';
            return;
        }

        setVisible(loadingEl, true);
        setVisible(emptyEl, false);
        setVisible(panelEl, false);
        tbody.innerHTML = '';
        panelEl.querySelectorAll('form[id^="unit-grade-form-"]').forEach(function(f) { f.remove(); });

        const params = new URLSearchParams({
            ajax: 'student_units',
            student_id: studentId,
            trimester: trimesterSelect.value,
            academic_year: yearInput.value.trim()
        });

        fetch(baseUrl + '/modules/academics/grades.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                setVisible(loadingEl, false);

                if (!data.ok) {
                    infoBox.textContent = data.error || 'Could not load units.';
                    setVisible(infoBox, true);
                    return;
                }

                infoBox.innerHTML = '<strong>' + escapeHtml(data.student.student_number) + '</strong> — '
                    + escapeHtml(data.student.name) + ' &nbsp;|&nbsp; '
                    + escapeHtml(data.student.trimester) + ', ' + escapeHtml(data.student.academic_year);
                setVisible(infoBox, true);

                if (!data.units.length) {
                    setVisible(emptyEl, true);
                    return;
                }

                data.units.forEach(function(unit) {
                    const formId = 'unit-grade-form-' + unit.unit_id;
                    const tr = document.createElement('tr');
                    const hasGrade = unit.grade_id !== null;
                    const gradeBadge = hasGrade
                        ? '<span class="badge badge-success">' + escapeHtml(unit.grade_letter) + ' (' + unit.marks + '%)</span>'
                        : '<span class="badge badge-warning">Pending</span>';

                    tr.innerHTML =
                        '<td><strong>' + escapeHtml(unit.unit_code) + '</strong></td>' +
                        '<td>' + escapeHtml(unit.unit_name) + '</td>' +
                        '<td>' + escapeHtml(String(unit.credit_hours)) + '</td>' +
                        '<td>' + gradeBadge + '</td>' +
                        '<td><input form="' + formId + '" type="number" name="marks" class="form-control" min="0" max="100" step="0.01" required placeholder="Marks" value="' + (hasGrade ? unit.marks : '') + '"></td>' +
                        '<td><input form="' + formId + '" type="text" name="remarks" class="form-control" placeholder="Remarks" value="' + escapeHtml(unit.remarks || '') + '"></td>' +
                        '<td><button form="' + formId + '" type="submit" class="btn btn-primary btn-sm">' + (hasGrade ? 'Update' : 'Save') + '</button></td>';

                    const form = document.createElement('form');
                    form.id = formId;
                    form.method = 'POST';
                    form.style.display = 'none';
                    form.innerHTML =
                        '<input type="hidden" name="action" value="save_unit_grade">' +
                        '<input type="hidden" name="student_id" value="' + studentId + '">' +
                        '<input type="hidden" name="unit_id" value="' + unit.unit_id + '">' +
                        '<input type="hidden" name="trimester" value="' + escapeHtml(trimesterSelect.value) + '">' +
                        '<input type="hidden" name="academic_year" value="' + escapeHtml(yearInput.value.trim()) + '">';

                    tbody.appendChild(tr);
                    panelEl.appendChild(form);
                });

                setVisible(panelEl, true);
            })
            .catch(function() {
                setVisible(loadingEl, false);
                infoBox.textContent = 'Failed to load registered units.';
                setVisible(infoBox, true);
            });
    }

    studentSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
            trimesterSelect.value = opt.dataset.trimester || 'Trimester 1';
            yearInput.value = opt.dataset.year || '2025/2026';
        }
        loadStudentUnits();
    });

    trimesterSelect.addEventListener('change', loadStudentUnits);
    yearInput.addEventListener('change', loadStudentUnits);

    if (studentSelect.value) {
        loadStudentUnits();
    }
})();
</script>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?= isStudentUser() ? 'My Grades' : 'Recent Grades' ?></h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if (!isStudentUser()): ?><th>Student</th><?php endif; ?>
                        <th>Course</th>
                        <th>Code</th>
                        <th>Marks</th>
                        <th>Grade</th>
                        <th>Trimester</th>
                        <?php if (!isStudentUser()): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $g): ?>
                    <tr>
                        <?php if (!isStudentUser()): ?>
                        <td><?= sanitize($g['student_number'] . ' - ' . $g['first_name']) ?></td>
                        <?php endif; ?>
                        <td><?= sanitize($g['course_name']) ?></td>
                        <td><?= sanitize($g['course_code'] ?? '-') ?></td>
                        <td><?= $g['marks'] ?>%</td>
                        <td><span class="badge badge-info"><?= sanitize($g['grade_letter']) ?></span></td>
                        <td><?= sanitize($g['trimester']) ?></td>
                        <?php if (!isStudentUser()): ?>
                        <td>
                            <a href="?report=<?= $g['student_id'] ?>" class="btn btn-secondary btn-sm">Report</a>
                            <a href="?record=<?= $g['student_id'] ?>" class="btn btn-primary btn-sm">Add Grade</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
