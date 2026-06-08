CREATE DATABASE IF NOT EXISTS college_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE college_management;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  role ENUM('admin', 'teacher', 'student') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, username, email, password, role, is_active)
VALUES
  ('Admin User', 'admin_user', 'admin@college.test', '$2y$10$ohabqSnYakv1sTMYavh91eOrUUqlO4AE4lGTMrb0ll13bZKpzdZAC', 'admin', 1),
  ('Teacher User', 'teacher_user', 'teacher@college.test', '$2y$10$BeR3LIbV.HpctK2p.vUwJeWr8yLWhY.4/3tovrpjMg6fLT/2Cbvtu', 'teacher', 1),
  ('Student User', 'student_user', 'student@college.test', '$2y$10$lw06DyljpdfbKcqn53k3MuWWRqHybsMmc.XsodSeLw8LA3UiIhhjO', 'student', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  username = VALUES(username),
  password = VALUES(password),
  role = VALUES(role),
  is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_name VARCHAR(150) NOT NULL,
  course_code VARCHAR(40) NOT NULL UNIQUE,
  duration VARCHAR(60) NOT NULL,
  semester_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  total_fees DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO courses (course_name, course_code, duration, semester_count, total_fees, description, is_active)
VALUES
  ('Bachelor of Computer Science', 'BCS-101', '4 Years', 8, 45000.00, 'Computer science undergraduate program.', 1),
  ('Bachelor of Business Administration', 'BBA-201', '4 Years', 8, 38000.00, 'Business administration undergraduate program.', 1),
  ('Master of Information Technology', 'MIT-301', '2 Years', 4, 52000.00, 'Postgraduate IT program.', 1)
ON DUPLICATE KEY UPDATE
  course_name = VALUES(course_name),
  duration = VALUES(duration),
  semester_count = VALUES(semester_count),
  total_fees = VALUES(total_fees),
  description = VALUES(description),
  is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  gender ENUM('male', 'female', 'other') NOT NULL DEFAULT 'other',
  date_of_birth DATE NULL,
  course_id INT UNSIGNED NULL,
  semester VARCHAR(30) NULL,
  profile_photo VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teachers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  department VARCHAR(120) NOT NULL,
  qualification VARCHAR(120) NULL,
  joining_date DATE NULL,
  profile_photo VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_teachers (
  course_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (course_id, teacher_id),
  CONSTRAINT fk_course_teachers_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
  CONSTRAINT fk_course_teachers_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('present', 'absent', 'leave') NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_attendance_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  INDEX idx_student_attendance_lookup (student_id, attendance_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_fees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(60) NOT NULL UNIQUE,
  amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('paid', 'partial', 'pending') NOT NULL DEFAULT 'pending',
  due_date DATE NULL,
  paid_at DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_fees_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  INDEX idx_student_fees_lookup (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
