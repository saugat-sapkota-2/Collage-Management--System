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

$name = $_SESSION['user']['name'];
$email = $_SESSION['user']['email'];

$dashboardData = [
    'summary' => [
        'totalStudents' => 0,
        'totalTeachers' => 0,
        'feesCollected' => 0,
        'pendingFees' => 0,
    ],
    'monthlyFinance' => [
        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        'income' => [0, 0, 0, 0, 0, 0],
        'expenses' => [0, 0, 0, 0, 0, 0],
    ],
    'attendance' => [
        'present' => 0,
        'absent' => 0,
        'leave' => 0,
    ],
    'performance' => [
        'labels' => ['A+', 'A', 'B+', 'B', 'C'],
        'values' => [0, 0, 0, 0, 0],
    ],
    'activities' => [],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
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
                <a class="nav-item active" href="#dashboard"><span class="nav-icon">▣</span><span>Dashboard</span></a>
                <a class="nav-item" href="management.php#students"><span class="nav-icon">◫</span><span>Students</span></a>
                <a class="nav-item" href="management.php#teachers"><span class="nav-icon">◫</span><span>Teachers</span></a>
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

                <button class="icon-button close-sidebar" type="button" aria-label="Close sidebar" data-close-sidebar="true">✕</button>

                <label class="search-bar" aria-label="Search dashboard">
                    <span class="search-icon">⌕</span>
                    <input type="search" placeholder="Search..." />
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
                        <div class="profile-avatar" aria-hidden="true">A</div>
                        <span class="profile-caret">▾</span>
                    </div>
                </div>
            </header>

            <main class="dashboard" id="dashboard">
                <section class="hero-copy">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back! Here's what's happening today.</p>
                </section>

                <section class="stats-grid" aria-label="Dashboard summary">
                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-title">Total Students</span>
                            <strong class="stat-value" data-stat-value="totalStudents">0</strong>
                            <span class="stat-trend positive">+0% from last month</span>
                        </div>
                        <div class="stat-icon blue" aria-hidden="true">👥</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-title">Total Teachers</span>
                            <strong class="stat-value" data-stat-value="totalTeachers">0</strong>
                            <span class="stat-trend positive">+0 new this month</span>
                        </div>
                        <div class="stat-icon purple" aria-hidden="true">🎓</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-title">Fees Collected</span>
                            <strong class="stat-value" data-stat-value="feesCollected">$0</strong>
                            <span class="stat-trend positive">+0% from last month</span>
                        </div>
                        <div class="stat-icon green" aria-hidden="true">$</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-title">Pending Fees</span>
                            <strong class="stat-value" data-stat-value="pendingFees">$0</strong>
                            <span class="stat-trend negative">-0% from last month</span>
                        </div>
                        <div class="stat-icon red" aria-hidden="true">!</div>
                    </article>
                </section>

                <section class="content-grid">
                    <article class="panel-card chart-card chart-card-large">
                        <div class="panel-header">
                            <h2>Monthly Income & Expenses</h2>
                        </div>
                        <div class="chart-wrap chart-wrap-line">
                            <canvas id="incomeExpensesChart" aria-label="Monthly income and expenses chart"></canvas>
                        </div>
                    </article>

                    <article class="panel-card chart-card chart-card-small">
                        <div class="panel-header">
                            <h2>Attendance Overview</h2>
                        </div>
                        <div class="chart-wrap chart-wrap-pie">
                            <canvas id="attendanceChart" aria-label="Attendance overview chart"></canvas>
                        </div>
                    </article>

                    <article class="panel-card chart-card chart-card-wide">
                        <div class="panel-header">
                            <h2>Student Performance Distribution</h2>
                        </div>
                        <div class="chart-wrap chart-wrap-bar">
                            <canvas id="performanceChart" aria-label="Student performance chart"></canvas>
                        </div>
                    </article>

                    <article class="panel-card activity-card">
                        <div class="panel-header">
                            <h2>Recent Activities</h2>
                        </div>
                        <div class="activity-list" id="activityList" data-empty-state="true"></div>
                    </article>
                </section>
            </main>
        </div>
    </div>

    <script>
        window.dashboardData = <?php echo json_encode($dashboardData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const body = document.body;
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.querySelector('.backdrop');
            const toggleButtons = document.querySelectorAll('[data-toggle-sidebar="true"]');
            const closeButtons = document.querySelectorAll('[data-close-sidebar="true"]');
            const themeToggle = document.querySelector('[data-theme-toggle="true"]');
            const activityList = document.getElementById('activityList');
            const data = window.dashboardData || {};

            const formatNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value) || 0);
            const formatCurrency = (value) => `$${formatNumber(value)}`;

            const setSidebarState = (isOpen) => {
                body.dataset.sidebarOpen = String(isOpen);
                sidebar.classList.toggle('is-open', isOpen);
                backdrop.hidden = !isOpen;
            };

            toggleButtons.forEach((button) => {
                button.addEventListener('click', () => setSidebarState(true));
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => setSidebarState(body.dataset.sidebarOpen !== 'true'));
            });

            backdrop.addEventListener('click', () => setSidebarState(false));

            const savedTheme = window.localStorage.getItem('admin-dashboard-theme');
            if (savedTheme === 'dark') {
                body.classList.add('theme-dark');
            }

            themeToggle?.addEventListener('click', () => {
                body.classList.toggle('theme-dark');
                window.localStorage.setItem('admin-dashboard-theme', body.classList.contains('theme-dark') ? 'dark' : 'light');
            });

            document.querySelectorAll('[data-stat-value]').forEach((element) => {
                const key = element.getAttribute('data-stat-value');
                const value = data.summary?.[key] ?? 0;
                element.textContent = key === 'feesCollected' || key === 'pendingFees' ? formatCurrency(value) : formatNumber(value);
            });

            const monthlyLabels = data.monthlyFinance?.labels || [];
            const incomeValues = data.monthlyFinance?.income || [];
            const expenseValues = data.monthlyFinance?.expenses || [];
            const attendanceData = data.attendance || { present: 0, absent: 0, leave: 0 };
            const performanceLabels = data.performance?.labels || [];
            const performanceValues = data.performance?.values || [];

            const chartBaseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 700,
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: true,
                    },
                },
            };

            const gridColor = 'rgba(100, 116, 139, 0.35)';

            new Chart(document.getElementById('incomeExpensesChart'), {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomeValues,
                            borderColor: '#10b981',
                            backgroundColor: '#10b981',
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#10b981',
                            pointBorderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.35,
                            borderWidth: 3,
                            fill: false,
                        },
                        {
                            label: 'Expenses',
                            data: expenseValues,
                            borderColor: '#ef4444',
                            backgroundColor: '#ef4444',
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#ef4444',
                            pointBorderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.35,
                            borderWidth: 3,
                            fill: false,
                        },
                    ],
                },
                options: {
                    ...chartBaseOptions,
                    scales: {
                        x: {
                            grid: {
                                color: gridColor,
                                borderDash: [4, 4],
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 12,
                                    family: 'Inter, sans-serif',
                                },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor,
                                borderDash: [4, 4],
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 12,
                                    family: 'Inter, sans-serif',
                                },
                            },
                        },
                    },
                },
            });

            new Chart(document.getElementById('attendanceChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent', 'Leave'],
                    datasets: [{
                        data: [attendanceData.present, attendanceData.absent, attendanceData.leave],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    ...chartBaseOptions,
                    cutout: '56%',
                    plugins: {
                        ...chartBaseOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `${context.label}: ${context.formattedValue}%`;
                                },
                            },
                        },
                    },
                },
            });

            new Chart(document.getElementById('performanceChart'), {
                type: 'bar',
                data: {
                    labels: performanceLabels,
                    datasets: [{
                        label: 'Students',
                        data: performanceValues,
                        backgroundColor: '#8b5cf6',
                        borderRadius: 8,
                        borderSkipped: false,
                    }],
                },
                options: {
                    ...chartBaseOptions,
                    plugins: {
                        ...chartBaseOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return `Students: ${context.formattedValue}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                color: gridColor,
                                borderDash: [4, 4],
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 12,
                                    family: 'Inter, sans-serif',
                                },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor,
                                borderDash: [4, 4],
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 12,
                                    family: 'Inter, sans-serif',
                                },
                            },
                        },
                    },
                },
            });

            const activities = Array.isArray(data.activities) ? data.activities : [];
            if (!activities.length) {
                activityList.innerHTML = '<div class="empty-state"><strong>No recent activity</strong><span>Activity records will appear here once the backend is connected.</span></div>';
            } else {
                activityList.innerHTML = activities.map((activity) => `
                    <article class="activity-item">
                        <span class="activity-dot ${activity.status || 'neutral'}" aria-hidden="true"></span>
                        <div class="activity-content">
                            <strong>${activity.title || ''}</strong>
                            <span>${activity.user || ''}</span>
                            <time>${activity.timestamp || ''}</time>
                        </div>
                    </article>
                `).join('');
            }
        });
    </script>
</body>
</html>
