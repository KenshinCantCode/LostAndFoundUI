<?php
require_once __DIR__ . '/../config/config.php';

function sendEmail($to, $subject, $message) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)
        || !filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL)
        || SMTP_HOST === ''
        || SMTP_USERNAME === ''
        || SMTP_PASSWORD === '') {
        error_log('Email delivery is not configured: check SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD and SMTP_FROM_EMAIL.');
        return false;
    }

    try {
        $mailer = new LostFoundSmtpMailer();
        return $mailer->send($to, $subject, $message);
    } catch (Throwable $e) {
        error_log('SMTP email delivery failed: ' . $e->getMessage());
        return false;
    }
}

class LostFoundSmtpMailer {
    private $socket;
    private $timeout = 20;

    public function send($to, $subject, $html) {
        $encryption = SMTP_ENCRYPTION;
        if (!in_array($encryption, ['tls', 'ssl'], true)) {
            throw new RuntimeException('SMTP_ENCRYPTION must be tls or ssl.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => SMTP_HOST,
            ],
        ]);
        $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $address = $transport . '://' . SMTP_HOST . ':' . SMTP_PORT;
        $this->socket = @stream_socket_client(
            $address,
            $errorNumber,
            $errorMessage,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$this->socket) {
            throw new RuntimeException('Could not connect to SMTP server (error ' . $errorNumber . ').');
        }
        stream_set_timeout($this->socket, $this->timeout);

        try {
            $this->expect([220]);
            $clientName = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'localhost');
            $this->command('EHLO ' . $clientName, [250]);

            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new RuntimeException('Could not establish TLS encryption with the SMTP server.');
                }
                $this->command('EHLO ' . $clientName, [250]);
            }

            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode(SMTP_USERNAME), [334]);
            $this->command(base64_encode(SMTP_PASSWORD), [235]);
            $this->command('MAIL FROM:<' . SMTP_FROM_EMAIL . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);

            $safeName = str_replace(["\r", "\n"], '', SMTP_FROM_NAME);
            $safeSubject = str_replace(["\r", "\n"], '', $subject);
            $fromName = '=?UTF-8?B?' . base64_encode($safeName) . '?=';
            $encodedSubject = '=?UTF-8?B?' . base64_encode($safeSubject) . '?=';
            $fromDomain = substr(strrchr(SMTP_FROM_EMAIL, '@'), 1);
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $fromDomain . '>',
                'From: ' . $fromName . ' <' . SMTP_FROM_EMAIL . '>',
                'To: <' . $to . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $body = preg_replace("/\r\n|\r|\n/", "\r\n", $html);
            $body = preg_replace('/^\./m', '..', $body);
            $this->write(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
            return true;
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }
    }

    private function command($command, array $expectedCodes) {
        $this->write($command . "\r\n");
        $this->expect($expectedCodes);
    }

    private function write($data) {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($this->socket, substr($data, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Could not write to the SMTP connection.');
            }
            $written += $result;
        }
    }

    private function expect(array $expectedCodes) {
        do {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                $metadata = stream_get_meta_data($this->socket);
                throw new RuntimeException(!empty($metadata['timed_out'])
                    ? 'The SMTP server timed out.'
                    : 'The SMTP server closed the connection unexpectedly.');
            }
            $code = (int) substr($line, 0, 3);
        } while (isset($line[3]) && $line[3] === '-');

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP server returned an unexpected response code: ' . $code . '.');
        }
    }
}

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

function sendEmailVerificationEmail($toEmail, $toName, $token) {
    $verificationUrl = SITE_URL . '/email-verification.php?token=' . rawurlencode($token);
    $safeName = htmlspecialchars($toName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'Verify your ' . SITE_NAME . ' email address';
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <body style='margin:0;padding:24px;background:#f5f8f5;font-family:Arial,sans-serif;color:#173b2f;'>
        <div style='max-width:560px;margin:0 auto;padding:32px;background:#fff;border-radius:16px;'>
            <h1 style='margin-top:0;'>Verify your email</h1>
            <p>Hello {$safeName},</p>
            <p>Confirm your email address to activate your " . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . " account. This link expires in 30 minutes and can only be used once.</p>
            <p style='margin:28px 0;'>
                <a href='{$safeUrl}' style='display:inline-block;padding:12px 20px;border-radius:8px;background:#173b2f;color:#fff;text-decoration:none;font-weight:bold;'>Verify email address</a>
            </p>
            <p style='font-size:13px;color:#64748b;'>If you did not create this account, you can ignore this email.</p>
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
