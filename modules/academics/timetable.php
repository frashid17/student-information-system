<?php
$pageTitle = 'My Timetable';
require_once __DIR__ . '/../../includes/init.php';
requireModuleAccess('timetable');

$pdo = getDBConnection();
$facultyId = requireTeachingProfile($pdo);
$term = getCurrentTeachingTerm($pdo, $facultyId);

$trimester = $_POST['trimester'] ?? $_GET['trimester'] ?? $term['trimester'];
$academicYear = $_POST['academic_year'] ?? $_GET['academic_year'] ?? $term['academic_year'];
$assignedUnits = getAssignedUnitsForTeacher($pdo, $facultyId, $trimester, $academicYear);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    if (!teacherCanAccessUnit($pdo, $facultyId, $unitId, $trimester, $academicYear)) {
        setFlash('error', 'You can only manage timetable entries for your assigned units.');
        redirect(BASE_URL . '/modules/academics/timetable.php');
    }

    if (($_POST['action'] ?? '') === 'delete' && !empty($_POST['entry_id'])) {
        $del = $pdo->prepare('DELETE FROM timetable_entries WHERE id = ? AND faculty_id = ?');
        $del->execute([(int) $_POST['entry_id'], $facultyId]);
        setFlash('success', 'Timetable entry removed.');
        redirect(BASE_URL . '/modules/academics/timetable.php?trimester=' . urlencode($trimester) . '&academic_year=' . urlencode($academicYear));
    }

    $stmt = $pdo->prepare("
        INSERT INTO timetable_entries (faculty_id, unit_id, trimester, academic_year, day_of_week, start_time, end_time, room, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $facultyId,
        $unitId,
        $trimester,
        $academicYear,
        $_POST['day_of_week'],
        $_POST['start_time'],
        $_POST['end_time'],
        trim($_POST['room'] ?? '') ?: null,
        trim($_POST['notes'] ?? '') ?: null,
    ]);
    setFlash('success', 'Timetable entry added.');
    redirect(BASE_URL . '/modules/academics/timetable.php?trimester=' . urlencode($trimester) . '&academic_year=' . urlencode($academicYear));
}

$entries = getTeacherTimetable($pdo, $facultyId, $trimester, $academicYear);
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Manage Timetable</h3></div>
    <div class="card-body">
        <form method="GET" class="form-row" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Trimester</label>
                <select name="trimester" class="form-control">
                    <?php foreach (['Trimester 1', 'Trimester 2', 'Trimester 3'] as $t): ?>
                    <option value="<?= $t ?>" <?= $trimester === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="form-control" value="<?= sanitize($academicYear) ?>">
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-secondary">Apply</button>
            </div>
        </form>

        <?php if (empty($assignedUnits)): ?>
        <div class="alert alert-info">No units assigned for this period. Contact the administrator.</div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="trimester" value="<?= sanitize($trimester) ?>">
            <input type="hidden" name="academic_year" value="<?= sanitize($academicYear) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">Select unit</option>
                        <?php foreach ($assignedUnits as $u): ?>
                        <option value="<?= (int) $u['unit_id'] ?>"><?= sanitize($u['unit_code'] . ' — ' . $u['unit_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Day *</label>
                    <select name="day_of_week" class="form-control" required>
                        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?>
                        <option value="<?= $day ?>"><?= $day ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Time *</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" class="form-control" placeholder="e.g. Lab 2">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Timetable Entry</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>My Schedule — <?= sanitize($trimester) ?>, <?= sanitize($academicYear) ?></h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($entries)): ?>
        <div class="empty-state"><h4>No timetable entries</h4><p>Add your class schedule above.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Day</th><th>Time</th><th>Unit</th><th>Room</th><th>Notes</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= sanitize($e['day_of_week']) ?></td>
                        <td><?= date('g:i A', strtotime($e['start_time'])) ?> – <?= date('g:i A', strtotime($e['end_time'])) ?></td>
                        <td><?= sanitize($e['unit_code'] . ' — ' . $e['unit_name']) ?></td>
                        <td><?= sanitize($e['room'] ?? '-') ?></td>
                        <td><?= sanitize($e['notes'] ?? '-') ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="entry_id" value="<?= (int) $e['id'] ?>">
                                <input type="hidden" name="trimester" value="<?= sanitize($trimester) ?>">
                                <input type="hidden" name="academic_year" value="<?= sanitize($academicYear) ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this entry?">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
