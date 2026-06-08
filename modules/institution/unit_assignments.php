<?php
$pageTitle = 'Unit Assignments';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$faculty = $pdo->query("SELECT id, staff_id, first_name, last_name, department FROM faculty WHERE status = 'active' ORDER BY first_name")->fetchAll();
$units = $pdo->query('SELECT id, unit_code, unit_name FROM units WHERE is_active = 1 ORDER BY unit_code LIMIT 500')->fetchAll();

$trimester = $_POST['trimester'] ?? $_GET['trimester'] ?? 'Trimester 1';
$academicYear = $_POST['academic_year'] ?? $_GET['academic_year'] ?? '2025/2026';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove']) && is_numeric($_POST['remove'])) {
        $pdo->prepare('DELETE FROM unit_assignments WHERE id = ?')->execute([(int) $_POST['remove']]);
        setFlash('success', 'Assignment removed.');
    } else {
        $facultyId = (int) ($_POST['faculty_id'] ?? 0);
        $trimester = trim($_POST['trimester'] ?? '');
        $academicYear = trim($_POST['academic_year'] ?? '');
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $_POST['unit_ids'] ?? []))));

        if (!$facultyId || $trimester === '' || $academicYear === '') {
            setFlash('error', 'Lecturer, trimester, and academic year are required.');
            redirect(BASE_URL . '/modules/institution/unit_assignments.php?trimester=' . urlencode($trimester) . '&academic_year=' . urlencode($academicYear));
        }

        if (empty($unitIds)) {
            setFlash('error', 'Select at least one unit to assign.');
            redirect(BASE_URL . '/modules/institution/unit_assignments.php?trimester=' . urlencode($trimester) . '&academic_year=' . urlencode($academicYear));
        }

        $stmt = $pdo->prepare('
            INSERT IGNORE INTO unit_assignments (faculty_id, unit_id, trimester, academic_year)
            VALUES (?, ?, ?, ?)
        ');

        $assigned = 0;
        foreach ($unitIds as $unitId) {
            $stmt->execute([$facultyId, $unitId, $trimester, $academicYear]);
            if ($stmt->rowCount() > 0) {
                $assigned++;
            }
        }

        $skipped = count($unitIds) - $assigned;
        if ($assigned > 0) {
            $message = $assigned === 1
                ? '1 unit assigned to lecturer.'
                : "{$assigned} units assigned to lecturer.";
            if ($skipped > 0) {
                $message .= " {$skipped} unit(s) were already assigned.";
            }
            setFlash('success', $message);
        } else {
            setFlash('error', 'All selected units are already assigned to this lecturer for the chosen period.');
        }
    }
    redirect(BASE_URL . '/modules/institution/unit_assignments.php?trimester=' . urlencode($trimester) . '&academic_year=' . urlencode($academicYear));
}

