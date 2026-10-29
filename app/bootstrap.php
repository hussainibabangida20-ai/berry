<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('BASE_PATH', $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/'));

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'fcwm_student_support';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $server = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $schema = [
        "CREATE TABLE IF NOT EXISTS schools (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS departments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            UNIQUE KEY school_department_name (school_id, name),
            CONSTRAINT departments_school_fk FOREIGN KEY (school_id) REFERENCES schools(id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            fullname VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            matric_number VARCHAR(50) NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'student',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT users_school_fk FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
            CONSTRAINT users_department_fk FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
            INDEX users_role_idx (role)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS courses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            department_id INT UNSIGNED NOT NULL,
            code VARCHAR(30) NOT NULL,
            name VARCHAR(160) NOT NULL,
            UNIQUE KEY department_course_code (department_id, code),
            CONSTRAINT courses_department_fk FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(32) NOT NULL UNIQUE,
            student_id INT UNSIGNED NOT NULL,
            department_id INT UNSIGNED NULL,
            routed_by INT UNSIGNED NULL,
            course_id INT UNSIGNED NULL,
            course_name VARCHAR(180) NOT NULL,
            topic VARCHAR(100) NOT NULL,
            request TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT tickets_student_fk FOREIGN KEY (student_id) REFERENCES users(id),
            CONSTRAINT tickets_department_fk FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
            CONSTRAINT tickets_router_fk FOREIGN KEY (routed_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT tickets_course_fk FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
            INDEX tickets_department_status_idx (department_id, status),
            INDEX tickets_student_idx (student_id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS ticket_updates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            status VARCHAR(20) NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT updates_ticket_fk FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
            CONSTRAINT updates_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX updates_ticket_idx (ticket_id, created_at)
        ) ENGINE=InnoDB",
    ];
    foreach ($schema as $statement) {
        $connection->exec($statement);
    }

    seed_academic_structure($connection);
    return $connection;
}

function seed_academic_structure(PDO $connection): void
{
    $schools = [
        'School of Wildlife Management' => ['Wildlife Management'],
        'School of Fisheries and Aquaculture' => ['Fisheries Technology'],
        'School of Forestry and Forest Products' => ['Forestry Technology'],
        'School of Agricultural Technology' => ['Agricultural Technology'],
        'School of Environmental Management' => ['Environmental Management'],
    ];
    $schoolInsert = $connection->prepare('INSERT IGNORE INTO schools (name) VALUES (?)');
    $schoolIdQuery = $connection->prepare('SELECT id FROM schools WHERE name = ?');
    $departmentInsert = $connection->prepare('INSERT IGNORE INTO departments (school_id, name) VALUES (?, ?)');

    foreach ($schools as $schoolName => $departments) {
        $schoolInsert->execute([$schoolName]);
        $schoolIdQuery->execute([$schoolName]);
        $schoolId = (int) $schoolIdQuery->fetchColumn();
        foreach ($departments as $departmentName) {
            $departmentInsert->execute([$schoolId, $departmentName]);
        }
    }
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $query = ''): string
{
    return BASE_PATH . '/index.php' . ($query === '' ? '' : '?' . ltrim($query, '?'));
}

function asset(string $path): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function redirect(string $query = ''): void
{
    header('Location: ' . url($query));
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('The form expired or could not be verified. Go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_role(string ...$roles): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('page=login');
    }
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $user;
}

function status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'In progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        default => 'Pending',
    };
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'in_progress' => 'bg-light-warning text-warning',
        'resolved' => 'bg-light-success text-success',
        'closed' => 'bg-light-secondary text-secondary',
        default => 'bg-light-primary text-primary',
    };
    return '<span class="badge rounded-pill ' . $class . '">' . h(status_label($status)) . '</span>';
}

function formatted_date(string $date): string
{
    return date('M j, Y · g:i a', strtotime($date));
}
