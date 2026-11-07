-- Run once against an existing school_db to add guardian, profile and communication features.
USE school_db;

ALTER TABLE student
  ADD COLUMN Guardian_Name VARCHAR(120) NULL AFTER Phone_Number,
  ADD COLUMN Guardian_Phone VARCHAR(20) NULL AFTER Guardian_Name,
  ADD COLUMN Guardian_Email VARCHAR(120) NULL AFTER Guardian_Phone,
  ADD COLUMN Photo_Path VARCHAR(255) NULL AFTER Guardian_Email;

ALTER TABLE teacher
  ADD COLUMN Photo_Path VARCHAR(255) NULL AFTER Address;

CREATE TABLE announcement (
  Announcement_ID INT AUTO_INCREMENT PRIMARY KEY,
  Title VARCHAR(160) NOT NULL,
  Body TEXT NOT NULL,
  Audience ENUM('all','students','teachers') NOT NULL,
  Created_By INT NOT NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (Created_By) REFERENCES users(User_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teacher_comment (
  Comment_ID INT AUTO_INCREMENT PRIMARY KEY,
  Student_ID INT NOT NULL,
  Exam_ID INT NOT NULL,
  Teacher_ID INT NOT NULL,
  Academic_Comment TEXT NOT NULL,
  Progress_Comment TEXT NOT NULL,
  Updated_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teacher_student_exam (Teacher_ID, Student_ID, Exam_ID),
  FOREIGN KEY (Student_ID) REFERENCES student(Student_ID) ON DELETE CASCADE,
  FOREIGN KEY (Exam_ID) REFERENCES examination(Exam_ID) ON DELETE CASCADE,
  FOREIGN KEY (Teacher_ID) REFERENCES teacher(Teacher_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE parent_notification (
  Notification_ID INT AUTO_INCREMENT PRIMARY KEY,
  Student_ID INT NOT NULL,
  Exam_ID INT NULL,
  Sent_By INT NOT NULL,
  Channel ENUM('email','sms') NOT NULL,
  Recipient VARCHAR(160) NOT NULL,
  Status ENUM('sent','failed') NOT NULL,
  Error_Message VARCHAR(500) NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (Student_ID) REFERENCES student(Student_ID) ON DELETE CASCADE,
  FOREIGN KEY (Exam_ID) REFERENCES examination(Exam_ID) ON DELETE SET NULL,
  FOREIGN KEY (Sent_By) REFERENCES users(User_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Existing subjects with no teacher must be assigned from the Subjects page.
-- These triggers prevent new unassigned subjects until every row is migrated.
DELIMITER //
CREATE TRIGGER subject_require_teacher_insert
BEFORE INSERT ON subject FOR EACH ROW
BEGIN
  IF NEW.Teacher_ID IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Every subject must have an assigned teacher.';
  END IF;
END//
CREATE TRIGGER subject_require_teacher_update
BEFORE UPDATE ON subject FOR EACH ROW
BEGIN
  IF NEW.Teacher_ID IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Every subject must have an assigned teacher.';
  END IF;
END//
DELIMITER ;

SET @subject_teacher_fk = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subject'
    AND COLUMN_NAME = 'Teacher_ID'
    AND REFERENCED_TABLE_NAME = 'teacher'
  LIMIT 1
);
SET @drop_subject_teacher_fk = CONCAT('ALTER TABLE subject DROP FOREIGN KEY `', @subject_teacher_fk, '`');
PREPARE drop_subject_teacher_fk FROM @drop_subject_teacher_fk;
EXECUTE drop_subject_teacher_fk;
DEALLOCATE PREPARE drop_subject_teacher_fk;
ALTER TABLE subject
  ADD CONSTRAINT fk_subject_teacher
  FOREIGN KEY (Teacher_ID) REFERENCES teacher(Teacher_ID) ON DELETE RESTRICT;

-- After assigning the subjects returned below, make the legacy column non-null with:
-- ALTER TABLE subject MODIFY Teacher_ID INT NOT NULL;
SELECT Subject_ID, Subject_Name, Class_ID
FROM subject
WHERE Teacher_ID IS NULL;
