<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$categories = getCategories();
$error = '';

$item_id = intval($_GET['id'] ?? 0);
if (!$item_id) {
    setFlash('danger', 'Invalid item ID');
    redirect(SITE_URL . '/my-reports.php');
}

// Get item
$stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    setFlash('danger', 'Item not found or you do not have permission to edit it');
    redirect(SITE_URL . '/my-reports.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $location = sanitize($_POST['location'] ?? '');
    $building = sanitize($_POST['building'] ?? '');
    $date_occurred = $_POST['date_occurred'] ?? '';
    $status = sanitize($_POST['status'] ?? 'open');

    if (empty($title)) {
        $error = 'Please enter an item title';
    } elseif (empty($location)) {
        $error = 'Please enter the location';
    } elseif (empty($date_occurred)) {
        $error = 'Please enter the date';
    } else {
        $image = $item['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $result = uploadImage($_FILES['image'], ITEMS_UPLOAD);
            if ($result['success']) {
                if ($item['image']) {
                    $old_image = ITEMS_UPLOAD . $item['image'];
                    if (file_exists($old_image)) {
                        unlink($old_image);
                    }
                }
                $image = $result['filename'];
            } else {
                $error = $result['message'];
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                UPDATE items SET 
                    category_id = ?,
                    title = ?,
                    description = ?,
                    location = ?,
                    building = ?,
                    date_occurred = ?,
                    image = ?,
                    status = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $category_id > 0 ? $category_id : null,
                $title,
                $description,
                $location,
                $building,
                $date_occurred,
                $image,
                $status,
                $item_id,
                $_SESSION['user_id']
            ]);

            logActivity($_SESSION['user_id'], 'edit_item', "Edited item: $title");
            setFlash('success', 'Item updated successfully!');
            redirect(SITE_URL . '/item.php?id=' . $item_id);
        }
    }
}

$pageTitle = "Edit Item";
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header <?= $item['type'] === 'lost' ? 'bg-danger' : 'bg-success' ?> text-white">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Edit <?= ucfirst($item['type']) ?> Item Report
                </h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">Item Name / Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?= sanitize($item['title']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="0">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $item['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= $cat['name'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= sanitize($item['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?= sanitize($item['location']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="building" class="form-label">Building</label>
                            <input type="text" class="form-control" id="building" name="building" 
                                   value="<?= sanitize($item['building']) ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_occurred" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="date_occurred" name="date_occurred" 
                                   value="<?= $item['date_occurred'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="open" <?= $item['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="closed" <?= $item['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="returned" <?= $item['status'] === 'returned' ? 'selected' : '' ?>>Returned</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Photo</label>
                        <?php if ($item['image']): ?>
                        <div class="mb-2">
                            <img src="<?= SITE_URL ?>/uploads/items/<?= $item['image'] ?>" class="img-thumbnail" style="max-height:150px;">
                            <small class="text-muted d-block">Current image. Upload new to replace.</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>

                    <div class="mb-3" id="imagePreview" style="display:none;">
                        <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-height:200px;">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
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
