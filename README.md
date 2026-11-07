# Web-Based School Management System
Adventist Preparatory School, New Edubiase, Ashanti Region. Stack: HTML, CSS, JavaScript, PHP (PDO), MySQL, XAMPP.

## Run it on your computer (XAMPP)
1. Install XAMPP, then start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy this folder (`school-management-system`) into `C:\xampp\htdocs\`.
3. Open http://localhost/phpmyadmin, click **Import**, choose `sql/school_db.sql`, click **Import**.
4. Open http://localhost/school-management-system/ . You will be sent to a one-time page to create the administrator account.
5. After that, delete `setup.php`.

If your MySQL root user has a password, edit `config.php`.

### Existing installation
Back up the database, then import `sql/migrate_school_features.sql` once in phpMyAdmin. It adds guardian contacts, profile photos, announcements, teacher comments, and notification history. If the script lists subjects without an assigned teacher, assign each from **Subjects** before teachers enter scores.

### Parent notifications
Set the school's SMTP host, port, encryption, sender address and credentials in `config.php`. For SMS, configure an HTTPS gateway URL and bearer token; the gateway must accept JSON `POST` requests with `to` and `message` fields. The SMS provider has not been selected, so this adapter is configurable rather than tied to a specific vendor. Delivery failures are shown in the app and recorded in `parent_notification`; messages are not reported as sent unless the provider accepts them.

## First steps as administrator
1. Grading: check the grading scale (menu: Grading) matches the school's approved scale.
2. Teachers: register teachers. Each gets a login (staff number) and a one-time password shown on screen.
3. Classrooms: create classes and pick a class teacher.
4. Students: register students (login = student number, password shown once). Assign them to classes.
5. Subjects: add subjects to each class and pick the teacher for each.
6. Exams: create an examination. Teachers enter scores for their assigned subjects. Click **Publish results** so students can see results, including resit examination results.

## Modules
- Admin: students and guardian contacts (guardian phone required; email optional), teachers, classrooms, subjects, exams, grading scale, reports, password resets, announcements, parent result messages
- Teacher: assigned classes and subjects, attendance (present/absent/late), exam score entry, class and individual reports with academic and progress comments, announcements to students, parent result messages for authorised results
- Student: profile photo, attendance summary, published results with printable slip, announcements, password changes
- Reports: student information, attendance (date range), academic performance; select an entire class or an individual student, print or download CSV
- Student and teacher profiles support passport photo uploads. New student registration sends an email and/or SMS notification to the recorded parent/guardian contacts.

## Security built in
Passwords hashed (bcrypt), prepared SQL statements, CSRF tokens on every form, role checks on every page, session ID regenerated on login, login lockout after 5 failures, results hidden from students until published.
