<?php

declare(strict_types=1);

require __DIR__ . '/../../Backend/bootstrap.php';
require __DIR__ . '/../../Backend/db.php';
require __DIR__ . '/../../Backend/management_data.php';

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

try {
    $managementData = loadManagementData($pdo);
} catch (Throwable $e) {
    $managementData = [
        'students' => [],
        'teachers' => [],
        'courses' => [],
        'summary' => [
            'students' => 0,
            'teachers' => 0,
            'courses' => 0,
            'activeStudents' => 0,
            'activeTeachers' => 0,
            'activeCourses' => 0,
        ],
    ];
}

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
                <a class="nav-item" href="#finance"><span class="nav-icon">Rs</span><span>Finance</span></a>
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
                    <div class="module-header" id="studentsHeader">
                        <div>
                            <h2>Student Management</h2>
                            <p>Add, edit, search, filter, activate, deactivate, and manage login credentials.</p>
                        </div>
                        <div class="module-actions">
                            <button class="primary-button" type="button" data-open-modal="student">Add Student</button>
                        </div>
                    </div>

                    <div class="module-grid full-width-grid" id="studentsGrid">
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
                    </div>

                    <!-- New Full Page Student Profile View -->
                    <div class="profile-container hidden" id="studentProfileContainer">
                        <div class="profile-header-actions">
                            <button type="button" class="back-button" id="backToStudentsList">
                                <span class="back-icon">←</span> Back to Students List
                            </button>
                        </div>
                        
                        <div class="profile-hero-card">
                            <div class="profile-hero-bg"></div>
                            <div class="profile-hero-content">
                                <div class="profile-hero-avatar" id="studentHeroAvatar">S</div>
                                <div class="profile-hero-info">
                                    <div class="profile-hero-name-row">
                                        <h2 id="studentHeroName">Student Name</h2>
                                        <span class="status-badge" id="studentHeroStatus">Active</span>
                                    </div>
                                    <p class="profile-hero-meta">
                                        <span id="studentHeroCourse">Course</span> • 
                                        <span id="studentHeroSemester">Semester</span> • 
                                        <span id="studentHeroId">ID</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="profile-grid">
                            <!-- Left Column: Personal and Academic Info -->
                            <div class="profile-col-main">
                                <section class="profile-section-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">👤</span>
                                        <h3>Personal Details</h3>
                                    </div>
                                    <div class="profile-fields-grid">
                                        <div class="profile-field-item">
                                            <span class="field-label">Full Name</span>
                                            <strong class="field-value" id="studentDetailFullName">John Doe</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Gender</span>
                                            <strong class="field-value" id="studentDetailGender">Male</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Date of Birth</span>
                                            <strong class="field-value" id="studentDetailDob">Jan 1, 2000</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Phone</span>
                                            <strong class="field-value" id="studentDetailPhone">9876543210</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Email</span>
                                            <strong class="field-value" id="studentDetailEmail">john@example.com</strong>
                                        </div>
                                        <div class="profile-field-item full-width">
                                            <span class="field-label">Address</span>
                                            <strong class="field-value" id="studentDetailAddress">123 Street Name, City</strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-section-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">🎓</span>
                                        <h3>Academic Information</h3>
                                    </div>
                                    <div class="profile-fields-grid">
                                        <div class="profile-field-item">
                                            <span class="field-label">Course Name</span>
                                            <strong class="field-value" id="studentDetailCourseName">BSc CS</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Course Code</span>
                                            <strong class="field-value" id="studentDetailCourseCode">CS-101</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Semester</span>
                                            <strong class="field-value" id="studentDetailSemester">Semester 1</strong>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <!-- Right Column: Attendance, Fees, Credentials -->
                            <div class="profile-col-side">
                                <section class="profile-section-card highlight-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">📅</span>
                                        <h3>Attendance Stats</h3>
                                    </div>
                                    <div class="attendance-summary-list">
                                        <div class="attendance-stat-item present">
                                            <span class="stat-lbl">Present</span>
                                            <strong class="stat-val" id="studentDetailPresent">0</strong>
                                        </div>
                                        <div class="attendance-stat-item absent">
                                            <span class="stat-lbl">Absent</span>
                                            <strong class="stat-val" id="studentDetailAbsent">0</strong>
                                        </div>
                                        <div class="attendance-stat-item leave">
                                            <span class="stat-lbl">Leave</span>
                                            <strong class="stat-val" id="studentDetailLeave">0</strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-section-card highlight-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">💳</span>
                                        <h3>Fee Summary</h3>
                                    </div>
                                    <div class="fee-details-box">
                                        <div class="fee-stat-row">
                                            <span>Amount Due</span>
                                            <strong class="due-amount" id="studentDetailFeeDue">Rs. 0.00</strong>
                                        </div>
                                        <div class="fee-stat-row">
                                            <span>Amount Paid</span>
                                            <strong class="paid-amount" id="studentDetailFeePaid">Rs. 0.00</strong>
                                        </div>
                                        <div class="fee-status-badge-row">
                                            <span>Status</span>
                                            <span class="status-badge" id="studentDetailFeeStatus">Pending</span>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-section-card highlight-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">🔐</span>
                                        <h3>Login Credentials</h3>
                                    </div>
                                    <div class="credential-card">
                                        <div class="credential-label">LOGIN EMAIL</div>
                                        <div class="credential-value" id="studentProfileEmail">-</div>
                                        <div class="credential-label credential-spacer">PASSWORD</div>
                                        <div class="credential-value password-mask" id="studentProfilePassword">********</div>
                                        <div class="credential-actions stacked">
                                            <button type="button" data-toggle-password="students">Show Password</button>
                                            <div class="credential-actions-sub-row">
                                                <button type="button" data-copy-email="students">Copy Email</button>
                                                <button type="button" data-copy-password="students">Copy Password</button>
                                            </div>
                                            <button type="button" data-reset-password="students" class="danger">Reset Password</button>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="module-section" data-module="teachers" id="teachers">
                    <div class="module-header" id="teachersHeader">
                        <div>
                            <h2>Teacher Management</h2>
                            <p>Maintain teacher accounts, departments, qualifications, and credentials.</p>
                        </div>
                        <div class="module-actions">
                            <button class="primary-button" type="button" data-open-modal="teacher">Add Teacher</button>
                        </div>
                    </div>

                    <div class="module-grid full-width-grid" id="teachersGrid">
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
                    </div>

                    <!-- New Full Page Teacher Profile View -->
                    <div class="profile-container hidden" id="teacherProfileContainer">
                        <div class="profile-header-actions">
                            <button type="button" class="back-button" id="backToTeachersList">
                                <span class="back-icon">←</span> Back to Teachers List
                            </button>
                        </div>
                        
                        <div class="profile-hero-card">
                            <div class="profile-hero-bg"></div>
                            <div class="profile-hero-content">
                                <div class="profile-hero-avatar" id="teacherHeroAvatar">T</div>
                                <div class="profile-hero-info">
                                    <div class="profile-hero-name-row">
                                        <h2 id="teacherHeroName">Teacher Name</h2>
                                        <span class="status-badge" id="teacherHeroStatus">Active</span>
                                    </div>
                                    <p class="profile-hero-meta">
                                        <span id="teacherHeroDepartment">Department</span> • 
                                        <span id="teacherHeroId">ID</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="profile-grid">
                            <!-- Left Column: Personal and Professional Info -->
                            <div class="profile-col-main">
                                <section class="profile-section-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">👤</span>
                                        <h3>Personal Details</h3>
                                    </div>
                                    <div class="profile-fields-grid">
                                        <div class="profile-field-item">
                                            <span class="field-label">Full Name</span>
                                            <strong class="field-value" id="teacherDetailFullName">John Doe</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Phone</span>
                                            <strong class="field-value" id="teacherDetailPhone">9876543210</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Email</span>
                                            <strong class="field-value" id="teacherDetailEmail">teacher@example.com</strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-section-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">🎓</span>
                                        <h3>Professional Information</h3>
                                    </div>
                                    <div class="profile-fields-grid">
                                        <div class="profile-field-item">
                                            <span class="field-label">Department</span>
                                            <strong class="field-value" id="teacherDetailDepartment">Computer Science</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Qualification</span>
                                            <strong class="field-value" id="teacherDetailQualification">PhD CS</strong>
                                        </div>
                                        <div class="profile-field-item">
                                            <span class="field-label">Joining Date</span>
                                            <strong class="field-value" id="teacherDetailJoiningDate">Jan 1, 2020</strong>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <!-- Right Column: Assigned Courses & Credentials -->
                            <div class="profile-col-side">
                                <section class="profile-section-card highlight-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">📚</span>
                                        <h3>Assigned Courses</h3>
                                    </div>
                                    <div class="fee-details-box">
                                        <div class="fee-stat-row">
                                            <span>Active Courses</span>
                                            <strong class="paid-amount" id="teacherDetailAssignedCourses">0</strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="profile-section-card highlight-card">
                                    <div class="section-card-header">
                                        <span class="section-icon">🔐</span>
                                        <h3>Login Credentials</h3>
                                    </div>
                                    <div class="credential-card">
                                        <div class="credential-label">LOGIN EMAIL</div>
                                        <div class="credential-value" id="teacherProfileEmail">-</div>
                                        <div class="credential-label credential-spacer">PASSWORD</div>
                                        <div class="credential-value password-mask" id="teacherProfilePassword">********</div>
                                        <div class="credential-actions stacked">
                                            <button type="button" data-toggle-password="teachers">Show Password</button>
                                            <div class="credential-actions-sub-row">
                                                <button type="button" data-copy-email="teachers">Copy Email</button>
                                                <button type="button" data-copy-password="teachers">Copy Password</button>
                                            </div>
                                            <button type="button" data-reset-password="teachers" class="danger">Reset Password</button>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
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

    <div class="management-toast hidden" data-management-toast role="status" aria-live="polite"></div>

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
            activeUser: <?php echo json_encode(['name' => $activeUserName, 'email' => $activeUserEmail], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
            apiUrl: '../../Backend/management_api.php'
        };
    </script>
    <script src="management.js" defer></script>
</body>
</html>

