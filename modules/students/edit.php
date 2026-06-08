<?php
$pageTitle = 'Edit Student';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect(BASE_URL . '/modules/students/list.php');
}

$programs = $pdo->query("SELECT * FROM programs WHERE is_active = 1 ORDER BY program_name")->fetchAll();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$loginStmt = $pdo->prepare("SELECT username, is_active FROM users WHERE related_id = ? AND role = 'student'");
$loginStmt->execute([$id]);
$studentLogin = $loginStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldTrimester = $student['trimester'];
    $oldYear = $student['academic_year'];

    $update = $pdo->prepare("UPDATE students SET
        first_name=?, last_name=?, middle_name=?, gender=?, date_of_birth=?,
        email=?, phone=?, address=?, national_id=?, program_id=?, campus_id=?,
        trimester=?, academic_year=?, enrollment_date=?, status=?,
        kin_name=?, kin_relationship=?, kin_phone=?, kin_address=?
        WHERE id=?");

    $update->execute([
        trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['middle_name'] ?? ''),
        $_POST['gender'], $_POST['date_of_birth'] ?: null,
        trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''),
        trim($_POST['national_id'] ?? ''), $_POST['program_id'] ?: null, $_POST['campus_id'] ?: null,
        $_POST['trimester'], $_POST['academic_year'], $_POST['enrollment_date'] ?: null,
        $_POST['status'],
        trim($_POST['kin_name'] ?? ''), trim($_POST['kin_relationship'] ?? ''),
        trim($_POST['kin_phone'] ?? ''), trim($_POST['kin_address'] ?? ''),
        $id
    ]);

    if ($oldTrimester !== $_POST['trimester'] || $oldYear !== $_POST['academic_year']) {
        completeStudentRegistrationsForTerm($pdo, (int) $id, $oldTrimester, $oldYear);
        ensureFeeStructureForTerm(
            $pdo,
            (int) ($_POST['program_id'] ?: $student['program_id']),
            $_POST['trimester'],
            $_POST['academic_year'],
            $oldTrimester,
            $oldYear
        );
    }

    $message = 'Student updated successfully.';
    if (!empty($_POST['create_login']) && !$studentLogin) {
        $loginPassword = $_POST['login_password'] ?: 'student123';
        $fullName = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
        $result = createUserAccount(
            $pdo,
            $student['student_number'],
            $loginPassword,
            'student',
            $fullName,
            trim($_POST['email'] ?? '') ?: null,
            (int) $id
        );
        if ($result === true) {
            $message .= " Login created: Student / {$student['student_number']} / $loginPassword";
        } else {
            $message .= ' Login not created: ' . $result;
        }
    }

    setFlash('success', $message);
    redirect(BASE_URL . '/modules/students/list.php?view=' . $id);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Edit Student - <?= sanitize($student['student_number']) ?></h3>
        <a href="<?= BASE_URL ?>/modules/students/list.php" class="btn btn-secondary btn-sm">Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-section-title">Personal Details</div>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="<?= sanitize($student['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="<?= sanitize($student['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= sanitize($student['middle_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $student['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= $student['date_of_birth'] ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['active','inactive','graduated','suspended'] as $st): ?>
                        <option value="<?= $st ?>" <?= $student['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($student['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($student['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>National ID</label>
                    <input type="text" name="national_id" class="form-control" value="<?= sanitize($student['national_id'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="<?= sanitize($student['address'] ?? '') ?>">
            </div>

            <div class="form-section-title">Academic Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Program</label>
                    <select name="program_id" class="form-control">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $student['program_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Campus</label>
                    <select name="campus_id" class="form-control">
                        <option value="">Select Campus</option>
                        <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $student['campus_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" class="form-control" value="<?= $student['enrollment_date'] ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Trimester</label>
                    <select name="trimester" class="form-control">
                        <?php foreach (['Trimester 1','Trimester 2','Trimester 3'] as $t): ?>
                        <option value="<?= $t ?>" <?= $student['trimester'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= sanitize($student['academic_year']) ?>">
                </div>
            </div>

            <div class="form-section-title">Next of Kin</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="kin_name" class="form-control" value="<?= sanitize($student['kin_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="kin_relationship" class="form-control" value="<?= sanitize($student['kin_relationship'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="kin_phone" class="form-control" value="<?= sanitize($student['kin_phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="kin_address" class="form-control" value="<?= sanitize($student['kin_address'] ?? '') ?>">
            </div>

            <div class="form-section-title">Student Login</div>
            <?php if ($studentLogin): ?>
                <div class="alert alert-info">
                    Portal login exists: <strong><?= sanitize($studentLogin['username']) ?></strong>
                    (<?= $studentLogin['is_active'] ? 'Active' : 'Inactive' ?>).
                    Student logs in with role <strong>Student</strong>.
                </div>
            <?php else: ?>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="checkbox" name="create_login" value="1"> Create student login account</label>
                        <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Username: <?= sanitize($student['student_number']) ?></p>
                    </div>
                    <div class="form-group">
                        <label>Login Password</label>
                        <input type="password" name="login_password" class="form-control" placeholder="Default: student123">
                    </div>
                </div>
            <?php endif; ?>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Update Student</button>
                <a href="<?= BASE_URL ?>/modules/students/list.php?view=<?= $id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