$assignments = [];
if (tableExists($pdo, 'unit_assignments')) {
    $stmt = $pdo->prepare("
        SELECT ua.*, f.staff_id, f.first_name, f.last_name, u.unit_code, u.unit_name
        FROM unit_assignments ua
        JOIN faculty f ON f.id = ua.faculty_id
        JOIN units u ON u.id = ua.unit_id
        WHERE ua.trimester = ? AND ua.academic_year = ?
        ORDER BY f.first_name, u.unit_code
    ");
    $stmt->execute([$trimester, $academicYear]);
    $assignments = $stmt->fetchAll();
}

$lecturerStudentLinks = getLecturerStudentLinksForTerm($pdo, $trimester, $academicYear);

$flash = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>How student–lecturer links work</strong><br>
    You do <em>not</em> assign students to lecturers manually. When you assign units to a lecturer here,
    any student who registers for those units (same trimester and academic year) is automatically linked.
    The lecturer will see them under <strong>My Classes → My Students</strong>.
</div>

<div class="card">
    <div class="card-header"><h3>Assign Lecturer to Units</h3></div>
    <div class="card-body">
        <p style="margin-bottom:16px;color:var(--text-muted);">
            Select a lecturer and one or more units. Students who register for those units will automatically appear in the lecturer's class list.
            Staff/faculty logins must be linked to a faculty record under <a href="<?= BASE_URL ?>/modules/settings/users.php">User Accounts</a>.
        </p>
        <form method="POST" id="assignUnitsForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Lecturer *</label>
                    <select name="faculty_id" class="form-control" required>
                        <option value="">Select lecturer</option>
                        <?php foreach ($faculty as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= sanitize($f['staff_id'] . ' — ' . $f['first_name'] . ' ' . $f['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trimester *</label>
                    <select name="trimester" class="form-control" required>
                        <?php foreach (['Trimester 1', 'Trimester 2', 'Trimester 3'] as $t): ?>
                        <option value="<?= $t ?>" <?= $trimester === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= sanitize($academicYear) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Units * <small style="color:var(--text-muted);font-weight:normal;">(select one or more)</small></label>
                <input type="text" id="unitSearch" class="form-control" placeholder="Search by unit code or name..." style="margin-bottom:8px;">
                <div id="unitList" style="max-height:280px;overflow-y:auto;border:1px solid var(--border);padding:12px;border-radius:6px;background:var(--bg-secondary, #fafafa);">
                    <?php foreach ($units as $u): ?>
                    <label class="unit-option" style="display:block;margin-bottom:8px;cursor:pointer;" data-search="<?= sanitize(strtolower($u['unit_code'] . ' ' . $u['unit_name'])) ?>">
                        <input type="checkbox" name="unit_ids[]" value="<?= (int) $u['id'] ?>">
                        <?= sanitize($u['unit_code'] . ' — ' . $u['unit_name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button type="button" class="btn btn-secondary btn-sm" id="selectVisibleUnits">Select all visible</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="clearUnitSelection">Clear selection</button>
                    <span id="selectedUnitCount" style="font-size:0.85rem;color:var(--text-muted);">0 selected</span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Assign Selected Units</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Current Unit Assignments</h3>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="trimester" class="form-control" onchange="this.form.submit()">
                <?php foreach (['Trimester 1', 'Trimester 2', 'Trimester 3'] as $t): ?>
                <option value="<?= $t ?>" <?= $trimester === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="academic_year" class="form-control" value="<?= sanitize($academicYear) ?>" onchange="this.form.submit()">
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Lecturer</th><th>Unit</th><th>Trimester</th><th>Year</th><th>Students</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:24px;">No assignments for this period.</td></tr>
                    <?php else: ?>
                    <?php
                    $linkCounts = [];
                    foreach ($lecturerStudentLinks as $link) {
                        $linkCounts[(int) $link['assignment_id']] = (int) $link['student_count'];
                    }
                    ?>
                    <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?= sanitize($a['staff_id'] . ' — ' . $a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= sanitize($a['unit_code'] . ' — ' . $a['unit_name']) ?></td>
                        <td><?= sanitize($a['trimester']) ?></td>
                        <td><?= sanitize($a['academic_year']) ?></td>
                        <td><?= number_format($linkCounts[(int) $a['id']] ?? 0) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="remove" value="<?= (int) $a['id'] ?>">
                                <input type="hidden" name="trimester" value="<?= sanitize($trimester) ?>">
                                <input type="hidden" name="academic_year" value="<?= sanitize($academicYear) ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove assignment?">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Automatic Student–Lecturer Links (<?= sanitize($trimester) ?>, <?= sanitize($academicYear) ?>)</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($lecturerStudentLinks)): ?>
        <div class="empty-state" style="padding:24px;">
            <h4>No links yet</h4>
            <p style="color:var(--text-muted);">Assign units to lecturers above. Students will appear here once they register for those units.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Lecturer</th><th>Unit</th><th>Registered Students</th><th>Student Names</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lecturerStudentLinks as $link): ?>
                    <tr>
                        <td><?= sanitize($link['staff_id'] . ' — ' . $link['first_name'] . ' ' . $link['last_name']) ?></td>
                        <td><?= sanitize($link['unit_code'] . ' — ' . $link['unit_name']) ?></td>
                        <td><?= number_format((int) $link['student_count']) ?></td>
                        <td>
                            <?php if ((int) $link['student_count'] === 0): ?>
                            <span style="color:var(--text-muted);">No students registered yet</span>
                            <?php else: ?>
                            <?= sanitize($link['student_list']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const searchInput = document.getElementById('unitSearch');
    const unitOptions = Array.from(document.querySelectorAll('.unit-option'));
    const checkboxes = Array.from(document.querySelectorAll('input[name="unit_ids[]"]'));
    const countEl = document.getElementById('selectedUnitCount');
    const form = document.getElementById('assignUnitsForm');

    function updateCount() {
        const selected = checkboxes.filter(function (cb) { return cb.checked; }).length;
        countEl.textContent = selected + ' selected';
    }

    function filterUnits() {
        const query = (searchInput.value || '').trim().toLowerCase();
        unitOptions.forEach(function (option) {
            const match = !query || (option.dataset.search || '').includes(query);
            option.style.display = match ? 'block' : 'none';
        });
    }

    searchInput.addEventListener('input', filterUnits);

    document.getElementById('selectVisibleUnits').addEventListener('click', function () {
        unitOptions.forEach(function (option) {
            if (option.style.display !== 'none') {
                const cb = option.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = true;
            }
        });
        updateCount();
    });

    document.getElementById('clearUnitSelection').addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        updateCount();
    });

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    form.addEventListener('submit', function (e) {
        const selected = checkboxes.some(function (cb) { return cb.checked; });
        if (!selected) {
            e.preventDefault();
            alert('Please select at least one unit.');
        }
    });

    updateCount();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
