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

if ($_SESSION['user']['role'] !== 'admin') {
    if ($_SESSION['user']['role'] === 'teacher') {
        header('Location: ../teacher_dashboard/dashboard.php');
        exit;
    }

    if ($_SESSION['user']['role'] === 'student') {
        header('Location: ../student_dashboard/dashboard.php');
        exit;
    }

    $_SESSION = [];
    session_destroy();
    header('Location: ../../index.html?error=session_expired');
    exit;
}

$activeUserName = $_SESSION['user']['name'];
$activeUserEmail = $_SESSION['user']['email'];

$managementData = [
    'students' => [],
    'teachers' => [],
    'courses' => [],
    'courseTeachers' => [],
];

try {
    $studentRows = $pdo->query(
        'SELECT
            s.id,
            s.full_name,
            s.phone,
            s.address,
            s.gender,
            s.date_of_birth,
            s.semester,
            s.profile_photo,
            s.is_active,
            u.username,
            u.email,
            c.id AS course_id,
            c.course_name,
            c.course_code,
            COALESCE(att.present_count, 0) AS present_count,
            COALESCE(att.absent_count, 0) AS absent_count,
            COALESCE(att.leave_count, 0) AS leave_count,
            COALESCE(fee.amount_due_total, 0) AS amount_due_total,
            COALESCE(fee.amount_paid_total, 0) AS amount_paid_total,
            COALESCE(fee.status_label, "pending") AS fee_status
         FROM students s
         INNER JOIN users u ON u.id = s.user_id
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN (
            SELECT student_id,
                   SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) AS present_count,
                   SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) AS absent_count,
                   SUM(CASE WHEN status = "leave" THEN 1 ELSE 0 END) AS leave_count
            FROM student_attendance
            GROUP BY student_id
         ) att ON att.student_id = s.id
         LEFT JOIN (
            SELECT student_id,
                   SUM(amount_due) AS amount_due_total,
                   SUM(amount_paid) AS amount_paid_total,
                   CASE
                       WHEN SUM(amount_due) > 0 AND SUM(amount_paid) >= SUM(amount_due) THEN "paid"
                       WHEN SUM(amount_paid) > 0 THEN "partial"
                       ELSE "pending"
                   END AS status_label
            FROM student_fees
            GROUP BY student_id
         ) fee ON fee.student_id = s.id
         ORDER BY s.full_name ASC'
    )->fetchAll();

    $teacherRows = $pdo->query(
        'SELECT
            t.id,
            t.full_name,
            t.phone,
            t.department,
            t.qualification,
            t.joining_date,
            t.profile_photo,
            t.is_active,
            u.username,
            u.email,
            COALESCE(course_count.assigned_courses, 0) AS assigned_courses
         FROM teachers t
         INNER JOIN users u ON u.id = t.user_id
         LEFT JOIN (
            SELECT teacher_id, COUNT(*) AS assigned_courses
            FROM course_teachers
            GROUP BY teacher_id
         ) course_count ON course_count.teacher_id = t.id
         ORDER BY t.full_name ASC'
    )->fetchAll();

    $courseRows = $pdo->query(
        'SELECT
            c.id,
            c.course_name,
            c.course_code,
            c.duration,
            c.semester_count,
            c.total_fees,
            c.description,
            c.is_active,
            COALESCE(student_count.total_students, 0) AS total_students,
            COALESCE(teacher_count.total_teachers, 0) AS total_teachers
         FROM courses c
         LEFT JOIN (
            SELECT course_id, COUNT(*) AS total_students
            FROM students
            WHERE course_id IS NOT NULL
            GROUP BY course_id
         ) student_count ON student_count.course_id = c.id
         LEFT JOIN (
            SELECT course_id, COUNT(*) AS total_teachers
            FROM course_teachers
            GROUP BY course_id
         ) teacher_count ON teacher_count.course_id = c.id
         ORDER BY c.course_name ASC'
    )->fetchAll();

    $courseTeacherRows = $pdo->query(
        'SELECT
            ct.course_id,
            t.id AS teacher_id,
            t.full_name,
            u.username,
            u.email
         FROM course_teachers ct
         INNER JOIN teachers t ON t.id = ct.teacher_id
         INNER JOIN users u ON u.id = t.user_id
         ORDER BY t.full_name ASC'
    )->fetchAll();

    $managementData['students'] = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'gender' => (string) ($row['gender'] ?? 'other'),
            'date_of_birth' => (string) ($row['date_of_birth'] ?? ''),
            'semester' => (string) ($row['semester'] ?? ''),
            'profile_photo' => (string) ($row['profile_photo'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'course_id' => isset($row['course_id']) ? (int) $row['course_id'] : null,
            'course_name' => (string) ($row['course_name'] ?? ''),
            'course_code' => (string) ($row['course_code'] ?? ''),
            'attendance' => [
                'present' => (int) ($row['present_count'] ?? 0),
                'absent' => (int) ($row['absent_count'] ?? 0),
                'leave' => (int) ($row['leave_count'] ?? 0),
            ],
            'fees' => [
                'due' => (float) ($row['amount_due_total'] ?? 0),
                'paid' => (float) ($row['amount_paid_total'] ?? 0),
                'status' => (string) ($row['fee_status'] ?? 'pending'),
            ],
            'credentials' => [
                'username' => (string) ($row['username'] ?? ''),
                'password' => '',
                'password_available' => false,
            ],
        ];
    }, $studentRows);

    $managementData['teachers'] = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'department' => (string) ($row['department'] ?? ''),
            'qualification' => (string) ($row['qualification'] ?? ''),
            'joining_date' => (string) ($row['joining_date'] ?? ''),
            'profile_photo' => (string) ($row['profile_photo'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'assigned_courses' => (int) ($row['assigned_courses'] ?? 0),
            'credentials' => [
                'username' => (string) ($row['username'] ?? ''),
                'password' => '',
                'password_available' => false,
            ],
        ];
    }, $teacherRows);

    $courseTeacherMap = [];
    foreach ($courseTeacherRows as $row) {
        $courseId = (int) ($row['course_id'] ?? 0);
        $courseTeacherMap[$courseId][] = [
            'id' => (int) ($row['teacher_id'] ?? 0),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
        ];
    }

    $managementData['courses'] = array_map(static function (array $row) use ($courseTeacherMap): array {
        $courseId = (int) $row['id'];

        return [
            'id' => $courseId,
            'course_name' => (string) $row['course_name'],
            'course_code' => (string) $row['course_code'],
            'duration' => (string) $row['duration'],
            'semester_count' => (int) $row['semester_count'],
            'total_fees' => (float) $row['total_fees'],
            'description' => (string) ($row['description'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'total_students' => (int) ($row['total_students'] ?? 0),
            'total_teachers' => (int) ($row['total_teachers'] ?? 0),
            'assigned_teachers' => $courseTeacherMap[$courseId] ?? [],
        ];
    }, $courseRows);
} catch (Throwable $e) {
    $managementData['students'] = [];
    $managementData['teachers'] = [];
    $managementData['courses'] = [];
}

$managementData['summary'] = [
    'students' => count($managementData['students']),
    'teachers' => count($managementData['teachers']),
    'courses' => count($managementData['courses']),
    'activeStudents' => count(array_filter($managementData['students'], static fn (array $student): bool => ($student['is_active'] ?? false) === true)),
    'activeTeachers' => count(array_filter($managementData['teachers'], static fn (array $teacher): bool => ($teacher['is_active'] ?? false) === true)),
    'activeCourses' => count(array_filter($managementData['courses'], static fn (array $course): bool => ($course['is_active'] ?? false) === true)),
];

function moduleStatusLabel(bool $active): string
{
    return $active ? 'Active' : 'Inactive';
}

function moduleStatusClass(bool $active): string
{
    return $active ? 'status-active' : 'status-inactive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Center</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="management.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body data-sidebar-open="false">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3 2.5 8 12 13l9.5-5L12 3Z"></path>
                        <path d="M6 10.5V16l6 3 6-3v-5.5"></path>
                    </svg>
                </div>
                <div>
                    <div class="brand-title">College MS</div>
                    <div class="brand-subtitle">Admin Panel</div>
                </div>
            </div>

            <nav class="nav-menu">
                <a class="nav-item" href="dashboard.php"><span class="nav-icon">▣</span><span>Dashboard</span></a>
                <a class="nav-item active" href="#students"><span class="nav-icon">◫</span><span>Students</span></a>
                <a class="nav-item" href="#teachers"><span class="nav-icon">◫</span><span>Teachers</span></a>
                <a class="nav-item" href="#courses"><span class="nav-icon">▭</span><span>Courses</span></a>
                <a class="nav-item" href="#attendance"><span class="nav-icon">◔</span><span>Attendance</span></a>
                <a class="nav-item" href="#finance"><span class="nav-icon">$</span><span>Finance</span></a>
                <a class="nav-item" href="#reports"><span class="nav-icon">▦</span><span>Reports</span></a>
                <a class="nav-item" href="#timetable"><span class="nav-icon">▤</span><span>Timetable</span></a>
                <a class="nav-item" href="#notices"><span class="nav-icon">◌</span><span>Notices</span></a>
            </nav>
        </aside>

        <div class="backdrop" data-close-sidebar="true" hidden></div>

        <div class="app-main">
            <header class="topbar management-topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Toggle sidebar" data-toggle-sidebar="true">
                    <span class="icon-lines"></span>
                </button>

                <button class="icon-button close-sidebar" type="button" aria-label="Toggle sidebar" data-close-sidebar="true">✕</button>

                <div class="management-heading">
                    <span class="management-eyebrow">Admin Management</span>
                    <h1>Student, Teacher & Course System</h1>
                    <p>Ready for future PHP/MySQL data binding without changing the layout.</p>
                </div>

                <div class="topbar-actions">
                    <button class="icon-button" type="button" aria-label="Dark mode placeholder">☾</button>
                    <button class="icon-button notification-button" type="button" aria-label="Notifications">
                        <span>🔔</span>
                        <span class="notification-dot" aria-hidden="true"></span>
                    </button>
                    <div class="profile-card">
                        <div class="profile-meta">
                            <strong><?php echo htmlspecialchars($activeUserName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Admin</span>
                        </div>
                        <div class="profile-avatar" aria-hidden="true">A</div>
                        <span class="profile-caret">▾</span>
                    </div>
                </div>
            </header>

            <main class="management-page" id="managementRoot">
                <section class="management-overview">
                    <article class="overview-card">
                        <span class="overview-label">Total Students</span>
                        <strong data-summary-value="students">0</strong>
                        <small data-summary-subtitle="activeStudents">0 active</small>
                    </article>
                    <article class="overview-card">
                        <span class="overview-label">Total Teachers</span>
                        <strong data-summary-value="teachers">0</strong>
                        <small data-summary-subtitle="activeTeachers">0 active</small>
                    </article>
                    <article class="overview-card">
                        <span class="overview-label">Total Courses</span>
                        <strong data-summary-value="courses">0</strong>
                        <small data-summary-subtitle="activeCourses">0 active</small>
                    </article>
                </section>

                <nav class="module-switcher" aria-label="Management modules">
                    <button class="module-switcher-button active" type="button" data-module-switch="students">Students</button>
                    <button class="module-switcher-button" type="button" data-module-switch="teachers">Teachers</button>
                    <button class="module-switcher-button" type="button" data-module-switch="courses">Courses</button>
                </nav>

                <section class="module-section active" data-module="students" id="students">
                    <div class="module-header">
                        <div>
                            <h2>Student Management</h2>
                            <p>Add, edit, search, filter, activate, deactivate, and manage login credentials.</p>
                        </div>
                        <div class="module-actions">
                            <button class="primary-button" type="button" data-open-modal="student">Add Student</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-panel">
                            <div class="table-toolbar">
                                <label class="toolbar-search">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search students..." data-search-target="students">
                                </label>
                                <select class="toolbar-select" data-filter-target="students">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="students"></tbody>
                                </table>
                                <div class="empty-state hidden" data-empty-state="students">
                                    <strong>No students found</strong>
                                    <span>Add student records or connect the database to load them automatically.</span>
                                </div>
                            </div>
                        </article>

                        <aside class="panel-card detail-panel" data-detail-panel="students">
                            <div class="panel-header">
                                <h3>Student Profile</h3>
                                <span class="panel-subtitle">Personal, course, attendance, fee, and login details</span>
                            </div>
                            <div class="detail-placeholder" data-placeholder="students">
                                Select a student row to view profile and login credentials.
                            </div>
                            <div class="detail-content hidden" data-detail-content="students">
                                <div class="detail-avatar-row">
                                    <div class="detail-avatar" data-avatar="students">S</div>
                                    <div>
                                        <strong data-detail-title="students">Student Name</strong>
                                        <span data-detail-subtitle="students">Username</span>
                                    </div>
                                </div>

                                <div class="info-grid">
                                    <div>
                                        <span>Personal Information</span>
                                        <p data-detail-personal="students">-</p>
                                    </div>
                                    <div>
                                        <span>Course Information</span>
                                        <p data-detail-course="students">-</p>
                                    </div>
                                    <div>
                                        <span>Attendance Summary</span>
                                        <p data-detail-attendance="students">-</p>
                                    </div>
                                    <div>
                                        <span>Fee Status</span>
                                        <p data-detail-fees="students">-</p>
                                    </div>
                                </div>

                                <div class="credential-card">
                                    <div class="credential-label">MYSQL USERNAME</div>
                                    <div class="credential-value" data-credential-username="students">-</div>
                                    <div class="credential-label credential-spacer">MYSQL PASSWORD</div>
                                    <div class="credential-value password-mask" data-credential-password="students">********</div>
                                    <div class="credential-actions">
                                        <button type="button" data-copy-username="students">Copy Username</button>
                                        <button type="button" data-toggle-password="students">Show Password</button>
                                        <button type="button" data-copy-password="students">Copy Password</button>
                                        <button type="button" data-reset-password="students">Reset Password</button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="module-section" data-module="teachers" id="teachers">
                    <div class="module-header">
                        <div>
                            <h2>Teacher Management</h2>
                            <p>Maintain teacher accounts, departments, qualifications, and credentials.</p>
                        </div>
                        <div class="module-actions">
                            <button class="primary-button" type="button" data-open-modal="teacher">Add Teacher</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-panel">
                            <div class="table-toolbar">
                                <label class="toolbar-search">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search teachers..." data-search-target="teachers">
                                </label>
                                <select class="toolbar-select" data-filter-target="teachers">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Teacher ID</th>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="teachers"></tbody>
                                </table>
                                <div class="empty-state hidden" data-empty-state="teachers">
                                    <strong>No teachers found</strong>
                                    <span>Add teacher records or connect the database to load them automatically.</span>
                                </div>
                            </div>
                        </article>

                        <aside class="panel-card detail-panel" data-detail-panel="teachers">
                            <div class="panel-header">
                                <h3>Teacher Profile</h3>
                                <span class="panel-subtitle">Department, qualification, and login credentials</span>
                            </div>
                            <div class="detail-placeholder" data-placeholder="teachers">
                                Select a teacher row to view profile and login credentials.
                            </div>
                            <div class="detail-content hidden" data-detail-content="teachers">
                                <div class="detail-avatar-row">
                                    <div class="detail-avatar" data-avatar="teachers">T</div>
                                    <div>
                                        <strong data-detail-title="teachers">Teacher Name</strong>
                                        <span data-detail-subtitle="teachers">Username</span>
                                    </div>
                                </div>

                                <div class="info-grid">
                                    <div>
                                        <span>Personal Information</span>
                                        <p data-detail-personal="teachers">-</p>
                                    </div>
                                    <div>
                                        <span>Department & Qualification</span>
                                        <p data-detail-course="teachers">-</p>
                                    </div>
                                    <div>
                                        <span>Joining Date</span>
                                        <p data-detail-attendance="teachers">-</p>
                                    </div>
                                    <div>
                                        <span>Assigned Courses</span>
                                        <p data-detail-fees="teachers">-</p>
                                    </div>
                                </div>

                                <div class="credential-card">
                                    <div class="credential-label">MYSQL USERNAME</div>
                                    <div class="credential-value" data-credential-username="teachers">-</div>
                                    <div class="credential-label credential-spacer">MYSQL PASSWORD</div>
                                    <div class="credential-value password-mask" data-credential-password="teachers">********</div>
                                    <div class="credential-actions">
                                        <button type="button" data-copy-username="teachers">Copy Username</button>
                                        <button type="button" data-toggle-password="teachers">Show Password</button>
                                        <button type="button" data-copy-password="teachers">Copy Password</button>
                                        <button type="button" data-reset-password="teachers">Reset Password</button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="module-section" data-module="courses" id="courses">
                    <div class="module-header">
                        <div>
                            <h2>Course Management</h2>
                            <p>Create, update, search, and review course details with assigned students and teachers.</p>
                        </div>
                        <div class="module-actions">
                            <button class="primary-button" type="button" data-open-modal="course">Add Course</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-panel">
                            <div class="table-toolbar">
                                <label class="toolbar-search">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search courses..." data-search-target="courses">
                                </label>
                                <select class="toolbar-select" data-filter-target="courses">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Course ID</th>
                                            <th>Course Name</th>
                                            <th>Course Code</th>
                                            <th>Duration</th>
                                            <th>Total Fees</th>
                                            <th>Total Students</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="courses"></tbody>
                                </table>
                                <div class="empty-state hidden" data-empty-state="courses">
                                    <strong>No courses found</strong>
                                    <span>Add course records or connect the database to load them automatically.</span>
                                </div>
                            </div>
                        </article>

                        <aside class="panel-card detail-panel" data-detail-panel="courses">
                            <div class="panel-header">
                                <h3>Course Details</h3>
                                <span class="panel-subtitle">Course information, assigned students, teachers, and fees</span>
                            </div>
                            <div class="detail-placeholder" data-placeholder="courses">
                                Select a course row to view course details.
                            </div>
                            <div class="detail-content hidden" data-detail-content="courses">
                                <div class="detail-avatar-row">
                                    <div class="detail-avatar" data-avatar="courses">C</div>
                                    <div>
                                        <strong data-detail-title="courses">Course Name</strong>
                                        <span data-detail-subtitle="courses">Course Code</span>
                                    </div>
                                </div>

                                <div class="info-grid">
                                    <div>
                                        <span>Course Information</span>
                                        <p data-detail-personal="courses">-</p>
                                    </div>
                                    <div>
                                        <span>Assigned Students</span>
                                        <p data-detail-course="courses">-</p>
                                    </div>
                                    <div>
                                        <span>Assigned Teachers</span>
                                        <p data-detail-attendance="courses">-</p>
                                    </div>
                                    <div>
                                        <span>Course Fees</span>
                                        <p data-detail-fees="courses">-</p>
                                    </div>
                                </div>

                                <div class="assigned-list" data-course-teachers="courses"></div>
                            </div>
                        </aside>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="modal-backdrop hidden" data-modal-backdrop></div>
    <div class="modal hidden" data-modal>
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 data-modal-title>Add Student</h3>
                    <p data-modal-subtitle>Fill in the fields below to create a record.</p>
                </div>
                <button type="button" class="icon-button modal-close" data-modal-close aria-label="Close modal">✕</button>
            </div>

            <form class="modal-form" data-modal-form>
                <div class="form-grid" data-form-fields="students"></div>
                <div class="form-grid hidden" data-form-fields="teachers"></div>
                <div class="form-grid hidden" data-form-fields="courses"></div>

                <div class="modal-footer">
                    <button type="button" class="secondary-button" data-modal-close>Cancel</button>
                    <button type="submit" class="primary-button">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.managementData = <?php echo json_encode($managementData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        window.managementConfig = {
            activeUser: <?php echo json_encode(['name' => $activeUserName, 'email' => $activeUserEmail], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <script src="management.js" defer></script>
</body>
</html><?php

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

if ($_SESSION['user']['role'] !== 'admin') {
    if ($_SESSION['user']['role'] === 'teacher') {
        header('Location: ../teacher_dashboard/dashboard.php');
        exit;
    }

    if ($_SESSION['user']['role'] === 'student') {
        header('Location: ../student_dashboard/dashboard.php');
        exit;
    }

    $_SESSION = [];
    session_destroy();
    header('Location: ../../index.html?error=session_expired');
    exit;
}

$name = $_SESSION['user']['name'];
$email = $_SESSION['user']['email'];
$avatarLetter = strtoupper(substr($name, 0, 1));

$managementData = [
    'students' => [],
    'teachers' => [],
    'courses' => [],
];

try {
    $studentsStmt = $pdo->query(
        'SELECT
            s.id AS student_id,
            s.full_name,
            u.username,
            u.email,
            COALESCE(u.phone, s.phone) AS phone,
            s.address,
            s.gender,
            s.date_of_birth,
            s.semester,
            s.profile_photo,
            s.is_active,
            s.course_id,
            c.course_name,
            c.course_code,
            COALESCE(att.present_count, 0) AS present_count,
            COALESCE(att.absent_count, 0) AS absent_count,
            COALESCE(att.leave_count, 0) AS leave_count,
            COALESCE(fees.total_due, 0) AS total_due,
            COALESCE(fees.total_paid, 0) AS total_paid,
            COALESCE(fees.fee_status, ''pending'') AS fee_status
         FROM students s
         INNER JOIN users u ON u.id = s.user_id
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN (
            SELECT
                student_id,
                SUM(CASE WHEN status = ''present'' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN status = ''absent'' THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN status = ''leave'' THEN 1 ELSE 0 END) AS leave_count
            FROM student_attendance
            GROUP BY student_id
         ) att ON att.student_id = s.id
         LEFT JOIN (
            SELECT
                student_id,
                SUM(amount_due) AS total_due,
                SUM(amount_paid) AS total_paid,
                CASE
                    WHEN SUM(amount_due) IS NULL OR SUM(amount_due) = 0 THEN ''pending''
                    WHEN SUM(amount_paid) >= SUM(amount_due) THEN ''paid''
                    WHEN SUM(amount_paid) > 0 THEN ''partial''
                    ELSE ''pending''
                END AS fee_status
            FROM student_fees
            GROUP BY student_id
         ) fees ON fees.student_id = s.id
         ORDER BY s.created_at DESC'
    );

    $teacherStmt = $pdo->query(
        'SELECT
            t.id AS teacher_id,
            t.full_name,
            u.username,
            u.email,
            COALESCE(u.phone, t.phone) AS phone,
            t.department,
            t.qualification,
            t.joining_date,
            t.profile_photo,
            t.is_active,
            COALESCE(course_map.course_names, '''') AS course_names,
            COALESCE(course_map.course_count, 0) AS course_count
         FROM teachers t
         INNER JOIN users u ON u.id = t.user_id
         LEFT JOIN (
            SELECT
                ct.teacher_id,
                GROUP_CONCAT(c.course_name ORDER BY c.course_name SEPARATOR '', '') AS course_names,
                COUNT(*) AS course_count
            FROM course_teachers ct
            INNER JOIN courses c ON c.id = ct.course_id
            GROUP BY ct.teacher_id
         ) course_map ON course_map.teacher_id = t.id
         ORDER BY t.created_at DESC'
    );

    $courseStmt = $pdo->query(
        'SELECT
            c.id AS course_id,
            c.course_name,
            c.course_code,
            c.duration,
            c.semester_count,
            c.total_fees,
            c.description,
            c.is_active,
            COALESCE(student_counts.total_students, 0) AS total_students,
            COALESCE(teacher_counts.total_teachers, 0) AS total_teachers
         FROM courses c
         LEFT JOIN (
            SELECT course_id, COUNT(*) AS total_students
            FROM students
            WHERE course_id IS NOT NULL
            GROUP BY course_id
         ) student_counts ON student_counts.course_id = c.id
         LEFT JOIN (
            SELECT ct.course_id, COUNT(*) AS total_teachers
            FROM course_teachers ct
            GROUP BY ct.course_id
         ) teacher_counts ON teacher_counts.course_id = c.id
         ORDER BY c.created_at DESC'
    );

    $managementData['students'] = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['student_id'] ?? 0),
            'fullName' => (string) ($row['full_name'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'gender' => (string) ($row['gender'] ?? 'other'),
            'dateOfBirth' => (string) ($row['date_of_birth'] ?? ''),
            'semester' => (string) ($row['semester'] ?? ''),
            'profilePhoto' => (string) ($row['profile_photo'] ?? ''),
            'status' => (int) ($row['is_active'] ?? 0) === 1 ? 'active' : 'inactive',
            'courseId' => (int) ($row['course_id'] ?? 0),
            'courseName' => (string) ($row['course_name'] ?? ''),
            'courseCode' => (string) ($row['course_code'] ?? ''),
            'attendance' => [
                'present' => (int) ($row['present_count'] ?? 0),
                'absent' => (int) ($row['absent_count'] ?? 0),
                'leave' => (int) ($row['leave_count'] ?? 0),
            ],
            'fees' => [
                'totalDue' => (float) ($row['total_due'] ?? 0),
                'totalPaid' => (float) ($row['total_paid'] ?? 0),
                'status' => (string) ($row['fee_status'] ?? 'pending'),
            ],
            'credentials' => [
                'password' => null,
                'passwordVisible' => false,
            ],
            'viewState' => [
                'passwordVisible' => false,
            ],
        ];
    }, $studentsStmt->fetchAll());

    $managementData['teachers'] = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['teacher_id'] ?? 0),
            'fullName' => (string) ($row['full_name'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'department' => (string) ($row['department'] ?? ''),
            'qualification' => (string) ($row['qualification'] ?? ''),
            'joiningDate' => (string) ($row['joining_date'] ?? ''),
            'profilePhoto' => (string) ($row['profile_photo'] ?? ''),
            'status' => (int) ($row['is_active'] ?? 0) === 1 ? 'active' : 'inactive',
            'courseNames' => (string) ($row['course_names'] ?? ''),
            'courseCount' => (int) ($row['course_count'] ?? 0),
            'credentials' => [
                'password' => null,
                'passwordVisible' => false,
            ],
            'viewState' => [
                'passwordVisible' => false,
            ],
        ];
    }, $teacherStmt->fetchAll());

    $managementData['courses'] = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['course_id'] ?? 0),
            'courseName' => (string) ($row['course_name'] ?? ''),
            'courseCode' => (string) ($row['course_code'] ?? ''),
            'duration' => (string) ($row['duration'] ?? ''),
            'semesterCount' => (int) ($row['semester_count'] ?? 0),
            'totalFees' => (float) ($row['total_fees'] ?? 0),
            'description' => (string) ($row['description'] ?? ''),
            'status' => (int) ($row['is_active'] ?? 0) === 1 ? 'active' : 'inactive',
            'totalStudents' => (int) ($row['total_students'] ?? 0),
            'totalTeachers' => (int) ($row['total_teachers'] ?? 0),
        ];
    }, $courseStmt->fetchAll());
} catch (Throwable $e) {
    $managementData = [
        'students' => [],
        'teachers' => [],
        'courses' => [],
    ];
}

$managementData['summary'] = [
    'students' => count($managementData['students']),
    'teachers' => count($managementData['teachers']),
    'courses' => count($managementData['courses']),
    'activeStudents' => count(array_filter($managementData['students'], static fn (array $item): bool => ($item['status'] ?? '') === 'active')),
    'activeTeachers' => count(array_filter($managementData['teachers'], static fn (array $item): bool => ($item['status'] ?? '') === 'active')),
    'activeCourses' => count(array_filter($managementData['courses'], static fn (array $item): bool => ($item['status'] ?? '') === 'active')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Center</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="management.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body data-sidebar-open="false">
    <div class="app-shell management-shell">
        <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3 2.5 8 12 13l9.5-5L12 3Z"></path>
                        <path d="M6 10.5V16l6 3 6-3v-5.5"></path>
                    </svg>
                </div>
                <div>
                    <div class="brand-title">College MS</div>
                    <div class="brand-subtitle">Admin Panel</div>
                </div>
            </div>

            <nav class="nav-menu">
                <a class="nav-item" href="dashboard.php"><span class="nav-icon">▣</span><span>Dashboard</span></a>
                <a class="nav-item active" href="management.php"><span class="nav-icon">◫</span><span>Management</span></a>
                <a class="nav-item" href="management.php#students"><span class="nav-icon">👥</span><span>Students</span></a>
                <a class="nav-item" href="management.php#teachers"><span class="nav-icon">🎓</span><span>Teachers</span></a>
                <a class="nav-item" href="management.php#courses"><span class="nav-icon">▭</span><span>Courses</span></a>
                <a class="nav-item" href="#attendance"><span class="nav-icon">◔</span><span>Attendance</span></a>
                <a class="nav-item" href="#finance"><span class="nav-icon">$</span><span>Finance</span></a>
                <a class="nav-item" href="#reports"><span class="nav-icon">▦</span><span>Reports</span></a>
                <a class="nav-item" href="#timetable"><span class="nav-icon">▤</span><span>Timetable</span></a>
                <a class="nav-item" href="#notices"><span class="nav-icon">◌</span><span>Notices</span></a>
            </nav>
        </aside>

        <div class="backdrop" data-close-sidebar="true" hidden></div>

        <div class="app-main">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Toggle sidebar" data-toggle-sidebar="true">
                    <span class="icon-lines"></span>
                </button>

                <button class="icon-button close-sidebar" type="button" aria-label="Collapse sidebar" data-close-sidebar="true">✕</button>

                <label class="search-bar management-search" aria-label="Search management">
                    <span class="search-icon">⌕</span>
                    <input type="search" placeholder="Search students, teachers, courses..." data-global-search="true" />
                </label>

                <div class="topbar-actions">
                    <button class="icon-button theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle="true">☾</button>
                    <button class="icon-button notification-button" type="button" aria-label="Notifications">
                        <span>🔔</span>
                        <span class="notification-dot" aria-hidden="true"></span>
                    </button>
                    <div class="profile-card" aria-label="Admin profile">
                        <div class="profile-meta">
                            <strong><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Admin</span>
                        </div>
                        <div class="profile-avatar" aria-hidden="true"><?php echo htmlspecialchars($avatarLetter, ENT_QUOTES, 'UTF-8'); ?></div>
                        <span class="profile-caret">▾</span>
                    </div>
                </div>
            </header>

            <main class="management-page" id="managementPage" data-view="management">
                <section class="management-hero panel-card">
                    <div class="hero-copy">
                        <p class="eyebrow">Management Center</p>
                        <h1>Student, Teacher & Course Management</h1>
                        <p>Everything is wired for database-backed CRUD, credentials, profile views, filtering, and future API integration.</p>
                    </div>

                    <div class="hero-stats">
                        <article class="mini-stat">
                            <span>Students</span>
                            <strong data-summary-value="students"><?php echo (int) $managementData['summary']['students']; ?></strong>
                        </article>
                        <article class="mini-stat">
                            <span>Teachers</span>
                            <strong data-summary-value="teachers"><?php echo (int) $managementData['summary']['teachers']; ?></strong>
                        </article>
                        <article class="mini-stat">
                            <span>Courses</span>
                            <strong data-summary-value="courses"><?php echo (int) $managementData['summary']['courses']; ?></strong>
                        </article>
                        <article class="mini-stat">
                            <span>Active Records</span>
                            <strong data-summary-value="activeRecords"><?php echo (int) ($managementData['summary']['activeStudents'] + $managementData['summary']['activeTeachers'] + $managementData['summary']['activeCourses']); ?></strong>
                        </article>
                    </div>
                </section>

                <section class="module-tabs" aria-label="Management modules">
                    <button class="module-tab active" type="button" data-tab-target="students">Students</button>
                    <button class="module-tab" type="button" data-tab-target="teachers">Teachers</button>
                    <button class="module-tab" type="button" data-tab-target="courses">Courses</button>
                </section>

                <section class="module-pane is-active" data-module-pane="students" id="students">
                    <div class="module-header">
                        <div>
                            <h2>Student Management</h2>
                            <p>Add, edit, search, filter, activate, deactivate, and review credentials.</p>
                        </div>
                        <div class="module-actions">
                            <select class="module-filter" data-filter="students">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="primary-action" type="button" data-open-modal="students">Add Student</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-card">
                            <div class="module-toolbar">
                                <label class="module-search" aria-label="Search students">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search students..." data-search="students" />
                                </label>
                            </div>
                            <div class="table-wrap">
                                <table class="module-table">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="students"></tbody>
                                </table>
                            </div>
                        </article>

                        <aside class="panel-card detail-card" data-detail-card="students">
                            <div class="detail-empty" data-empty-detail="students">
                                <strong>No student selected</strong>
                                <span>Choose a record to view personal information, attendance summary, fee status, and login credentials.</span>
                            </div>
                            <div class="detail-content" data-detail-content="students" hidden>
                                <div class="detail-header">
                                    <div class="avatar-badge" data-avatar="students"></div>
                                    <div>
                                        <h3 data-detail-name="students"></h3>
                                        <p data-detail-meta="students"></p>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <h4>Personal Information</h4>
                                    <dl class="detail-list">
                                        <div><dt>Phone</dt><dd data-detail-phone="students"></dd></div>
                                        <div><dt>Address</dt><dd data-detail-address="students"></dd></div>
                                        <div><dt>Gender</dt><dd data-detail-gender="students"></dd></div>
                                        <div><dt>Date of Birth</dt><dd data-detail-dob="students"></dd></div>
                                    </dl>
                                </div>
                                <div class="detail-group">
                                    <h4>Course Information</h4>
                                    <dl class="detail-list">
                                        <div><dt>Course</dt><dd data-detail-course="students"></dd></div>
                                        <div><dt>Semester</dt><dd data-detail-semester="students"></dd></div>
                                    </dl>
                                </div>
                                <div class="detail-group split-grid">
                                    <div>
                                        <h4>Attendance Summary</h4>
                                        <div class="summary-strip" data-attendance-summary="students"></div>
                                    </div>
                                    <div>
                                        <h4>Fee Status</h4>
                                        <div class="summary-strip" data-fee-summary="students"></div>
                                    </div>
                                </div>
                                <div class="credential-panel">
                                    <div class="credential-header">
                                        <div>
                                            <span>MYSQL USERNAME</span>
                                            <strong data-credential-username="students"></strong>
                                        </div>
                                        <div class="credential-actions">
                                            <button type="button" data-copy="students-username">Copy Username</button>
                                        </div>
                                    </div>
                                    <div class="credential-body">
                                        <span>MYSQL PASSWORD</span>
                                        <strong data-credential-password="students"></strong>
                                    </div>
                                    <div class="credential-actions stacked">
                                        <button type="button" data-toggle-password="students">Show Password</button>
                                        <button type="button" data-copy="students-password">Copy Password</button>
                                        <button type="button" data-reset-password="students">Reset Password</button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="module-pane" data-module-pane="teachers" id="teachers">
                    <div class="module-header">
                        <div>
                            <h2>Teacher Management</h2>
                            <p>Add, edit, search, filter, activate, deactivate, and manage credentials.</p>
                        </div>
                        <div class="module-actions">
                            <select class="module-filter" data-filter="teachers">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="primary-action" type="button" data-open-modal="teachers">Add Teacher</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-card">
                            <div class="module-toolbar">
                                <label class="module-search" aria-label="Search teachers">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search teachers..." data-search="teachers" />
                                </label>
                            </div>
                            <div class="table-wrap">
                                <table class="module-table">
                                    <thead>
                                        <tr>
                                            <th>Teacher ID</th>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="teachers"></tbody>
                                </table>
                            </div>
                        </article>

                        <aside class="panel-card detail-card" data-detail-card="teachers">
                            <div class="detail-empty" data-empty-detail="teachers">
                                <strong>No teacher selected</strong>
                                <span>Select a teacher to view profile information, qualifications, assigned courses, and credentials.</span>
                            </div>
                            <div class="detail-content" data-detail-content="teachers" hidden>
                                <div class="detail-header">
                                    <div class="avatar-badge" data-avatar="teachers"></div>
                                    <div>
                                        <h3 data-detail-name="teachers"></h3>
                                        <p data-detail-meta="teachers"></p>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <h4>Teacher Profile</h4>
                                    <dl class="detail-list">
                                        <div><dt>Phone</dt><dd data-detail-phone="teachers"></dd></div>
                                        <div><dt>Department</dt><dd data-detail-department="teachers"></dd></div>
                                        <div><dt>Qualification</dt><dd data-detail-qualification="teachers"></dd></div>
                                        <div><dt>Joining Date</dt><dd data-detail-joining="teachers"></dd></div>
                                    </dl>
                                </div>
                                <div class="detail-group">
                                    <h4>Assigned Courses</h4>
                                    <div class="summary-strip" data-course-summary="teachers"></div>
                                </div>
                                <div class="credential-panel">
                                    <div class="credential-header">
                                        <div>
                                            <span>MYSQL USERNAME</span>
                                            <strong data-credential-username="teachers"></strong>
                                        </div>
                                        <div class="credential-actions">
                                            <button type="button" data-copy="teachers-username">Copy Username</button>
                                        </div>
                                    </div>
                                    <div class="credential-body">
                                        <span>MYSQL PASSWORD</span>
                                        <strong data-credential-password="teachers"></strong>
                                    </div>
                                    <div class="credential-actions stacked">
                                        <button type="button" data-toggle-password="teachers">Show Password</button>
                                        <button type="button" data-copy="teachers-password">Copy Password</button>
                                        <button type="button" data-reset-password="teachers">Reset Password</button>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="module-pane" data-module-pane="courses" id="courses">
                    <div class="module-header">
                        <div>
                            <h2>Course Management</h2>
                            <p>Create courses, assign students and teachers, and track course fees.</p>
                        </div>
                        <div class="module-actions">
                            <select class="module-filter" data-filter="courses">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="primary-action" type="button" data-open-modal="courses">Add Course</button>
                        </div>
                    </div>

                    <div class="module-grid">
                        <article class="panel-card module-table-card">
                            <div class="module-toolbar">
                                <label class="module-search" aria-label="Search courses">
                                    <span>⌕</span>
                                    <input type="search" placeholder="Search courses..." data-search="courses" />
                                </label>
                            </div>
                            <div class="table-wrap">
                                <table class="module-table">
                                    <thead>
                                        <tr>
                                            <th>Course ID</th>
                                            <th>Course Name</th>
                                            <th>Course Code</th>
                                            <th>Duration</th>
                                            <th>Total Fees</th>
                                            <th>Total Students</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody data-table-body="courses"></tbody>
                                </table>
                            </div>
                        </article>

                        <aside class="panel-card detail-card" data-detail-card="courses">
                            <div class="detail-empty" data-empty-detail="courses">
                                <strong>No course selected</strong>
                                <span>Select a course to review assigned students, assigned teachers, and fee details.</span>
                            </div>
                            <div class="detail-content" data-detail-content="courses" hidden>
                                <div class="detail-header">
                                    <div class="avatar-badge course-avatar" data-avatar="courses"></div>
                                    <div>
                                        <h3 data-detail-name="courses"></h3>
                                        <p data-detail-meta="courses"></p>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <h4>Course Information</h4>
                                    <dl class="detail-list">
                                        <div><dt>Duration</dt><dd data-detail-duration="courses"></dd></div>
                                        <div><dt>Semester Count</dt><dd data-detail-semesters="courses"></dd></div>
                                        <div><dt>Total Fees</dt><dd data-detail-fees="courses"></dd></div>
                                        <div><dt>Status</dt><dd data-detail-status="courses"></dd></div>
                                    </dl>
                                </div>
                                <div class="detail-group">
                                    <h4>Assigned Students</h4>
                                    <div class="summary-strip" data-student-summary="courses"></div>
                                </div>
                                <div class="detail-group">
                                    <h4>Assigned Teachers</h4>
                                    <div class="summary-strip" data-course-teacher-summary="courses"></div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <template id="modalTemplate">
        <div class="modal-backdrop" data-modal-close="true"></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <div>
                    <p class="eyebrow" data-modal-eyebrow>Form</p>
                    <h2 id="modalTitle" data-modal-title></h2>
                    <p data-modal-description></p>
                </div>
                <button class="modal-close" type="button" data-modal-close="true">✕</button>
            </div>
            <form class="modal-form" data-modal-form></form>
        </div>
    </template>

    <div class="modal-host" hidden></div>
    <script>
        window.managementData = <?php echo json_encode($managementData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="management.js" defer></script>
</body>
</html>
