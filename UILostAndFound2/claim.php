<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

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

// Check if user is the owner
if ($item['user_id'] == $_SESSION['user_id']) {
    setFlash('warning', 'You cannot claim your own item');
    redirect(SITE_URL . '/item.php?id=' . $item_id);
}

// Check if already claimed
$stmt = $conn->prepare("SELECT id FROM claims WHERE item_id = ? AND claimer_id = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
if ($stmt->fetch()) {
    setFlash('warning', 'You have already claimed this item');
    redirect(SITE_URL . '/item.php?id=' . $item_id);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = sanitize($_POST['message'] ?? '');
    $proof_description = sanitize($_POST['proof_description'] ?? '');

    if (empty($message)) {
        $error = 'Please provide a message explaining why this is your item';
    } elseif (empty($proof_description)) {
        $error = 'Please provide proof of ownership details';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO claims (item_id, claimer_id, owner_id, message, proof_description, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $item_id,
            $_SESSION['user_id'],
            $item['user_id'],
            $message,
            $proof_description
        ]);

        // Update item status
        $conn->prepare("UPDATE items SET status = 'claimed' WHERE id = ?")->execute([$item_id]);

        // Create notification for item owner
        createNotification(
            $item['user_id'],
            'New Claim on Your Item',
            $_SESSION['full_name'] . ' has claimed your found item: ' . $item['title'],
            'claim',
            SITE_URL . '/my-reports.php'
        );

        // Send email notification
        if ($item['reporter_email']) {
            try {
                sendClaimNotificationEmail(
                    $item['reporter_email'],
                    $item['reporter_name'],
                    $item,
                    ['full_name' => $_SESSION['full_name']]
                );
            } catch (Exception $e) {
                // Email failed, but claim was created
            }
        }

        logActivity($_SESSION['user_id'], 'claim_item', "Claimed item: {$item['title']}");

        setFlash('success', 'Your claim has been submitted! The item owner will be notified.');
        redirect(SITE_URL . '/item.php?id=' . $item_id);
    }
}

$pageTitle = "Claim Item";
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-hand-paper me-2"></i>Claim This Item</h4>
            </div>
            <div class="card-body p-4">
                <!-- Item Summary -->
                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if ($item['image']): ?>
                                <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" 
                                     class="img-fluid rounded" alt="<?= sanitize($item['title']) ?>">
                                <?php else: ?>
                                <div class="bg-white rounded d-flex align-items-center justify-content-center" style="height:100px;">
                                    <i class="fas <?= $item['category_icon'] ?? 'fa-tag' ?> fa-3x text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h5><?= sanitize($item['title']) ?></h5>
                                <p class="mb-1"><strong>Location:</strong> <?= sanitize($item['location']) ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?= formatDate($item['date_occurred']) ?></p>
                                <p class="mb-0"><strong>Category:</strong> <?= $item['category_name'] ?? 'Other' ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="message" class="form-label">Why is this your item? *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required
                                  placeholder="Explain why you believe this is your item. Include details that match your lost item report."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="proof_description" class="form-label">Proof of Ownership *</label>
                        <textarea class="form-control" id="proof_description" name="proof_description" rows="4" required
                                  placeholder="Provide details that only the owner would know (e.g., contents of wallet, scratches on phone, etc.). DO NOT include sensitive info like PINs or passwords."></textarea>
                        <small class="text-muted">This helps verify you are the rightful owner.</small>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> By submitting this claim, you agree to meet with the item owner or campus security to verify ownership and retrieve the item.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i>Submit Claim
                        </button>
                        <a href="item.php?id=<?= $item_id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
