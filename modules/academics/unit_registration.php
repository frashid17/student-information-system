<?php
$pageTitle = 'Unit Registration';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['student']);

$pdo = getDBConnection();
$student = getLinkedStudent($pdo);

if (!$student) {
    setFlash('error', 'Your student profile is not linked to this account.');
    redirect(BASE_URL . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitIds = $_POST['unit_ids'] ?? [];
    $result = registerStudentUnits($pdo, $student, is_array($unitIds) ? $unitIds : []);
    setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
    redirect(BASE_URL . '/modules/academics/unit_registration.php');
}

$feeCheck = canRegisterForUnits($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$feeBalance = getStudentFeeBalance($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$paymentPercent = getFeePaymentPercent($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$registeredUnits = getStudentRegisteredUnits($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$availableUnits = getAvailableUnitsForStudent($pdo, $student);
$remainingSlots = getRemainingUnitSlots($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$atMaxUnits = count($registeredUnits) >= MAX_UNITS_PER_SEMESTER;
$feeEligible = $paymentPercent >= UNIT_REGISTRATION_FEE_PERCENT;
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Registration Status</h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Student Number</label><span><?= sanitize($student['student_number']) ?></span></div>
            <div class="detail-item"><label>Program</label><span><?= sanitize($student['program_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($student) ?></span></div>
            <div class="detail-item"><label>Trimester / Year</label><span><?= sanitize($student['trimester']) ?> (<?= sanitize($student['academic_year']) ?>)</span></div>
            <div class="detail-item"><label>Fee Paid</label><span><?= number_format($paymentPercent, 1) ?>% of <?= formatCurrency((float) $feeBalance['total_fee']) ?></span></div>
            <div class="detail-item"><label>Units This Semester</label><span><?= count($registeredUnits) ?> / <?= MAX_UNITS_PER_SEMESTER ?></span></div>
            <div class="detail-item"><label>Registration</label>
                <?php if ($atMaxUnits): ?>
                <span class="badge badge-success">Fully registered (<?= MAX_UNITS_PER_SEMESTER ?>/<?= MAX_UNITS_PER_SEMESTER ?>)</span>
                <?php elseif ($feeEligible): ?>
                <span class="badge badge-success">Eligible — <?= $remainingSlots ?> slot(s) left</span>
                <?php else: ?>
                <span class="badge badge-warning">Pay ≥<?= UNIT_REGISTRATION_FEE_PERCENT ?>% to register</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($atMaxUnits): ?>
        <div class="alert alert-success" style="margin-top:16px;margin-bottom:0;">
            You have registered all <?= MAX_UNITS_PER_SEMESTER ?> units for this semester.
            <?php if ((float) $feeBalance['balance'] > 0): ?>
            Clear your remaining fee balance to print your exam card.
            <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="btn btn-secondary btn-sm" style="margin-left:12px;">View Fee Balance</a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/modules/academics/exam_card.php" class="btn btn-secondary btn-sm" style="margin-left:12px;">View Exam Card</a>
            <?php endif; ?>
        </div>
        <?php elseif (!$feeEligible): ?>
        <div class="alert alert-error" style="margin-top:16px;margin-bottom:0;">
            <?= sanitize($feeCheck['reason']) ?>
            <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="btn btn-secondary btn-sm" style="margin-left:12px;">View Fee Balance</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>My Registered Units (<?= count($registeredUnits) ?> / <?= MAX_UNITS_PER_SEMESTER ?>)</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($registeredUnits)): ?>
        <div class="empty-state">
            <h4>No units registered</h4>
            <p>Select units below once you have paid at least <?= UNIT_REGISTRATION_FEE_PERCENT ?>% of your fees. Maximum <?= MAX_UNITS_PER_SEMESTER ?> units per semester.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Code</th><th>Unit Name</th><th>Credits</th><th>Category</th><th>Registered</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registeredUnits as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                        <td><?= sanitize($u['category']) ?></td>
                        <td><?= formatDate($u['registered_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($feeCheck['allowed'] && !empty($availableUnits) && $remainingSlots > 0): ?>
<div class="card">
    <div class="card-header"><h3>Register for Units</h3></div>
    <div class="card-body">
        <form method="POST">
            <p style="margin-bottom:16px;color:var(--text-muted);">
                You can register for <strong><?= $remainingSlots ?></strong> more unit(s) this semester
                (<?= count($registeredUnits) ?> of <?= MAX_UNITS_PER_SEMESTER ?> already registered).
            </p>
            <div class="table-responsive" style="max-height:420px;overflow:auto;margin-bottom:16px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAllUnits"></th>
                            <th>Code</th>
                            <th>Unit Name</th>
                            <th>Credits</th>
                            <th>Year</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableUnits as $u): ?>
                        <tr>
                            <td><input type="checkbox" name="unit_ids[]" value="<?= $u['id'] ?>" class="unit-checkbox"></td>
                            <td><?= sanitize($u['unit_code']) ?></td>
                            <td><?= sanitize($u['unit_name']) ?></td>
                            <td><?= sanitize((string) $u['credit_hours']) ?></td>
                            <td><?= (int) ($u['year_of_study'] ?? 1) ?></td>
                            <td><?= sanitize($u['category']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Register Selected Units</button>
        </form>
    </div>
</div>
<script>
(function() {
    const maxSelect = <?= (int) $remainingSlots ?>;
    const checkboxes = () => Array.from(document.querySelectorAll('.unit-checkbox'));
    const selectAll = document.getElementById('selectAllUnits');

    function enforceLimit(changed) {
        const checked = checkboxes().filter(cb => cb.checked);
        if (checked.length > maxSelect) {
            changed.checked = false;
            alert('You can only register up to ' + maxSelect + ' unit(s) this semester.');
        }
        if (selectAll) {
            selectAll.checked = checked.length === maxSelect && maxSelect > 0;
        }
    }

    checkboxes().forEach(cb => cb.addEventListener('change', () => enforceLimit(cb)));

    selectAll?.addEventListener('change', function() {
        checkboxes().forEach(cb => cb.checked = false);
        if (this.checked) {
            checkboxes().slice(0, maxSelect).forEach(cb => cb.checked = true);
        }
    });
})();
</script>
<?php elseif ($feeCheck['allowed'] && $remainingSlots <= 0): ?>
<div class="alert alert-success">You have registered the maximum of <?= MAX_UNITS_PER_SEMESTER ?> units for this semester.</div>
<?php elseif ($feeCheck['allowed']): ?>
<div class="alert alert-success">You have registered for all available units this semester.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
