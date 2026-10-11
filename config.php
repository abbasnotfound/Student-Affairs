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

// =====================================================================
// SENDER EMAIL & SMTP CONFIGURATION
// >>> studentaffairsccs@gmail.com <<<
// =====================================================================
define('MAIL_FROM_EMAIL', 'studentaffairsccs@gmail.com');  // Set your sender email address here
define('MAIL_FROM_NAME',  'Student Affairs Portal');      // Set your sender display name here

// To send REAL live emails to inboxes (e.g. via Gmail, Outlook, or SMTP server):
define('SMTP_ENABLED',    false);                         // Change to true to enable real SMTP delivery
define('SMTP_HOST',       'smtp.gmail.com');              // SMTP server (e.g. smtp.gmail.com)
define('SMTP_PORT',       587);                           // SMTP port: 587 (TLS) or 465 (SSL)
define('SMTP_ENCRYPTION', 'tls');                         // 'tls' or 'ssl'
define('SMTP_USER',       'studentaffairsccs@gmail.com');        // Your email address
define('SMTP_PASS',       'your-16-char-app-password');   // For Gmail: 16-character Google App Password

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

    // Upgrade existing complaints table if missing columns or lengths from earlier versions
    $pdo->exec("ALTER TABLE `complaints` MODIFY COLUMN `tracking_code` VARCHAR(32) NOT NULL");
    $pdo->exec("ALTER TABLE `complaints` MODIFY COLUMN `status` ENUM('Pending', 'In Progress', 'Resolved', 'Rejected') NOT NULL DEFAULT 'Pending'");
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

    // 4. Email logs (tracks outgoing notifications with tracking codes)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `complaint_id` INT UNSIGNED DEFAULT NULL,
        `recipient_email` VARCHAR(254) NOT NULL,
        `tracking_code` VARCHAR(32) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body_preview` TEXT NOT NULL,
        `delivery_status` ENUM('Sent', 'Logged', 'Failed') NOT NULL DEFAULT 'Sent',
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

    // Seed sample complaints if complaints table is empty so admin panel immediately shows live connected data
    $complaintCount = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
    if ($complaintCount === 0) {
        $sampleStmt = $pdo->prepare('
            INSERT INTO complaints (
                tracking_code, student_name, student_class, section_name, email,
                category, subject, description, status, admin_note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $sampleStmt->execute([
            'SA-9B2F4E18A0C3D651',
            'Sara Khan',
            'BS Computer Science (Year 2)',
            'B',
            'sara.khan@college.edu',
            'Facilities & Classrooms',
            'Broken projector in CS Lab 3',
            'The ceiling HDMI projector keeps flickering and turns off after 5 minutes during lectures.',
            'Pending',
            null
        ]);
        $sampleStmt->execute([
            'SA-4E7A1C93F8B20D45',
            'Bilal Ahmed',
            'BBA (Year 3)',
            'A',
            'bilal.ahmed@college.edu',
            'Cleanliness & Washrooms',
            'Water dispenser filter replacement needed',
            'The 2nd floor water cooler water tastes metallic and needs a fresh filter cartridge.',
            'In Progress',
            'Facilities management has been notified. New filter requested from maintenance.'
        ]);
        $sampleStmt->execute([
            'SA-2D8F6B109E3C7A54',
            'Zainab Fatima',
            'BS Software Engineering (Year 1)',
            'C',
            'zainab.f@college.edu',
            'IT & Equipment',
            'Campus Wi-Fi connectivity in Library',
            'Frequent disconnects when trying to access digital library resources from the east wing reading area.',
            'Resolved',
            'IT Dept reconfigured Access Point #4 in the Library East Wing. Signal strength is now restored.'
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

/**
 * Sends the complaint tracking code automatically to the student's email.
 * Includes both real mail() dispatch and fallback logging for local XAMPP.
 */
function send_tracking_code_email(
    string $recipientEmail,
    string $studentName,
    string $trackingCode,
    string $complaintSubject,
    string $category,
    ?int $complaintId = null
): bool {
    $emailSubject = "Student Affairs - Tracking Code: {$trackingCode}";

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $trackUrl = "{$protocol}://{$host}/Student-Affairs-1/index.html#track";

    $escapedName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
    $escapedSubject = htmlspecialchars($complaintSubject, ENT_QUOTES, 'UTF-8');
    $escapedCategory = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
    $escapedCode = htmlspecialchars($trackingCode, ENT_QUOTES, 'UTF-8');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Your Complaint Tracking Code</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f7f9fd; margin: 0; padding: 24px; color: #18253d; }
    .card { max-width: 580px; margin: 0 auto; background: #ffffff; border: 1px solid #e5eaf2; border-radius: 14px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .brand { margin-bottom: 22px; border-bottom: 1px solid #edf0f5; padding-bottom: 16px; }
    .badge { width: 38px; height: 38px; background: #2459d3; color: #ffffff; font-weight: 800; border-radius: 10px; text-align: center; line-height: 38px; font-size: 13px; display: inline-block; vertical-align: middle; }
    .title { font-size: 16px; font-weight: 700; color: #10264a; display: inline-block; vertical-align: middle; margin-left: 10px; }
    h2 { font-size: 21px; color: #10264a; margin-top: 0; }
    p { font-size: 14px; line-height: 1.6; color: #475569; }
    .code-box { background: #f5f8ff; border: 1px solid #dbe6fa; border-radius: 8px; padding: 18px; text-align: center; margin: 22px 0; }
    .code-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #2459d3; letter-spacing: 1px; }
    .tracking-code { font-family: "Courier New", Courier, monospace; font-size: 21px; font-weight: 800; color: #10264a; margin-top: 6px; letter-spacing: 1px; }
    .details { background: #fafbfc; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 13px; border: 1px solid #edf0f5; }
    .details p { margin: 6px 0; font-size: 13px; }
    .btn { display: inline-block; background: #2459d3; color: #ffffff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; margin: 12px 0; }
    .footer { margin-top: 28px; border-top: 1px solid #edf0f5; padding-top: 16px; font-size: 11px; color: #94a3b8; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <span class="badge">SA</span>
      <span class="title">Student Affairs · Student Complaint Portal</span>
    </div>

    <h2>Complaint Confirmation</h2>
    <p>Dear <strong>{$escapedName}</strong>,</p>
    <p>Your concern has been registered with the Student Affairs office. Below is your official tracking code to monitor its live status and view staff responses:</p>

    <div class="code-box">
      <div class="code-label">Your Unique Tracking Code</div>
      <div class="tracking-code">{$escapedCode}</div>
    </div>

    <div class="details">
      <p><strong>Category:</strong> {$escapedCategory}</p>
      <p><strong>Subject:</strong> {$escapedSubject}</p>
      <p><strong>Current Status:</strong> Pending Review</p>
    </div>

    <p style="text-align: center;">
      <a href="{$trackUrl}" class="btn">Track Your Complaint Online →</a>
    </p>

    <div class="footer">
      <p>This is an automated confirmation sent by the Student Affairs Complaint Portal to {$recipientEmail}. Keep this code for your reference. For urgent safety concerns, please contact your college administration directly.</p>
    </div>
  </div>
</body>
</html>
HTML;

    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : ('no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'college.edu'));
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Student Affairs Portal';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        'X-Mailer: PHP/' . phpversion()
    ];

    $mailSuccess = false;
    $methodUsed = 'mail()';

    if (defined('SMTP_ENABLED') && SMTP_ENABLED === true) {
        $mailSuccess = send_smtp_socket_email($recipientEmail, $emailSubject, $htmlBody);
        $methodUsed = 'SMTP (' . SMTP_HOST . ')';
    } else {
        try {
            $mailSuccess = @mail($recipientEmail, $emailSubject, $htmlBody, implode("\r\n", $headers));
        } catch (\Throwable $t) {
            $mailSuccess = false;
        }
    }

    // Write to local logs/emails.log so local testing can be verified without external SMTP
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $deliveryStatus = $mailSuccess ? "SENT via {$methodUsed}" : "LOGGED (Local XAMPP / SMTP Disabled)";
    $logEntry = sprintf(
        "[%s] From: %s <%s> | To: %s | Code: %s | Subject: %s | Status: %s\n",
        date('Y-m-d H:i:s'),
        $fromName,
        $fromEmail,
        $recipientEmail,
        $trackingCode,
        $complaintSubject,
        $deliveryStatus
    );
    @file_put_contents($logDir . '/emails.log', $logEntry, FILE_APPEND);

    // Save in database email_logs table
    try {
        $pdo = get_db_connection();
        $logStmt = $pdo->prepare('
            INSERT INTO email_logs (complaint_id, recipient_email, tracking_code, subject, body_preview, delivery_status)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $logStmt->execute([
            $complaintId,
            $recipientEmail,
            $trackingCode,
            $emailSubject,
            "Tracking code {$trackingCode} sent for: {$complaintSubject}",
            $mailSuccess ? 'Sent' : 'Logged'
        ]);
    } catch (\Throwable $t) {
        error_log("Failed to insert email log: " . $t->getMessage());
    }

    return true;
}

/**
 * Direct lightweight SMTP mailer for Gmail, Outlook, or standard SMTP relays.
 */
function send_smtp_socket_email(string $to, string $subject, string $htmlBody): bool {
    $host = SMTP_HOST;
    $port = (int)SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $encryption = strtolower(SMTP_ENCRYPTION);

    $remoteHost = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
    $socket = @stream_socket_client("{$remoteHost}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("SMTP connection failed: {$errstr} ({$errno})");
        return false;
    }

    $read = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $write = function(string $cmd) use ($socket): void {
        fputs($socket, $cmd . "\r\n");
    };

    $response = $read();
    if (!str_starts_with($response, '220')) {
        fclose($socket);
        return false;
    }

    $clientName = gethostname() ?: 'localhost';
    $write("EHLO {$clientName}");
    $response = $read();

    if ($encryption === 'tls') {
        $write("STARTTLS");
        $response = $read();
        if (!str_starts_with($response, '220')) {
            fclose($socket);
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $write("EHLO {$clientName}");
        $response = $read();
    }

    if (!empty($user) && !empty($pass)) {
        $write("AUTH LOGIN");
        $response = $read();
        $write(base64_encode($user));
        $response = $read();
        $write(base64_encode($pass));
        $response = $read();
        if (!str_starts_with($response, '235')) {
            error_log("SMTP authentication failed: {$response}");
            fclose($socket);
            return false;
        }
    }

    $fromEmail = MAIL_FROM_EMAIL ?: $user;
    $write("MAIL FROM:<{$fromEmail}>");
    $read();
    $write("RCPT TO:<{$to}>");
    $read();
    $write("DATA");
    $read();

    $headers = [
        "From: " . MAIL_FROM_NAME . " <{$fromEmail}>",
        "To: <{$to}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "Date: " . date('r'),
        "Message-ID: <" . bin2hex(random_bytes(16)) . "@{$host}>"
    ];

    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
    $write($emailData);
    $response = $read();

    $write("QUIT");
    fclose($socket);

    return str_starts_with($response, '250');
}
