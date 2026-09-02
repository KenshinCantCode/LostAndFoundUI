<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user stats
$stats = [];
$stats['my_lost'] = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND type = 'lost'");
$stats['my_lost']->execute([$user_id]);
$stats['my_lost'] = $stats['my_lost']->fetchColumn();

$stats['my_found'] = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND type = 'found'");
$stats['my_found']->execute([$user_id]);
$stats['my_found'] = $stats['my_found']->fetchColumn();

$stats['my_claims'] = $conn->prepare("SELECT COUNT(*) FROM claims WHERE claimer_id = ?");
$stats['my_claims']->execute([$user_id]);
$stats['my_claims'] = $stats['my_claims']->fetchColumn();

$stats['returned'] = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND status = 'returned'");
$stats['returned']->execute([$user_id]);
$stats['returned'] = $stats['returned']->fetchColumn();

// Get recent reports
$stmt = $conn->prepare("
    SELECT i.*, c.name as category_name 
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.user_id = ? 
    ORDER BY i.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending claims on user's items
$stmt = $conn->prepare("
    SELECT c.*, i.title, i.type 
    FROM claims c
    JOIN items i ON c.item_id = i.id
    WHERE i.user_id = ? AND c.status = 'pending'
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$pending_claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Dashboard";
require_once 'includes/header.php';
?>

<div class="mb-4">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
    <p class="text-muted">Welcome back, <?= sanitize($_SESSION['full_name']) ?>!</p>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-0">Lost Items</h6>
                        <h2 class="mb-0"><?= $stats['my_lost'] ?></h2>
                    </div>
                    <i class="fas fa-frown fa-2x opacity-50"></i>
                </div>
                <a href="my-reports.php?type=lost" class="text-white small">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-0">Found Items</h6>
                        <h2 class="mb-0"><?= $stats['my_found'] ?></h2>
                    </div>
                    <i class="fas fa-smile fa-2x opacity-50"></i>
                </div>
                <a href="my-reports.php?type=found" class="text-white small">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-0">Claims Made</h6>
                        <h2 class="mb-0"><?= $stats['my_claims'] ?></h2>
                    </div>
                    <i class="fas fa-hand-paper fa-2x opacity-50"></i>
                </div>
                <a href="my-claims.php" class="text-white small">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-0">Returned</h6>
                        <h2 class="mb-0"><?= $stats['returned'] ?></h2>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fas fa-frown fa-3x text-danger mb-3"></i>
                <h5>Lost Something?</h5>
                <p class="text-muted">Report your lost item to find it faster.</p>
                <a href="report-lost.php" class="btn btn-danger">Report Lost Item</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="fas fa-smile fa-3x text-success mb-3"></i>
                <h5>Found Something?</h5>
                <p class="text-muted">Help return items to their owners.</p>
                <a href="report-found.php" class="btn btn-success">Report Found Item</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Reports -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>My Recent Reports</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_reports)): ?>
                <p class="text-muted text-center py-4">No reports yet. <a href="report-lost.php">Create your first report</a></p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report): ?>
                            <tr>
                                <td><?= sanitize($report['title']) ?></td>
                                <td>
                                    <span class="badge <?= $report['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>">
                                        <?= ucfirst($report['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $report['status'] === 'open' ? 'warning' : ($report['status'] === 'returned' ? 'success' : 'secondary') ?>">
                                        <?= ucfirst($report['status']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($report['created_at']) ?></td>
                                <td>
                                    <a href="item.php?id=<?= $report['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notifications & Claims -->
    <div class="col-lg-5">
        <!-- Pending Claims -->
        <?php if (!empty($pending_claims)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pending Claims (<?= count($pending_claims) ?>)</h5>
            </div>
            <div class="card-body">
                <?php foreach ($pending_claims as $claim): ?>
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= sanitize($claim['title']) ?></h6>
                        <small class="text-muted">Claimed by someone</small>
                    </div>
                    <a href="my-reports.php" class="btn btn-sm btn-warning">Review</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Notifications -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h5>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                <p class="text-muted text-center py-3">No notifications yet</p>
                <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                <div class="d-flex align-items-start mb-3 pb-3 border-bottom <?= !$notif['is_read'] ? 'bg-light p-2 rounded' : '' ?>">
                    <div class="me-3">
                        <i class="fas fa-<?= $notif['type'] === 'match' ? 'handshake text-success' : ($notif['type'] === 'claim' ? 'hand-paper text-warning' : 'info-circle text-info') ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 small"><?= sanitize($notif['title']) ?></h6>
                        <small class="text-muted"><?= sanitize(substr($notif['message'], 0, 50)) ?>...</small>
                        <br><small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
