<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['read_all'])) {
    $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    setFlash('success', 'All notifications marked as read');
    redirect(SITE_URL . '/notifications.php');
}

if (isset($_GET['read_id'])) {
    $notif_id = intval($_GET['read_id']);
    $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notif_id, $user_id]);
    // Get the link and redirect
    $stmt = $conn->prepare("SELECT link FROM notifications WHERE id = ?");
    $stmt->execute([$notif_id]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($notif && $notif['link']) {
        redirect($notif['link']);
    }
    redirect(SITE_URL . '/notifications.php');
}

// Get notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as displayed as read
$conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);

$pageTitle = "Notifications";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-bell me-2"></i>Notifications</h1>
    <?php if (!empty($notifications)): ?>
    <a href="notifications.php?read_all=1" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-check-double me-1"></i>Mark All as Read
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
        <h5>No notifications</h5>
        <p class="text-muted">You're all caught up! Notifications about matches, claims, and updates will appear here.</p>
    </div>
</div>
<?php else: ?>
<div class="list-group shadow-sm">
    <?php foreach ($notifications as $notif): ?>
    <a href="<?= $notif['link'] ? 'notifications.php?read_id=' . $notif['id'] : '#' ?>" 
       class="list-group-item list-group-item-action <?= !$notif['is_read'] ? 'list-group-item-light' : '' ?>">
        <div class="d-flex w-100 justify-content-between align-items-start">
            <div class="d-flex">
                <div class="me-3">
                    <?php if ($notif['type'] === 'match'): ?>
                    <i class="fas fa-handshake fa-lg text-success"></i>
                    <?php elseif ($notif['type'] === 'claim'): ?>
                    <i class="fas fa-hand-paper fa-lg text-warning"></i>
                    <?php elseif ($notif['type'] === 'admin'): ?>
                    <i class="fas fa-shield-alt fa-lg text-danger"></i>
                    <?php else: ?>
                    <i class="fas fa-info-circle fa-lg text-info"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h6 class="mb-1 <?= !$notif['is_read'] ? 'fw-bold' : '' ?>">
                        <?= sanitize($notif['title']) ?>
                        <?php if (!$notif['is_read']): ?>
                        <span class="badge bg-primary ms-2">New</span>
                        <?php endif; ?>
                    </h6>
                    <p class="mb-1 text-muted"><?= sanitize($notif['message']) ?></p>
                    <small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
