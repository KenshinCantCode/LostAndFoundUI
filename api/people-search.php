<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sign in to search people.']);
    exit;
}

$queryInput = $_GET['q'] ?? '';
$query = is_string($queryInput) ? trim($queryInput) : '';
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$query = substr($query, 0, 100);
$searchTerm = '%' . $query . '%';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("
        SELECT id, username, full_name, role
        FROM users
        WHERE id <> ? AND is_active = 1 AND email_verified_at IS NOT NULL
          AND (full_name LIKE ? OR username LIKE ?)
        ORDER BY CASE WHEN username = ? THEN 0 WHEN full_name = ? THEN 1 ELSE 2 END, full_name
        LIMIT 8
    ");
    $stmt->execute([
        (int) $_SESSION['user_id'],
        $searchTerm,
        $searchTerm,
        $query,
        $query,
    ]);

    echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('People search failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'People search is temporarily unavailable.']);
}
