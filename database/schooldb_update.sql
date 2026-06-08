-- =============================================================================
-- KeMU Student Information Management System
-- DATABASE UPDATE (for databases that already have the ORIGINAL schooldb.sql)
-- =============================================================================
--
-- HOW TO RUN (phpMyAdmin):
--   1. Select database: schooldb
--   2. SQL tab → paste ONE section at a time → Go
--
-- IF YOU SEE: "Duplicate column name 'show_on_homepage'"
--   → Step 1 is ALREADY DONE. Skip to Step 2 only (sample announcements).
--
-- CHECK FIRST (optional):
--   SHOW COLUMNS FROM announcements LIKE 'show_on_homepage';
--   If you get 1 row back, Step 1 is already applied.
-- =============================================================================

USE schooldb;

-- =============================================================================
-- STEP 1 — Add homepage columns (run once only)
-- Skip this entire step if columns already exist
-- =============================================================================

ALTER TABLE announcements
    ADD COLUMN show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER target_audience;

ALTER TABLE announcements
    ADD COLUMN homepage_category ENUM('general', 'results', 'exam_calendar', 'intake', 'portal') NOT NULL DEFAULT 'general' AFTER show_on_homepage;

ALTER TABLE announcements
    ADD COLUMN homepage_sort INT NOT NULL DEFAULT 0 AFTER homepage_category;

-- =============================================================================
-- STEP 2 — Sample homepage announcements (optional)
-- Safe to run even if Step 1 was skipped — will not duplicate sample titles
-- =============================================================================

INSERT INTO announcements (title, message, target_audience, show_on_homepage, homepage_category, homepage_sort, is_active)
SELECT 'September Intake Now Open',
       'Applications for the September intake are now being accepted. Visit the admissions office or login to the portal for program details and fee structures.',
       'all', 1, 'intake', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM announcements WHERE title = 'September Intake Now Open'
);

INSERT INTO announcements (title, message, target_audience, show_on_homepage, homepage_category, homepage_sort, is_active)
SELECT 'Exam Timetable Published',
       'The end-of-trimester exam calendar is available. Check your faculty notice board or login to the portal for your personal schedule.',
       'all', 1, 'exam_calendar', 2, 1
WHERE NOT EXISTS (
    SELECT 1 FROM announcements WHERE title = 'Exam Timetable Published'
);

INSERT INTO announcements (title, message, target_audience, show_on_homepage, homepage_category, homepage_sort, is_active)
SELECT 'Results Are Out',
       'Trimester results have been released. Login to the student portal to view your grades and download your report card.',
       'students', 1, 'results', 3, 1
WHERE NOT EXISTS (
    SELECT 1 FROM announcements WHERE title = 'Results Are Out'
);

-- =============================================================================
-- Done. Visit: http://localhost/student-managment-system/
-- =============================================================================
