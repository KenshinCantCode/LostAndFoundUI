<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

$search = sanitize($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$limit = intval($_GET['limit'] ?? 10);

if (empty($search) && $type === 'all') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$query = "SELECT i.id, i.title, i.type, i.location, i.date_occurred, i.image, c.name as category_name
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id
          WHERE i.is_resolved = 0";
$params = [];

if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($type !== 'all' && in_array($type, ['lost', 'found'])) {
    $query .= " AND i.type = ?";
    $params[] = $type;
}

$query .= " ORDER BY i.created_at DESC LIMIT ?";
$params[] = min($limit, 50);

$stmt = $conn->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'results' => $results
]);
?>
