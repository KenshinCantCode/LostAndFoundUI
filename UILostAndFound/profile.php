<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

$user = $auth->getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    
    if (!empty($_POST['full_name'])) {
        $data['full_name'] = sanitize($_POST['full_name']);
    }
    if (!empty($_POST['email'])) {
        $email = sanitize($_POST['email']);
        if (validateEmail($email)) {
            $data['email'] = $email;
        } else {
            $error = 'Invalid email address';
        }
    }
    if (!empty($_POST['phone'])) {
        $data['phone'] = sanitize($_POST['phone']);
    }
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) >= 6) {
            if ($_POST['password'] === $_POST['confirm_password']) {
                $data['password'] = $_POST['password'];
            } else {
                $error = 'Passwords do not match';
            }
        } else {
            $error = 'Password must be at least 6 characters';
        }
    }
    $data['email_notifications'] = isset($_POST['email_notifications']) ? 1 : 0;

    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $result = uploadImage($_FILES['avatar'], AVATARS_UPLOAD);
        if ($result['success']) {
            // Delete old avatar
            if ($user['avatar'] !== 'default.png') {
                $old_avatar = AVATARS_UPLOAD . $user['avatar'];
                if (file_exists($old_avatar)) {
                    unlink($old_avatar);
                }
            }
            $data['avatar'] = $result['filename'];
        } else {
            $error = $result['message'];
        }
    }

    if (empty($error) && !empty($data)) {
        $result = $auth->updateProfile($user_id, $data);
        if ($result['success']) {
            if (isset($data['full_name'])) {
                $_SESSION['full_name'] = $data['full_name'];
            }
            $success = $result['message'];
            $user = $auth->getCurrentUser(); // Refresh user data
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = "My Profile";
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-cog me-2"></i>My Profile</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Avatar -->
                        <div class="col-md-4 text-center mb-4">
                            <div class="mb-3">
                                <?php if ($user['avatar'] !== 'default.png'): ?>
                                <img src="<?= SITE_URL ?>/uploads/avatars/<?= $user['avatar'] ?>" 
                                     class="rounded-circle" width="150" height="150" style="object-fit:cover;">
                                <?php else: ?>
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width:150px;height:150px;">
                                    <i class="fas fa-user fa-5x text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="avatar" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-camera me-1"></i>Change Photo
                                </label>
                                <input type="file" class="d-none" id="avatar" name="avatar" accept="image/*">
                            </div>
                        </div>

                        <!-- Profile Info -->
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= sanitize($user['username']) ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= sanitize($user['full_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= sanitize($user['email']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= sanitize($user['phone'] ?? '') ?>">
                            </div>

                            <hr>

                            <h5 class="mb-3">Change Password</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" 
                                           name="email_notifications" <?= $user['email_notifications'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Receive email notifications for matches
                                    </label>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Info -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Member Since</small>
                        <strong><?= formatDate($user['created_at']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Last Updated</small>
                        <strong><?= formatDate($user['updated_at']) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Account Status</small>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
