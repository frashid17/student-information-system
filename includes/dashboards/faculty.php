<?php if (!$linkedFaculty): ?>
<div class="alert alert-error">
    Your faculty profile is not linked to this login account. Contact the administration office.
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
        <div class="label">Active Students</div>
        <div class="value"><?= number_format($stats['students']) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Grades Recorded</div>
        <div class="value"><?= number_format($gradesRecorded) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Attendance Entries</div>
        <div class="value"><?= number_format($attendanceRecorded) ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Library Check-ins Today</div>
        <div class="value"><?= number_format($libraryToday) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:24px;">
    <div class="card">
        <div class="card-header"><h3>Teaching Tools</h3></div>
        <div class="card-body">
            <div class="btn-group">
                <a href="<?= BASE_URL ?>/modules/academics/grades.php" class="btn btn-primary">Record Grades</a>
                <a href="<?= BASE_URL ?>/modules/academics/attendance.php" class="btn btn-secondary">Take Attendance</a>
                <a href="<?= BASE_URL ?>/modules/library/checkin.php" class="btn btn-secondary">Library Check-in</a>
            </div>
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

<?php endif; ?>
