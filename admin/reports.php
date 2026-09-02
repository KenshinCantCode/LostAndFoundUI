<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($item_id > 0) {
        switch ($action) {
            case 'delete':
                $stmt = $conn->prepare("SELECT image, title FROM items WHERE id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($item && $item['image']) {
                    $image_path = ITEMS_UPLOAD . $item['image'];
                    if (file_exists($image_path)) unlink($image_path);
                }
                $conn->prepare("DELETE FROM items WHERE id = ?")->execute([$item_id]);
                setFlash('success', 'Item deleted successfully');
                break;
            case 'mark_returned':
                $conn->prepare("UPDATE items SET status = 'returned', is_resolved = 1 WHERE id = ?")->execute([$item_id]);

                // Notify owner
                $stmt = $conn->prepare("SELECT user_id, title FROM items WHERE id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                createNotification($item['user_id'], 'Item Marked as Returned', "Your item '{$item['title']}' has been marked as returned.", 'admin', SITE_URL . '/item.php?id=' . $item_id);
                setFlash('success', 'Item marked as returned');
                break;
            case 'mark_closed':
                $conn->prepare("UPDATE items SET status = 'closed', is_resolved = 1 WHERE id = ?")->execute([$item_id]);
                setFlash('success', 'Item marked as closed');
                break;
            case 'reopen':
                $conn->prepare("UPDATE items SET status = 'open', is_resolved = 0 WHERE id = ?")->execute([$item_id]);
                setFlash('success', 'Item reopened');
                break;
        }
        logActivity($_SESSION['user_id'], 'admin_item_' . $action, "Admin item action on #$item_id");
        redirect(SITE_URL . '/admin/reports.php');
    }
}

$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = sanitize($_GET['q'] ?? '');

$query = "SELECT i.*, u.username, c.name as category_name, 
          (SELECT COUNT(*) FROM claims WHERE item_id = i.id) as claim_count
          FROM items i 
          LEFT JOIN users u ON i.user_id = u.id
          LEFT JOIN categories c ON i.category_id = c.id
          WHERE 1=1";
$params = [];

if ($type_filter !== 'all' && in_array($type_filter, ['lost', 'found'])) {
    $query .= " AND i.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (i.title LIKE ? OR i.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY i.created_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-list me-2"></i>Manage Reports</h1>
            <span class="badge bg-secondary fs-6"><?= count($items) ?> reports</span>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="lost" <?= $type_filter === 'lost' ? 'selected' : '' ?>>Lost</option>
                            <option value="found" <?= $type_filter === 'found' ? 'selected' : '' ?>>Found</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="matched" <?= $status_filter === 'matched' ? 'selected' : '' ?>>Matched</option>
                            <option value="claimed" <?= $status_filter === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                            <option value="returned" <?= $status_filter === 'returned' ? 'selected' : '' ?>>Returned</option>
                            <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="q" class="form-control" placeholder="Search..." value="<?= sanitize($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Reported By</th>
                                <th>Claims</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['image']): ?>
                                        <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" 
                                             class="rounded me-2" style="width:40px;height:40px;object-fit:cover;">
                                        <?php else: ?>
                                        <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                            <i class="fas fa-tag text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                        <a href="../item.php?id=<?= $item['id'] ?>" class="text-decoration-none">
                                            <?= sanitize($item['title']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>">
                                        <?= ucfirst($item['type']) ?>
                                    </span>
                                </td>
                                <td><?= $item['category_name'] ?? '-' ?></td>
                                <td><?= sanitize($item['username'] ?? 'Unknown') ?></td>
                                <td><span class="badge bg-info"><?= $item['claim_count'] ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $item['status'] === 'open' ? 'warning' : ($item['status'] === 'returned' ? 'success' : ($item['status'] === 'claimed' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($item['created_at']) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="../item.php?id=<?= $item['id'] ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($item['status'] !== 'returned'): ?>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Mark this item as returned?')">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="action" value="mark_returned" class="dropdown-item text-success">
                                                        <i class="fas fa-check-circle me-2"></i>Mark Returned
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($item['is_resolved']): ?>
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="action" value="reopen" class="dropdown-item text-warning">
                                                        <i class="fas fa-redo me-2"></i>Reopen
                                                    </button>
                                                </form>
                                            </li>
                                            <?php else: ?>
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="action" value="mark_closed" class="dropdown-item text-secondary">
                                                        <i class="fas fa-times-circle me-2"></i>Close
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Delete this report?')">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="action" value="delete" class="dropdown-item text-danger">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
