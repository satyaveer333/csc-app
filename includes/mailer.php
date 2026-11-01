<?php
/**
 * Email Dispatcher for CSC Print Orders
 * Supports native PHP mail() with MIME multipart attachments and SMTP fallback
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function sendPrintOrderEmail($order, $files, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $toEmail = getSetting('owner_email', DEFAULT_OWNER_EMAIL, $pdo);
    $centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
    $subject = "🖨️ New Print Order: " . $order['token_id'] . " from " . $order['customer_name'];

    // Build HTML Message
    $fileListHtml = '';
    foreach ($files as $file) {
        $fileName = htmlspecialchars($file['original_name']);
        $fileSize = round($file['size'] / 1024, 1) . ' KB';
        $fileListHtml .= "<li><strong>{$fileName}</strong> ({$fileSize})</li>";
    }

    $notesHtml = !empty($order['notes']) ? nl2br(htmlspecialchars($order['notes'])) : '<em>None</em>';

    $messageBody = "
    <div style='font-family: Arial, sans-serif; max-width: 650px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; color: #1e293b;'>
        <div style='background-color: #2563eb; color: #ffffff; padding: 20px; text-align: center;'>
            <h2 style='margin: 0; font-size: 22px;'>{$centerName}</h2>
            <p style='margin: 5px 0 0; opacity: 0.9;'>New Online Print Order Received</p>
        </div>
        <div style='padding: 24px; background-color: #ffffff;'>
            <div style='background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 15px; margin-bottom: 20px; border-radius: 0 4px 4px 0;'>
                <p style='margin: 0; font-size: 14px; color: #64748b;'>Order Tracking Token</p>
                <h3 style='margin: 4px 0 0; font-size: 24px; color: #2563eb; letter-spacing: 1px;'>{$order['token_id']}</h3>
            </div>

            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;'>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; width: 40%;'>Customer Name:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;'>" . htmlspecialchars($order['customer_name']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Customer Phone:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;'><a href='tel:" . htmlspecialchars($order['customer_phone']) . "' style='color: #2563eb; text-decoration: none;'>" . htmlspecialchars($order['customer_phone']) . "</a></td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Print Type:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;'>" . htmlspecialchars($order['print_type']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Paper & Sides:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;'>" . htmlspecialchars($order['paper_size']) . " (" . htmlspecialchars($order['sides']) . ")</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Number of Copies:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;'>" . htmlspecialchars($order['copies']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9; color: #64748b;'>Special Instructions:</td>
                    <td style='padding: 8px 0; border-bottom: 1px solid #f1f5f9;'>" . $notesHtml . "</td>
                </tr>
            </table>

            <h4 style='margin: 15px 0 8px; color: #334155;'>Attached Documents:</h4>
            <ul style='margin: 0; padding-left: 20px; line-height: 1.6;'>
                {$fileListHtml}
            </ul>

            <div style='margin-top: 25px; padding: 15px; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; text-align: center;'>
                <p style='margin: 0; font-size: 13px; color: #065f46;'>
                    You can also view, manage, and print this order directly inside your <strong>CSC Owner Portal</strong>.
                </p>
            </div>
        </div>
        <div style='background-color: #f1f5f9; padding: 12px; text-align: center; font-size: 12px; color: #94a3b8;'>
            Sent automatically by {$centerName} Digital Portal
        </div>
    </div>";

    // Setup headers and multipart boundary for attachments
    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";

    $mailMethod = getSetting('mail_method', '', $pdo);
    if (empty($mailMethod)) {
        $mailMethod = getenv('MAIL_METHOD') ?: 'php_mail';
    }

    $serverHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'serverbyt.local';
    $serverSender = "noreply@" . preg_replace('/^www\./', '', $serverHost);

    if ($mailMethod === 'smtp') {
        $fromEmail = getSetting('smtp_user', '', $pdo) ?: getenv('SMTP_USER');
        if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $serverSender;
        }
    } else {
        // Native php_mail must use server domain to satisfy cPanel/Exim SPF anti-spoofing policies
        $fromEmail = $serverSender;
    }

    // Sanitize headers to prevent email header injection
    $safeCenterName = str_replace(["\r", "\n"], '', $centerName);
    $cleanReplyTo = (!empty($order['customer_email']) && filter_var($order['customer_email'], FILTER_VALIDATE_EMAIL))
        ? $order['customer_email'] 
        : $fromEmail;
    $cleanReplyTo = str_replace(["\r", "\n"], '', $cleanReplyTo);

    $headers  = "From: {$safeCenterName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$cleanReplyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    // Multipart body
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $messageBody . "\r\n\r\n";

    // Attach actual files
    foreach ($files as $file) {
        $filePath = PRINTS_DIR . '/' . $file['stored_name'];
        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $encodedData = chunk_split(base64_encode($fileData));
            $cleanName = basename($file['original_name']);

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"{$cleanName}\"\r\n";
            $body .= "Content-Description: {$cleanName}\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$cleanName}\"; size=" . filesize($filePath) . ";\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $encodedData . "\r\n\r\n";
        }
    }

    $body .= "--{$boundary}--";

    $mailSuccess = false;

    if ($mailMethod === 'smtp') {
        // SMTP sending via socket
        $mailSuccess = sendViaSmtpSocket($toEmail, $subject, $body, $headers, $pdo);
    }

    // Default or fallback to standard PHP mail()
    if (!$mailSuccess) {
        $mailSuccess = @mail($toEmail, $subject, $body, $headers);
    }

    // Update order with email status
    if ($mailSuccess) {
        $updateStmt = $pdo->prepare("UPDATE print_orders SET email_sent = 1 WHERE id = :id");
        $updateStmt->execute([':id' => $order['id']]);
    }

    return $mailSuccess;
}

function sendViaSmtpSocket($to, $subject, $body, $headers, $pdo) {
    $host = getSetting('smtp_host', '', $pdo);
    if (empty($host) || $host === 'smtp.example.com') {
        $host = getenv('SMTP_HOST') ?: $host;
    }
    $port = (int)getSetting('smtp_port', 587, $pdo);
    if ($port <= 0) {
        $port = (int)(getenv('SMTP_PORT') ?: 587);
    }
    $user = getSetting('smtp_user', '', $pdo);
    if (empty($user)) {
        $user = getenv('SMTP_USER') ?: '';
    }
    $storedPass = getSetting('smtp_pass', '', $pdo);
    $pass = !empty($storedPass) ? decryptSensitiveData($storedPass) : (getenv('SMTP_PASS') ?: '');
    $secure = getSetting('smtp_secure', 'tls', $pdo);
    if (empty($secure)) {
        $secure = getenv('SMTP_SECURE') ?: 'tls';
    }

    if (empty($host) || empty($user)) {
        return false;
    }

    $socketHost = ($secure === 'ssl') ? 'ssl://' . $host : $host;
    $socket = @fsockopen($socketHost, $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Connection Error: $errstr ($errno)");
        return false;
    }

    $readSmtp = function($sock) {
        $response = '';
        while ($line = fgets($sock, 515)) {
            $response .= $line;
            // In RFC 5321, multi-line replies have a hyphen at index 3 (e.g. "250-").
            // The final reply line has a space at index 3 (e.g. "250 ").
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };

    $greeting = $readSmtp($socket);
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $serverName = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    fputs($socket, "EHLO " . $serverName . "\r\n");
    $ehlo = $readSmtp($socket);

    if ($secure === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        $tlsResp = $readSmtp($socket);
        if (substr($tlsResp, 0, 3) === '220') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO " . $serverName . "\r\n");
            $ehlo = $readSmtp($socket);
        }
    }

    fputs($socket, "AUTH LOGIN\r\n");
    $readSmtp($socket);

    fputs($socket, base64_encode($user) . "\r\n");
    $readSmtp($socket);

    fputs($socket, base64_encode($pass) . "\r\n");
    $authResp = $readSmtp($socket);
    if (substr($authResp, 0, 3) !== '235') {
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return false;
    }

    fputs($socket, "MAIL FROM: <$user>\r\n");
    $readSmtp($socket);

    fputs($socket, "RCPT TO: <$to>\r\n");
    $readSmtp($socket);

    fputs($socket, "DATA\r\n");
    $dataResp = $readSmtp($socket);
    if (substr($dataResp, 0, 3) !== '354') {
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return false;
    }

    // Dot-stuffing according to RFC 5321 section 4.5.2
    $safeBody = preg_replace('/^\./m', '..', $body);
    $safeSubject = str_replace(["\r", "\n"], '', $subject);

    fputs($socket, "Subject: {$safeSubject}\r\n" . $headers . "\r\n" . $safeBody . "\r\n.\r\n");
    $sendResp = $readSmtp($socket);

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return (substr($sendResp, 0, 3) === '250');
}

/**
 * Generic HTML Email Dispatcher supporting native PHP mail() and SMTP socket
 */
