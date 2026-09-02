<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$categories = getCategories();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $location = sanitize($_POST['location'] ?? '');
    $building = sanitize($_POST['building'] ?? '');
    $date_occurred = $_POST['date_occurred'] ?? '';

    // Validation
    if (empty($title)) {
        $error = 'Please enter an item title';
    } elseif (empty($location)) {
        $error = 'Please enter the location where you lost the item';
    } elseif (empty($date_occurred)) {
        $error = 'Please enter the date you lost the item';
    } else {
        // Handle image upload
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
                VALUES (?, ?, ?, ?, 'lost', ?, ?, ?, ?, 'open')
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

            // Log activity
            logActivity($_SESSION['user_id'], 'report_lost', "Reported lost item: $title");

            // Check for matches
            include 'includes/matching.php';
            checkAndNotifyMatches($item_id, 'lost');

            setFlash('success', 'Lost item reported successfully! We will notify you if a match is found.');
            redirect(SITE_URL . '/item.php?id=' . $item_id);
        }
    }
}

$pageTitle = "Report Lost Item";
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="fas fa-frown me-2"></i>Report Lost Item</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">Fill in the details below to report your lost item. The more details you provide, the better chance of finding it.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">Item Name / Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= sanitize($_POST['title'] ?? '') ?>" 
                                   placeholder="e.g., Blue iPhone 13 Pro" required>
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
                                  placeholder="Describe the item in detail (color, brand, distinguishing features, etc.)"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location Lost *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?= sanitize($_POST['location'] ?? '') ?>" 
                                   placeholder="e.g., Library 2nd Floor" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="building" class="form-label">Building</label>
                            <input type="text" class="form-control" id="building" name="building" 
                                   value="<?= sanitize($_POST['building'] ?? '') ?>" 
                                   placeholder="e.g., Science Building">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_occurred" class="form-label">Date Lost *</label>
                            <input type="date" class="form-control" id="date_occurred" name="date_occurred" 
                                   value="<?= $_POST['date_occurred'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">Photo (Optional)</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Upload a photo to help identify the item (Max 5MB)</small>
                        </div>
                    </div>

                    <!-- Image Preview -->
                    <div class="mb-3" id="imagePreview" style="display:none;">
                        <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:200px;">
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Tip:</strong> After submitting, our system will automatically search for potential matches with found items.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
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
