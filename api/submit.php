<?php
/**
 * Student Affairs - Submit Complaint API Endpoint
 * Accepts POST requests from the public submission form.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Ensure CORS / Content-Type headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ], 405);
}

// Support both application/json and multipart/form-data
$inputData = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    $rawBody = file_get_contents('php://input');
    $inputData = json_decode($rawBody, true) ?? [];
} else {
    $inputData = $_POST;
}

// Extract and sanitize input fields
$name = trim((string)($inputData['name'] ?? ''));
$studentClass = trim((string)($inputData['studentId'] ?? $inputData['student_class'] ?? ''));
$section = trim((string)($inputData['section'] ?? ''));
$email = trim((string)($inputData['email'] ?? ''));
$category = trim((string)($inputData['category'] ?? ''));
$subject = trim((string)($inputData['subject'] ?? ''));
$description = trim((string)($inputData['description'] ?? ''));

// Server-side validation
$errors = [];

if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'Full name is required and must not exceed 120 characters.';
}

if ($studentClass === '' || mb_strlen($studentClass) > 60) {
    $errors[] = 'Class / Student ID is required and must not exceed 60 characters.';
}

if (mb_strlen($section) > 60) {
    $errors[] = 'Section must not exceed 60 characters.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    $errors[] = 'A valid email address is required.';
}

$allowedCategories = [
    'Facilities & Classrooms',
    'Cleanliness & Washrooms',
    'Teaching & Academics',
    'Administration',
    'Transport',
    'IT & Equipment',
    'Safety or Harassment',
    'Suggestion',
    'Other'
];

if ($category === '' || !in_array($category, $allowedCategories, true)) {
    $errors[] = 'Please select a valid complaint category.';
}

if ($subject === '' || mb_strlen($subject) > 180) {
    $errors[] = 'Subject is required and must not exceed 180 characters.';
}

$descLength = mb_strlen($description);
if ($descLength < 10 || $descLength > 3000) {
    $errors[] = 'Description must be between 10 and 3,000 characters.';
}

if (!empty($errors)) {
    json_response([
        'success' => false,
        'message' => implode(' ', $errors),
        'errors' => $errors
    ], 400);
}

// Generate unique tracking code (Format: SA- + 16 hex chars)
$pdo = get_db_connection();

function generate_unique_code(PDO $pdo): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'SA-' . strtoupper(bin2hex(random_bytes(8)));
        $check = $pdo->prepare('SELECT id FROM complaints WHERE tracking_code = ? LIMIT 1');
        $check->execute([$code]);
        if (!$check->fetch()) {
            return $code;
        }
    }
    // Fallback timestamp based code
    return 'SA-' . strtoupper(dechex((int)(microtime(true) * 1000))) . strtoupper(bin2hex(random_bytes(4)));
}

$trackingCode = generate_unique_code($pdo);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO complaints (
            tracking_code, student_name, student_class, section_name, email,
            category, subject, description, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $trackingCode,
        $name,
        $studentClass,
        $section,
        $email,
        $category,
        $subject,
        $description,
        'Pending'
    ]);

    $complaintId = (int)$pdo->lastInsertId();

    // Log the submission event
    $logStmt = $pdo->prepare('
        INSERT INTO complaint_logs (complaint_id, changed_by, old_status, new_status, note)
        VALUES (?, NULL, ?, ?, ?)
    ');
    $logStmt->execute([
        $complaintId,
        'None',
        'Pending',
        'Complaint submitted by student.'
    ]);

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => 'Complaint submitted successfully!',
        'tracking_code' => $trackingCode,
        'data' => [
            'tracking_code' => $trackingCode,
            'subject' => $subject,
            'category' => $category,
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Complaint insertion error: " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Failed to save complaint due to a server error. Please try again.'
    ], 500);
}
