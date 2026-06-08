-- Run ONLY Step 2 if Step 1 (ALTER TABLE) already failed with "Duplicate column"
-- Use this file when homepage columns already exist but you want sample announcements

USE schooldb;

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
