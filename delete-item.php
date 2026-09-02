<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/my-reports.php');
}

$database = new Database();
$conn = $database->getConnection();

$item_id = intval($_POST['item_id'] ?? 0);
if (!$item_id) {
    setFlash('danger', 'Invalid item ID');
    redirect(SITE_URL . '/my-reports.php');
}

// Get item
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    setFlash('danger', 'Item not found or you do not have permission');
    redirect(SITE_URL . '/my-reports.php');
}

// Delete image
if ($item['image']) {
    $image_path = ITEMS_UPLOAD . $item['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

// Delete related records
$conn->prepare("DELETE FROM matches WHERE lost_item_id = ? OR found_item_id = ?")->execute([$item_id, $item_id]);
$conn->prepare("DELETE FROM claims WHERE item_id = ?")->execute([$item_id]);
$conn->prepare("DELETE FROM items WHERE id = ?")->execute([$item_id]);

logActivity($_SESSION['user_id'], 'delete_item', "Deleted item: {$item['title']}");

setFlash('success', 'Item report deleted successfully!');
redirect(SITE_URL . '/my-reports.php');
?>
