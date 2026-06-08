<?php
$pageTitle = 'Units Management';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$programs = $pdo->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_name")->fetchAll();
$filterProgram = isset($_GET['program']) && is_numeric($_GET['program']) ? (int) $_GET['program'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_unit';

    if ($action === 'seed_units') {
        $result = seedProgramUnits($pdo);
        setFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? $result['message'] : $result['error']);
        redirect(BASE_URL . '/modules/institution/units.php');
    }

    if ($action === 'add_unit') {
        $code = strtoupper(trim($_POST['unit_code'] ?? ''));
        $name = trim($_POST['unit_name'] ?? '');

        if ($code === '' || $name === '') {
            setFlash('error', 'Unit code and name are required.');
            redirect(BASE_URL . '/modules/institution/units.php');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO units (unit_code, unit_name, credit_hours, category, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $code,
                $name,
                (float) ($_POST['credit_hours'] ?? 3),
                $_POST['category'] ?? 'core',
                trim($_POST['description'] ?? ''),
            ]);
            $unitId = (int) $pdo->lastInsertId();

            if (!empty($_POST['program_ids']) && is_array($_POST['program_ids'])) {
                $link = $pdo->prepare("INSERT IGNORE INTO program_units (program_id, unit_id, year_of_study) VALUES (?, ?, ?)");
                foreach ($_POST['program_ids'] as $programId) {
                    $link->execute([(int) $programId, $unitId, (int) ($_POST['year_of_study'] ?? 1)]);
                }
            }

            setFlash('success', 'Unit added successfully.');
        } catch (PDOException $e) {
            setFlash('error', 'Could not add unit. The unit code may already exist.');
        }

        redirect(BASE_URL . '/modules/institution/units.php');
    }

    if ($action === 'assign_programs') {
        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $programIds = array_map('intval', $_POST['program_ids'] ?? []);

        $pdo->prepare("DELETE FROM program_units WHERE unit_id = ?")->execute([$unitId]);
        $link = $pdo->prepare("INSERT INTO program_units (program_id, unit_id, year_of_study) VALUES (?, ?, ?)");
        foreach ($programIds as $programId) {
            if ($programId > 0) {
                $link->execute([$programId, $unitId, (int) ($_POST['year_of_study'] ?? 1)]);
            }
        }

        setFlash('success', 'Program assignments updated.');
        redirect(BASE_URL . '/modules/institution/units.php' . ($filterProgram ? '?program=' . $filterProgram : ''));
    }
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE units SET is_active = NOT is_active WHERE id = ?")->execute([(int) $_GET['toggle']]);
    setFlash('success', 'Unit status updated.');
    redirect(BASE_URL . '/modules/institution/units.php' . ($filterProgram ? '?program=' . $filterProgram : ''));
}

$unitCount = 0;
$linkCount = 0;
try {
    $unitCount = (int) $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
    $linkCount = (int) $pdo->query('SELECT COUNT(*) FROM program_units')->fetchColumn();
} catch (PDOException $e) {
    // Tables not migrated yet
}

if ($filterProgram) {
    $units = getProgramUnits($pdo, $filterProgram);
} else {
    try {
        $units = $pdo->query("
            SELECT u.*, GROUP_CONCAT(DISTINCT p.program_code ORDER BY p.program_code SEPARATOR ', ') AS programs
            FROM units u
            LEFT JOIN program_units pu ON pu.unit_id = u.id
            LEFT JOIN programs p ON p.id = pu.program_id
            GROUP BY u.id
            ORDER BY u.unit_code
            LIMIT 200
        ")->fetchAll();
    } catch (PDOException $e) {
        $units = [];
    }
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($unitCount === 0 && $linkCount === 0): ?>
<div class="alert alert-error">
    Units tables are empty or not created. Import <strong>database/schooldb_update_v3.sql</strong> in phpMyAdmin, then use the seed button below.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Quick Setup</h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom:16px;color:var(--text-muted);">
            Seed <?= UNITS_PER_PROGRAM ?> units per program (<?= COMMON_UNITS_COUNT ?> shared common units + program-specific units).
            Some units are shared across all courses.
        </p>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="seed_units">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Seed 120 units per program? Run only once.')">Seed Units for All Programs</button>
        </form>
        <span style="margin-left:16px;color:var(--text-muted);">
            Catalogue: <?= number_format($unitCount) ?> units · <?= number_format($linkCount) ?> program links
        </span>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Add Unit</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_unit">
            <div class="form-row">
                <div class="form-group">
                    <label>Unit Code *</label>
                    <input type="text" name="unit_code" class="form-control" placeholder="e.g. BSCS101" required>
                </div>
                <div class="form-group">
                    <label>Unit Name *</label>
                    <input type="text" name="unit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Credit Hours</label>
                    <input type="number" name="credit_hours" class="form-control" value="3" min="1" max="12" step="0.5">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="common">Common (shared)</option>
                        <option value="core">Core</option>
                        <option value="elective">Elective</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year of Study (when assigning)</label>
                    <input type="number" name="year_of_study" class="form-control" value="1" min="1" max="6">
                </div>
                <div class="form-group">
                    <label>Assign to Programs</label>
                    <select name="program_ids[]" class="form-control" multiple size="3">
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['program_code'] . ' — ' . $p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Add Unit</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Units Catalogue</h3>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="program" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="">All units</option>
                <?php foreach ($programs as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filterProgram === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= sanitize($p['program_code']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Unit Name</th>
                        <th>Credits</th>
                        <th>Category</th>
                        <?php if (!$filterProgram): ?><th>Programs</th><?php else: ?><th>Year</th><?php endif; ?>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:24px;">No units found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($units as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize($u['category']) ?></span></td>
                        <?php if (!$filterProgram): ?>
                        <td><?= sanitize($u['programs'] ?? '-') ?></td>
                        <?php else: ?>
                        <td><?= (int) ($u['year_of_study'] ?? 1) ?></td>
                        <?php endif; ?>
                        <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="?toggle=<?= $u['id'] ?><?= $filterProgram ? '&program=' . $filterProgram : '' ?>" class="btn btn-secondary btn-sm">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$filterProgram && count($units) >= 200): ?>
        <p style="padding:12px 16px;color:var(--text-muted);font-size:0.9rem;">Showing first 200 units. Filter by program to see all units for a course.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
