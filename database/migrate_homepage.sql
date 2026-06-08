-- Run once in phpMyAdmin if you already imported schooldb.sql before homepage support was added
USE schooldb;

ALTER TABLE announcements
    ADD COLUMN show_on_homepage TINYINT(1) DEFAULT 0 AFTER target_audience,
    ADD COLUMN homepage_category ENUM('general', 'results', 'exam_calendar', 'intake', 'portal') DEFAULT 'general' AFTER show_on_homepage,
    ADD COLUMN homepage_sort INT DEFAULT 0 AFTER homepage_category;

-- Sample homepage announcements (optional)
INSERT INTO announcements (title, message, target_audience, show_on_homepage, homepage_category, homepage_sort, is_active) VALUES
('September Intake Now Open', 'Applications for the September intake are now being accepted. Visit the admissions office or login to the portal for program details and fee structures.', 'all', 1, 'intake', 1, 1),
('Exam Timetable Published', 'The end-of-trimester exam calendar is available. Check your faculty notice board or login to the portal for your personal schedule.', 'all', 1, 'exam_calendar', 2, 1),
('Results Are Out', 'Trimester results have been released. Login to the student portal to view your grades and download your report card.', 'students', 1, 'results', 3, 1);
