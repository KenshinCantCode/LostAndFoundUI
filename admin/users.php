<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
        switch ($action) {
            case 'activate':
                $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User activated successfully');
                break;
            case 'deactivate':
                $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User deactivated successfully');
                break;
            case 'make_admin':
                $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User promoted to admin');
                break;
            case 'make_staff':
                $conn->prepare("UPDATE users SET role = 'staff' WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User role changed to staff');
                break;
            case 'make_student':
                $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User role changed to student');
                break;
            case 'delete':
                $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                setFlash('success', 'User deleted successfully');
                break;
        }
        logActivity($_SESSION['user_id'], 'admin_user_' . $action, "Admin action on user #$user_id");
        redirect(SITE_URL . '/admin/users.php');
    }
}

$role_filter = $_GET['role'] ?? 'all';
$search = sanitize($_GET['q'] ?? '');

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM items WHERE user_id = u.id) as total_items,
          (SELECT COUNT(*) FROM items WHERE user_id = u.id AND status = 'returned') as returned_items
          FROM users u WHERE 1=1";
$params = [];

if ($role_filter !== 'all' && in_array($role_filter, ['student', 'staff', 'admin'])) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Users";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users me-2"></i>Manage Users</h1>
            <span class="badge bg-secondary fs-6"><?= count($users) ?> users</span>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="role" class="form-select">
                            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
                            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admins</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="q" class="form-control" placeholder="Search users..." value="<?= sanitize($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Items</th>
                                <th>Returned</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($user['avatar'] !== 'default.png'): ?>
                                        <img src="<?= SITE_URL ?>/uploads/avatars/<?= $user['avatar'] ?>" 
                                             class="rounded-circle me-2" style="width:35px;height:35px;object-fit:cover;">
                                        <?php else: ?>
                                        <div class="bg-light rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:35px;height:35px;">
                                            <i class="fas fa-user text-muted small"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= sanitize($user['full_name']) ?></strong>
                                            <br>
                                            <small class="text-muted">@<?= sanitize($user['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'staff' ? 'info' : 'secondary') ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td><?= $user['total_items'] ?></td>
                                <td class="text-success"><?= $user['returned_items'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="../item.php?id=<?= $user['id'] ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <?php if ($user['is_active']): ?>
                                                    <button type="submit" name="action" value="deactivate" class="dropdown-item text-warning">
                                                        <i class="fas fa-ban me-2"></i>Deactivate
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate" class="dropdown-item text-success">
                                                        <i class="fas fa-check me-2"></i>Activate
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                    <button type="submit" name="action" value="make_admin" class="dropdown-item text-danger">
                                                        <i class="fas fa-shield-alt me-2"></i>Make Admin
                                                    </button>
                                                    <?php endif; ?>
                                                    <button type="submit" name="action" value="delete" class="dropdown-item text-danger" 
                                                            onclick="return confirm('Delete this user permanently?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