function sendGeneralEmail($toEmail, $subject, $htmlMessage, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
    $safeCenterName = str_replace(["\r", "\n"], '', $centerName);

    $mailMethod = getSetting('mail_method', '', $pdo);
    if (empty($mailMethod)) {
        $mailMethod = getenv('MAIL_METHOD') ?: 'php_mail';
    }

    $serverHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'serverbyt.local';
    $serverSender = "noreply@" . preg_replace('/^www\./', '', $serverHost);

    if ($mailMethod === 'smtp') {
        $fromEmail = getSetting('smtp_user', '', $pdo) ?: getenv('SMTP_USER');
        if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $serverSender;
        }
    } else {
        // Native php_mail must use server domain to satisfy cPanel/Exim SPF anti-spoofing policies
        $fromEmail = $serverSender;
    }
    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);

    $ownerEmail = getSetting('owner_email', '', $pdo) ?: (getenv('DEFAULT_OWNER_EMAIL') ?: $fromEmail);
    $replyTo = filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) ? $ownerEmail : $fromEmail;
    $replyTo = str_replace(["\r", "\n"], '', $replyTo);

    $headers  = "From: {$safeCenterName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$safeCenterName} <{$replyTo}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    $mailSuccess = false;

    if ($mailMethod === 'smtp') {
        $mailSuccess = sendViaSmtpSocket($toEmail, $subject, $htmlMessage, $headers, $pdo);
    }

    if (!$mailSuccess) {
        $mailSuccess = @mail($toEmail, $subject, $htmlMessage, $headers);
    }

    return $mailSuccess;
}

