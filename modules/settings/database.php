<?php
$pageTitle = 'Database Setup';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin']);

$pdo = getDBConnection();
$settings = [];
$rows = $pdo->query("SELECT * FROM system_settings")->fetchAll();
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$connected = false;
$connectionMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['db_host', 'db_port', 'db_name', 'institution_name', 'institution_short'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $_POST[$key], $_POST[$key]]);
            $settings[$key] = $_POST[$key];
        }
    }
    setFlash('success', 'Settings saved. Update config/database.php to apply connection changes.');
    redirect(BASE_URL . '/modules/settings/database.php');
}

if (isset($_GET['test'])) {
    try {
        $testDsn = 'mysql:host=' . ($settings['db_host'] ?? DB_HOST) . ';port=' . ($settings['db_port'] ?? DB_PORT) . ';dbname=' . ($settings['db_name'] ?? DB_NAME);
        $testPdo = new PDO($testDsn, DB_USER, DB_PASS);
        $connected = true;
        $connectionMessage = 'Database connection successful.';
    } catch (PDOException $e) {
        $connectionMessage = 'Connection failed: ' . $e->getMessage();
    }
}

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($connectionMessage): ?>
    <div class="alert alert-<?= $connected ? 'success' : 'error' ?>"><?= sanitize($connectionMessage) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Database Configuration</h3></div>
    <div class="card-body">
        <div class="alert alert-info">
            Current connection uses <strong>config/database.php</strong>. Settings below are stored in the database for reference.
            To change the live connection, edit <code>config/database.php</code> directly.
        </div>
        <form method="POST">
            <div class="form-section-title">Database Credentials</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Host Address</label>
                    <input type="text" name="db_host" class="form-control" value="<?= sanitize($settings['db_host'] ?? '127.0.0.1') ?>">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="db_port" class="form-control" value="<?= sanitize($settings['db_port'] ?? '3306') ?>">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="<?= sanitize($settings['db_name'] ?? 'schooldb') ?>">
                </div>
            </div>
            <div class="form-section-title">Institution Settings</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Institution Name</label>
                    <input type="text" name="institution_name" class="form-control" value="<?= sanitize($settings['institution_name'] ?? APP_INSTITUTION) ?>">
                </div>
                <div class="form-group">
                    <label>Short Name</label>
                    <input type="text" name="institution_short" class="form-control" value="<?= sanitize($settings['institution_short'] ?? 'KeMU') ?>">
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="?test=1" class="btn btn-secondary">Test Connection</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Database Tables (<?= count($tables) ?>)</h3></div>
    <div class="card-body">
        <p style="margin-bottom:16px;color:var(--text-muted);">These tables should exist after importing <strong>database/schooldb.sql</strong> into phpMyAdmin:</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
            <?php
            $expectedTables = [
                'users', 'campus_branches', 'programs', 'students', 'faculty',
                'fee_structures', 'fee_payments', 'payroll', 'payslips',
                'books', 'library_checkins', 'book_issues', 'grades', 'attendance',
                'announcements', 'system_settings', 'registration_log'
            ];
            foreach ($expectedTables as $table):
                $exists = in_array($table, $tables);
            ?>
            <div style="padding:12px 16px;border-radius:8px;border:1px solid var(--border);background:<?= $exists ? '#d1fae5' : '#fee2e2' ?>;">
                <strong><?= $table ?></strong>
                <div style="font-size:0.8rem;margin-top:4px;"><?= $exists ? 'Installed' : 'Missing' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
