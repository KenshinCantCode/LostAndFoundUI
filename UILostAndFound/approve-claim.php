<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/my-claims.php');
}

$database = new Database();
$conn = $database->getConnection();

$claim_id = intval($_POST['claim_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$claim_id || !in_array($action, ['approve', 'reject'])) {
    setFlash('danger', 'Invalid request');
    redirect(SITE_URL . '/my-claims.php');
}

// Get claim
$stmt = $conn->prepare("
    SELECT c.*, i.title as item_title, i.user_id as item_owner_id, i.status as item_status,
           u.full_name as claimer_name, u.email as claimer_email
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON c.claimer_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim || $claim['item_owner_id'] != $_SESSION['user_id']) {
    setFlash('danger', 'Claim not found or you do not have permission');
    redirect(SITE_URL . '/my-claims.php');
}

if ($claim['status'] !== 'pending') {
    setFlash('warning', 'This claim has already been processed');
    redirect(SITE_URL . '/my-claims.php');
}

$new_status = ($action === 'approve') ? 'approved' : 'rejected';
$item_status = ($action === 'approve') ? 'returned' : 'open';

// Update claim status
$conn->prepare("UPDATE claims SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$new_status, $claim_id]);

// Update item status
$conn->prepare("UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$item_status, $claim['item_id']]);

// If rejected, check for other pending claims
if ($action === 'reject') {
    $stmt = $conn->prepare("SELECT id FROM claims WHERE item_id = ? AND status = 'pending' AND id != ?");
    $stmt->execute([$claim['item_id'], $claim_id]);
    if (!$stmt->fetch()) {
        $conn->prepare("UPDATE items SET status = 'open' WHERE id = ?")->execute([$claim['item_id']]);
    }
}

// Notify claimer
createNotification(
    $claim['claimer_id'],
    'Claim ' . ucfirst($new_status),
    'Your claim for "' . $claim['item_title'] . '" has been ' . $new_status . '.',
    'claim',
    SITE_URL . '/item.php?id=' . $claim['item_id']
);

// Send email
try {
    $status_text = ($action === 'approve') ? 'approved and the item is ready for pickup' : 'not approved';
    $email_subject = "Your Claim Has Been " . ucfirst($new_status);
    $email_message = "
    <!DOCTYPE html>
    <html>
    <head><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:" . ($action === 'approve' ? '#198754' : '#dc3545') . ";color:white;padding:20px;text-align:center;border-radius:10px 10px 0 0;}.content{background:#f8f9fa;padding:20px;border:1px solid #dee2e6;}.btn{display:inline-block;background:#0d6efd;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;margin:10px 0;}.footer{text-align:center;padding:15px;color:#666;font-size:12px;}</style></head>
    <body>
        <div class='container'>
            <div class='header'><h1>Claim " . ucfirst($new_status) . "</h1></div>
            <div class='content'>
                <p>Hi <strong>" . $claim['claimer_name'] . "</strong>,</p>
                <p>Your claim for <strong>" . $claim['item_title'] . "</strong> has been " . $status_text . ".</p>
                " . ($action === 'approve' ? '<p>Please contact the item owner or visit campus security to retrieve your item.</p>' : '<p>The item owner has decided to wait for other potential matches.</p>') . "
                <a href='" . SITE_URL . "/item.php?id=" . $claim['item_id'] . "' class='btn'>View Item</a>
            </div>
            <div class='footer'>&copy; " . date('Y') . " " . SITE_NAME . "</div>
        </div>
    </body>
    </html>";
    
    sendEmail($claim['claimer_email'], $email_subject, $email_message);
} catch (Exception $e) {
    // Email failed
}

logActivity($_SESSION['user_id'], 'claim_' . $new_status, "Claim #$claim_id " . $new_status . " for item: {$claim['item_title']}");

setFlash('success', 'Claim has been ' . $new_status . ' successfully!');
redirect(SITE_URL . '/my-claims.php');
?>
