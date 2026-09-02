<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <!-- Sidebar -->
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="col-lg-9">
        <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h1>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Users</h6>
                                <h3 class="mb-0"><?= $conn->query("SELECT COUNT(*) FROM users")->fetchColumn() ?></h3>
                            </div>
                            <i class="fas fa-users fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Lost Items</h6>
                                <h3 class="mb-0"><?= $conn->query("SELECT COUNT(*) FROM items WHERE type='lost'")->fetchColumn() ?></h3>
                            </div>
                            <i class="fas fa-frown fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Found Items</h6>
                                <h3 class="mb-0"><?= $conn->query("SELECT COUNT(*) FROM items WHERE type='found'")->fetchColumn() ?></h3>
                            </div>
                            <i class="fas fa-smile fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pending Claims</h6>
                                <h3 class="mb-0"><?= $conn->query("SELECT COUNT(*) FROM claims WHERE status='pending'")->fetchColumn() ?></h3>
                            </div>
                            <i class="fas fa-hand-paper fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- More Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-1"><?= $conn->query("SELECT COUNT(*) FROM items WHERE status='returned'")->fetchColumn() ?></h3>
                        <small class="text-muted">Items Returned</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-1"><?= $conn->query("SELECT COUNT(*) FROM items WHERE status='open'")->fetchColumn() ?></h3>
                        <small class="text-muted">Open Reports</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-1"><?= $conn->query("SELECT COUNT(*) FROM matches")->fetchColumn() ?></h3>
                        <small class="text-muted">Items Matched</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-danger mb-1"><?= $conn->query("SELECT COUNT(*) FROM categories")->fetchColumn() ?></h3>
                        <small class="text-muted">Categories</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activity -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-activity me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $conn->query("
                            SELECT a.*, u.username FROM activity_log a 
                            LEFT JOIN users u ON a.user_id = u.id 
                            ORDER BY a.created_at DESC LIMIT 10
                        ");
                        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if (empty($activities)): ?>
                        <p class="text-muted text-center py-4">No activity yet</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?= $activity['username'] ?? 'System' ?></strong>
                                        <span class="text-muted">- <?= str_replace('_', ' ', ucfirst($activity['action'])) ?></span>
                                    </div>
                                    <small class="text-muted"><?= timeAgo($activity['created_at']) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Reports</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $conn->query("
                            SELECT i.*, u.username, c.name as category_name 
                            FROM items i 
                            LEFT JOIN users u ON i.user_id = u.id
                            LEFT JOIN categories c ON i.category_id = c.id
                            ORDER BY i.created_at DESC LIMIT 5
                        ");
                        $recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if (empty($recent_items)): ?>
                        <p class="text-muted text-center py-4">No reports yet</p>
                        <?php else: ?>
                        <?php foreach ($recent_items as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <strong><?= sanitize($item['title']) ?></strong>
                                <br>
                                <small class="text-muted">by <?= $item['username'] ?? 'Unknown' ?> - <?= $item['category_name'] ?? 'Other' ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>"><?= ucfirst($item['type']) ?></span>
                                <br>
                                <a href="../item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary mt-1">View</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
