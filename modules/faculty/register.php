<?php
$pageTitle = 'Faculty Registration';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId = generateStaffId($pdo);

    $stmt = $pdo->prepare("INSERT INTO faculty (staff_id, first_name, last_name, gender, email, phone, faculty_type, department, campus_id, hire_date, kin_name, kin_relationship, kin_phone, kin_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $staffId,
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        $_POST['gender'],
        trim($_POST['email'] ?? ''),
        trim($_POST['phone'] ?? ''),
        $_POST['faculty_type'],
        trim($_POST['department'] ?? ''),
        $_POST['campus_id'] ?: null,
        $_POST['hire_date'] ?: date('Y-m-d'),
        trim($_POST['kin_name'] ?? ''),
        trim($_POST['kin_relationship'] ?? ''),
        trim($_POST['kin_phone'] ?? ''),
        trim($_POST['kin_address'] ?? ''),
    ]);

    $facultyId = $pdo->lastInsertId();

    if (!empty($_POST['create_login'])) {
        $username = strtolower($staffId);
        $loginPassword = $_POST['login_password'] ?: 'faculty123';
        $fullName = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
        $result = createUserAccount(
            $pdo,
            $username,
            $loginPassword,
            'faculty',
            $fullName,
            trim($_POST['email'] ?? '') ?: null,
            (int) $facultyId
        );
        if ($result === true) {
            setFlash('success', "Faculty registered. Staff ID: $staffId. Login: Faculty / $username / $loginPassword");
        } else {
            setFlash('success', "Faculty registered. Staff ID: $staffId. Login not created: $result");
        }
    } else {
        setFlash('success', "Faculty registered. Staff ID: $staffId");
    }
    redirect(BASE_URL . '/modules/faculty/register.php');
}

$faculty = $pdo->query("
    SELECT f.*, c.branch_name FROM faculty f
    LEFT JOIN campus_branches c ON f.campus_id = c.id
    ORDER BY f.created_at DESC
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Register Faculty / Staff</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-section-title">Staff Details</div>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Faculty Type *</label>
                    <select name="faculty_type" class="form-control" required>
                        <option value="teaching">Teaching</option>
                        <option value="non_teaching">Non-Teaching</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" class="form-control">
                </div>
                <div class="form-group">
                    <label>Campus</label>
                    <select name="campus_id" class="form-control">
                        <option value="">Select Campus</option>
                        <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-section-title">Next of Kin (Emergency Contact)</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="kin_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="kin_relationship" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="kin_phone" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="kin_address" class="form-control">
            </div>

            <div class="form-section-title">System Access</div>
            <div class="form-row">
                <div class="form-group">
                    <label><input type="checkbox" name="create_login" value="1" checked> Create faculty login account</label>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Username will be the staff ID.</p>
                </div>
                <div class="form-group">
                    <label>Login Password</label>
                    <input type="password" name="login_password" class="form-control" placeholder="Default: faculty123">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Register Faculty</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Registered Faculty</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Campus</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faculty as $f): ?>
                    <tr>
                        <td><?= sanitize($f['staff_id']) ?></td>
                        <td><?= sanitize($f['first_name'] . ' ' . $f['last_name']) ?></td>
                        <td><?= sanitize(ucfirst(str_replace('_', ' ', $f['faculty_type']))) ?></td>
                        <td><?= sanitize($f['department'] ?? '-') ?></td>
                        <td><?= sanitize($f['branch_name'] ?? '-') ?></td>
                        <td><span class="badge badge-success"><?= sanitize($f['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