/**
 * Dispatch Branded 2FA & Password Reset OTP Verification Email
 */
function sendOtpEmail($toEmail, $otp, $purpose = 'login', $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);

    $purposeTitles = [
        'login' => '🔐 Owner Portal 2FA Verification Code',
        'forgot_password' => '🔑 Password Reset Verification Code',
        'register_email' => '✉️ Verify Official Registered Email'
    ];
    $subject = ($purposeTitles[$purpose] ?? '🔐 Verification Code') . " - " . $centerName;

    $purposeExplanations = [
        'login' => 'A sign-in attempt was initiated for your CSC Center Owner account. Please use the verification code below to complete your login:',
        'forgot_password' => 'A request was received to reset your owner password. Please enter the verification code below to set a new password:',
        'register_email' => 'You requested to set this email as the official Registered Email for your CSC Center. Use the code below to complete verification:'
    ];
    $explanation = $purposeExplanations[$purpose] ?? 'Please use the verification code below to proceed:';

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff;'>
        <div style='background: #2563eb; color: #ffffff; padding: 24px; text-align: center;'>
            <h2 style='margin: 0; font-size: 22px; font-weight: 700;'>{$centerName}</h2>
            <p style='margin: 6px 0 0; opacity: 0.9; font-size: 13px;'>Security Verification</p>
        </div>
        <div style='padding: 28px; text-align: center;'>
            <p style='color: #475569; font-size: 14px; line-height: 1.6; margin-top: 0;'>{$explanation}</p>
            <div style='margin: 24px auto; padding: 16px 28px; background: #f8fafc; border: 2px dashed #93c5fd; border-radius: 12px; display: inline-block;'>
                <span style='font-family: monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1e3a8a;'>{$otp}</span>
            </div>
            <p style='color: #64748b; font-size: 13px; margin-bottom: 0;'>This code is strictly confidential and valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>
        </div>
        <div style='background: #f1f5f9; padding: 14px; text-align: center; font-size: 11px; color: #94a3b8;'>
            If you did not request this verification, please inspect your account security immediately.
        </div>
    </div>";

    return sendGeneralEmail($toEmail, $subject, $html, $pdo);
}
