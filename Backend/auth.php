<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ../index.html?error=missing_fields');
    exit;
}

try {
    $pdo = dbConnection($config);

    $stmt = $pdo->prepare('SELECT id, name, email, password, role, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    header('Location: ../index.html?error=server');
    exit;
}

if ($row === null) {
    header('Location: ../index.html?error=invalid_credentials');
    exit;
}

$storedHash = (string) ($row['password'] ?? '');
if ($storedHash === '' || !password_verify($password, $storedHash)) {
    header('Location: ../index.html?error=invalid_credentials');
    exit;
}

$isActive = (int) ($row['is_active'] ?? 0) === 1;
$role = strtolower(trim((string) ($row['role'] ?? '')));
$name = trim((string) ($row['name'] ?? ''));
$dbEmail = strtolower(trim((string) ($row['email'] ?? '')));

if (!$isActive || $role === '' || $name === '' || $dbEmail === '') {
    header('Location: ../index.html?error=inactive_account');
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) ($row['id'] ?? 0),
    'role' => $role,
    'name' => $name,
    'email' => $dbEmail,
];

if ($role === 'admin') {
    header('Location: ../Frontend/admin_dashboard/dashboard.php');
    exit;
}

if ($role === 'teacher') {
    header('Location: ../Frontend/teacher_dashboard/dashboard.php');
    exit;
}

if ($role === 'student') {
    header('Location: ../Frontend/student_dashboard/dashboard.php');
    exit;
}

header('Location: ../index.html?error=inactive_account');
exit;
