<?php if (!$linkedStudent): ?>
<div class="alert alert-error">
    Your student profile is not linked to this login account. Contact the administration office.
</div>
<?php else: ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Welcome, <?= sanitize($linkedStudent['first_name']) ?></h3></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item"><label>Student Number</label><span><?= sanitize($linkedStudent['student_number']) ?></span></div>
            <div class="detail-item"><label>Program</label><span><?= sanitize($linkedStudent['program_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Campus</label><span><?= sanitize($linkedStudent['branch_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Trimester</label><span><?= sanitize($linkedStudent['trimester']) ?> (<?= sanitize($linkedStudent['academic_year']) ?>)</span></div>
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($linkedStudent) ?></span></div>
        </div>
    </div>
</div>

<?php
$semesterGpa = $studentGpa['semester']['gpa'] ?? null;
$cumulativeGpa = $studentGpa['cumulative']['gpa'] ?? null;
?>
<div class="card gpa-dashboard-card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3>Academic Performance (GPA)</h3>
        <a href="<?= BASE_URL ?>/modules/academics/grades.php?report=<?= (int) $linkedStudent['id'] ?>" class="btn btn-secondary btn-sm">Full Report</a>
    </div>
    <div class="card-body">
        <div class="gpa-dashboard-grid">
            <div class="gpa-dashboard-item">
                <div class="gpa-dashboard-label">Semester GPA</div>
                <div class="gpa-dashboard-value gpa-dashboard-value--<?= getGpaClass($semesterGpa) ?>"><?= formatGpa($semesterGpa) ?></div>
                <div class="gpa-dashboard-meta">Sem <?= (int) ($studentGpa['semester_number'] ?? 1) ?> · <?= sanitize($studentGpa['semester_label'] ?? '') ?></div>
                <?php if (!empty($studentGpa['semester'])): ?>
                <div class="gpa-dashboard-meta"><?= (int) $studentGpa['semester']['units_count'] ?> unit(s) · <?= number_format((float) $studentGpa['semester']['total_credits'], 1) ?> credits</div>
                <?php else: ?>
                <div class="gpa-dashboard-meta">No graded units this semester yet</div>
                <?php endif; ?>
            </div>
            <div class="gpa-dashboard-item">
                <div class="gpa-dashboard-label">Cumulative GPA</div>
                <div class="gpa-dashboard-value gpa-dashboard-value--<?= getGpaClass($cumulativeGpa) ?>"><?= formatGpa($cumulativeGpa) ?></div>
                <div class="gpa-dashboard-meta">All semesters combined</div>
                <?php if (!empty($studentGpa['cumulative'])): ?>
                <div class="gpa-dashboard-meta"><?= (int) $studentGpa['cumulative']['units_count'] ?> unit(s) · <?= number_format((float) $studentGpa['cumulative']['total_credits'], 1) ?> credits</div>
                <?php else: ?>
                <div class="gpa-dashboard-meta">No grades recorded yet</div>
                <?php endif; ?>
            </div>
            <div class="gpa-dashboard-item gpa-dashboard-scale">
                <div class="gpa-dashboard-label">Grading Scale (<?= GPA_SCALE_MAX ?>.0 scale)</div>
                <div class="gpa-scale-list">
                    <span>A = 4.0</span>
                    <span>B = 3.0</span>
                    <span>C = 2.0</span>
                    <span>D = 1.0</span>
                    <span>F = 0.0</span>
                </div>
                <div class="gpa-dashboard-meta">Weighted by unit credit hours</div>
            </div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="label">Total Fee</div>
        <div class="value" style="font-size:1.4rem;"><?= formatCurrency((float) ($feeBalance['total_fee'] ?? 0)) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Total Paid</div>
        <div class="value" style="font-size:1.4rem;"><?= formatCurrency((float) ($feeBalance['total_paid'] ?? 0)) ?></div>
    </div>
    <div class="stat-card <?= ($feeBalance['balance'] ?? 0) > 0 ? 'warning' : 'accent' ?>">
        <div class="label">Balance</div>
        <div class="value" style="font-size:1.4rem;"><?= formatCurrency((float) ($feeBalance['balance'] ?? 0)) ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Registered Units</div>
        <div class="value"><?= count($registeredUnits ?? []) ?> / <?= MAX_UNITS_PER_SEMESTER ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Semester GPA</div>
        <div class="value"><?= formatGpa($semesterGpa ?? null) ?></div>
    </div>
    <div class="stat-card primary">
        <div class="label">Cumulative GPA</div>
        <div class="value"><?= formatGpa($cumulativeGpa ?? null) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:24px;">
    <div class="card">
        <div class="card-header">
            <h3>My Registered Units</h3>
            <a href="<?= BASE_URL ?>/modules/academics/unit_registration.php" class="btn btn-secondary btn-sm">Manage</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($registeredUnits)): ?>
                <div class="empty-state">
                    <h4>No units registered</h4>
                    <p>Register for up to <?= MAX_UNITS_PER_SEMESTER ?> units once you have paid at least <?= UNIT_REGISTRATION_FEE_PERCENT ?>% of your fees.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Code</th><th>Unit Name</th><th>Credits</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredUnits as $u): ?>
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
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>My Grades</h3>
            <a href="<?= BASE_URL ?>/modules/academics/grades.php?report=<?= (int) $linkedStudent['id'] ?>" class="btn btn-secondary btn-sm">Full Report</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentGrades)): ?>
                <div class="empty-state">
                    <h4>No grades recorded yet</h4>
                    <p>Your results will appear here once published.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Course</th><th>Trimester</th><th>Marks</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGrades as $g): ?>
                        <tr>
                            <td><?= sanitize($g['course_name']) ?></td>
                            <td><?= sanitize($g['trimester']) ?></td>
                            <td><?= sanitize((string) $g['marks']) ?></td>
                            <td><span class="badge badge-info"><?= sanitize($g['grade_letter']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Announcements</h3>
            <a href="<?= BASE_URL ?>/modules/communications/announcements.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <h4>No announcements</h4>
                    <p>Campus updates will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $a): ?>
                <div style="padding:12px 0;border-bottom:1px solid var(--border);">
                    <strong><?= sanitize($a['title']) ?></strong>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">
                        <?= sanitize(substr($a['message'], 0, 120)) ?><?= strlen($a['message']) > 120 ? '...' : '' ?>
                    </p>
                    <small style="color:var(--text-muted);"><?= formatDate($a['created_at']) ?></small>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3>Quick Links</h3></div>
    <div class="card-body">
        <div class="btn-group">
            <a href="<?= BASE_URL ?>/modules/academics/unit_registration.php" class="btn btn-primary">Unit Registration</a>
            <a href="<?= BASE_URL ?>/modules/academics/exam_card.php" class="btn btn-secondary">Exam Card</a>
            <a href="<?= BASE_URL ?>/modules/academics/semester_reporting.php" class="btn btn-secondary">Semester Reporting</a>
            <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="btn btn-secondary">My Fee Balance</a>
            <a href="<?= BASE_URL ?>/modules/library/issue.php" class="btn btn-secondary">My Library Books</a>
            <a href="<?= BASE_URL ?>/modules/academics/grades.php?report=<?= (int) $linkedStudent['id'] ?>" class="btn btn-secondary">Report Card</a>
        </div>
    </div>
</div>

<?php endif; ?>
