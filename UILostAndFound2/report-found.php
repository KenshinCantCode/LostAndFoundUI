<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$categories = getCategories();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $location = sanitize($_POST['location'] ?? '');
    $building = sanitize($_POST['building'] ?? '');
    $date_occurred = $_POST['date_occurred'] ?? '';

    if (empty($title)) {
        $error = 'Please enter an item title';
    } elseif (empty($location)) {
        $error = 'Please enter the location where you found the item';
    } elseif (empty($date_occurred)) {
        $error = 'Please enter the date you found the item';
    } else {
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $result = uploadImage($_FILES['image'], ITEMS_UPLOAD);
            if ($result['success']) {
                $image = $result['filename'];
            } else {
                $error = $result['message'];
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                INSERT INTO items (user_id, category_id, title, description, type, location, building, date_occurred, image, status)
                VALUES (?, ?, ?, ?, 'found', ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $category_id > 0 ? $category_id : null,
                $title,
                $description,
                $location,
                $building,
                $date_occurred,
                $image
            ]);

            $item_id = $conn->lastInsertId();

            logActivity($_SESSION['user_id'], 'report_found', "Reported found item: $title");

            include 'includes/matching.php';
            checkAndNotifyMatches($item_id, 'found');

            setFlash('success', 'Found item reported successfully! We will notify you if someone claims it.');
            redirect(SITE_URL . '/item.php?id=' . $item_id);
        }
    }
}

$pageTitle = "Report Found Item";
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-smile me-2"></i>Report Found Item</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">Thank you for helping! Fill in the details of the item you found.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">Item Name / Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= sanitize($_POST['title'] ?? '') ?>" 
                                   placeholder="e.g., Black Leather Wallet" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="0">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= $cat['name'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"
                                  placeholder="Describe the item (DO NOT include any sensitive information like IDs, cash amounts, etc.)"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location Found *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?= sanitize($_POST['location'] ?? '') ?>" 
                                   placeholder="e.g., Student Center Lobby" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="building" class="form-label">Building</label>
                            <input type="text" class="form-control" id="building" name="building" 
                                   value="<?= sanitize($_POST['building'] ?? '') ?>" 
                                   placeholder="e.g., Main Campus">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_occurred" class="form-label">Date Found *</label>
                            <input type="date" class="form-control" id="date_occurred" name="date_occurred" 
                                   value="<?= $_POST['date_occurred'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">Photo (Optional)</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Upload a photo (Max 5MB)</small>
                        </div>
                    </div>

                    <div class="mb-3" id="imagePreview" style="display:none;">
                        <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:200px;">
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Do NOT include sensitive information in the description (like ID numbers, cash amounts, etc.). The owner will need to verify these details.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i>Submit Report
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('previewImg').src = event.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
