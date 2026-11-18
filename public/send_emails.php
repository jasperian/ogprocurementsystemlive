<?php
ini_set('error_log', __DIR__ . '/cron_errors.log');

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/mailer.php';


// Fetch pending emails
// Fetch pending emails and mark them as processing
$stmt = $pdo->prepare("SELECT * FROM email_queue WHERE status='pending' LIMIT 20 FOR UPDATE");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($emails as $email) {
    // Mark as processing before sending
    $pdo->prepare("UPDATE email_queue SET status='processing' WHERE id=?")->execute([$email['id']]);

    $result = sendMail(
        $email['recipient_email'],
        $email['recipient_name'],
        $email['subject'],
        $email['body'],
        $email['alt_body']
    );

    // Check DB connection
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        error_log("Reconnecting to DB: " . $e->getMessage());
        require __DIR__ . '/../includes/db.php';
    }

    // Update to sent or failed
    if ($result === true) {
        $pdo->prepare("UPDATE email_queue SET status='sent' WHERE id=?")->execute([$email['id']]);
    } else {
        $pdo->prepare("UPDATE email_queue SET status='failed' WHERE id=?")->execute([$email['id']]);
        error_log("Failed to send email to {$email['recipient_email']}: " . $result);
    }
}
