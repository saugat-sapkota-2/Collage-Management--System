<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/db.php';
require __DIR__ . '/management_data.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, array $payload = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function normalizeModule(string $module): string
{
    return match ($module) {
        'student', 'students' => 'students',
        'teacher', 'teachers' => 'teachers',
        'course', 'courses' => 'courses',
        default => '',
    };
}

function boolFromPayload(array $payload, string $key, bool $default = true): bool
{
    if (!array_key_exists($key, $payload)) {
        return $default;
    }

    $value = $payload[$key];

    if (is_bool($value)) {
        return $value;
    }

    return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
}

function intOrNull(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, ['message' => 'Method not allowed.'], 405);
    }

    requireManagementAdmin();
    $pdo = dbConnection($config);
    $body = readJsonBody();
    $module = normalizeModule((string) ($body['module'] ?? ''));
    $action = strtolower(trim((string) ($body['action'] ?? '')));
    $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];

    if ($module === '' || $action === '') {
        jsonResponse(false, ['message' => 'Module and action are required.'], 422);
    }

    if ($module === 'students') {
        if ($action === 'create') {
            $fullName = trim((string) ($payload['full_name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $username = trim((string) ($payload['username'] ?? ''));

            if ($fullName === '' || $email === '') {
                jsonResponse(false, ['message' => 'Full name and email are required.'], 422);
            }

            if ($username === '') {
                $username = generateUsername($pdo, $fullName, 'student');
            }

            assertUniqueUser($pdo, $username, $email);

            $plainPassword = generatePassword();
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $courseId = intOrNull($payload['course_id'] ?? null);
            $isActive = boolFromPayload($payload, 'is_active', true);

            $pdo->beginTransaction();

            $userStmt = $pdo->prepare(
                'INSERT INTO users (name, username, email, password, phone, role, is_active)
                 VALUES (:name, :username, :email, :password, :phone, "student", :is_active)'
            );
            $userStmt->execute([
                'name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password' => $passwordHash,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $userId = (int) $pdo->lastInsertId();

            $studentStmt = $pdo->prepare(
                'INSERT INTO students (
                    user_id, full_name, phone, address, gender, date_of_birth,
                    course_id, semester, profile_photo, is_active
                 ) VALUES (
                    :user_id, :full_name, :phone, :address, :gender, :date_of_birth,
                    :course_id, :semester, :profile_photo, :is_active
                 )'
            );
            $studentStmt->execute([
                'user_id' => $userId,
                'full_name' => $fullName,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'address' => trim((string) ($payload['address'] ?? '')) ?: null,
                'gender' => in_array(($payload['gender'] ?? 'other'), ['male', 'female', 'other'], true)
                    ? $payload['gender']
                    : 'other',
                'date_of_birth' => nullableDate((string) ($payload['date_of_birth'] ?? '')),
                'course_id' => $courseId,
                'semester' => trim((string) ($payload['semester'] ?? '')) ?: null,
                'profile_photo' => trim((string) ($payload['profile_photo'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $studentId = (int) $pdo->lastInsertId();
            createInitialStudentFee($pdo, $studentId, $courseId);
            $pdo->commit();

            $record = fetchStudentById($pdo, $studentId);
            if ($record !== null) {
                $record['credentials']['password'] = $plainPassword;
                $record['credentials']['password_available'] = true;
                $record['credentials']['passwordVisible'] = true;
            }

            jsonResponse(true, [
                'message' => 'Student created successfully.',
                'record' => $record,
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'update') {
            $studentId = (int) ($payload['id'] ?? 0);
            if ($studentId <= 0) {
                jsonResponse(false, ['message' => 'Student id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT s.id, s.user_id FROM students s WHERE s.id = :id LIMIT 1');
            $lookup->execute(['id' => $studentId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Student not found.'], 404);
            }

            $fullName = trim((string) ($payload['full_name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $username = trim((string) ($payload['username'] ?? ''));

            if ($fullName === '' || $email === '' || $username === '') {
                jsonResponse(false, ['message' => 'Full name, username, and email are required.'], 422);
            }

            assertUniqueUser($pdo, $username, $email, (int) $existing['user_id']);

            $courseId = intOrNull($payload['course_id'] ?? null);
            $isActive = boolFromPayload($payload, 'is_active', true);
            $userId = (int) $existing['user_id'];

            $pdo->beginTransaction();

            $userStmt = $pdo->prepare(
                'UPDATE users
                 SET name = :name, username = :username, email = :email, phone = :phone, is_active = :is_active
                 WHERE id = :id'
            );
            $userStmt->execute([
                'name' => $fullName,
                'username' => $username,
                'email' => $email,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
                'id' => $userId,
            ]);

            $studentStmt = $pdo->prepare(
                'UPDATE students
                 SET full_name = :full_name, phone = :phone, address = :address, gender = :gender,
                     date_of_birth = :date_of_birth, course_id = :course_id, semester = :semester,
                     profile_photo = :profile_photo, is_active = :is_active
                 WHERE id = :id'
            );
            $studentStmt->execute([
                'full_name' => $fullName,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'address' => trim((string) ($payload['address'] ?? '')) ?: null,
                'gender' => in_array(($payload['gender'] ?? 'other'), ['male', 'female', 'other'], true)
                    ? $payload['gender']
                    : 'other',
                'date_of_birth' => nullableDate((string) ($payload['date_of_birth'] ?? '')),
                'course_id' => $courseId,
                'semester' => trim((string) ($payload['semester'] ?? '')) ?: null,
                'profile_photo' => trim((string) ($payload['profile_photo'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
                'id' => $studentId,
            ]);

            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Student updated successfully.',
                'record' => fetchStudentById($pdo, $studentId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'delete') {
            $studentId = (int) ($payload['id'] ?? 0);
            if ($studentId <= 0) {
                jsonResponse(false, ['message' => 'Student id is required.'], 422);
            }

            $lookup = $pdo->prepare(
                'SELECT s.user_id FROM students s WHERE s.id = :id LIMIT 1'
            );
            $lookup->execute(['id' => $studentId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Student not found.'], 404);
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM students WHERE id = :id')->execute(['id' => $studentId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => (int) $existing['user_id']]);
            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Student deleted successfully.',
                'id' => $studentId,
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'toggle') {
            $studentId = (int) ($payload['id'] ?? 0);
            if ($studentId <= 0) {
                jsonResponse(false, ['message' => 'Student id is required.'], 422);
            }

            $lookup = $pdo->prepare(
                'SELECT s.is_active, s.user_id FROM students s WHERE s.id = :id LIMIT 1'
            );
            $lookup->execute(['id' => $studentId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Student not found.'], 404);
            }

            $nextStatus = ((int) ($existing['is_active'] ?? 0) === 1) ? 0 : 1;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE students SET is_active = :is_active WHERE id = :id')
                ->execute(['is_active' => $nextStatus, 'id' => $studentId]);
            $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id')
                ->execute(['is_active' => $nextStatus, 'id' => (int) $existing['user_id']]);
            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Student status updated.',
                'record' => fetchStudentById($pdo, $studentId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'reset_password') {
            $studentId = (int) ($payload['id'] ?? 0);
            if ($studentId <= 0) {
                jsonResponse(false, ['message' => 'Student id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT s.user_id FROM students s WHERE s.id = :id LIMIT 1');
            $lookup->execute(['id' => $studentId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Student not found.'], 404);
            }

            $plainPassword = resetUserPassword($pdo, (int) $existing['user_id']);
            $record = fetchStudentById($pdo, $studentId);
            if ($record === null) {
                jsonResponse(false, ['message' => 'Student not found.'], 404);
            }

            jsonResponse(true, [
                'message' => 'Student password reset successfully.',
                'record' => applyPlainPasswordToCredentials($record, $plainPassword),
            ]);
        }
    }

    if ($module === 'teachers') {
        if ($action === 'create') {
            $fullName = trim((string) ($payload['full_name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $username = trim((string) ($payload['username'] ?? ''));
            $department = trim((string) ($payload['department'] ?? ''));

            if ($fullName === '' || $email === '' || $department === '') {
                jsonResponse(false, ['message' => 'Full name, email, and department are required.'], 422);
            }

            if ($username === '') {
                $username = generateUsername($pdo, $fullName, 'teacher');
            }

            assertUniqueUser($pdo, $username, $email);

            $plainPassword = generatePassword();
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $isActive = boolFromPayload($payload, 'is_active', true);

            $pdo->beginTransaction();

            $userStmt = $pdo->prepare(
                'INSERT INTO users (name, username, email, password, phone, role, is_active)
                 VALUES (:name, :username, :email, :password, :phone, "teacher", :is_active)'
            );
            $userStmt->execute([
                'name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password' => $passwordHash,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $userId = (int) $pdo->lastInsertId();

            $teacherStmt = $pdo->prepare(
                'INSERT INTO teachers (
                    user_id, full_name, phone, department, qualification, joining_date, profile_photo, is_active
                 ) VALUES (
                    :user_id, :full_name, :phone, :department, :qualification, :joining_date, :profile_photo, :is_active
                 )'
            );
            $teacherStmt->execute([
                'user_id' => $userId,
                'full_name' => $fullName,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'department' => $department,
                'qualification' => trim((string) ($payload['qualification'] ?? '')) ?: null,
                'joining_date' => nullableDate((string) ($payload['joining_date'] ?? '')),
                'profile_photo' => trim((string) ($payload['profile_photo'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $teacherId = (int) $pdo->lastInsertId();
            $pdo->commit();

            $record = fetchTeacherById($pdo, $teacherId);
            if ($record !== null) {
                $record['credentials']['password'] = $plainPassword;
                $record['credentials']['password_available'] = true;
                $record['credentials']['passwordVisible'] = true;
            }

            jsonResponse(true, [
                'message' => 'Teacher created successfully.',
                'record' => $record,
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'update') {
            $teacherId = (int) ($payload['id'] ?? 0);
            if ($teacherId <= 0) {
                jsonResponse(false, ['message' => 'Teacher id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT t.id, t.user_id FROM teachers t WHERE t.id = :id LIMIT 1');
            $lookup->execute(['id' => $teacherId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Teacher not found.'], 404);
            }

            $fullName = trim((string) ($payload['full_name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $username = trim((string) ($payload['username'] ?? ''));
            $department = trim((string) ($payload['department'] ?? ''));

            if ($fullName === '' || $email === '' || $username === '' || $department === '') {
                jsonResponse(false, ['message' => 'Full name, username, email, and department are required.'], 422);
            }

            assertUniqueUser($pdo, $username, $email, (int) $existing['user_id']);
            $isActive = boolFromPayload($payload, 'is_active', true);
            $userId = (int) $existing['user_id'];

            $pdo->beginTransaction();

            $pdo->prepare(
                'UPDATE users
                 SET name = :name, username = :username, email = :email, phone = :phone, is_active = :is_active
                 WHERE id = :id'
            )->execute([
                'name' => $fullName,
                'username' => $username,
                'email' => $email,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
                'id' => $userId,
            ]);

            $pdo->prepare(
                'UPDATE teachers
                 SET full_name = :full_name, phone = :phone, department = :department,
                     qualification = :qualification, joining_date = :joining_date,
                     profile_photo = :profile_photo, is_active = :is_active
                 WHERE id = :id'
            )->execute([
                'full_name' => $fullName,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'department' => $department,
                'qualification' => trim((string) ($payload['qualification'] ?? '')) ?: null,
                'joining_date' => nullableDate((string) ($payload['joining_date'] ?? '')),
                'profile_photo' => trim((string) ($payload['profile_photo'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
                'id' => $teacherId,
            ]);

            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Teacher updated successfully.',
                'record' => fetchTeacherById($pdo, $teacherId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'delete') {
            $teacherId = (int) ($payload['id'] ?? 0);
            if ($teacherId <= 0) {
                jsonResponse(false, ['message' => 'Teacher id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT user_id FROM teachers WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $teacherId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Teacher not found.'], 404);
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM teachers WHERE id = :id')->execute(['id' => $teacherId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => (int) $existing['user_id']]);
            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Teacher deleted successfully.',
                'id' => $teacherId,
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'toggle') {
            $teacherId = (int) ($payload['id'] ?? 0);
            if ($teacherId <= 0) {
                jsonResponse(false, ['message' => 'Teacher id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT is_active, user_id FROM teachers WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $teacherId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Teacher not found.'], 404);
            }

            $nextStatus = ((int) ($existing['is_active'] ?? 0) === 1) ? 0 : 1;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE teachers SET is_active = :is_active WHERE id = :id')
                ->execute(['is_active' => $nextStatus, 'id' => $teacherId]);
            $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id')
                ->execute(['is_active' => $nextStatus, 'id' => (int) $existing['user_id']]);
            $pdo->commit();

            jsonResponse(true, [
                'message' => 'Teacher status updated.',
                'record' => fetchTeacherById($pdo, $teacherId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'reset_password') {
            $teacherId = (int) ($payload['id'] ?? 0);
            if ($teacherId <= 0) {
                jsonResponse(false, ['message' => 'Teacher id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT user_id FROM teachers WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $teacherId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Teacher not found.'], 404);
            }

            $plainPassword = resetUserPassword($pdo, (int) $existing['user_id']);
            $record = fetchTeacherById($pdo, $teacherId);
            if ($record === null) {
                jsonResponse(false, ['message' => 'Teacher not found.'], 404);
            }

            jsonResponse(true, [
                'message' => 'Teacher password reset successfully.',
                'record' => applyPlainPasswordToCredentials($record, $plainPassword),
            ]);
        }
    }

    if ($module === 'courses') {
        if ($action === 'create') {
            $courseName = trim((string) ($payload['course_name'] ?? ''));
            $courseCode = trim((string) ($payload['course_code'] ?? ''));
            $duration = trim((string) ($payload['duration'] ?? ''));

            if ($courseName === '' || $courseCode === '' || $duration === '') {
                jsonResponse(false, ['message' => 'Course name, code, and duration are required.'], 422);
            }

            $codeCheck = $pdo->prepare('SELECT id FROM courses WHERE course_code = :course_code LIMIT 1');
            $codeCheck->execute(['course_code' => $courseCode]);
            if ($codeCheck->fetch() !== false) {
                jsonResponse(false, ['message' => 'Course code already exists.'], 422);
            }

            $isActive = boolFromPayload($payload, 'is_active', true);

            $stmt = $pdo->prepare(
                'INSERT INTO courses (
                    course_name, course_code, duration, semester_count, total_fees, description, is_active
                 ) VALUES (
                    :course_name, :course_code, :duration, :semester_count, :total_fees, :description, :is_active
                 )'
            );
            $stmt->execute([
                'course_name' => $courseName,
                'course_code' => $courseCode,
                'duration' => $duration,
                'semester_count' => max(1, (int) ($payload['semester_count'] ?? 1)),
                'total_fees' => (float) ($payload['total_fees'] ?? 0),
                'description' => trim((string) ($payload['description'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
            ]);

            $courseId = (int) $pdo->lastInsertId();

            jsonResponse(true, [
                'message' => 'Course created successfully.',
                'record' => fetchCourseById($pdo, $courseId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'update') {
            $courseId = (int) ($payload['id'] ?? 0);
            if ($courseId <= 0) {
                jsonResponse(false, ['message' => 'Course id is required.'], 422);
            }

            $courseName = trim((string) ($payload['course_name'] ?? ''));
            $courseCode = trim((string) ($payload['course_code'] ?? ''));
            $duration = trim((string) ($payload['duration'] ?? ''));

            if ($courseName === '' || $courseCode === '' || $duration === '') {
                jsonResponse(false, ['message' => 'Course name, code, and duration are required.'], 422);
            }

            $codeCheck = $pdo->prepare(
                'SELECT id FROM courses WHERE course_code = :course_code AND id <> :id LIMIT 1'
            );
            $codeCheck->execute(['course_code' => $courseCode, 'id' => $courseId]);
            if ($codeCheck->fetch() !== false) {
                jsonResponse(false, ['message' => 'Course code already exists.'], 422);
            }

            $isActive = boolFromPayload($payload, 'is_active', true);

            $pdo->prepare(
                'UPDATE courses
                 SET course_name = :course_name, course_code = :course_code, duration = :duration,
                     semester_count = :semester_count, total_fees = :total_fees,
                     description = :description, is_active = :is_active
                 WHERE id = :id'
            )->execute([
                'course_name' => $courseName,
                'course_code' => $courseCode,
                'duration' => $duration,
                'semester_count' => max(1, (int) ($payload['semester_count'] ?? 1)),
                'total_fees' => (float) ($payload['total_fees'] ?? 0),
                'description' => trim((string) ($payload['description'] ?? '')) ?: null,
                'is_active' => $isActive ? 1 : 0,
                'id' => $courseId,
            ]);

            jsonResponse(true, [
                'message' => 'Course updated successfully.',
                'record' => fetchCourseById($pdo, $courseId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'delete') {
            $courseId = (int) ($payload['id'] ?? 0);
            if ($courseId <= 0) {
                jsonResponse(false, ['message' => 'Course id is required.'], 422);
            }

            $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute(['id' => $courseId]);

            jsonResponse(true, [
                'message' => 'Course deleted successfully.',
                'id' => $courseId,
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }

        if ($action === 'toggle') {
            $courseId = (int) ($payload['id'] ?? 0);
            if ($courseId <= 0) {
                jsonResponse(false, ['message' => 'Course id is required.'], 422);
            }

            $lookup = $pdo->prepare('SELECT is_active FROM courses WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $courseId]);
            $existing = $lookup->fetch();
            if ($existing === false) {
                jsonResponse(false, ['message' => 'Course not found.'], 404);
            }

            $nextStatus = ((int) ($existing['is_active'] ?? 0) === 1) ? 0 : 1;
            $pdo->prepare('UPDATE courses SET is_active = :is_active WHERE id = :id')
                ->execute(['is_active' => $nextStatus, 'id' => $courseId]);

            jsonResponse(true, [
                'message' => 'Course status updated.',
                'record' => fetchCourseById($pdo, $courseId),
                'summary' => buildManagementSummary(loadManagementData($pdo)),
            ]);
        }
    }

    jsonResponse(false, ['message' => 'Unsupported action.'], 400);
} catch (RuntimeException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = $exception->getCode();
    $statusCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 400;
    jsonResponse(false, ['message' => $exception->getMessage()], $statusCode);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, ['message' => 'Server error while processing the request.'], 500);
}
