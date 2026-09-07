<?php
require_once __DIR__ . '/../config/config.php';

// Simple email function (no PHPMailer dependency)
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";

    return mail($to, $subject, $message, $headers);
}

// Send match notification email
function sendMatchNotificationEmail($toEmail, $toName, $lostItem, $foundItem) {
    $subject = "Possible Match Found for Your Lost Item!";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
            .item-box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #0d6efd; }
            .btn { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Good News! Possible Match Found</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>{$toName}</strong>,</p>
                <p>We found an item that might be yours! Here are the details:</p>
                
                <div class='item-box'>
                    <h3>{$foundItem['title']}</h3>
                    <p><strong>Type:</strong> " . ucfirst($foundItem['type']) . " Item</p>
                    <p><strong>Location:</strong> {$foundItem['location']}</p>
                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($foundItem['date_occurred'])) . "</p>
                    <p><strong>Description:</strong> {$foundItem['description']}</p>
                </div>

                <p>If this is your item, please submit a claim with proof of ownership.</p>
                
                <a href='" . SITE_URL . "/item.php?id={$foundItem['id']}' class='btn'>View Details & Claim</a>
                
                <p>If this is not your item, you can ignore this notification.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($toEmail, $subject, $message);
}

// Send claim notification email
function sendClaimNotificationEmail($toEmail, $toName, $item, $claimer) {
    $subject = "Someone Claims Your Found Item";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #198754; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
            .item-box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #198754; }
            .btn { display: inline-block; background: #198754; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Claim on Your Item</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>{$toName}</strong>,</p>
                <p><strong>{$claimer['full_name']}</strong> has claimed your found item.</p>
                
                <div class='item-box'>
                    <h3>{$item['title']}</h3>
                    <p>Please review the claim and contact the person to verify ownership.</p>
                </div>

                <a href='" . SITE_URL . "/my-reports.php' class='btn'>Review Claim</a>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($toEmail, $subject, $message);
}

// Send welcome email
function sendWelcomeEmail($toEmail, $toName) {
    $subject = "Welcome to " . SITE_NAME . "!";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
            .feature { padding: 10px 0; border-bottom: 1px solid #dee2e6; }
            .btn { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome, {$toName}!</h1>
            </div>
            <div class='content'>
                <p>Thank you for joining <strong>" . SITE_NAME . "</strong>!</p>
                <p>With your account, you can:</p>
                
                <div class='feature'>
                    <strong>Report Lost Items</strong>
                    <p>Let others know what you've lost.</p>
                </div>
                <div class='feature'>
                    <strong>Report Found Items</strong>
                    <p>Help return items to their owners.</p>
                </div>
                <div class='feature'>
                    <strong>Search & Filter</strong>
                    <p>Find items by category, location, or date.</p>
                </div>
                <div class='feature'>
                    <strong>Get Notified</strong>
                    <p>Receive alerts when matches are found.</p>
                </div>

                <a href='" . SITE_URL . "' class='btn'>Get Started</a>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($toEmail, $subject, $message);
}

// Send status update email
function sendStatusUpdateEmail($toEmail, $toName, $item, $status) {
    $statusMessages = [
        'claimed' => 'Your item has been claimed!',
        'returned' => 'Your item has been marked as returned!',
        'closed' => 'Your item report has been closed.'
    ];

    $subject = $statusMessages[$status] ?? "Item Status Updated";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6c757d; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
            .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Item Status Updated</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>{$toName}</strong>,</p>
                <p>{$statusMessages[$status]}</p>
                <p><strong>Item:</strong> {$item['title']}</p>
                <p><strong>Status:</strong> " . ucfirst($status) . "</p>
                <p><a href='" . SITE_URL . "/item.php?id={$item['id']}'>View Item</a></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($toEmail, $subject, $message);
}
?>
