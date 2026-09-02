<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $name = sanitize($_POST['name'] ?? '');
            $icon = sanitize($_POST['icon'] ?? 'fa-tag');
            $description = sanitize($_POST['description'] ?? '');

            if (empty($name)) {
                setFlash('danger', 'Category name is required');
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name, icon, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $icon, $description]);
                logActivity($_SESSION['user_id'], 'admin_add_category', "Added category: $name");
                setFlash('success', 'Category added successfully');
            }
            break;

        case 'update':
            $cat_id = intval($_POST['category_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $icon = sanitize($_POST['icon'] ?? 'fa-tag');
            $description = sanitize($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($cat_id > 0 && !empty($name)) {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, icon = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $icon, $description, $is_active, $cat_id]);
                logActivity($_SESSION['user_id'], 'admin_update_category', "Updated category #$cat_id");
                setFlash('success', 'Category updated successfully');
            }
            break;

        case 'delete':
            $cat_id = intval($_POST['category_id'] ?? 0);
            if ($cat_id > 0) {
                $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$cat_id]);
                logActivity($_SESSION['user_id'], 'admin_delete_category', "Deleted category #$cat_id");
                setFlash('success', 'Category deleted successfully');
            }
            break;
    }
    redirect(SITE_URL . '/admin/categories.php');
}

$categories = getCategories();

// Load edit category if requested
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    foreach ($categories as $cat) {
        if ($cat['id'] === $edit_id) {
            $edit_category = $cat;
            break;
        }
    }
}

$pageTitle = "Manage Categories";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-9">
        <h1 class="mb-4"><i class="fas fa-folder me-2"></i>Manage Categories</h1>

        <div class="row">
            <!-- Add/Edit Category Form -->
            <div class="col-md-4 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><?= $edit_category ? 'Edit Category' : 'Add Category' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $edit_category ? 'update' : 'add' ?>">
                            <?php if ($edit_category): ?>
                            <input type="hidden" name="category_id" value="<?= $edit_category['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?= sanitize($edit_category['name'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Icon</label>
                                <input type="text" class="form-control" name="icon" 
                                       value="<?= sanitize($edit_category['icon'] ?? 'fa-tag') ?>" 
                                       placeholder="e.g., fa-laptop">
                                <small class="text-muted">Font Awesome icon class</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"><?= sanitize($edit_category['description'] ?? '') ?></textarea>
                            </div>

                            <?php if ($edit_category): ?>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="is_active" <?= $edit_category['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-save me-1"></i><?= $edit_category ? 'Update' : 'Add' ?>
                                </button>
                                <?php if ($edit_category): ?>
                                <a href="categories.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Categories List -->
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><i class="fas <?= $cat['icon'] ?> fs-4 text-primary"></i></td>
                                        <td><strong><?= sanitize($cat['name']) ?></strong></td>
                                        <td><small class="text-muted"><?= sanitize($cat['description']) ?></small></td>
                                        <td>
                                            <span class="badge bg-<?= $cat['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Delete this category?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
