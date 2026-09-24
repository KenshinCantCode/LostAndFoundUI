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

$conn->exec("CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_comment', 'edit_comment', 'delete_comment'])) {
    $auth->requireLogin();
    $comment_action = $_POST['action'];
    $comment_id = intval($_POST['comment_id'] ?? 0);

    if ($comment_action === 'delete_comment') {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND item_id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $item_id, $_SESSION['user_id']]);
        setFlash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Comment deleted.' : 'You cannot delete this comment.');
        redirect(SITE_URL . '/item.php?id=' . $item_id . '#comments');
    }

    $content = trim($_POST['content'] ?? '');

    if ($content === '') {
        setFlash('danger', 'Comment cannot be empty.');
    } elseif ($comment_action === 'edit_comment') {
        $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ? AND item_id = ? AND user_id = ?");
        $stmt->execute([$content, $comment_id, $item_id, $_SESSION['user_id']]);
        setFlash($stmt->rowCount() ? 'success' : 'danger', $stmt->rowCount() ? 'Comment updated.' : 'You cannot edit this comment.');
    } else {
        $stmt = $conn->prepare("INSERT INTO comments (item_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$item_id, $_SESSION['user_id'], $content]);

        preg_match_all('/@([A-Za-z0-9_]{1,50})/', $content, $matches);
        $mentioned = array_unique($matches[1]);
        $user_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND is_active = 1");
        foreach ($mentioned as $username) {
            $user_stmt->execute([$username]);
            $mentioned_user_id = $user_stmt->fetchColumn();
            if ($mentioned_user_id && (int) $mentioned_user_id !== (int) $_SESSION['user_id']) {
                createNotification(
                    $mentioned_user_id,
                    'You were mentioned in a comment',
                    $_SESSION['username'] . " mentioned you on '{$item['title']}'.",
                    'system',
                    SITE_URL . '/item.php?id=' . $item_id
                );
            }
        }
        setFlash('success', 'Comment posted.');
    }
    redirect(SITE_URL . '/item.php?id=' . $item_id . '#comments');
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

$stmt = $conn->prepare("SELECT c.*, u.username, u.full_name FROM comments c JOIN users u ON c.user_id = u.id WHERE c.item_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$item_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$edit_comment_id = intval($_GET['edit_comment'] ?? 0);

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
        <div class="card shadow mb-4" id="comments">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Comments (<?= count($comments) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($auth->isLoggedIn()): ?>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_comment">
                    <textarea name="content" class="form-control mb-2" rows="3" maxlength="1000" placeholder="Write a comment or mention @username..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>Post Comment</button>
                </form>
                <?php else: ?>
                <p class="text-muted small">Log in to join the conversation.</p>
                <?php endif; ?>

                <?php if (empty($comments)): ?>
                <p class="text-muted text-center mb-0">Be the first to comment.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="border-top pt-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <strong><?= sanitize($comment['full_name'] ?: $comment['username']) ?></strong>
                            <small class="text-muted"><?= timeAgo($comment['created_at']) ?></small>
                        </div>
                        <?php if ($edit_comment_id === (int) $comment['id'] && $auth->isLoggedIn() && $_SESSION['user_id'] == $comment['user_id']): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="action" value="edit_comment">
                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                            <textarea name="content" class="form-control mb-2" rows="3" maxlength="1000" required><?= sanitize($comment['content']) ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            <a href="item.php?id=<?= $item_id ?>#comments" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        </form>
                        <?php else: ?>
                        <p class="mb-1 mt-1" style="white-space:pre-wrap;"><?= sanitize($comment['content']) ?></p>
                        <?php if ($auth->isLoggedIn() && $_SESSION['user_id'] == $comment['user_id']): ?>
                        <div class="mt-2">
                            <a href="item.php?id=<?= $item_id ?>&edit_comment=<?= $comment['id'] ?>#comments" class="btn btn-link btn-sm p-0 me-2">Edit</a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this comment?')">
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

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
                        <button type="button" class="btn btn-outline-danger w-100" data-ui-toggle="modal" data-ui-target="#deleteModal">
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
                <button type="button" class="btn-close" data-ui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this report? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-ui-dismiss="modal">Cancel</button>
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
