<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

function sendEmail($to, $subject, $body, $isHTML = false) {
    $db = getDb();
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
    $stmt->execute();
    $smtpSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $host    = $smtpSettings['smtp_host'] ?? 'mail.horsementech.com';
    $port    = $smtpSettings['smtp_port'] ?? 465;
    $user    = $smtpSettings['smtp_username'] ?? 'notifications@horsementech.com';
    $pass    = $smtpSettings['smtp_password'] ?? 'Ihhashi@44';
    $encrypt = $smtpSettings['smtp_encryption'] ?? 'ssl';

    $encryptionMap = [
        'ssl' => PHPMailer::ENCRYPTION_SMTPS,
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        'none' => false
    ];
    $encryption = $encryptionMap[$encrypt] ?? PHPMailer::ENCRYPTION_SMTPS;

    $mail = new PHPMailer(true);

    try {
        // Connection options that worked in the test
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = $encryption;
        $mail->Port       = $port;

        $mail->setFrom($user, 'The Professional Barbershop');
        $mail->addAddress($to);

        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        $logFile = __DIR__ . '/../logs/email_errors.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - To: $to - Error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Sends an email with a file attachment using SMTP settings from the database.
 */
function sendEmailWithAttachment($to, $subject, $body, $attachmentPath, $attachmentName, $isHTML = true) {
    $db = getDb();
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
    $stmt->execute();
    $smtpSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $host    = $smtpSettings['smtp_host'] ?? 'mail.horsementech.com';
    $port    = $smtpSettings['smtp_port'] ?? 465;
    $user    = $smtpSettings['smtp_username'] ?? 'notificactions@horsementech.com';
    $pass    = $smtpSettings['smtp_password'] ?? 'Ihhashi@44';
    $encrypt = $smtpSettings['smtp_encryption'] ?? 'ssl';

    $encryptionMap = [
        'ssl' => PHPMailer::ENCRYPTION_SMTPS,
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        'none' => false
    ];
    $encryption = $encryptionMap[$encrypt] ?? PHPMailer::ENCRYPTION_SMTPS;

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = $encryption;
        $mail->Port       = $port;

        $mail->setFrom($user, 'The Professional Barbershop');
        $mail->addAddress($to);

        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}