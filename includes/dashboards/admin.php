<div class="stats-grid">
    <div class="stat-card primary">
        <div class="label">Active Students</div>
        <div class="value"><?= number_format($stats['students']) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Active Faculty</div>
        <div class="value"><?= number_format($stats['faculty']) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label">Programs</div>
        <div class="value"><?= number_format($stats['programs']) ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Total Fees Collected</div>
        <div class="value" style="font-size:1.4rem;"><?= formatCurrency((float) $totalFees) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:24px;">
    <div class="card">
        <div class="card-header">
            <h3>Recent Registrations</h3>
            <?php if (canAccess('students')): ?>
            <a href="<?= BASE_URL ?>/modules/students/list.php" class="btn btn-secondary btn-sm">View All</a>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentStudents)): ?>
                <div class="empty-state">
                    <h4>No students registered yet</h4>
                    <p>Start by registering students in the system.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student No.</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentStudents as $s): ?>
                        <tr>
                            <td><?= sanitize($s['student_number']) ?></td>
                            <td><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><?= sanitize($s['program_name'] ?? '-') ?></td>
                            <td><span class="badge badge-success"><?= sanitize($s['status']) ?></span></td>
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
            <?php if (canAccess('communications')): ?>
            <a href="<?= BASE_URL ?>/modules/communications/announcements.php" class="btn btn-secondary btn-sm">Manage</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <h4>No announcements</h4>
                    <p>Important updates will appear here.</p>
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
