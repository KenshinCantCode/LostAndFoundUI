<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
        <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Admin Panel</h6>
    </div>
    <div class="list-group list-group-flush">
        <a href="<?= SITE_URL ?>/admin/" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a href="<?= SITE_URL ?>/admin/users.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users me-2"></i>Users
        </a>
        <a href="<?= SITE_URL ?>/admin/reports.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-list me-2"></i>Reports
        </a>
        <a href="<?= SITE_URL ?>/admin/claims.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'claims.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-paper me-2"></i>Claims
        </a>
        <a href="<?= SITE_URL ?>/admin/categories.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-folder me-2"></i>Categories
        </a>
        <a href="<?= SITE_URL ?>/admin/activity.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'activity.php' ? 'active' : '' ?>">
            <i class="fas fa-history me-2"></i>Activity Log
        </a>
    </div>
    <div class="card-footer">
        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm w-100">
            <i class="fas fa-arrow-left me-1"></i>Back to User Panel
        </a>
    </div>
</div>
