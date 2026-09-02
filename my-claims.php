<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get claims made by user
$stmt = $conn->prepare("
    SELECT c.*, i.title as item_title, i.type as item_type, i.image as item_image, 
           i.location as item_location, i.status as item_status,
           u.full_name as owner_name
    FROM claims c
    JOIN items i ON c.item_id = i.id
    LEFT JOIN users u ON i.user_id = u.id
    WHERE c.claimer_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$my_claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get claims on user's items
$stmt = $conn->prepare("
    SELECT c.*, i.title as item_title, i.type as item_type, i.image as item_image,
           u.full_name as claimer_name, u.email as claimer_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    LEFT JOIN users u ON c.claimer_id = u.id
    WHERE i.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$claims_on_my_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Claims";
require_once 'includes/header.php';
?>

<h1 class="mb-4"><i class="fas fa-hand-paper me-2"></i>My Claims</h1>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#myClaims">
            Claims I Made (<?= count($my_claims) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#claimsOnMyItems">
            Claims on My Items (<?= count($claims_on_my_items) ?>)
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- My Claims Tab -->
    <div class="tab-pane fade show active" id="myClaims">
        <?php if (empty($my_claims)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-hand-paper fa-4x text-muted mb-3"></i>
                <h5>No claims made yet</h5>
                <p class="text-muted">When you claim an item, it will appear here.</p>
                <a href="search.php" class="btn btn-primary">Search for Items</a>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($my_claims as $claim): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= sanitize($claim['item_title']) ?></h5>
                            <span class="badge bg-<?= $claim['status'] === 'pending' ? 'warning' : ($claim['status'] === 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst($claim['status']) ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-2">
                            <span class="badge <?= $claim['item_type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>">
                                <?= ucfirst($claim['item_type']) ?>
                            </span>
                            <i class="fas fa-map-marker-alt ms-2 me-1"></i><?= sanitize($claim['item_location']) ?>
                        </p>
                        <p class="mb-2"><strong>Your message:</strong> <?= sanitize(substr($claim['message'], 0, 100)) ?>...</p>
                        <p class="mb-2"><strong>Owner:</strong> <?= sanitize($claim['owner_name']) ?></p>
                        <p class="text-muted small">Submitted <?= timeAgo($claim['created_at']) ?></p>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="item.php?id=<?= $claim['item_id'] ?>" class="btn btn-outline-primary btn-sm">View Item</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Claims on My Items Tab -->
    <div class="tab-pane fade" id="claimsOnMyItems">
        <?php if (empty($claims_on_my_items)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h5>No claims on your items</h5>
                <p class="text-muted">When someone claims your found item, it will appear here.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($claims_on_my_items as $claim): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= sanitize($claim['item_title']) ?></h5>
                            <span class="badge bg-<?= $claim['status'] === 'pending' ? 'warning' : ($claim['status'] === 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst($claim['status']) ?>
                            </span>
                        </div>
                        <p class="mb-2"><strong>Claimed by:</strong> <?= sanitize($claim['claimer_name']) ?></p>
                        <p class="mb-2"><strong>Their message:</strong> <?= sanitize(substr($claim['message'], 0, 100)) ?>...</p>
                        <p class="text-muted small">Submitted <?= timeAgo($claim['created_at']) ?></p>

                        <?php if ($claim['status'] === 'pending'): ?>
                        <div class="d-flex gap-2 mt-3">
                            <form method="POST" action="approve-claim.php" class="d-inline">
                                <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" action="approve-claim.php" class="d-inline">
                                <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="item.php?id=<?= $claim['item_id'] ?>" class="btn btn-outline-primary btn-sm">View Item</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
