<?php

declare(strict_types=1);

function requireManagementAdmin(): void
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        throw new RuntimeException('Unauthorized.', 401);
    }

    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        throw new RuntimeException('Forbidden.', 403);
    }
}

function formatStudentRow(array $row, string $plainPassword = ''): array
{
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
        'course_id' => isset($row['course_id']) && $row['course_id'] !== null ? (int) $row['course_id'] : null,
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
            'email' => (string) ($row['email'] ?? ''),
            'password' => $plainPassword,
            'password_available' => $plainPassword !== '',
            'passwordVisible' => $plainPassword !== '',
        ],
    ];
}

function formatTeacherRow(array $row, string $plainPassword = ''): array
{
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
            'email' => (string) ($row['email'] ?? ''),
            'password' => $plainPassword,
            'password_available' => $plainPassword !== '',
            'passwordVisible' => $plainPassword !== '',
        ],
    ];
}

function formatCourseRow(array $row, array $assignedTeachers = []): array
{
    return [
        'id' => (int) $row['id'],
        'course_name' => (string) $row['course_name'],
        'course_code' => (string) $row['course_code'],
        'duration' => (string) $row['duration'],
        'semester_count' => (int) $row['semester_count'],
        'total_fees' => (float) $row['total_fees'],
        'description' => (string) ($row['description'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        'total_students' => (int) ($row['total_students'] ?? 0),
        'total_teachers' => (int) ($row['total_teachers'] ?? 0),
        'assigned_teachers' => $assignedTeachers,
    ];
}

function buildManagementSummary(array $managementData): array
{
    return [
        'students' => count($managementData['students']),
        'teachers' => count($managementData['teachers']),
        'courses' => count($managementData['courses']),
        'activeStudents' => count(array_filter(
            $managementData['students'],
            static fn (array $student): bool => ($student['is_active'] ?? false) === true
        )),
        'activeTeachers' => count(array_filter(
            $managementData['teachers'],
            static fn (array $teacher): bool => ($teacher['is_active'] ?? false) === true
        )),
        'activeCourses' => count(array_filter(
            $managementData['courses'],
            static fn (array $course): bool => ($course['is_active'] ?? false) === true
        )),
    ];
}

function studentSelectSql(string $extraWhere = ''): string
{
    return 'SELECT
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
         ' . $extraWhere;
}

function teacherSelectSql(string $extraWhere = ''): string
{
    return 'SELECT
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
         ' . $extraWhere;
}

function courseSelectSql(string $extraWhere = ''): string
{
    return 'SELECT
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
         ' . $extraWhere;
}

function fetchStudentById(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare(studentSelectSql('WHERE s.id = :id LIMIT 1'));
    $stmt->execute(['id' => $studentId]);
    $row = $stmt->fetch();

    return $row ? formatStudentRow($row) : null;
}

function fetchTeacherById(PDO $pdo, int $teacherId): ?array
{
    $stmt = $pdo->prepare(teacherSelectSql('WHERE t.id = :id LIMIT 1'));
    $stmt->execute(['id' => $teacherId]);
    $row = $stmt->fetch();

    return $row ? formatTeacherRow($row) : null;
}

function fetchCourseById(PDO $pdo, int $courseId): ?array
{
    $stmt = $pdo->prepare(courseSelectSql('WHERE c.id = :id LIMIT 1'));
    $stmt->execute(['id' => $courseId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $teacherStmt = $pdo->prepare(
        'SELECT t.id AS teacher_id, t.full_name, u.username, u.email
         FROM course_teachers ct
         INNER JOIN teachers t ON t.id = ct.teacher_id
         INNER JOIN users u ON u.id = t.user_id
         WHERE ct.course_id = :course_id
         ORDER BY t.full_name ASC'
    );
    $teacherStmt->execute(['course_id' => $courseId]);
    $assignedTeachers = array_map(static function (array $teacherRow): array {
        return [
            'id' => (int) ($teacherRow['teacher_id'] ?? 0),
            'full_name' => (string) ($teacherRow['full_name'] ?? ''),
            'username' => (string) ($teacherRow['username'] ?? ''),
            'email' => (string) ($teacherRow['email'] ?? ''),
        ];
    }, $teacherStmt->fetchAll());

    return formatCourseRow($row, $assignedTeachers);
}

function loadManagementData(PDO $pdo): array
{
    $studentRows = $pdo->query(studentSelectSql('ORDER BY s.full_name ASC'))->fetchAll();
    $teacherRows = $pdo->query(teacherSelectSql('ORDER BY t.full_name ASC'))->fetchAll();
    $courseRows = $pdo->query(courseSelectSql('ORDER BY c.course_name ASC'))->fetchAll();
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

    $managementData = [
        'students' => array_map(static fn (array $row): array => formatStudentRow($row), $studentRows),
        'teachers' => array_map(static fn (array $row): array => formatTeacherRow($row), $teacherRows),
        'courses' => array_map(
            static function (array $row) use ($courseTeacherMap): array {
                $courseId = (int) $row['id'];

                return formatCourseRow($row, $courseTeacherMap[$courseId] ?? []);
            },
            $courseRows
        ),
    ];

    $managementData['summary'] = buildManagementSummary($managementData);

    return $managementData;
}

function generateUsername(PDO $pdo, string $fullName, string $fallback = 'user'): string
{
    $base = strtolower(trim($fullName));
    $base = preg_replace('/[^a-z0-9]+/', '_', $base) ?? '';
    $base = trim($base, '_');
    if ($base === '') {
        $base = $fallback;
    }

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = $base . '_' . random_int(100, 999);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $candidate]);
        if ($stmt->fetch() === false) {
            return $candidate;
        }
    }

    return $base . '_' . time();
}

function generatePassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';

    for ($index = 0; $index < $length; $index++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

function resetUserPassword(PDO $pdo, int $userId): string
{
    $plainPassword = generatePassword();
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
    $stmt->execute([
        'password' => $passwordHash,
        'id' => $userId,
    ]);

    return $plainPassword;
}

function applyPlainPasswordToCredentials(array $record, string $plainPassword): array
{
    $record['credentials'] = is_array($record['credentials'] ?? null) ? $record['credentials'] : [];
    $record['credentials']['password'] = $plainPassword;
    $record['credentials']['password_available'] = true;
    $record['credentials']['passwordVisible'] = true;

    return $record;
}

function assertUniqueUser(PDO $pdo, string $username, string $email, ?int $excludeUserId = null): void
{
    if ($excludeUserId === null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id FROM users
             WHERE (username = :username OR email = :email) AND id <> :exclude_id
             LIMIT 1'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'exclude_id' => $excludeUserId,
        ]);
    }

    if ($stmt->fetch() !== false) {
        throw new RuntimeException('Username or email already exists.');
    }
}

function nullableDate(string $value): ?string
{
    $value = trim($value);

    return $value === '' ? null : $value;
}

function createInitialStudentFee(PDO $pdo, int $studentId, ?int $courseId): void
{
    if ($courseId === null || $courseId <= 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT total_fees FROM courses WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $courseId]);
    $course = $stmt->fetch();

    if ($course === false) {
        return;
    }

    $amountDue = (float) ($course['total_fees'] ?? 0);
    if ($amountDue <= 0) {
        return;
    }

    $invoiceNumber = sprintf('INV-%d-%s', $studentId, date('YmdHis'));
    $insert = $pdo->prepare(
        'INSERT INTO student_fees (student_id, invoice_number, amount_due, amount_paid, status)
         VALUES (:student_id, :invoice_number, :amount_due, 0, "pending")'
    );
    $insert->execute([
        'student_id' => $studentId,
        'invoice_number' => $invoiceNumber,
        'amount_due' => $amountDue,
    ]);
}
