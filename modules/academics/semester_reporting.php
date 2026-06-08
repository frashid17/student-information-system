<?php
$pageTitle = 'Semester Reporting';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['student']);

$pdo = getDBConnection();
$student = getLinkedStudent($pdo);

if (!$student) {
    setFlash('error', 'Your student profile is not linked to this account.');
    redirect(BASE_URL . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = reportNewSemester($pdo, $student);
    setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
    redirect(BASE_URL . '/modules/academics/semester_reporting.php');
}

$reportCheck = canReportNewSemester($pdo, $student);
$feeBalance = getStudentFeeBalance($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$registeredUnits = getStudentRegisteredUnits($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$ungraded = getUngradedRegistrations($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);

$history = [];
try {
    $histStmt = $pdo->prepare("SELECT * FROM semester_reporting WHERE student_id = ? ORDER BY reported_at DESC LIMIT 10");
    $histStmt->execute([(int) $student['id']]);
    $history = $histStmt->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Current Semester</h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($student) ?></span></div>
            <div class="detail-item"><label>Trimester</label><span><?= sanitize($student['trimester']) ?></span></div>
            <div class="detail-item"><label>Academic Year</label><span><?= sanitize($student['academic_year']) ?></span></div>
            <div class="detail-item"><label>Fee Balance</label><span><?= formatCurrency((float) $feeBalance['balance']) ?></span></div>
            <div class="detail-item"><label>Registered Units</label><span><?= count($registeredUnits) ?></span></div>
            <div class="detail-item"><label>Results Pending</label><span><?= count($ungraded) ?> unit(s)</span></div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Report for New Semester</h3></div>
    <div class="card-body">
        <p style="margin-bottom:16px;color:var(--text-muted);">
            When your semester ends, report here to advance to the next semester. Requirements:
        </p>
        <ul style="margin-bottom:20px;color:var(--text-muted);padding-left:20px;">
            <li>Zero fee balance for the current semester</li>
            <li>Results awarded for all registered units</li>
            <li>Your fees will reset to the full amount for the new semester</li>
        </ul>

        <?php if ($reportCheck['allowed']): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            You are eligible to report for <strong>Semester <?= (int) $reportCheck['next_semester'] ?></strong>
            (<?= sanitize($reportCheck['next']['trimester']) ?>, <?= sanitize($reportCheck['next']['academic_year']) ?>).
        </div>
        <form method="POST" onsubmit="return confirm('Report for the new semester? Your fee balance will reset for the new term.')">
            <button type="submit" class="btn btn-primary">Report for New Semester</button>
        </form>
        <?php else: ?>
        <div class="alert alert-error">
            <?= sanitize($reportCheck['reason']) ?>
        </div>
        <?php if (!empty($ungraded)): ?>
        <div class="table-responsive" style="margin-top:16px;">
            <table class="data-table">
                <thead><tr><th>Unit Code</th><th>Unit Name</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($ungraded as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><span class="badge badge-warning">Awaiting results</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($history)): ?>
<div class="card">
    <div class="card-header"><h3>Reporting History</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Semester Change</th>
                        <th>Reported On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= sanitize($h['from_trimester']) ?> (<?= sanitize($h['from_academic_year']) ?>)</td>
                        <td><?= sanitize($h['to_trimester']) ?> (<?= sanitize($h['to_academic_year']) ?>)</td>
                        <td>Sem <?= (int) $h['from_semester_number'] ?> → Sem <?= (int) $h['to_semester_number'] ?></td>
                        <td><?= formatDate($h['reported_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
