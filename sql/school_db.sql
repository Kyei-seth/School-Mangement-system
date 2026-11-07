-- Adventist Preparatory School - School Management System
-- Import this file in phpMyAdmin (or: mysql -u root < school_db.sql)

CREATE DATABASE IF NOT EXISTS school_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_db;

CREATE TABLE teacher (
  Teacher_ID INT AUTO_INCREMENT PRIMARY KEY,
  Staff_No VARCHAR(20) NOT NULL UNIQUE,
  First_Name VARCHAR(60) NOT NULL,
  Last_Name VARCHAR(60) NOT NULL,
  Gender ENUM('Male','Female') NOT NULL,
  Phone_Number VARCHAR(20),
  Email VARCHAR(120),
  Address VARCHAR(200),
  Photo_Path VARCHAR(255),
  Date_Employed DATE
) ENGINE=InnoDB;

CREATE TABLE classroom (
  Class_ID INT AUTO_INCREMENT PRIMARY KEY,
  Class_Name VARCHAR(60) NOT NULL,
  Class_Level VARCHAR(40) NOT NULL,
  Academic_Year VARCHAR(9) NOT NULL,
  Teacher_ID INT NULL,
  UNIQUE KEY uq_class (Class_Name, Academic_Year),
  FOREIGN KEY (Teacher_ID) REFERENCES teacher(Teacher_ID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE student (
  Student_ID INT AUTO_INCREMENT PRIMARY KEY,
  Student_No VARCHAR(20) NOT NULL UNIQUE,
  First_Name VARCHAR(60) NOT NULL,
  Last_Name VARCHAR(60) NOT NULL,
  Gender ENUM('Male','Female') NOT NULL,
  Date_of_Birth DATE,
  Address VARCHAR(200),
  Phone_Number VARCHAR(20),
  Guardian_Name VARCHAR(120),
  Guardian_Phone VARCHAR(20),
  Guardian_Email VARCHAR(120),
  Photo_Path VARCHAR(255),
  Admission_Date DATE,
  Class_ID INT NULL,
  FOREIGN KEY (Class_ID) REFERENCES classroom(Class_ID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE subject (
  Subject_ID INT AUTO_INCREMENT PRIMARY KEY,
  Subject_Name VARCHAR(80) NOT NULL,
  Subject_Code VARCHAR(20) NOT NULL,
  Class_ID INT NOT NULL,
  Teacher_ID INT NOT NULL,
  UNIQUE KEY uq_subject (Subject_Code, Class_ID),
  FOREIGN KEY (Class_ID) REFERENCES classroom(Class_ID) ON DELETE CASCADE,
  FOREIGN KEY (Teacher_ID) REFERENCES teacher(Teacher_ID) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE attendance (
  Attendance_ID INT AUTO_INCREMENT PRIMARY KEY,
  Student_ID INT NOT NULL,
  Class_ID INT NOT NULL,
  Attendance_Date DATE NOT NULL,
  Attendance_Status ENUM('Present','Absent','Late') NOT NULL,
  UNIQUE KEY uq_att (Student_ID, Attendance_Date),
  FOREIGN KEY (Student_ID) REFERENCES student(Student_ID) ON DELETE CASCADE,
  FOREIGN KEY (Class_ID) REFERENCES classroom(Class_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE examination (
  Exam_ID INT AUTO_INCREMENT PRIMARY KEY,
  Exam_Name VARCHAR(100) NOT NULL,
  Exam_Type VARCHAR(40) NOT NULL,
  Academic_Year VARCHAR(9) NOT NULL,
  Term TINYINT NOT NULL,
  Exam_Date DATE,
  Is_Published TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE result (
  Result_ID INT AUTO_INCREMENT PRIMARY KEY,
  Student_ID INT NOT NULL,
  Subject_ID INT NOT NULL,
  Exam_ID INT NOT NULL,
  Class_Score DECIMAL(5,2) NOT NULL DEFAULT 0,
  Score DECIMAL(5,2) NOT NULL DEFAULT 0,
  Total_Score DECIMAL(5,2) NOT NULL DEFAULT 0,
  Grade VARCHAR(2),
  UNIQUE KEY uq_result (Student_ID, Subject_ID, Exam_ID),
  FOREIGN KEY (Student_ID) REFERENCES student(Student_ID) ON DELETE CASCADE,
  FOREIGN KEY (Subject_ID) REFERENCES subject(Subject_ID) ON DELETE CASCADE,
  FOREIGN KEY (Exam_ID) REFERENCES examination(Exam_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE grade_scale (
  Grade VARCHAR(2) PRIMARY KEY,
  Min_Score DECIMAL(5,2) NOT NULL,
  Max_Score DECIMAL(5,2) NOT NULL,
  Remark VARCHAR(40) NOT NULL
) ENGINE=InnoDB;
INSERT INTO grade_scale VALUES
 ('A',80,100,'Excellent'),('B',70,79.99,'Very Good'),('C',60,69.99,'Good'),
 ('D',50,59.99,'Credit'),('E',40,49.99,'Pass'),('F',0,39.99,'Fail');

CREATE TABLE users (
  User_ID INT AUTO_INCREMENT PRIMARY KEY,
  Username VARCHAR(50) NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL,
  Role ENUM('admin','teacher','student') NOT NULL,
  Student_ID INT NULL UNIQUE,
  Teacher_ID INT NULL UNIQUE,
  FOREIGN KEY (Student_ID) REFERENCES student(Student_ID) ON DELETE CASCADE,
  FOREIGN KEY (Teacher_ID) REFERENCES teacher(Teacher_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE report (
  Report_ID INT AUTO_INCREMENT PRIMARY KEY,
  Report_Type VARCHAR(40) NOT NULL,
  Generated_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Generated_By INT NOT NULL,
  FOREIGN KEY (Generated_By) REFERENCES users(User_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

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
