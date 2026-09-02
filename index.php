<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Get recent items
$stmt = $conn->query("
    SELECT i.*, c.name as category_name, c.icon as category_icon, u.full_name as reporter_name
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.status = 'open' AND i.is_resolved = 0
    ORDER BY i.created_at DESC 
    LIMIT 9
");
$recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats = [];
$stats['lost'] = $conn->query("SELECT COUNT(*) FROM items WHERE type='lost' AND status='open' AND is_resolved = 0")->fetchColumn();
$stats['found'] = $conn->query("SELECT COUNT(*) FROM items WHERE type='found' AND status='open' AND is_resolved = 0")->fetchColumn();
$stats['returned'] = $conn->query("SELECT COUNT(*) FROM items WHERE status='returned'")->fetchColumn();
$stats['users'] = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

$pageTitle = "Home";
require_once 'includes/header.php';
?>

<!-- main Section -->
<div class="main-section bg-primary text-white rounded-4 p-5 mb-5 shadow">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h1 class="display-4 fw-bold mb-3">Lost Something? Find It Here!</h1>
            <p class="lead mb-4">Help Find Your Lost Items. Report lost or found items and let us help you find a match.</p>
            <div class="d-flex gap-3 flex-wrap">
                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="report-lost.php" class="btn btn-light btn-lg">
                    <i class="fas fa-frown me-2"></i>Report Lost Item
                </a>
                <a href="report-found.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-smile me-2"></i>Report Found Item
                </a>
                <?php else: ?>
                <a href="register.php" class="btn btn-light btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Get Started
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5 text-center d-none d-lg-block">
            <i class="fas fa-search-location fa-10x opacity-50"></i>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row mb-5 g-4">
    <div class="col-6 col-md-3">
        <div class="card border-danger h-100 text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-frown fa-2x text-danger mb-2"></i>
                <h2 class="text-danger mb-0"><?= $stats['lost'] ?></h2>
                <p class="text-muted mb-0">Lost Items</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-success h-100 text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-smile fa-2x text-success mb-2"></i>
                <h2 class="text-success mb-0"><?= $stats['found'] ?></h2>
                <p class="text-muted mb-0">Found Items</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-primary h-100 text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                <h2 class="text-primary mb-0"><?= $stats['returned'] ?></h2>
                <p class="text-muted mb-0">Returned</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-info h-100 text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h2 class="text-info mb-0"><?= $stats['users'] ?></h2>
                <p class="text-muted mb-0">Members</p>
            </div>
        </div>
    </div>
</div>

<!-- How It Works -->
<div class="mb-5">
    <h2 class="text-center mb-4"><i class="fas fa-question-circle me-2"></i>How It Works</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                    <h5>1. Report</h5>
                    <p class="text-muted">Report your lost item or found item with details and photos.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5>2. Match</h5>
                    <p class="text-muted">Our system automatically finds potential matches.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                        <i class="fas fa-handshake fa-2x"></i>
                    </div>
                    <h5>3. Return</h5>
                    <p class="text-muted">Verify ownership and return the item to its owner.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Items -->
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clock me-2"></i>Recent Reports</h2>
        <a href="search.php" class="btn btn-outline-primary">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>

    <?php if (empty($recent_items)): ?>
    <div class="alert alert-info text-center">
        <i class="fas fa-info-circle me-2"></i>No items reported yet. Be the first to <a href="report-lost.php">report a lost item</a> or <a href="report-found.php">report a found item</a>!
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($recent_items as $item): ?>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm item-card">
                <div class="position-relative">
                    <?php if ($item['image']): ?>
                    <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" 
                         class="card-img-top" alt="<?= sanitize($item['title']) ?>" 
                         style="height:200px;object-fit:cover;">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                        <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> fa-4x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <span class="position-absolute top-0 end-0 m-2 badge <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>">
                        <?= ucfirst($item['type']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">
                            <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> me-1"></i>
                            <?= $item['category_name'] ?? 'Uncategorized' ?>
                        </small>
                    </div>
                    <h5 class="card-title"><?= sanitize($item['title']) ?></h5>
                    <p class="card-text text-muted small">
                        <i class="fas fa-map-marker-alt me-1"></i><?= sanitize($item['location'] ?? 'Not specified') ?>
                    </p>
                    <p class="card-text text-muted small">
                        <i class="fas fa-calendar me-1"></i><?= formatDate($item['date_occurred']) ?>
                    </p>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="item.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary w-100">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
