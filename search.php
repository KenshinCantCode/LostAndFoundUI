<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$database = new Database();
$conn = $database->getConnection();
$categories = getCategories();

// Get filter values
$search = sanitize($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$category = intval($_GET['category'] ?? 0);
$location = sanitize($_GET['location'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? 'open';

// Build query
$query = "SELECT i.*, c.name as category_name, c.icon as category_icon, u.full_name as reporter_name
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id
          LEFT JOIN users u ON i.user_id = u.id
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

if ($category > 0) {
    $query .= " AND i.category_id = ?";
    $params[] = $category;
}

if (!empty($location)) {
    $query .= " AND i.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($date_from)) {
    $query .= " AND i.date_occurred >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND i.date_occurred <= ?";
    $params[] = $date_to;
}

if ($status !== 'all') {
    $query .= " AND i.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Search Items";
require_once 'includes/header.php';
?>

<div class="row">
    <!-- Filters Sidebar -->
    <div class="col-lg-3 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="search.php">
                    <!-- Search Box -->
                    <div class="mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="q" 
                               placeholder="Search items..." value="<?= sanitize($search) ?>">
                    </div>

                    <!-- Type -->
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost Items</option>
                            <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found Items</option>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="0" <?= $category === 0 ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= $cat['name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" 
                               placeholder="e.g., Library" value="<?= sanitize($location) ?>">
                    </div>

                    <!-- Date Range -->
                    <div class="mb-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="matched" <?= $status === 'matched' ? 'selected' : '' ?>>Matched</option>
                            <option value="claimed" <?= $status === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                            <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="search.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-search me-2"></i>Search Results</h1>
            <span class="badge bg-secondary fs-6"><?= count($items) ?> items found</span>
        </div>

        <?php if (empty($items)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h4>No items found</h4>
                <p class="text-muted">Try adjusting your search filters or <a href="report-lost.php">report a lost item</a>.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm item-card">
                    <div class="position-relative">
                        <?php if ($item['image']): ?>
                        <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" 
                             class="card-img-top" alt="<?= sanitize($item['title']) ?>" 
                             style="height:180px;object-fit:cover;">
                        <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                            <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> fa-3x text-muted"></i>
                        </div>
                        <?php endif; ?>
                        <span class="position-absolute top-0 end-0 m-2 badge <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>">
                            <?= ucfirst($item['type']) ?>
                        </span>
                        <?php if ($item['status'] !== 'open'): ?>
                        <span class="position-absolute top-0 start-0 m-2 badge bg-secondary">
                            <?= ucfirst($item['status']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?= sanitize($item['title']) ?></h6>
                        <p class="card-text text-muted small mb-1">
                            <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> me-1"></i>
                            <?= $item['category_name'] ?? 'Other' ?>
                        </p>
                        <p class="card-text text-muted small mb-1">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?= sanitize($item['location']) ?>
                        </p>
                        <p class="card-text text-muted small">
                            <i class="fas fa-calendar me-1"></i>
                            <?= formatDate($item['date_occurred']) ?>
                        </p>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
