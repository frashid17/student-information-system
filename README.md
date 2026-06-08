# Student Information Management System

A modern web-based Student Information Management System for Kenya Methodist University, built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

- **User Authentication** - Role-based login (Super Admin, Admin, Staff, Faculty, Student)
- **Student Registration** - Personal details, next of kin, academic programs
- **Fee Management** - Record payments, check balances, print receipts
- **Faculty Management** - Staff registration, payroll, pay slips
- **Library** - Check-in tracking, book catalog, issue/return
- **Academics** - Grades, report cards, attendance tracking
- **Institution Control** - Campus branches, programs, fee setup
- **Reports** - Enrollment, student lists, fee collection, library usage
- **Communications** - Announcements to students and staff
- **Database Setup** - Configuration and table verification

## Requirements

- XAMPP (Apache + MySQL + PHP 8.0+)
- Web browser (Chrome, Firefox, Edge)

## Installation

### Step 1: Copy Project Files

Copy the entire `student-managment-system` folder to your XAMPP htdocs directory:

```
C:\xampp\htdocs\student-managment-system\
```

### Step 2: Import Database into phpMyAdmin

1. Start **Apache** and **MySQL** in XAMPP Control Panel
2. Open **phpMyAdmin**: http://localhost/phpmyadmin
3. Click **Import** tab
4. Choose file: `database/schooldb.sql`
5. Click **Go** to import

This creates the `schooldb` database with all 17 tables.

### Step 3: Configure Database Connection

Edit `config/database.php` if your MySQL credentials differ:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'schooldb');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP password is empty
```

### Step 4: Run Setup Script

Visit: http://localhost/student-managment-system/install.php

This sets up the admin password. Delete `install.php` after setup.

### Step 5: Access the System

Open: http://localhost/student-managment-system/

**Default Login:**
| Field | Value |
|-------|-------|
| Role | Super Administrator |
| Username | admin |
| Password | admin1 |

## Database Tables (Copy to phpMyAdmin)

Import the single file `database/schooldb.sql` which creates all tables:

| Table | Purpose |
|-------|---------|
| `users` | System login accounts (Admin, Staff, Students, Faculty) |
| `campus_branches` | Institution campus locations |
| `programs` | Academic programs per campus |
| `students` | Student personal, academic, and next-of-kin data |
| `faculty` | Teaching and non-teaching staff records |
| `fee_structures` | Fee amounts per program/trimester |
| `fee_payments` | Student fee payment transactions |
| `payroll` | Employee salary configuration |
| `payslips` | Generated pay slip records |
| `books` | Library book catalog |
| `library_checkins` | Student library visit tracking |
| `book_issues` | Book issue and return records |
| `grades` | Student academic results |
| `attendance` | Student attendance records |
| `announcements` | System communications |
| `system_settings` | Database and institution configuration |
| `registration_log` | Student registration audit trail |

## Project Structure

```
student-managment-system/
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   └── schooldb.sql          <-- Import this into phpMyAdmin
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── modules/
│   ├── students/             Registration, list, edit
│   ├── fees/                 Payments, balance
│   ├── faculty/              Registration, payroll, payslip
│   ├── library/              Check-in, books, issue/return
│   ├── institution/          Campus, programs, fee setup
│   ├── academics/            Grades, attendance
│   ├── reports/              System reports
│   ├── communications/       Announcements
│   └── settings/             Database setup
├── dashboard.php
├── index.php                 Login page
├── install.php               One-time setup
└── logout.php
```

## User Roles & Access

| Role | Access |
|------|--------|
| Super Administrator | Full system access including database setup |
| Administrator | All modules except database setup |
| Staff | Students, fees, library, academics, reports |
| Faculty | Library, academics, communications |
| Student | Fees (balance), library, academics, communications |

## Technologies

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 8+
- **Database:** MySQL (via phpMyAdmin / XAMPP)
- **Server:** Apache (XAMPP)

## License

Academic project - Kenya Methodist University, Diploma in Business Information Technology.
