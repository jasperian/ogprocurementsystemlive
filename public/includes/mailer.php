<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/mail-config.php'; // loads config
$config = include __DIR__ . '/mail-config.php';

function sendMail($to, $toName, $subject, $body, $altBody = '') {
    global $config;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->CharSet  = 'UTF-8';
    $mail->setFrom($config['from'], $config['from_name']);
    $mail->addAddress($to, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = $altBody;

    try {
        // --- First try: TLS on port 587 ---
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->send();
        return true;

    } catch (Exception $e1) {
        error_log("SMTP 587/TLS failed: " . $mail->ErrorInfo);

        try {
            // --- Second try: plain SMTP on port 26 ---
            $mail->smtpClose(); // close previous connection
            $mail->Host = $config['host'];
            $mail->SMTPSecure = ''; // no encryption
            $mail->Port = 26;
            $mail->send();
            return true;

        } catch (Exception $e2) {
            error_log("SMTP 26/plain failed: " . $mail->ErrorInfo);
            return "Mailer Error: " . $mail->ErrorInfo;
        }
    }
}

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// require_once __DIR__ . '/../vendor/autoload.php';
// $config = include __DIR__ . '/mail-config.php';

// function sendMail($to, $toName, $subject, $body, $altBody = '') {
//     global $config;
//     $mail = new PHPMailer(true);

//     try {
//         // ✅ Detect if sendmail/Exim exists (cPanel servers)
//         if (function_exists('exec') && trim(shell_exec('which sendmail'))) {
//             // Local sendmail (fast)
//             $mail->isMail();
//         } else {
//             // Remote SMTP (fallback)
//             $mail->isSMTP();
//             $mail->Host       = $config['host'];
//             $mail->SMTPAuth   = true;
//             $mail->Username   = $config['username'];
//             $mail->Password   = $config['password'];
//             $mail->Port       = $config['port'];

//             // Encryption
//             if (strtolower($config['secure']) === 'tls') {
//                 $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//             } elseif (strtolower($config['secure']) === 'ssl') {
//                 $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
//             }
//         }

//         $mail->setFrom($config['from'], $config['from_name']);
//         $mail->addAddress($to, $toName);
//         $mail->addReplyTo($config['from'], $config['from_name']);

//         $mail->isHTML(true);
//         $mail->Subject = $subject;
//         $mail->Body    = $body;
//         $mail->AltBody = $altBody ?: strip_tags($body);

//         $mail->send();
//         return true;

//     } catch (Exception $e) {
//         return "Mailer Error: " . $mail->ErrorInfo;
//     }
// }

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// require_once __DIR__ . '/../vendor/autoload.php';
// $config = include __DIR__ . '/mail-config.php';

// function sendMail($to, $toName, $subject, $body, $altBody = '') {
//     global $config;
//     $mail = new PHPMailer(true);

//     try {
//         $mail->isSMTP();
//         $mail->Host       = $config['host'];
//         $mail->SMTPAuth   = true;
//         $mail->Username   = $config['username'];
//         $mail->Password   = $config['password'];
//         $mail->Port       = $config['port'];

//         // Apply encryption setting
//         if (strtolower($config['secure']) === 'tls') {
//             $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//         } elseif (strtolower($config['secure']) === 'ssl') {
//             $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
//         }

//         $mail->setFrom($config['from'], $config['from_name']);
//         $mail->addAddress($to, $toName);
//         $mail->addReplyTo($config['from'], $config['from_name']);

//         $mail->isHTML(true);
//         $mail->Subject = $subject;
//         $mail->Body    = $body;
//         $mail->AltBody = $altBody ?: strip_tags($body);

//         $mail->send();
//         return true;
//     } catch (Exception $e) {
//         return "Mailer Error: " . $mail->ErrorInfo;
//     }
// }
