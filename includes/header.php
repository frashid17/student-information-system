<?php
require_once __DIR__ . '/init.php';

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= LOGO_PATH ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--sidebar">
            <span>Student Information Management</span>
        </div>
        <nav class="sidebar-nav">
            <?php if (canAccess('dashboard')): ?>
            <div class="nav-section">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">D</span> Dashboard
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('students')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Students</div>
                <a href="<?= BASE_URL ?>/modules/students/register.php" class="nav-link <?= $currentPage === 'register' ? 'active' : '' ?>">
                    <span class="nav-icon">R</span> Registration
                </a>
                <a href="<?= BASE_URL ?>/modules/students/list.php" class="nav-link <?= $currentPage === 'list' ? 'active' : '' ?>">
                    <span class="nav-icon">V</span> View Students
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('fees_manage')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Fees</div>
                <a href="<?= BASE_URL ?>/modules/fees/payments.php" class="nav-link <?= $currentPage === 'payments' ? 'active' : '' ?>">
                    <span class="nav-icon">P</span> Fee Records
                </a>
                <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="nav-link <?= $currentPage === 'balance' ? 'active' : '' ?>">
                    <span class="nav-icon">B</span> Fee Balance
                </a>
            </div>
            <?php elseif (canAccess('fees_balance')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Fees</div>
                <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="nav-link <?= $currentPage === 'balance' ? 'active' : '' ?>">
                    <span class="nav-icon">B</span> My Fee Balance
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('faculty')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Faculty</div>
                <a href="<?= BASE_URL ?>/modules/faculty/register.php" class="nav-link <?= $currentPage === 'register' && strpos($_SERVER['PHP_SELF'], 'faculty') !== false ? 'active' : '' ?>">
                    <span class="nav-icon">F</span> Faculty Registration
                </a>
                <a href="<?= BASE_URL ?>/modules/faculty/payroll.php" class="nav-link <?= $currentPage === 'payroll' ? 'active' : '' ?>">
                    <span class="nav-icon">S</span> Payroll
                </a>
                <a href="<?= BASE_URL ?>/modules/faculty/payslip.php" class="nav-link <?= $currentPage === 'payslip' ? 'active' : '' ?>">
                    <span class="nav-icon">L</span> Pay Slip
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('library')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Academic / Library</div>
                <a href="<?= BASE_URL ?>/modules/library/checkin.php" class="nav-link <?= $currentPage === 'checkin' ? 'active' : '' ?>">
                    <span class="nav-icon">C</span> Library Check-in
                </a>
                <a href="<?= BASE_URL ?>/modules/library/books.php" class="nav-link <?= $currentPage === 'books' ? 'active' : '' ?>">
                    <span class="nav-icon">A</span> Add Books
                </a>
                <a href="<?= BASE_URL ?>/modules/library/issue.php" class="nav-link <?= $currentPage === 'issue' ? 'active' : '' ?>">
                    <span class="nav-icon">I</span> Issue / Return
                </a>
            </div>
            <?php elseif (canAccess('library_student')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Library</div>
                <a href="<?= BASE_URL ?>/modules/library/issue.php" class="nav-link <?= $currentPage === 'issue' ? 'active' : '' ?>">
                    <span class="nav-icon">I</span> My Books
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('academics')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Academics</div>
                <a href="<?= BASE_URL ?>/modules/academics/grades.php" class="nav-link <?= $currentPage === 'grades' ? 'active' : '' ?>">
                    <span class="nav-icon">G</span> Grades / Results
                </a>
                <a href="<?= BASE_URL ?>/modules/academics/attendance.php" class="nav-link <?= $currentPage === 'attendance' ? 'active' : '' ?>">
                    <span class="nav-icon">T</span> Attendance
                </a>
            </div>
            <?php elseif (canAccess('academics_view')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Academics</div>
                <a href="<?= BASE_URL ?>/modules/academics/unit_registration.php" class="nav-link <?= $currentPage === 'unit_registration' ? 'active' : '' ?>">
                    <span class="nav-icon">U</span> Unit Registration
                </a>
                <a href="<?= BASE_URL ?>/modules/academics/exam_card.php" class="nav-link <?= $currentPage === 'exam_card' ? 'active' : '' ?>">
                    <span class="nav-icon">E</span> Exam Card
                </a>
                <a href="<?= BASE_URL ?>/modules/academics/semester_reporting.php" class="nav-link <?= $currentPage === 'semester_reporting' ? 'active' : '' ?>">
                    <span class="nav-icon">S</span> Semester Reporting
                </a>
                <a href="<?= BASE_URL ?>/modules/academics/grades.php" class="nav-link <?= $currentPage === 'grades' ? 'active' : '' ?>">
                    <span class="nav-icon">G</span> My Grades
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('institution')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Institution</div>
                <a href="<?= BASE_URL ?>/modules/institution/campus.php" class="nav-link <?= $currentPage === 'campus' ? 'active' : '' ?>">
                    <span class="nav-icon">M</span> Campus Branches
                </a>
                <a href="<?= BASE_URL ?>/modules/institution/programs.php" class="nav-link <?= $currentPage === 'programs' ? 'active' : '' ?>">
                    <span class="nav-icon">O</span> Programs
                </a>
                <a href="<?= BASE_URL ?>/modules/institution/units.php" class="nav-link <?= $currentPage === 'units' ? 'active' : '' ?>">
                    <span class="nav-icon">U</span> Units
                </a>
                <a href="<?= BASE_URL ?>/modules/institution/fees.php" class="nav-link <?= $currentPage === 'fees' && strpos($_SERVER['PHP_SELF'], 'institution') !== false ? 'active' : '' ?>">
                    <span class="nav-icon">E</span> Fee Setup
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('reports')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Reports</div>
                <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-link <?= $currentPage === 'index' && strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : '' ?>">
                    <span class="nav-icon">R</span> Reports
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('communications')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Communication</div>
                <a href="<?= BASE_URL ?>/modules/communications/announcements.php" class="nav-link <?= $currentPage === 'announcements' ? 'active' : '' ?>">
                    <span class="nav-icon">N</span> Announcements
                </a>
            </div>
            <?php endif; ?>

            <?php if (canAccess('users') || canAccess('settings') || canManageHomepage()): ?>
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <?php if (canManageHomepage()): ?>
                <a href="<?= BASE_URL ?>/modules/settings/homepage.php" class="nav-link <?= $currentPage === 'homepage' ? 'active' : '' ?>">
                    <span class="nav-icon">H</span> Homepage Manager
                </a>
                <?php endif; ?>
                <?php if (canAccess('users')): ?>
                <a href="<?= BASE_URL ?>/modules/settings/users.php" class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="nav-icon">U</span> User Accounts
                </a>
                <?php endif; ?>
                <?php if (canAccess('password_resets')): ?>
                <a href="<?= BASE_URL ?>/modules/settings/password_resets.php" class="nav-link <?= $currentPage === 'password_resets' ? 'active' : '' ?>">
                    <span class="nav-icon">K</span> Password Resets
                    <?php
                    $pendingPw = countPendingPasswordRequests(getDBConnection());
                    if ($pendingPw > 0): ?>
                    <span class="badge badge-warning" style="margin-left:4px;font-size:0.65rem;"><?= $pendingPw ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if (canAccess('settings')): ?>
                <a href="<?= BASE_URL ?>/modules/settings/database.php" class="nav-link <?= $currentPage === 'database' ? 'active' : '' ?>">
                    <span class="nav-icon">X</span> Database Setup
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <a href="<?= BASE_URL ?>/" class="nav-link">
                    <span class="nav-icon">C</span> Campus Home
                </a>
                <a href="<?= BASE_URL ?>/modules/settings/profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                    <span class="nav-icon">P</span> Profile Settings
                </a>
            </div>
        </nav>
    </aside>

    <div class="main-content">
        <header class="top-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">&#9776;</button>
                <h1 class="page-title"><?= isset($pageTitle) ? sanitize($pageTitle) : 'Dashboard' ?></h1>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="name"><?= sanitize($currentUser['full_name'] ?? '') ?></div>
                    <div class="role"><?= getRoleLabel($currentUser['role'] ?? '') ?></div>
                </div>
                <a href="<?= BASE_URL ?>/" class="btn btn-secondary btn-sm">Campus Home</a>
                <a href="<?= BASE_URL ?>/modules/settings/profile.php" class="btn btn-secondary btn-sm">Profile</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </header>
        <main class="content-area">
