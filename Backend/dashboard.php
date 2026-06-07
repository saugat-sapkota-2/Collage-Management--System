<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: ../index.html');
    exit;
}

$role = strtolower(trim((string) ($_SESSION['user']['role'] ?? '')));

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

$_SESSION = [];
session_destroy();
header('Location: ../index.html?error=session_expired');
exit;
