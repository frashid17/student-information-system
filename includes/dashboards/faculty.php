<?php if (!$linkedFaculty): ?>
<div class="alert alert-error">
    Your faculty profile is not linked to this login account. Contact the administration office to enable teaching access, payslips, and student lists.
</div>
<?php else: ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Welcome, <?= sanitize($linkedFaculty['first_name']) ?></h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Staff ID</label><span><?= sanitize($linkedFaculty['staff_id']) ?></span></div>
            <div class="detail-item"><label>Department</label><span><?= sanitize($linkedFaculty['department'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Campus</label><span><?= sanitize($linkedFaculty['branch_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Type</label><span><?= sanitize(ucfirst(str_replace('_', ' ', $linkedFaculty['faculty_type']))) ?></span></div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="label">My Units</div>
        <div class="value"><?= number_format($staffAssignedUnits ?? 0) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">My Students</div>
        <div class="value"><?= number_format($staffStudentCount ?? 0) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Timetable Entries</div>
        <div class="value"><?= number_format($staffTimetableCount ?? 0) ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Pay Slips</div>
        <div class="value"><?= number_format($staffPayslipCount ?? 0) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:24px;">
    <div class="card">
        <div class="card-header"><h3>Teaching</h3></div>
        <div class="card-body">
            <p style="color:var(--text-muted);margin-bottom:16px;font-size:0.9rem;">
                View students in your assigned units, record grades, take attendance, and manage your class timetable.
            </p>
            <div class="btn-group">
                <a href="<?= BASE_URL ?>/modules/students/my_students.php" class="btn btn-primary">My Students</a>
                <a href="<?= BASE_URL ?>/modules/academics/grades.php" class="btn btn-secondary">Record Grades</a>
                <a href="<?= BASE_URL ?>/modules/academics/attendance.php" class="btn btn-secondary">Attendance</a>
                <a href="<?= BASE_URL ?>/modules/academics/timetable.php" class="btn btn-secondary">Timetable</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Payroll &amp; Updates</h3></div>
        <div class="card-body">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= BASE_URL ?>/modules/faculty/my_payslip.php" class="btn btn-primary">My Pay Slip</a>
                <a href="<?= BASE_URL ?>/modules/communications/announcements.php" class="btn btn-secondary">Announcements</a>
            </div>
            <?php if (empty($announcements)): ?>
                <p style="color:var(--text-muted);font-size:0.9rem;">No announcements at this time.</p>
            <?php else: ?>
                <?php foreach (array_slice($announcements, 0, 3) as $a): ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <strong><?= sanitize($a['title']) ?></strong>
                    <small style="display:block;color:var(--text-muted);margin-top:4px;"><?= formatDate($a['created_at']) ?></small>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($staffUnitList)): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3>My Assigned Units</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Unit</th><th>Credits</th></tr></thead>
                <tbody>
                    <?php foreach ($staffUnitList as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
