<?php
$pageTitle = 'Student Registration';
require_once __DIR__ . '/../../includes/init.php';
requireModuleAccess('students');

$pdo = getDBConnection();
$programs = $pdo->query("SELECT * FROM programs WHERE is_active = 1 ORDER BY program_name")->fetchAll();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentNumber = generateStudentNumber($pdo);

    $stmt = $pdo->prepare("INSERT INTO students (
        student_number, first_name, last_name, middle_name, gender, date_of_birth,
        email, phone, address, national_id, program_id, campus_id, trimester,
        academic_year, enrollment_date, kin_name, kin_relationship, kin_phone, kin_address
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $studentNumber,
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        trim($_POST['middle_name'] ?? ''),
        $_POST['gender'],
        $_POST['date_of_birth'] ?: null,
        trim($_POST['email'] ?? ''),
        trim($_POST['phone'] ?? ''),
        trim($_POST['address'] ?? ''),
        trim($_POST['national_id'] ?? ''),
        $_POST['program_id'] ?: null,
        $_POST['campus_id'] ?: null,
        $_POST['trimester'],
        $_POST['academic_year'],
        $_POST['enrollment_date'] ?: date('Y-m-d'),
        trim($_POST['kin_name'] ?? ''),
        trim($_POST['kin_relationship'] ?? ''),
        trim($_POST['kin_phone'] ?? ''),
        trim($_POST['kin_address'] ?? ''),
    ]);

    $studentId = $pdo->lastInsertId();

    $log = $pdo->prepare("INSERT INTO registration_log (student_id, action, details, performed_by) VALUES (?, 'registered', ?, ?)");
    $log->execute([$studentId, 'New student registered: ' . $studentNumber, $_SESSION['user_id']]);

    $loginMessage = '';
    if (!empty($_POST['create_login'])) {
        $loginPassword = $_POST['login_password'] ?: 'student123';
        $fullName = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
        $result = createUserAccount(
            $pdo,
            $studentNumber,
            $loginPassword,
            'student',
            $fullName,
            trim($_POST['email'] ?? '') ?: null,
            (int) $studentId
        );
        if ($result === true) {
            $loginMessage = " Login: Student / $studentNumber / $loginPassword";
        } else {
            $loginMessage = ' (Login not created: ' . $result . ')';
        }
    }

    setFlash('success', "Student registered successfully. Student Number: $studentNumber.$loginMessage");
    redirect(BASE_URL . '/modules/students/list.php');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Register New Student</h3>
        <a href="<?= BASE_URL ?>/modules/students/list.php" class="btn btn-secondary btn-sm">View All Students</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-section-title">Personal Details</div>
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
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" class="form-control" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control">
                </div>
                <div class="form-group">
                    <label>National ID</label>
                    <input type="text" name="national_id" class="form-control">
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
                    <label>Address</label>
                    <input type="text" name="address" class="form-control">
                </div>
            </div>

            <div class="form-section-title">Academic Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Program *</label>
                    <select name="program_id" class="form-control" required>
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['program_code'] . ' - ' . $p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <div class="form-group">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Trimester *</label>
                    <select name="trimester" class="form-control" required>
                        <option value="Trimester 1">Trimester 1</option>
                        <option value="Trimester 2">Trimester 2</option>
                        <option value="Trimester 3">Trimester 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="2025/2026" required>
                </div>
            </div>

            <div class="form-section-title">Next of Kin Details</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
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

            <div class="form-section-title">Student Login (Portal Access)</div>
            <div class="form-row">
                <div class="form-group">
                    <label><input type="checkbox" name="create_login" value="1" checked> Create student login account</label>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Username will be the student number.</p>
                </div>
                <div class="form-group">
                    <label>Login Password</label>
                    <input type="password" name="login_password" class="form-control" placeholder="Default: student123">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Register Student</button>
                <button type="reset" class="btn btn-secondary">Clear Form</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
