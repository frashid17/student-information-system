<?php

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateStudentNumber(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE student_number LIKE 'STU{$year}%'");
    $count = (int) $stmt->fetch()['count'] + 1;
    return 'STU' . $year . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
}

function generateStaffId(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM faculty WHERE staff_id LIKE 'FAC{$year}%'");
    $count = (int) $stmt->fetch()['count'] + 1;
    return 'FAC' . $year . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
}

function generateReceiptNumber(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM fee_payments");
    $count = (int) $stmt->fetch()['count'] + 1;
    return 'RCP' . date('Ymd') . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
}

function getStudentFeeBalance(PDO $pdo, int $studentId, string $trimester, string $academicYear): array
{
    $stmt = $pdo->prepare("
        SELECT s.*, fs.amount as fee_amount
        FROM students s
        LEFT JOIN fee_structures fs ON fs.program_id = s.program_id 
            AND fs.trimester = ? AND fs.academic_year = ?
        WHERE s.id = ?
    ");
    $stmt->execute([$trimester, $academicYear, $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['total_fee' => 0, 'total_paid' => 0, 'balance' => 0];
    }

    $totalFee = (float) ($student['fee_amount'] ?? 0);

    $payStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_paid 
        FROM fee_payments 
        WHERE student_id = ? AND trimester = ? AND academic_year = ?
    ");
    $payStmt->execute([$studentId, $trimester, $academicYear]);
    $totalPaid = (float) $payStmt->fetch()['total_paid'];

    return [
        'total_fee' => $totalFee,
        'total_paid' => $totalPaid,
        'balance' => $totalFee - $totalPaid,
        'student' => $student
    ];
}

function calculateGrade(float $marks): string
{
    if ($marks >= 70) return 'A';
    if ($marks >= 60) return 'B';
    if ($marks >= 50) return 'C';
    if ($marks >= 40) return 'D';
    return 'F';
}

function formatDate(?string $date): string
{
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatCurrency(float $amount): string
{
    return 'KES ' . number_format($amount, 2);
}

function getRoleLabel(string $role): string
{
    $labels = [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'staff' => 'Staff',
        'student' => 'Student',
        'faculty' => 'Faculty'
    ];
    return $labels[$role] ?? ucfirst($role);
}

function createUserAccount(PDO $pdo, string $username, string $plainPassword, string $role, string $fullName, ?string $email = null, ?int $relatedId = null): bool|string
{
    $username = strtolower(trim($username));
    if ($username === '') {
        return 'Username is required.';
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        return 'Username already exists.';
    }

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email, related_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $role, $fullName, $email, $relatedId]);

    return true;
}

function getLinkedStudent(PDO $pdo): ?array
{
    if (!isStudentUser() || empty($_SESSION['related_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT s.*, p.program_name, p.program_code, c.branch_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN campus_branches c ON s.campus_id = c.id
        WHERE s.id = ? AND s.status = 'active'
    ");
    $stmt->execute([(int) $_SESSION['related_id']]);

    return $stmt->fetch() ?: null;
}

function getLinkedFaculty(PDO $pdo): ?array
{
    if (!isFacultyUser() || empty($_SESSION['related_id'])) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT f.*, c.branch_name
        FROM faculty f
        LEFT JOIN campus_branches c ON f.campus_id = c.id
        WHERE f.id = ? AND f.status = 'active'
    ");
    $stmt->execute([(int) $_SESSION['related_id']]);

    return $stmt->fetch() ?: null;
}

function getAnnouncementsForUser(PDO $pdo, int $limit = 5): array
{
    $role = $_SESSION['user_role'] ?? '';
    $audienceFilter = match ($role) {
        'student' => "AND (a.target_audience IN ('all', 'students'))",
        'faculty' => "AND (a.target_audience IN ('all', 'faculty', 'staff'))",
        'staff' => "AND (a.target_audience IN ('all', 'staff', 'faculty'))",
        default => ''
    };

    $stmt = $pdo->query("
        SELECT a.*, u.full_name as author
        FROM announcements a
        LEFT JOIN users u ON a.posted_by = u.id
        WHERE a.is_active = 1 $audienceFilter
        ORDER BY a.created_at DESC
        LIMIT $limit
    ");

    return $stmt->fetchAll();
}

function getHomepageAnnouncements(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT *
            FROM announcements
            WHERE is_active = 1 AND show_on_homepage = 1
            ORDER BY homepage_sort ASC, created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getHomepageCategoryLabel(string $category): string
{
    $labels = [
        'general' => 'Announcement',
        'results' => 'Results',
        'exam_calendar' => 'Exam Calendar',
        'intake' => 'Admissions',
        'portal' => 'Portal Access',
    ];
    return $labels[$category] ?? 'Announcement';
}

function getHomepageCategoryIcon(string $category): string
{
    $icons = [
        'general' => '&#9733;',
        'results' => '&#127942;',
        'exam_calendar' => '&#128197;',
        'intake' => '&#127979;',
        'portal' => '&#128274;',
    ];
    return $icons[$category] ?? '&#9733;';
}

function canManageHomepage(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['super_admin', 'admin'], true);
}

function getHomepageDocuments(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT * FROM homepage_documents
            WHERE is_active = 1 AND show_on_homepage = 1
            ORDER BY doc_type, created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getDocumentTypeLabel(string $type): string
{
    $labels = [
        'events_calendar' => 'Events Calendar',
        'timetable' => 'Class Timetable',
        'exam_timetable' => 'Exam Timetable',
    ];
    return $labels[$type] ?? 'Document';
}

function getDocumentTypeIcon(string $type): string
{
    $icons = [
        'events_calendar' => '&#128197;',
        'timetable' => '&#128218;',
        'exam_timetable' => '&#128221;',
    ];
    return $icons[$type] ?? '&#128196;';
}

function isAnnouncementLive(string $createdAt, int $days = 14): bool
{
    return (time() - strtotime($createdAt)) < ($days * 86400);
}

function uploadHomepagePdf(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Please select a PDF file to upload.'];
    }

    $maxSize = 10 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 10MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== 'application/pdf') {
        return ['ok' => false, 'error' => 'Only PDF files are allowed.'];
    }

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $storedName = $safeName . '_' . time() . '.pdf';
    $destination = UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'Failed to save uploaded file.'];
    }

    return [
        'ok' => true,
        'file_name' => $file['name'],
        'file_path' => $storedName,
    ];
}
