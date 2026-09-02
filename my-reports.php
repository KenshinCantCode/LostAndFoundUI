<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$query = "SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.user_id = ?";
$params = [$user_id];

if ($type_filter !== 'all' && in_array($type_filter, ['lost', 'found'])) {
    $query .= " AND i.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all' && in_array($status_filter, ['open', 'matched', 'claimed', 'returned', 'closed'])) {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY i.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Reports";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-list me-2"></i>My Reports</h1>
    <div>
        <a href="report-lost.php" class="btn btn-danger"><i class="fas fa-plus me-1"></i>Report Lost</a>
        <a href="report-found.php" class="btn btn-success"><i class="fas fa-plus me-1"></i>Report Found</a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="lost" <?= $type_filter === 'lost' ? 'selected' : '' ?>>Lost</option>
                    <option value="found" <?= $type_filter === 'found' ? 'selected' : '' ?>>Found</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
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
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
            <div class="col-md-3">
                <a href="my-reports.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
            </div>
        </form>
    </div>
</div>

<!-- Reports Table -->
<div class="card shadow">
    <div class="card-body">
        <?php if (empty($items)): ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
            <h5>No reports found</h5>
            <p class="text-muted">You haven't created any reports yet.</p>
            <a href="report-lost.php" class="btn btn-primary">Create Your First Report</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Status</th>
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
                                    <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> text-muted"></i>
                                </div>
                                <?php endif; ?>
                                <a href="item.php?id=<?= $item['id'] ?>" class="text-decoration-none">
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
                        <td><?= sanitize($item['location']) ?></td>
                        <td><?= formatDate($item['date_occurred']) ?></td>
                        <td>
                            <span class="badge bg-<?= $item['status'] === 'open' ? 'warning' : ($item['status'] === 'returned' ? 'success' : ($item['status'] === 'claimed' ? 'info' : 'secondary')) ?>">
                                <?= ucfirst($item['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($item['status'] === 'open'): ?>
                                <a href="edit-item.php?id=<?= $item['id'] ?>" class="btn btn-outline-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="delete-item.php" class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this?')">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
