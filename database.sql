CREATE DATABASE IF NOT EXISTS college_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE college_management;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'teacher', 'student') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password, role, is_active)
VALUES
  ('Admin User', 'admin@college.test', '$2y$10$ohabqSnYakv1sTMYavh91eOrUUqlO4AE4lGTMrb0ll13bZKpzdZAC', 'admin', 1),
  ('Teacher User', 'teacher@college.test', '$2y$10$BeR3LIbV.HpctK2p.vUwJeWr8yLWhY.4/3tovrpjMg6fLT/2Cbvtu', 'teacher', 1),
  ('Student User', 'student@college.test', '$2y$10$lw06DyljpdfbKcqn53k3MuWWRqHybsMmc.XsodSeLw8LA3UiIhhjO', 'student', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password = VALUES(password),
  role = VALUES(role),
  is_active = VALUES(is_active);
