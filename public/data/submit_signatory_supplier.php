<?php
session_start();
include '../../includes/db.php';
require '../includes/pusher.php';
require_once '../includes/mailer.php';
header('Content-Type: application/json'); // ✅ Ensure JSON response

// =========================
// 1. Initialize variables
// =========================
$batchId       = $_POST['batch_id'] ?? null;
$position      = $_POST['position'] ?? null;
$submitterName = $_POST['name'] ?? null;
$index         = $_POST['index'] ?? null;
$user_Id        = $_SESSION['user_id'] ?? null;
$fullname      = $_SESSION['full_name'] ?? '';
$vendorName    = $_POST['vendorName'] ?? '';
$vendorDealer  = $_POST['dealer'] ?? null;
$date          = date('Y-m-d H:i:s');


// =========================
// 2. Validate inputs
// =========================
if (empty($batchId) || empty($position) || empty($user_Id) || !isset($index) || !is_numeric($index)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
if (empty($vendorName) || empty($vendorDealer)) {
    echo json_encode(['status' => 'error', 'message' => 'Vendor details missing.']);
    exit;
}

$index  = intval($index);
$column = "checked_{$index}";

// Validate approved columns
$validColumns = ['checked_1', 'checked_2', 'checked_3'];
if (!in_array($column, $validColumns)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid column.']);
    exit;
}

// =========================
// 3. Fetch manager user IDs in one query
// =========================
try {
    // Fetch VP and SVP (positions only)
    $stmt = $pdo->query("
        SELECT position, user_id 
        FROM users 
        WHERE position IN ('VP for Accounting', 'SVP for Finance and Admin')
    ");
    $managers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $vpforaccounting_userid  = $managers['VP for Accounting'] ?? null;
    $svpforaccounting_userid = $managers['SVP for Finance and Admin'] ?? null;
} catch (PDOException $e) {
    error_log("DB Fetch Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve manager data.']);
    exit;
}

// =========================
// 4. Update approval column
// =========================
try {
    $stmt = $pdo->prepare("UPDATE supplier_approvals SET {$column} = NOW() WHERE vendor_id = ?");
    $updated = $stmt->execute([$batchId]);
} catch (PDOException $e) {
    error_log("DB Update Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    exit;
}

// =========================
// 5. Notification function
// =========================
function sendNotification($pdo, $pusher, $targetUserId, $vendorName, $vendorDealer, $batchId, $date)
{
    try {
        // Insert into notifications table
        $stmt = $pdo->prepare("
            SELECT email_address, full_name
            FROM users
            WHERE user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $targetUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $receiver_email = $row['email_address'];
            $receiver_fullname = $row['full_name'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, is_read, link, created_at)
            VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
        ");
        $stmt->execute([
            ':user_id'    => $targetUserId,
            ':title'      => "Pending Supplier Approval from " . $vendorDealer,
            ':message'    => "Supplier Name: $vendorName",
            ':link'       => "supplier_verification?vendor_id=$batchId&vendor_name=$vendorName&dealer=$vendorDealer",
            ':created_at' => $date
        ]);

        // Pusher real-time notification
        if ($pusher) {
            $channel = "private-user-$targetUserId";
            $data['notifications'] = [[
                'user_id'    => $targetUserId,
                'title'      => 'Pending Supplier Approval from ' . $vendorDealer,
                'message'    => 'Supplier Name: ' . $vendorName,
                'type'       => 'info',
                'is_read'    => 0,
                'link'       => 'supplier_verification?vendor_id=' . $batchId . '&vendor_name=' . $vendorName . '&dealer=' . $vendorDealer,
                'created_at' => $date
            ]];
            $pusher->trigger($channel, 'my-event', $data);
        }

        // Send email notification
        // $email_subject = "OGPS: New Supplier Awaiting Your Approval";

        // $email_body = '
        //     <!DOCTYPE html>
        //     <html>
        //     <head>
        //         <meta charset="UTF-8">
        //         <title>' . htmlspecialchars($email_subject) . '</title>
        //     </head>
        //     <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#2c3e50;">

        //         <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        //             <tr>
        //                 <td align="center" style="padding: 20px 0;">
        //                     <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:6px;">
                                
        //                         <!-- Accent strip -->
        //                         <tr>
        //                             <td bgcolor="#004080" style="height:5px; line-height:5px; font-size:0;"></td>
        //                         </tr>

        //                         <!-- Header -->
        //                         <tr>
        //                             <td align="center" style="padding: 20px; border-bottom:1px solid #f0f0f0;">
        //                                 <h1 style="font-size:20px; font-weight:600; color:#004080; margin:0;">
        //                                     OGPS Supplier Verification
        //                                 </h1>
        //                             </td>
        //                         </tr>

        //                         <!-- Body content -->
        //                         <tr>
        //                             <td style="padding: 24px 28px; font-size:15px; line-height:1.6; color:#333333;">
        //                                 <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
        //                                 <p style="margin:0 0 15px;">
        //                                     A new supplier has been added by <strong>' . htmlspecialchars($vendorDealer) . '</strong> and is pending your approval in OGPS.
        //                                 </p>

        //                                 <!-- Details table -->
        //                                 <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin: 15px 0;">
        //                                     <tr>
        //                                         <td style="font-weight:600; color:#555;">Supplier Name</td>
        //                                         <td style="text-align:right;">' . htmlspecialchars($vendorName) . '</td>
        //                                     </tr>
        //                                     <tr>
        //                                         <td style="font-weight:600; color:#555;">Submitted By</td>
        //                                         <td style="text-align:right;">' . htmlspecialchars($vendorDealer) . '</td>
        //                                     </tr>
        //                                 </table>

        //                                 <p style="margin:0 0 15px;">
        //                                     Please log in to the system to review and verify the supplier at your earliest convenience.
        //                                 </p>
        //                                 <p style="margin:0 0 20px;">
        //                                     Thank you for your attention.
        //                                 </p>

        //                                 <!-- Button -->
        //                                 <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
        //                                     <tr>
        //                                         <td bgcolor="#004080" style="border-radius:4px;">
        //                                             <a href="supplier_verification?vendor_id=' . urlencode($batchId) . '" 
        //                                             style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
        //                                             Review & Approve Supplier
        //                                             </a>
        //                                         </td>
        //                                     </tr>
        //                                 </table>

        //                                 <p style="margin:25px 0 0;">Best regards,<br><strong>' . htmlspecialchars($vendorDealer) . '</strong></p>
        //                             </td>
        //                         </tr>

        //                         <!-- Footer -->
        //                         <tr>
        //                             <td align="center" style="padding: 14px 20px; background-color:#f4f6f8; font-size:12px; color:#888888;">
        //                                 This is an automated message. Please do not reply.
        //                             </td>
        //                         </tr>

        //                     </table>
        //                 </td>
        //             </tr>
        //         </table>

        //     </body>
        //     </html>
        //     ';

        // function queueMail($pdo, $user_Id, $to, $toName, $email_subject, $email_body, $altBody = '')
        // {
        //     $stmt = $pdo->prepare("
        //             INSERT INTO email_queue (sender_id, recipient_email, recipient_name, subject, body, alt_body)
        //             VALUES (?, ?, ?, ?, ?, ?)
        //         ");
        //     return $stmt->execute([
        //         $user_Id,
        //         $to,
        //         $toName,
        //         $email_subject,
        //         $email_body,
        //         $altBody ?: strip_tags($email_body)
        //     ]);
        // }
        // // Queue the actual email
        // if (queueMail(
        //     $pdo,
        //     $user_Id,
        //     $receiver_email, // recipient email
        //     $receiver_fullname,             // recipient name
        //     $email_subject,               // subject
        //     $email_body // HTML body
        // )) {
        //     // echo "Email queued successfully!";
        // } else {
        //     // echo "Failed to queue email.";
        // }
        // Send using your existing function
        // $result = sendMail(
        //     $receiver_email, // recipient email
        //     $receiver_fullname, // recipient name
        //     $email_subject,              // subject
        //     $email_body                  // HTML body
        // );
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }
}

if ($updated) {
    if ($position === 'Manager' && $vpforaccounting_userid) {
        sendNotification($pdo, $pusher, $vpforaccounting_userid, $vendorName, $vendorDealer, $batchId, $date);
    } elseif ($position === 'VP for Accounting' && $svpforaccounting_userid) {
        sendNotification($pdo, $pusher, $svpforaccounting_userid, $vendorName, $vendorDealer, $batchId, $date);
    } elseif ($position === 'SVP for Finance and Admin') {
        try {
            // 1️⃣ Insert into notifications table (single record)
            $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, link, created_at)
            VALUES (:title, :message, 'info', :link, :created_at)
             ");
            $stmt->execute([
                ':title'      => "New supplier officially accredited by $vendorDealer.",
                ':message'    => "Supplier Name: $vendorName",
                ':link'       => "view_supplier?vendor_id=$batchId",
                ':created_at' => $date
            ]);
            $notificationId = $pdo->lastInsertId();

            $stmtUsers = $pdo->prepare("
                SELECT user_id, full_name, email_address
                FROM users
                WHERE department = 'PURCHASING'
            ");
            $stmtUsers->execute();
            $purchasingUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            // ✅ 2. Prepare the statement for inserting into notification_recipients
            $stmtRec = $pdo->prepare("
                INSERT INTO notification_recipients (notification_id, user_id, is_read)
                VALUES (:notification_id, :user_id, 0)
            ");

            // $stmtQueue = $pdo->prepare("
            //     INSERT INTO email_queue (recipient_email, recipient_name, subject, body)
            //     VALUES (:email, :name, :subject, :body)
            // ");

            // ✅ 3. Loop through Purchasing users
            foreach ($purchasingUsers as $user) {
                $userId       = $user['user_id'];
                $fullName     = $user['full_name'];
                $emailAddress = $user['email_address'];
                // Insert recipient record
                $stmtRec->execute([
                    ':notification_id' => $notificationId,  // Use the actual notification ID
                    ':user_id'         => $userId
                ]);

                // Trigger Pusher per recipient
                if ($pusher) {
                    $channel = "private-user-$userId";
                    $data['notifications'] = [[
                        'notification_id' => $notificationId,
                        'title'      => 'New supplier officially accredited by ' . $vendorDealer . '.',
                        'message'    => 'Supplier Name: ' . $vendorName,
                        'type'       => 'info',
                        'is_read'    => 0,
                        'link'       => 'view_supplier?vendor_id=' . $batchId,
                        'created_at' => $date
                    ]];
                    $pusher->trigger($channel, 'my-event', $data);
                }

                // Send email notification
            //     $email_subject = "OGPS: New Supplier Awaiting Your Approval";

            //     $email_body = '
            // <!DOCTYPE html>
            // <html>
            // <head>
            //     <meta charset="UTF-8">
            //     <title>' . htmlspecialchars($email_subject) . '</title>
            // </head>
            // <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#2c3e50;">

            //     <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            //         <tr>
            //             <td align="center" style="padding: 20px 0;">
            //                 <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:6px;">
                                
            //                     <!-- Accent strip -->
            //                     <tr>
            //                         <td bgcolor="#004080" style="height:5px; line-height:5px; font-size:0;"></td>
            //                     </tr>

            //                     <!-- Header -->
            //                     <tr>
            //                         <td align="center" style="padding: 20px; border-bottom:1px solid #f0f0f0;">
            //                             <h1 style="font-size:20px; font-weight:600; color:#004080; margin:0;">
            //                                 OGPS Supplier Verification
            //                             </h1>
            //                         </td>
            //                     </tr>

            //                     <!-- Body content -->
            //                     <tr>
            //                         <td style="padding: 24px 28px; font-size:15px; line-height:1.6; color:#333333;">
            //                             <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
            //                             <p style="margin:0 0 15px;">
            //                                 A new supplier has been added by <strong>' . htmlspecialchars($vendorDealer) . '</strong> and is pending your approval in OGPS.
            //                             </p>

            //                             <!-- Details table -->
            //                             <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin: 15px 0;">
            //                                 <tr>
            //                                     <td style="font-weight:600; color:#555;">Supplier Name</td>
            //                                     <td style="text-align:right;">' . htmlspecialchars($vendorName) . '</td>
            //                                 </tr>
            //                                 <tr>
            //                                     <td style="font-weight:600; color:#555;">Submitted By</td>
            //                                     <td style="text-align:right;">' . htmlspecialchars($vendorDealer) . '</td>
            //                                 </tr>
            //                             </table>

            //                             <p style="margin:0 0 15px;">
            //                                 Please log in to the system to review and verify the supplier at your earliest convenience.
            //                             </p>
            //                             <p style="margin:0 0 20px;">
            //                                 Thank you for your attention.
            //                             </p>

            //                             <!-- Button -->
            //                             <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
            //                                 <tr>
            //                                     <td bgcolor="#004080" style="border-radius:4px;">
            //                                         <a href="supplier_verification?vendor_id=' . urlencode($batchId) . '" 
            //                                         style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
            //                                         Review & Approve Supplier
            //                                         </a>
            //                                     </td>
            //                                 </tr>
            //                             </table>

            //                             <p style="margin:25px 0 0;">Best regards,<br><strong>' . htmlspecialchars($vendorDealer) . '</strong></p>
            //                         </td>
            //                     </tr>

            //                     <!-- Footer -->
            //                     <tr>
            //                         <td align="center" style="padding: 14px 20px; background-color:#f4f6f8; font-size:12px; color:#888888;">
            //                             This is an automated message. Please do not reply.
            //                         </td>
            //                     </tr>

            //                 </table>
            //             </td>
            //         </tr>
            //     </table>

            // </body>
            // </html>
            // ';
            //     function queueMail($pdo, $user_Id, $to, $toName, $email_subject, $email_body, $altBody = '')
            //     {
            //         $stmt = $pdo->prepare("
            //         INSERT INTO email_queue (sender_id, recipient_email, recipient_name, subject, body, alt_body)
            //         VALUES (?, ?, ?, ?, ?, ?)
            //     ");
            //         return $stmt->execute([
            //             $user_Id,
            //             $to,
            //             $toName,
            //             $email_subject,
            //             $email_body,
            //             $altBody ?: strip_tags($email_body)
            //         ]);
            //     }
            //     // Queue the actual email
            //     if (queueMail(
            //         $pdo,
            //         $user_Id,
            //         $emailAddress, // recipient email
            //         $fullName,             // recipient name
            //         $email_subject,               // subject
            //         $email_body // HTML body
            //     )) {
            //         // echo "Email queued successfully!";
            //     } else {
            //         // echo "Failed to queue email.";
            //     }
                // // Send using your existing function
                // $result = sendMail(
                //     $emailAddress, // recipient email
                //     $fullName, // recipient name
                //     $email_subject,              // subject
                //     $email_body                  // HTML body
                // );
                // Queue email instead of sending immediately
                // Queue email instead of sending immediately
                // $stmtQueue->execute([
                //     ':email'   => $emailAddress,
                //     ':name'    => $fullName,
                //     ':subject' => $email_subject,
                //     ':body'    => $email_body
                // ]);
            }

            // ✅ Approve supplier after final level
            $stmt2 = $pdo->prepare("
                UPDATE suppliers 
                SET is_active = 1, is_approved = 1, accreditation_date = :date 
                WHERE vendor_id = :vendor_id
            ");
            $stmt2->execute([
                ':date' => $date,
                ':vendor_id' => $batchId
            ]);

            // exec("php send_emails.php > /dev/null 2>&1 &");
        } catch (Exception $e) {
            error_log("Notification Error: " . $e->getMessage());
        }
    }

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No changes applied.']);
}
// if ($updated) {
//     if ($position === 'Manager' && $vpforaccounting_userid) {
//         sendNotification($pdo, $pusher, $vpforaccounting_userid, $vendorName, $vendorDealer, $batchId, $date);
//     } elseif ($position === 'VP for Accounting' && $svpforaccounting_userid) {
//         sendNotification($pdo, $pusher, $svpforaccounting_userid, $vendorName, $vendorDealer, $batchId, $date);
//     } elseif ($position === 'SVP for Finance and Admin') {
//         if ($purchasingmanager_userid) {
//             sendNotification($pdo, $pusher, $purchasingmanager_userid, $vendorName, $vendorDealer, $batchId, $date);
//         }

//         // ✅ Approve supplier after final level
//         try {
//             $stmt2 = $pdo->prepare("
//                 UPDATE suppliers 
//                 SET is_active = 1, is_approved = 1, accreditation_date = :date 
//                 WHERE vendor_id = ?
//             ");
//             $stmt2->execute([':date' => $date, $batchId]);
//         } catch (PDOException $e) {
//             error_log("Supplier Update Error: " . $e->getMessage());
//         }
//     }

//     echo json_encode(['status' => 'success']);
// } else {
//     echo json_encode(['status' => 'error', 'message' => 'No changes applied.']);
// }

// //////////
// try {
//     // 1️⃣ Insert into notifications table (single record)
//     $stmt = $pdo->prepare("
//         INSERT INTO notifications (title, message, type, link, created_at)
//         VALUES (:title, :message, 'info', :link, :created_at)
//     ");
//     $stmt->execute([
//         ':title'      => "Pending Supplier Approval from " . $vendorDealer,
//         ':message'    => "Supplier Name: $vendorName",
//         ':link'       => "supplier_verification?vendor_id=$batchId&vendor_name=$vendorName&dealer=$vendorDealer",
//         ':created_at' => $date
//     ]);
//     $notificationId = $pdo->lastInsertId();

//     // 2️⃣ Insert into notification_recipients for each recipient
//     $stmtRec = $pdo->prepare("
//         INSERT INTO notification_recipients (notification_id, user_id, is_read)
//         VALUES (:notification_id, :user_id, 0)
//     ");

//     foreach ($targetUserIds as $targetUserId) {  // $targetUserIds = array of user IDs
//         $stmtRec->execute([
//             ':notification_id' => $notificationId,
//             ':user_id'         => $targetUserId
//         ]);

//         // 3️⃣ Trigger Pusher per recipient
//         if ($pusher) {
//             $channel = "private-user-$targetUserId";
//             $data['notifications'] = [[
//                 'notification_id' => $notificationId,
//                 'title'      => 'Pending Supplier Approval from ' . $vendorDealer,
//                 'message'    => 'Supplier Name: ' . $vendorName,
//                 'type'       => 'info',
//                 'is_read'    => 0,
//                 'link'       => 'supplier_verification?vendor_id=' . $batchId . '&vendor_name=' . $vendorName . '&dealer=' . $vendorDealer,
//                 'created_at' => $date
//             ]];
//             $pusher->trigger($channel, 'my-event', $data);
//         }
//     }

// } catch (Exception $e) {
//     error_log("Notification Error: " . $e->getMessage());
// }


// session_start();
// include '../../includes/db.php';
// // file_put_contents('debug_log.txt', print_r($_POST, true), FILE_APPEND);
// // file_put_contents('debug_log.txt', print_r($_SESSION, true), FILE_APPEND);

// // ini_set('display_errors', 1);
// // error_reporting(E_ALL);
// require '../includes/pusher.php';
// header('Content-Type: application/json'); // ✅ ensure JSON response

// $batchId = $_POST['batch_id'] ?? null;
// $position = $_POST['position'] ?? null;
// $submitterName = $_POST['name'] ?? null;
// $index = $_POST['index'] ?? null;
// $userId = $_SESSION['user_id'] ?? null;
// $date = date('Y-m-d H:i:s');

// // ✅ Fix for index = 0
// if (empty($batchId) || empty($position) || empty($userId) || !isset($index) || !is_numeric($index)) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
//     exit;
// }

// $index = intval($_POST['index']); // 2, 1, 6, etc.
// $column = "checked_{$index}";

// $validColumns = ['checked_1', 'checked_2', 'checked_3'];
// if (!in_array($column, $validColumns)) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid column']);
//     exit;
// }

// // Get VP Manager user_id
// $stmt = $pdo->prepare("SELECT user_id FROM users WHERE position = :position LIMIT 1");
// $stmt->execute([':position' => 'VP for Accounting']);
// $vpforaccounting_userid = $stmt->fetchColumn();

// // Get SVP Manager user_id
// $stmt = $pdo->prepare("SELECT user_id FROM users WHERE position = :position LIMIT 1");
// $stmt->execute([':position' => 'SVP for Finance and Admin']);
// $svpforaccounting_userid = $stmt->fetchColumn();

// $stmt = $pdo->prepare("UPDATE supplier_approvals SET {$column} = NOW() WHERE vendor_id = ?");
// $updated = $stmt->execute([$batchId]);

// if ($updated && $position === 'Manager') {
//     // Insert notification only if manager exists
//     if ($vpforaccounting_userid) {
//         $stmt = $pdo->prepare("
//                 INSERT INTO notifications 
//                 (user_id, title, message, type, is_read, link, created_at)
//                 VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
//             ");
//         $stmt->execute([
//             ':user_id'    => $vpforaccounting_userid,
//             ':title'      => $fullname . ' Added a New Supplier',
//             ':message'    => 'Supplier Name: ' . $vendorName,
//             ':link'       => 'supplier_verification?vendor_id=' . $vendorID,
//             ':created_at' => $date
//         ]);

//         // Define target user ID (e.g. manager's ID)
//         $targetUserId = $vpforaccounting_userid;
//         $channelName = 'private-user-' . $targetUserId;

//         // Pusher Notification
//         if (isset($pusher)) {
//             $data['notifications'] = [[
//                 'user_id'    => $vpforaccounting_userid,
//                 'title'      => 'A new supplier added by ' . $fullname . ' awaits your approval.',
//                 'message'    => 'Supplier Name: ' . $vendorName,
//                 'type'       => 'info',
//                 'is_read'    => 0,
//                 'link'       => 'supplier_verification?vendor_id=' . $vendorID,
//                 'created_at' => $date
//             ]];

//             try {
//                 $pusher->trigger($channelName, 'my-event', $data);
//             } catch (Exception $e) {
//                 error_log("Pusher error: " . $e->getMessage());
//             }
//         }
//     }
// } elseif ($updated && $position === 'VP for Accounting') {
//     // Insert notification only if manager exists
//     if ($svpforaccounting_userid) {
//         $stmt = $pdo->prepare("
//                 INSERT INTO notifications 
//                 (user_id, title, message, type, is_read, link, created_at)
//                 VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
//             ");
//         $stmt->execute([
//             ':user_id'    => $svpforaccounting_userid,
//             ':title'      => $fullname . ' Added a New Supplier',
//             ':message'    => 'Supplier Name: ' . $vendorName,
//             ':link'       => 'supplier_verification?vendor_id=' . $vendorID,
//             ':created_at' => $date
//         ]);

//         // Define target user ID (e.g. manager's ID)
//         $targetUserId = $svpforaccounting_userid;
//         $channelName = 'private-user-' . $targetUserId;

//         // Pusher Notification
//         if (isset($pusher)) {
//             $data['notifications'] = [[
//                 'user_id'    => $svpforaccounting_userid,
//                 'title'      => 'A new supplier added by ' . $fullname . ' awaits your approval.',
//                 'message'    => 'Supplier Name: ' . $vendorName,
//                 'type'       => 'info',
//                 'is_read'    => 0,
//                 'link'       => 'supplier_verification?vendor_id=' . $vendorID,
//                 'created_at' => $date
//             ]];

//             try {
//                 $pusher->trigger($channelName, 'my-event', $data);
//             } catch (Exception $e) {
//                 error_log("Pusher error: " . $e->getMessage());
//             }
//         }
//     }
// } elseif ($updated && $position === 'SVP for Finance and Admin') {
//     // Insert notification only if manager exists
//     if ($purchasingmanager_userid) {
//         $stmt = $pdo->prepare("
//                 INSERT INTO notifications 
//                 (user_id, title, message, type, is_read, link, created_at)
//                 VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
//             ");
//         $stmt->execute([
//             ':user_id'    => $purchasingmanager_userid,
//             ':title'      => $fullname . ' Added a New Supplier',
//             ':message'    => 'Supplier Name: ' . $vendorName,
//             ':link'       => 'supplier_verification?vendor_id=' . $vendorID,
//             ':created_at' => $date
//         ]);

//         // Define target user ID (e.g. manager's ID)
//         $targetUserId = $purchasingmanager_userid;
//         $channelName = 'private-user-' . $targetUserId;

//         // Pusher Notification
//         if (isset($pusher)) {
//             $data['notifications'] = [[
//                 'user_id'    => $purchasingmanager_userid,
//                 'title'      => 'A new supplier added by ' . $fullname . ' awaits your approval.',
//                 'message'    => 'Supplier Name: ' . $vendorName,
//                 'type'       => 'info',
//                 'is_read'    => 0,
//                 'link'       => 'supplier_verification?vendor_id=' . $vendorID,
//                 'created_at' => $date
//             ]];

//             try {
//                 $pusher->trigger($channelName, 'my-event', $data);
//             } catch (Exception $e) {
//                 error_log("Pusher error: " . $e->getMessage());
//             }
//         }
//     }
//     $stmt2 = $pdo->prepare("UPDATE suppliers SET is_active = 1, is_approved = 1, accreditation_date = '$date' WHERE vendor_id = ?");
//     $stmt2->execute([$batchId]);
// }

// if ($updated) {
//     echo json_encode(['status' => 'success']);
// } else {
//     echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
// }
