<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
$pdo = db();
$schools = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll();
$current = current_user();
$page = (string) ($_GET['page'] ?? '');

if ($page === 'departments') {
    header('Content-Type: application/json; charset=utf-8');
    $schoolId = filter_input(INPUT_GET, 'school_id', FILTER_VALIDATE_INT);
    $query = $pdo->prepare('SELECT id, name FROM departments WHERE school_id = ? ORDER BY name');
    $query->execute([$schoolId ?: 0]);
    echo json_encode($query->fetchAll(), JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_admin') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount > 0) {
            flash('danger', 'The initial administrator has already been created.');
            redirect('page=login');
        }
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            flash('danger', 'Enter your name, a valid email address, and a password of at least 10 characters.');
            redirect('page=setup');
        }
        $insert = $pdo->prepare("INSERT INTO users (fullname, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        try {
            $insert->execute([$fullname, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That email address is already in use.');
            redirect('page=setup');
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $pdo->lastInsertId(), 'fullname' => $fullname, 'email' => $email, 'role' => 'admin'];
        redirect('page=dashboard');
    }

    if ($action === 'register') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $matric = strtoupper(trim((string) ($_POST['matric_number'] ?? '')));
        $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $password = (string) ($_POST['password'] ?? '');
        $departmentCheck = $pdo->prepare('SELECT id, school_id FROM departments WHERE id = ? AND school_id = ?');
        $departmentCheck->execute([$departmentId ?: 0, $schoolId ?: 0]);
        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $matric === '' || !$departmentCheck->fetch() || strlen($password) < 8) {
            flash('danger', 'Complete every field, choose a department in the selected school, and use a password of at least 8 characters.');
            redirect('page=register');
        }
        try {
            $insert = $pdo->prepare("INSERT INTO users (school_id, department_id, fullname, email, matric_number, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
            $insert->execute([$schoolId, $departmentId, $fullname, $email, $matric, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That email address or matriculation number is already registered.');
            redirect('page=register');
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $pdo->lastInsertId(), 'fullname' => $fullname, 'email' => $email,
            'role' => 'student', 'department_id' => (int) $departmentId, 'school_id' => (int) $schoolId,
        ];
        flash('success', 'Your student account is ready.');
        redirect('page=dashboard');
    }

    if ($action === 'login') {
        $identifier = strtolower(trim((string) ($_POST['identifier'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $query = $pdo->prepare('SELECT id, fullname, email, role, department_id, school_id, password_hash FROM users WHERE email = ? OR matric_number = ? LIMIT 1');
        $query->execute([$identifier, strtoupper($identifier)]);
        $user = $query->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('danger', 'The sign-in details were not recognized.');
            redirect('page=login');
        }
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        redirect('page=dashboard');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'You have been signed out.');
        redirect();
    }

    if ($action === 'create_ticket') {
        $student = require_role('student');
        $topic = trim((string) ($_POST['topic'] ?? ''));
        $request = trim((string) ($_POST['request'] ?? ''));
        $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
        $courseName = '';
        if ($courseId) {
            $courseQuery = $pdo->prepare('SELECT code, name FROM courses WHERE id = ? AND department_id = ?');
            $courseQuery->execute([$courseId, $student['department_id']]);
            $course = $courseQuery->fetch();
            if ($course) {
                $courseName = $course['code'] . ' — ' . $course['name'];
            }
        }
        $validTopics = ['Academic records', 'Course registration', 'Lectures and timetable', 'Examinations', 'Fees and payments', 'Student affairs', 'ICT and student portal', 'Facilities and safety', 'Other'];
        if (!in_array($topic, $validTopics, true) || strlen($request) < 15 || strlen($request) > 10000 || (!$courseId && !isset($_POST['general_course'])) || ($courseId && $courseName === '')) {
            flash('danger', 'Choose a topic and course, and describe your request in at least 15 characters.');
            redirect('page=new-ticket');
        }
        if (!$courseId) {
            $courseName = 'General / Not course-specific';
        }
        $reference = 'FCWM-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT INTO tickets (reference, student_id, course_id, course_name, topic, request) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([$reference, $student['id'], $courseId ?: null, $courseName, $topic, $request]);
            $ticketId = (int) $pdo->lastInsertId();
            $update = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, user_id, status, message) VALUES (?, ?, ?, ?)');
            $update->execute([$ticketId, $student['id'], 'pending', 'Complaint submitted. Your ticket is awaiting assignment.']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Your support ticket was submitted. Reference: ' . $reference);
        redirect('page=my-tickets');
    }

    if ($action === 'route_ticket') {
        $admin = require_role('admin');
        $ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
        $departmentCheck->execute([$departmentId ?: 0]);
        $ticketCheck = $pdo->prepare('SELECT id, department_id FROM tickets WHERE id = ?');
        $ticketCheck->execute([$ticketId ?: 0]);
        $ticket = $ticketCheck->fetch();
        if (!$ticket || !$departmentCheck->fetch()) {
            flash('danger', 'The ticket or department could not be found.');
            redirect('page=inbox');
        }
        if ((int) $ticket['department_id'] !== (int) $departmentId) {
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE tickets SET department_id = ?, routed_by = ? WHERE id = ?');
                $update->execute([$departmentId, $admin['id'], $ticketId]);
                $nameQuery = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
                $nameQuery->execute([$departmentId]);
                $activity = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, user_id, message) VALUES (?, ?, ?)');
                $activity->execute([$ticketId, $admin['id'], 'Ticket routed to ' . $nameQuery->fetchColumn() . '.']);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('success', 'Ticket routed to the selected department.');
        }
        redirect('page=inbox');
    }

    if ($action === 'update_ticket') {
        $staff = require_role('admin', 'department');
        $ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        $message = trim((string) ($_POST['message'] ?? ''));
        $validStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
        $ticketQuery = $pdo->prepare('SELECT id, status, department_id FROM tickets WHERE id = ?');
        $ticketQuery->execute([$ticketId ?: 0]);
        $ticket = $ticketQuery->fetch();
        if (!$ticket || !in_array($status, $validStatuses, true) || strlen($message) < 3 || strlen($message) > 5000) {
            flash('danger', 'Choose a valid status and enter an update of at least 3 characters.');
            redirect($staff['role'] === 'admin' ? 'page=inbox' : 'page=department-inbox');
        }
        if ($staff['role'] === 'department' && (int) $staff['department_id'] !== (int) $ticket['department_id']) {
            http_response_code(403);
            exit('You can only update tickets assigned to your department.');
        }
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE tickets SET status = ? WHERE id = ?');
            $update->execute([$status, $ticketId]);
            $activity = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, user_id, status, message) VALUES (?, ?, ?, ?)');
            $activity->execute([$ticketId, $staff['id'], $status, $message]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        flash('success', 'The ticket update has been recorded.');
        redirect($staff['role'] === 'admin' ? 'page=inbox' : 'page=department-inbox');
    }

    if ($action === 'create_staff') {
        require_role('admin');
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $password = (string) ($_POST['password'] ?? '');
        $departmentQuery = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
        $departmentQuery->execute([$departmentId ?: 0]);
        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$departmentQuery->fetch() || strlen($password) < 8) {
            flash('danger', 'Enter a name, valid email, department, and password of at least 8 characters.');
            redirect('page=staff');
        }
        try {
            $insert = $pdo->prepare("INSERT INTO users (department_id, fullname, email, password_hash, role) VALUES (?, ?, ?, ?, 'department')");
            $insert->execute([$departmentId, $fullname, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That email address is already in use.');
            redirect('page=staff');
        }
        flash('success', 'Department staff account created.');
        redirect('page=staff');
    }

    if ($action === 'create_school') {
        require_role('admin');
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || strlen($name) > 150) {
            flash('danger', 'Enter a school name of no more than 150 characters.');
            redirect('page=organization');
        }
        try {
            $insert = $pdo->prepare('INSERT INTO schools (name) VALUES (?)');
            $insert->execute([$name]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That school is already listed.');
            redirect('page=organization');
        }
        flash('success', 'School added.');
        redirect('page=organization');
    }

    if ($action === 'create_department') {
        require_role('admin');
        $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $schoolQuery = $pdo->prepare('SELECT id FROM schools WHERE id = ?');
        $schoolQuery->execute([$schoolId ?: 0]);
        if (!$schoolQuery->fetch() || $name === '' || strlen($name) > 150) {
            flash('danger', 'Choose a school and enter a department name of no more than 150 characters.');
            redirect('page=organization');
        }
        try {
            $insert = $pdo->prepare('INSERT INTO departments (school_id, name) VALUES (?, ?)');
            $insert->execute([$schoolId, $name]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That department is already listed under this school.');
            redirect('page=organization');
        }
        flash('success', 'Department added.');
        redirect('page=organization');
    }

    if ($action === 'update_school') {
        require_role('admin');
        $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        if (!$schoolId || $name === '' || strlen($name) > 150) {
            flash('danger', 'Enter a valid school name.');
            redirect('page=organization');
        }
        try {
            $update = $pdo->prepare('UPDATE schools SET name = ? WHERE id = ?');
            $update->execute([$name, $schoolId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'A school with that name already exists.');
            redirect('page=organization');
        }
        flash('success', 'School name updated.');
        redirect('page=organization');
    }

    if ($action === 'update_department') {
        require_role('admin');
        $departmentId = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $schoolQuery = $pdo->prepare('SELECT id FROM schools WHERE id = ?');
        $schoolQuery->execute([$schoolId ?: 0]);
        if (!$departmentId || !$schoolQuery->fetch() || $name === '' || strlen($name) > 150) {
            flash('danger', 'Choose a school and enter a valid department name.');
            redirect('page=organization');
        }
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE departments SET school_id = ?, name = ? WHERE id = ?');
            $update->execute([$schoolId, $name, $departmentId]);
            $studentSchoolUpdate = $pdo->prepare('UPDATE users SET school_id = ? WHERE department_id = ?');
            $studentSchoolUpdate->execute([$schoolId, $departmentId]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That department name already exists in the selected school.');
            redirect('page=organization');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Department updated.');
        redirect('page=organization');
    }

    if ($action === 'create_course') {
        $staff = require_role('admin', 'department');
        $courseDepartment = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($staff['role'] === 'department') {
            $courseDepartment = (int) $staff['department_id'];
        }
        $departmentQuery = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
        $departmentQuery->execute([$courseDepartment ?: 0]);
        if (!$departmentQuery->fetch() || $code === '' || strlen($code) > 30 || $name === '' || strlen($name) > 160) {
            flash('danger', 'Enter a valid course code, title, and department.');
            redirect('page=courses');
        }
        try {
            $insert = $pdo->prepare('INSERT INTO courses (department_id, code, name) VALUES (?, ?, ?)');
            $insert->execute([$courseDepartment, $code, $name]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('danger', 'That course code already exists in this department.');
            redirect('page=courses');
        }
        flash('success', 'Course added to the student course list.');
        redirect('page=courses');
    }

    flash('danger', 'That action is not available.');
    redirect();
}

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0 && $page === 'setup') {
    redirect($current === null ? 'page=login' : 'page=dashboard');
}
if ($adminCount === 0 && $page !== 'setup' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('page=setup');
}

if ($page === '' || $page === 'dashboard') {
    if ($current === null) {
        $page = 'home';
    } else {
        $page = 'dashboard';
    }
}
if ($current !== null && in_array($page, ['home', 'login', 'register', 'setup'], true)) {
    redirect('page=dashboard');
}

$pageTitles = [
    'home' => 'Student Support',
    'setup' => 'Initial setup',
    'login' => 'Sign in',
    'register' => 'Create student account',
    'track' => 'Track a complaint',
    'dashboard' => 'Dashboard',
    'new-ticket' => 'New support ticket',
    'my-tickets' => 'My complaints',
    'inbox' => 'All support tickets',
    'department-inbox' => 'Department inbox',
    'staff' => 'Department accounts',
    'courses' => 'Course directory',
    'organization' => 'Schools & departments',
];
$title = $pageTitles[$page] ?? 'Page not found';
$allowedPages = [
    'student' => ['dashboard', 'new-ticket', 'my-tickets'],
    'admin' => ['dashboard', 'inbox', 'staff', 'courses', 'organization'],
    'department' => ['dashboard', 'department-inbox', 'courses'],
];
if ($current !== null && isset($allowedPages[$current['role']]) && in_array($page, $allowedPages[$current['role']], true)) {
    $role = $current['role'];
} elseif ($page === 'home' || $page === 'setup' || $page === 'login' || $page === 'register' || $page === 'track') {
    $role = 'public';
} else {
    http_response_code($current === null ? 401 : 403);
    if ($current === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('page=login');
    }
    exit('You do not have permission to view this page.');
}

if ($page === 'departments') {
    exit;
}

$flashMessage = take_flash();
require __DIR__ . '/app/layout-start.php';
require __DIR__ . '/app/views.php';
require __DIR__ . '/app/layout-end.php';
