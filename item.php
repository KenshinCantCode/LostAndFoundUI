<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$database = new Database();
$conn = $database->getConnection();

$item_id = intval($_GET['id'] ?? 0);
if (!$item_id) {
    setFlash('danger', 'Invalid item ID');
    redirect(SITE_URL . '/search.php');
}

// Get item
$item = getItemById($item_id);

if (!$item) {
    setFlash('danger', 'Item not found');
    redirect(SITE_URL . '/search.php');
}

// Increment views
$conn->prepare("UPDATE items SET views = views + 1 WHERE id = ?")->execute([$item_id]);

// Get claims for this item
$stmt = $conn->prepare("
    SELECT c.*, u.full_name as claimer_name, u.username as claimer_username
    FROM claims c
    JOIN users u ON c.claimer_id = u.id
    WHERE c.item_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$item_id]);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find similar items
$opposite_type = $item['type'] === 'lost' ? 'found' : 'lost';
$stmt = $conn->prepare("
    SELECT i.*, c.name as category_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.type = ? AND i.status = 'open' AND i.id != ? AND i.category_id = ?
    ORDER BY ABS(DATEDIFF(i.date_occurred, ?)) ASC
    LIMIT 3
");
$stmt->execute([$opposite_type, $item_id, $item['category_id'], $item['date_occurred']]);
$similar_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $item['title'];
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <?php if ($item['image']): ?>
            <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" 
                 class="card-img-top" alt="<?= sanitize($item['title']) ?>" 
                 style="max-height:400px;object-fit:cover;">
            <?php else: ?>
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:300px;">
                <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> fa-6x text-muted"></i>
            </div>
            <?php endif; ?>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?> mb-2 fs-6">
                            <i class="fas <?= $item['type'] === 'lost' ? 'fa-frown' : 'fa-smile' ?> me-1"></i>
                            <?= ucfirst($item['type']) ?> Item
                        </span>
                        <h2 class="mb-0"><?= sanitize($item['title']) ?></h2>
                    </div>
                    <span class="badge bg-<?= $item['status'] === 'open' ? 'warning' : ($item['status'] === 'returned' ? 'success' : 'secondary') ?> fs-6">
                        <?= ucfirst($item['status']) ?>
                    </span>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><i class="fas fa-map-marker-alt text-danger me-2"></i><strong>Location:</strong> <?= sanitize($item['location']) ?></p>
                        <?php if ($item['building']): ?>
                        <p><i class="fas fa-building text-primary me-2"></i><strong>Building:</strong> <?= sanitize($item['building']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><i class="fas fa-calendar text-info me-2"></i><strong>Date:</strong> <?= formatDate($item['date_occurred']) ?></p>
                        <p><i class="fas fa-folder text-warning me-2"></i><strong>Category:</strong> <?= $item['category_name'] ?? 'Uncategorized' ?></p>
                    </div>
                </div>

                <?php if ($item['description']): ?>
                <div class="mb-4">
                    <h5>Description</h5>
                    <p class="text-muted"><?= nl2br(sanitize($item['description'])) ?></p>
                </div>
                <?php endif; ?>

                <hr>

                <div class="row text-muted small">
                    <div class="col-md-6">
                        <p><i class="fas fa-user me-1"></i>Reported by: <strong><?= sanitize($item['reporter_name']) ?></strong></p>
                        <p><i class="fas fa-clock me-1"></i>Posted: <?= timeAgo($item['created_at']) ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p><i class="fas fa-eye me-1"></i><?= $item['views'] ?> views</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-hand-paper me-2"></i>Claims (<?= count($claims) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($claims)): ?>
                <p class="text-muted text-center py-3">No claims yet</p>
                <?php else: ?>
                <?php foreach ($claims as $claim): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong><?= sanitize($claim['claimer_name']) ?></strong>
                            <span class="badge bg-<?= $claim['status'] === 'pending' ? 'warning' : ($claim['status'] === 'approved' ? 'success' : 'danger') ?> ms-2">
                                <?= ucfirst($claim['status']) ?>
                            </span>
                        </div>
                        <small class="text-muted"><?= timeAgo($claim['created_at']) ?></small>
                    </div>
                    <p class="mb-1 mt-2"><?= sanitize($claim['message']) ?></p>
                    <?php if ($claim['proof_description']): ?>
                    <p class="small text-muted"><strong>Proof:</strong> <?= sanitize($claim['proof_description']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Action Card -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <?php if ($auth->isLoggedIn()): ?>
                    <?php if ($_SESSION['user_id'] != $item['user_id']): ?>
                        <?php if ($item['type'] === 'found' && $item['status'] === 'open'): ?>
                        <a href="claim.php?id=<?= $item_id ?>" class="btn btn-success w-100 mb-3">
                            <i class="fas fa-hand-paper me-1"></i>Claim This Item
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($item['status'] === 'open'): ?>
                        <a href="edit-item.php?id=<?= $item_id ?>" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-edit me-1"></i>Edit Report
                        </a>
                        <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash me-1"></i>Delete Report
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                <a href="login.php" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-1"></i>Login to Claim
                </a>
                <?php endif; ?>

                <hr>
                </div>
            </div>
        </div>

        <!-- Similar Items -->
        <?php if (!empty($similar_items)): ?>
        <div class="card shadow">
            <div class="card-header bg-white">
                <h6 class="mb-0">Similar <?= ucfirst($opposite_type) ?> Items</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($similar_items as $similar): ?>
                <a href="item.php?id=<?= $similar['id'] ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 small"><?= sanitize($similar['title']) ?></h6>
                            <small class="text-muted"><?= $similar['category_name'] ?? 'Other' ?></small>
                        </div>
                        <small class="text-muted"><?= formatDate($similar['date_occurred']) ?></small>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<?php if ($auth->isLoggedIn() && $_SESSION['user_id'] == $item['user_id']): ?>
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this report? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="delete-item.php" class="d-inline">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function copyLink() {
    navigator.clipboard.writeText(window.location.href);
    alert('Link copied to clipboard!');
}
</script>

<?php require_once 'includes/footer.php'; ?>
