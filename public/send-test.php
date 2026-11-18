<?php
require_once 'includes/mailer.php';

$result = sendMail(
    'jasperiantalinio@gmail.com',
    'Recipient Name',
    'Test Subject',
    '<p>This is the <strong>test email</strong> body</p>'
);

echo $result === true ? 'Email sent successfully!' : $result;