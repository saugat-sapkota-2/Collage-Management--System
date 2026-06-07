<?php

declare(strict_types=1);

require __DIR__ . '/../../Backend/bootstrap.php';
require __DIR__ . '/../../Backend/db.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: ../../index.html');
    exit;
}

$sessionEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
if ($sessionEmail === '') {
    $_SESSION = [];
    session_destroy();
    header('Location: ../../index.html?error=session_expired');
    exit;
}

try {
    $pdo = dbConnection($config);
    $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $sessionEmail]);
    $row = $stmt->fetch();

    if ($row === null) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../../index.html?error=session_expired');
        exit;
    }

    $isActive = (int) ($row['is_active'] ?? 0) === 1;
    $role = strtolower(trim((string) ($row['role'] ?? '')));
    $name = trim((string) ($row['name'] ?? ''));
    $email = strtolower(trim((string) ($row['email'] ?? '')));

    if (!$isActive || $role === '' || $name === '' || $email === '') {
        $_SESSION = [];
        session_destroy();
        header('Location: ../../index.html?error=inactive_account');
        exit;
    }

    $_SESSION['user'] = [
        'id' => (int) ($row['id'] ?? 0),
        'role' => $role,
        'name' => $name,
        'email' => $email,
    ];
} catch (Throwable $e) {
    header('Location: ../../index.html?error=server');
    exit;
}

if ($_SESSION['user']['role'] !== 'student') {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: ../admin_dashboard/dashboard.php');
        exit;
    }

    if ($_SESSION['user']['role'] === 'teacher') {
        header('Location: ../teacher_dashboard/dashboard.php');
        exit;
    }

    $_SESSION = [];
    session_destroy();
    header('Location: ../../index.html?error=session_expired');
    exit;
}

$name = $_SESSION['user']['name'];
$email = $_SESSION['user']['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="student.css">
</head>
<body>
    <main class="panel">
        <div class="badge">Student Dashboard</div>
        <h1>Welcome, <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>You are signed in as <strong>Student</strong>.</p>
        <div class="details">
            <p><strong>Role:</strong> Student</p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Status:</strong> Database-backed login is active.</p>
        </div>
        <div class="actions">
            <a class="logout" href="../../Backend/logout.php">Logout</a>
        </div>
    </main>
</body>
</html>
