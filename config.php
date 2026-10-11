<?php
/**
 * Student Affairs Complaint Portal
 * Database Configuration & Security Helpers
 */

declare(strict_types=1);

// Prevent direct access if needed, or define constants
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

// Database Credentials (XAMPP defaults)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'student_affairs');

/**
 * Get or create the shared PDO database connection.
 */
function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        // Connect to MySQL server first to ensure database exists
        $serverPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Now connect to the specific database
        $dbDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dbDsn, DB_USER, DB_PASS, $options);

        // Ensure tables exist and match current schema
        init_database_tables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        http_response_code(500);
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please ensure MySQL is running in XAMPP.'
            ]);
        } else {
            echo "<!doctype html><html><body style='font-family:sans-serif;padding:30px;background:#f9fafb;'>";
            echo "<div style='max-width:550px;margin:auto;background:white;padding:24px;border-radius:10px;border:1px solid #e5e7eb;'>";
            echo "<h2 style='color:#b91c1c;margin-top:0;'>Database Connection Error</h2>";
            echo "<p>Could not connect to MySQL server. Please verify:</p>";
            echo "<ul><li>MySQL module is started in the XAMPP Control Panel.</li><li>Credentials in <code>config.php</code> are correct.</li></ul>";
            echo "<p style='color:#6b7280;font-size:12px;'>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div></body></html>";
        }
        exit;
    }
}

/**
 * Helper to add a missing column to an existing table.
 */
function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([DB_NAME, $table, $column]);
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

/**
 * Automatically creates all tables and initial admin if they don't already exist.
 */
function init_database_tables(PDO $pdo): void {
    // 1. Admins and Staff table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(80) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
        `full_name` VARCHAR(120) NOT NULL DEFAULT '',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Upgrade existing admins table if missing columns from earlier versions
    ensure_column($pdo, 'admins', 'role', "ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'");
    ensure_column($pdo, 'admins', 'full_name', "VARCHAR(120) NOT NULL DEFAULT ''");
    ensure_column($pdo, 'admins', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // 2. Complaints table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `complaints` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `tracking_code` VARCHAR(32) NOT NULL UNIQUE,
        `student_name` VARCHAR(120) NOT NULL,
        `student_class` VARCHAR(60) NOT NULL,
        `section_name` VARCHAR(60) NOT NULL DEFAULT '',
        `email` VARCHAR(254) NOT NULL DEFAULT '',
        `category` VARCHAR(100) NOT NULL,
        `subject` VARCHAR(180) NOT NULL,
        `description` TEXT NOT NULL,
        `status` ENUM('Pending', 'In Progress', 'Resolved', 'Rejected') NOT NULL DEFAULT 'Pending',
        `admin_note` TEXT DEFAULT NULL,
        `assigned_to` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_status` (`status`),
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Upgrade existing complaints table if missing columns from earlier versions
    ensure_column($pdo, 'complaints', 'email', "VARCHAR(254) NOT NULL DEFAULT ''");
    ensure_column($pdo, 'complaints', 'assigned_to', "INT UNSIGNED DEFAULT NULL");

    // 3. Complaint logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `complaint_logs` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `complaint_id` INT UNSIGNED NOT NULL,
        `changed_by` INT UNSIGNED DEFAULT NULL,
        `old_status` VARCHAR(50) NOT NULL,
        `new_status` VARCHAR(50) NOT NULL,
        `note` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default admin if missing or update role to admin
    $check = $pdo->prepare('SELECT id, role FROM admins WHERE username = ? LIMIT 1');
    $check->execute(['admin']);
    $existingAdmin = $check->fetch();
    if (!$existingAdmin) {
        $insert = $pdo->prepare('INSERT INTO admins (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)');
        $insert->execute([
            'admin',
            password_hash('ChangeMe123!', PASSWORD_DEFAULT),
            'admin',
            'System Administrator'
        ]);
    } else {
        $updateAdmin = $pdo->prepare("UPDATE admins SET role = 'admin', full_name = 'System Administrator' WHERE username = 'admin' AND (role != 'admin' OR full_name = '')");
        $updateAdmin->execute();
    }

    // Seed default staff handler if none exists
    $checkStaff = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
    $checkStaff->execute(['staff']);
    if (!$checkStaff->fetch()) {
        $insertStaff = $pdo->prepare('INSERT INTO admins (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)');
        $insertStaff->execute([
            'staff',
            password_hash('Staff@12345', PASSWORD_DEFAULT),
            'staff',
            'Complaint Officer'
        ]);
    }
}

/**
 * Configure and start secure PHP session.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        
        session_set_cookie_params([
            'lifetime' => 86400 * 2, // 2 days
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

/**
 * Generate or get existing CSRF token.
 */
function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function verify_csrf_token(?string $token): bool {
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the user is currently authenticated.
 */
function is_authenticated(): bool {
    start_secure_session();
    return !empty($_SESSION['admin_id']);
}

/**
 * Get the authenticated user record.
 */
function current_user(): ?array {
    start_secure_session();
    if (!is_authenticated()) {
        return null;
    }
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'staff',
        'full_name' => $_SESSION['admin_full_name'] ?? ''
    ];
}

/**
 * Require authentication or redirect/halt.
 */
function require_auth(?string $requiredRole = null): void {
    start_secure_session();
    if (!is_authenticated()) {
        header('Location: admin.php');
        exit;
    }

    if ($requiredRole !== null && ($_SESSION['admin_role'] ?? '') !== $requiredRole) {
        http_response_code(403);
        echo "<!doctype html><html><body style='font-family:sans-serif;padding:30px;'>";
        echo "<h2>403 Forbidden</h2><p>You do not have administrative permission to view this page.</p>";
        echo "<p><a href='admin.php'>Return to dashboard</a></p></body></html>";
        exit;
    }
}

/**
 * Standardized JSON response helper.
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize string input.
 */
function sanitize_string(?string $str): string {
    return trim((string)$str);
}
