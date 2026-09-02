<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$database = new Database();
$conn = $database->getConnection();

$pageTitle = "Activity Log";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>

    <div class="col-lg-9">
        <h1 class="mb-4"><i class="fas fa-history me-2"></i>Activity Log</h1>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->query("
                                SELECT a.*, CASE WHEN a.user_id IS NULL THEN 'System' ELSE u.username END as username
                                FROM activity_log a 
                                LEFT JOIN users u ON a.user_id = u.id 
                                ORDER BY a.created_at DESC 
                                LIMIT 200
                            ");
                            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($activities)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No activity logged yet</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><strong><?= sanitize($activity['username']) ?></strong></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= str_replace('_', ' ', ucfirst($activity['action'])) ?>
                                    </span>
                                </td>
                                <td><small><?= sanitize($activity['description'] ?? '-') ?></small></td>
                                <td><small class="text-muted"><?= $activity['ip_address'] ?? '-' ?></small></td>
                                <td><small class="text-muted"><?= formatDateTime($activity['created_at']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
