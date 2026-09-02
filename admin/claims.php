<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = intval($_POST['claim_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($claim_id > 0) {
        switch ($action) {
            case 'approve':
                $stmt = $conn->prepare("SELECT c.*, i.title, i.user_id FROM claims c JOIN items i ON c.item_id = i.id WHERE c.id = ?");
                $stmt->execute([$claim_id]);
                $claim = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($claim) {
                    $conn->prepare("UPDATE claims SET status = 'approved' WHERE id = ?")->execute([$claim_id]);
                    $conn->prepare("UPDATE items SET status = 'returned', is_resolved = 1 WHERE id = ?")->execute([$claim['item_id']]);

                    // Reject all other pending claims on this item
                    $conn->prepare("UPDATE claims SET status = 'rejected' WHERE item_id = ? AND id != ? AND status = 'pending'")->execute([$claim['item_id'], $claim_id]);

                    // Notify claimer
                    createNotification($claim['claimer_id'], 'Claim Approved!', "Your claim for '{$claim['title']}' has been approved.", 'claim', SITE_URL . '/item.php?id=' . $claim['item_id']);

                    setFlash('success', 'Claim approved and item marked as returned');
                }
                break;
            case 'reject':
                $stmt = $conn->prepare("SELECT c.*, i.title, i.user_id FROM claims c JOIN items i ON c.item_id = i.id WHERE c.id = ?");
                $stmt->execute([$claim_id]);
                $claim = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($claim) {
                    $conn->prepare("UPDATE claims SET status = 'rejected' WHERE id = ?")->execute([$claim_id]);

                    // Check if there are other pending claims
                    $stmt2 = $conn->prepare("SELECT id FROM claims WHERE item_id = ? AND status = 'pending' AND id != ?");
                    $stmt2->execute([$claim['item_id'], $claim_id]);
                    if (!$stmt2->fetch()) {
                        $conn->prepare("UPDATE items SET status = 'open' WHERE id = ? AND status = 'claimed'")->execute([$claim['item_id']]);
                    }

                    createNotification($claim['claimer_id'], 'Claim Rejected', "Your claim for '{$claim['title']}' was not approved.", 'claim', SITE_URL . '/item.php?id=' . $claim['item_id']);

                    setFlash('success', 'Claim rejected');
                }
                break;
            case 'delete':
                $conn->prepare("DELETE FROM claims WHERE id = ?")->execute([$claim_id]);
                setFlash('success', 'Claim deleted');
                break;
        }
        logActivity($_SESSION['user_id'], 'admin_claim_' . $action, "Admin claim action on #$claim_id");
        redirect(SITE_URL . '/admin/claims.php');
    }
}

$status_filter = $_GET['status'] ?? 'all';

$query = "SELECT c.*, i.title as item_title, i.type as item_type, i.image as item_image,
          cl.full_name as claimer_name, cl.username as claimer_username, cl.email as claimer_email,
          ow.full_name as owner_name, ow.username as owner_username
          FROM claims c 
          JOIN items i ON c.item_id = i.id
          LEFT JOIN users cl ON c.claimer_id = cl.id
          LEFT JOIN users ow ON i.user_id = ow.id
          WHERE 1=1";
$params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Claims";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-hand-paper me-2"></i>Manage Claims</h1>
            <span class="badge bg-secondary fs-6"><?= count($claims) ?> claims</span>
        </div>

        <!-- Status Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" href="claims.php">All</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" href="claims.php?status=pending">Pending</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'approved' ? 'active' : '' ?>" href="claims.php?status=approved">Approved</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'rejected' ? 'active' : '' ?>" href="claims.php?status=rejected">Rejected</a>
            </li>
        </ul>

        <!-- Claims List -->
        <div class="card shadow">
            <div class="card-body">
                <?php if (empty($claims)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-hand-paper fa-4x text-muted mb-3"></i>
                    <h5>No claims found</h5>
                </div>
                <?php else: ?>
                <?php foreach ($claims as $claim): ?>
                <div class="border-bottom pb-4 mb-4">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-start">
                                <?php if ($claim['item_image']): ?>
                                <img src="<?= SITE_URL ?>/uploads/items/<?= $claim['item_image'] ?>" 
                                     class="rounded me-3" style="width:70px;height:70px;object-fit:cover;">
                                <?php else: ?>
                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;">
                                    <i class="fas fa-tag text-muted fa-2x"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-1"><?= sanitize($claim['item_title']) ?></h5>
                                    <p class="mb-2">
                                        <span class="badge <?= $claim['item_type'] === 'lost' ? 'bg-danger' : 'bg-success' ?>"><?= ucfirst($claim['item_type']) ?></span>
                                        <span class="badge bg-<?= $claim['status'] === 'pending' ? 'warning' : ($claim['status'] === 'approved' ? 'success' : 'danger') ?>">
                                            <?= ucfirst($claim['status']) ?>
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>Item Owner:</strong> <?= sanitize($claim['owner_name'] ?? 'Unknown') ?> (@<?= sanitize($claim['owner_username'] ?? '-') ?>)</p>
                                    <p class="mb-1"><strong>Claimer:</strong> <?= sanitize($claim['claimer_name'] ?? 'Unknown') ?> (@<?= sanitize($claim['claimer_username'] ?? '-') ?>) - <?= sanitize($claim['claimer_email'] ?? '-') ?></p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <h6>Claimer's Message:</h6>
                                <p class="text-muted mb-2"><?= nl2br(sanitize($claim['message'])) ?></p>

                                <h6>Proof of Ownership:</h6>
                                <p class="text-muted"><?= nl2br(sanitize($claim['proof_description'])) ?></p>
                            </div>
                        </div>

                        <div class="col-md-4 text-md-end">
                            <small class="text-muted d-block mb-3">Submitted <?= timeAgo($claim['created_at']) ?></small>

                            <div class="d-flex flex-column gap-2">
                                <?php if ($claim['status'] === 'pending'): ?>
                                <form method="POST" onsubmit="return confirm('Approve this claim and mark item as returned?')">
                                    <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="btn btn-success w-100">
                                        <i class="fas fa-check me-1"></i>Approve & Return
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Reject this claim?')">
                                    <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                    <button type="submit" name="action" value="reject" class="btn btn-danger w-100">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Delete this claim?')">
                                    <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="../item.php?id=<?= $claim['item_id'] ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-eye me-1"></i>View Item
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
