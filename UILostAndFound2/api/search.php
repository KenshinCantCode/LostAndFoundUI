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

$limit = min(max($limit, 1), 50);
$query .= " ORDER BY i.created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key + 1, $value, $type);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'results' => $results
]);
?>
