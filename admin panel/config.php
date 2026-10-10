<?php
// XAMPP defaults: MySQL user "root" and an empty password.
// If your local MySQL password is different, update DB_PASS below.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_affairs');

try {
    $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $server->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tracking_code CHAR(12) NOT NULL UNIQUE,
        student_name VARCHAR(120) NOT NULL,
        student_class VARCHAR(60) NOT NULL,
        section_name VARCHAR(60) DEFAULT '',
        category VARCHAR(100) NOT NULL,
        subject VARCHAR(180) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('Pending','In Progress','Resolved') NOT NULL DEFAULT 'Pending',
        admin_note TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $check = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
    $check->execute(['admin']);
    if (!$check->fetch()) {
        $insert = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $insert->execute(['admin', password_hash('ChangeMe123!', PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Start MySQL in XAMPP and check DB_USER/DB_PASS in config.php.');
}
?>
