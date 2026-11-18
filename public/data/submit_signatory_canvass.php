<?php
session_start();
date_default_timezone_set('Asia/Manila');
include '../../includes/db.php';
require '../includes/pusher.php';
header('Content-Type: application/json'); // ✅ Ensure JSON response
require_once '../includes/mailer.php';
// file_put_contents('debug_log.txt', print_r($_POST, true), FILE_APPEND);
// file_put_contents('debug_log.txt', print_r($_SESSION, true), FILE_APPEND);
$batch = $_POST['batch'] ?? null;
$position = $_POST['position'] ?? null;
$submitterName = $_POST['name'] ?? null;
$index = $_POST['index'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$dealer = $_SESSION['dealer'] ?? '';
$date = date('Y-m-d H:i:s');
// ✅ Fix for index = 0
if (empty($batch) || empty($position) || empty($userId) || !isset($index) || !is_numeric($index)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$index = intval($_POST['index']); // 2, 1, 6, etc.
$column = "checked_{$index}";

$validColumns = ['checked_1', 'checked_2', 'checked_3', 'checked_4', 'checked_5', 'checked_6', 'checked_7', 'checked_8', 'checked_9'];
if (!in_array($column, $validColumns)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid column']);
    exit;
}

$stmt = $pdo->prepare("UPDATE canvass_approvals SET {$column} = NOW() WHERE batch_id = ?");
$updated = $stmt->execute([$batch]);


// Fetch canvass_type and pr_number
$stmt = $pdo->prepare("SELECT ca.canvass_type, pr.pr_number, ca.assigned_department, ca.assigned_dealer, ca.approver_2, ca.approver_3, ca.approver_4, ca.approver_5, ca.approver_6, ca.approver_7, ca.approver_8, ca.approver_9 
                       FROM canvass_approvals ca 
                       JOIN purchase_requisition pr ON ca.batch_id = pr.batch_id 
                       WHERE ca.batch_id = ?");
$stmt->execute([$batch]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$canvassUpdated = $updated; // From first update
$statusUpdated = false;

    function queueMail($pdo, $userId, $to, $toName, $email_subject, $email_body, $altBody = '') {
            $stmt = $pdo->prepare("
                INSERT INTO email_queue (sender_id, recipient_email, recipient_name, subject, body, alt_body)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $userId,
                $to,
                $toName,
                $email_subject,
                $email_body,
                $altBody ?: strip_tags($email_body)
            ]);
        }



if ($row) {
    $canvassType = $row['canvass_type'];
    $pr_number = $row['pr_number'];
    $assigned_department = $row['assigned_department'] ?? '';
    $assigned_dealer = $row['assigned_dealer'] ?? '';
    $checked_2 = $row['approver_2']; // For Requester Manager
    $checked_3 = $row['approver_3']; // For Purchasing Manager
    $checked_4 = $row['approver_4']; // For General Manager
    $checked_5 = $row['approver_5']; // For VP for Accounting
    $checked_6 = $row['approver_6']; // For SVP for Finance and Admin
    $checked_7 = $row['approver_7']; // For President
    $checked_8 = $row['approver_8']; // For Vice Chairman
    $checked_9 = $row['approver_9']; // For Chairman

    function sendNotification($pdo, $pusher, $targetUserId, $pr_number, $assigned_department, $date, $userId)
    {
        try {
            $stmt = $pdo->prepare("
            SELECT pr_department
            FROM purchase_requisition
            WHERE pr_number = ?
            ");
            $stmt->execute([$pr_number]);
            $pr_department = $stmt->fetchColumn();

            // Insert into notifications table
            $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, is_read, link, created_at)
            VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
        ");
            $stmt->execute([
                ':user_id'    => $targetUserId,
                ':title'      => "Pending Canvass Approval from " . $pr_department,
                ':message'    => "PR Number: $pr_number",
                ':link'       => "canvass_view?pr_number=$pr_number",
                ':created_at' => $date
            ]);

            // Pusher real-time notification
            if ($pusher) {
                $channel = "private-user-$targetUserId";
                $data['notifications'] = [[
                    'user_id'    => $targetUserId,
                    'title'      => 'Pending Canvass Approval from ' . $pr_department,
                    'message'    => 'PR Number: ' . $pr_number,
                    'type'       => 'info',
                    'is_read'    => 0,
                    'link'       => 'canvass_view?pr_number=' . $pr_number,
                    'created_at' => $date
                ]];
                $pusher->trigger($channel, 'my-event', $data);
            }

            // --- EMAIL NOTIFICATION ---
            $stmt = $pdo->prepare("
                SELECT email_address, full_name 
                FROM users 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $targetUserId]); // here is the ID
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception("User with ID $targetUserId not found.");
            }

            $department_email    = $row['email_address'] ?? '';
            $department_fullname = $row['full_name'] ?? '';

            $email_subject = "OGPS: " . htmlspecialchars($pr_number) . " for Approval from " . htmlspecialchars($pr_department);

            $email_body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>' . htmlspecialchars($email_subject) . '</title>
            </head>
            <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#2c3e50;">

                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:6px;">
                                
                                <!-- Accent strip -->
                                <tr>
                                    <td bgcolor="#004080" style="height:5px; line-height:5px; font-size:0;"></td>
                                </tr>

                                <!-- Header -->
                                <tr>
                                    <td align="center" style="padding: 20px; border-bottom:1px solid #f0f0f0;">
                                        <h1 style="font-size:20px; font-weight:600; color:#004080; margin:0;">
                                            Canvass Notification
                                        </h1>
                                    </td>
                                </tr>

                                <!-- Body content -->
                                <tr>
                                    <td style="padding: 24px 28px; font-size:15px; line-height:1.6; color:#333333;">
                                        <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
                                        <p style="margin:0 0 15px;">
                                            You have a Purchase Request (PR) pending your approval in OGPS.
                                        </p>

                                        <!-- Details table -->
                                        <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin: 15px 0;">
                                            <tr>
                                                <td style="font-weight:600; color:#555;">PR Number</td>
                                                <td style="text-align:right;">' . htmlspecialchars($pr_number) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight:600; color:#555;">Department</td>
                                                <td style="text-align:right;">' . htmlspecialchars($pr_department) . '</td>
                                            </tr>
                                        </table>

                                        <p style="margin:0 0 15px;">
                                            Please log in to the system to review and approve the request at your earliest convenience.
                                        </p>
                                        <p style="margin:0 0 20px;">
                                            Thank you for your attention.
                                        </p>

                                        <!-- Button -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
                                            <tr>
                                                <td bgcolor="#004080" style="border-radius:4px;">
                                                    <a href="https://ogprocurementsystem.toyotaobengroup.com" 
                                                    style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
                                                    View & Approve PR
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td align="center" style="padding: 14px 20px; background-color:#f4f6f8; font-size:12px; color:#888888;">
                                        This is an automated message. Please do not reply.
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>

            </body>
            </html>
            ';
            
            $queued = queueMail(
                        $pdo,
                        $userId,
                        $department_email,
                        $department_fullname,
                        $email_subject,
                        $email_body
                    );
                
        } catch (Exception $e) {
            error_log("Notification Error: " . $e->getMessage());
        }
    }

    if ($assigned_dealer === 'TMR' || $assigned_dealer === 'TSJ') {
        if ($userId === $checked_2 && $checked_3) {
            sendNotification($pdo, $pusher, $checked_3, $pr_number, $assigned_department, $date, $userId); // For Purchasing Manager
        } elseif ($userId  === $checked_3 && $checked_4) {
            sendNotification($pdo, $pusher, $checked_4, $pr_number, $assigned_department, $date, $userId); // For General Manager
        } elseif ($userId ===  $checked_4 && $checked_8) {
            sendNotification($pdo, $pusher, $checked_8, $pr_number, $assigned_department, $date, $userId); // For VP for Accounting
        } elseif ($userId === $checked_8 && $checked_5) {
            sendNotification($pdo, $pusher, $checked_5, $pr_number, $assigned_department, $date, $userId); // For SVP for Finance and Admin
        } elseif ($userId === $checked_5 && $checked_6) {
            sendNotification($pdo, $pusher, $checked_6, $pr_number, $assigned_department, $date, $userId); // For President
        } elseif ($userId === $checked_6 && $checked_7) {
            sendNotification($pdo, $pusher, $checked_7, $pr_number, $assigned_department, $date, $userId); // For Vice Chairman
        } elseif ($userId === $checked_7 && $checked_9) {
            sendNotification($pdo, $pusher, $checked_9, $pr_number, $assigned_department, $date, $userId); // For Chairman
        }
    } else {
        if ($userId === $checked_2 && $checked_3) {
            sendNotification($pdo, $pusher, $checked_3, $pr_number, $assigned_department, $date, $userId); // For Purchasing Manager
        } elseif ($userId  === $checked_3 && $checked_4) {
            sendNotification($pdo, $pusher, $checked_4, $pr_number, $assigned_department, $date, $userId); // For General Manager
        } elseif ($userId === $checked_4 && $checked_5) {
            sendNotification($pdo, $pusher, $checked_5, $pr_number, $assigned_department, $date, $userId); // For SVP for Finance and Admin
        } elseif ($userId === $checked_5 && $checked_6) {
            sendNotification($pdo, $pusher, $checked_6, $pr_number, $assigned_department, $date, $userId); // For President
        } elseif ($userId === $checked_6 && $checked_7) {
            sendNotification($pdo, $pusher, $checked_7, $pr_number, $assigned_department, $date, $userId);
        } elseif ($userId === $checked_7 && $checked_9) {
            sendNotification($pdo, $pusher, $checked_9, $pr_number, $assigned_department, $date, $userId); // For Chairman
        }
    }
    


    if (($canvassType == 1 && $index == 4) ||
        ($canvassType == 2 && $index == 7) ||  // ✅ confirm index
        ($canvassType == 3 && $index == 8)
    ) {
        $stmt = $pdo->prepare("
            SELECT pr_department
            FROM purchase_requisition
            WHERE pr_number = ?
            ");
        $stmt->execute([$pr_number]);
        $pr_department = $stmt->fetchColumn();


        $stmt = $pdo->prepare("UPDATE purchase_requisition SET pr_status = 'For PO' WHERE batch_id = ?");
        $stmt->execute([$batch]);

        $stmt = $pdo->prepare("UPDATE leadtime_tracking SET canvass_approved_date = NOW() WHERE pr_number = ?");
        $stmt->execute([$pr_number]);


        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, link, created_at)
            VALUES (:title, :message, 'info', :link, :created_at)
             ");
        $stmt->execute([
            ':title'      => "Canvass approval has been completed.",
            ':message'    => "PR Number: $pr_number From: $pr_department",
            ':link'       => "canvass_view?pr_number=$pr_number",
            ':created_at' => $date
        ]);
        $notificationId = $pdo->lastInsertId();


        // $stmtUsers = $pdo->prepare("SELECT user_id FROM users WHERE department = 'PURCHASING' AND dealer = :dealer");
        // $stmtUsers->execute([':dealer' => $dealer]);
        // $purchasingUsers = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
        $stmtUsers = $pdo->prepare("
                SELECT user_id, full_name, email_address
                FROM users
                WHERE department = 'PURCHASING'
                AND dealer = :dealer
            ");
        $stmtUsers->execute([':dealer'=>$dealer]);
        $purchasingUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        // ✅ 2. Prepare the statement for inserting into notification_recipients
        $stmtRec = $pdo->prepare("
                INSERT INTO notification_recipients (notification_id, user_id, is_read)
                VALUES (:notification_id, :user_id, 0)
            ");

        // ✅ 3. Loop through Purchasing users
        foreach ($purchasingUsers as $user) {
            $recipientId       = $user['user_id'];
            $fullName     = $user['full_name'];
            $emailAddress = $user['email_address'];
            // Insert recipient record
            $stmtRec->execute([
                ':notification_id' => $notificationId,  // Use the actual notification ID
                ':user_id'         => $recipientId
            ]);

            // Trigger Pusher per recipient
            if ($pusher) {
                $channel = "private-user-$recipientId";
                $data['notifications'] = [[
                    'notification_id' => $notificationId,
                    'title'      => 'Canvass approval has been completed.',
                    'message'    => 'PR Number: ' . $pr_number . ' From: ' . $pr_department,
                    'type'       => 'info',
                    'is_read'    => 0,
                    'link'       => 'canvass_view?pr_number=' . $pr_number,
                    'created_at' => $date
                ]];
                $pusher->trigger($channel, 'my-event', $data);
            }

            // --- EMAIL NOTIFICATION ---
            // $stmt = $pdo->prepare("
            //     SELECT email_address, full_name 
            //     FROM users 
            //     WHERE user_id = :user_id
            //     AND dealer = :dealer
            // ");
            // $stmt->execute([':user_id' => $userId, ':dealer' => $dealer]); // here is the ID
            // $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // if (!$row) {
            //     throw new Exception("User with ID $userId not found.");
            // }

            $department_email    = $emailAddress ?? '';
            $department_fullname = $fullName ?? '';

            $email_subject = "OGPS: " . htmlspecialchars($pr_number) . " for Approval from " . htmlspecialchars($pr_department);

            $email_body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>' . htmlspecialchars($email_subject) . '</title>
            </head>
            <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#2c3e50;">

                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:6px;">
                                
                                <!-- Accent strip -->
                                <tr>
                                    <td bgcolor="#004080" style="height:5px; line-height:5px; font-size:0;"></td>
                                </tr>

                                <!-- Header -->
                                <tr>
                                    <td align="center" style="padding: 20px; border-bottom:1px solid #f0f0f0;">
                                        <h1 style="font-size:20px; font-weight:600; color:#004080; margin:0;">
                                            Canvass Notification
                                        </h1>
                                    </td>
                                </tr>

                                <!-- Body content -->
                                <tr>
                                    <td style="padding: 24px 28px; font-size:15px; line-height:1.6; color:#333333;">
                                        <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
                                        <p style="margin:0 0 15px;">
                                            You have a Purchase Request (PR) pending your approval in OGPS.
                                        </p>

                                        <!-- Details table -->
                                        <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin: 15px 0;">
                                            <tr>
                                                <td style="font-weight:600; color:#555;">PR Number</td>
                                                <td style="text-align:right;">' . htmlspecialchars($pr_number) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight:600; color:#555;">Department</td>
                                                <td style="text-align:right;">' . htmlspecialchars($pr_department) . '</td>
                                            </tr>
                                        </table>

                                        <p style="margin:0 0 15px;">
                                            Please log in to the system to review and approve the request at your earliest convenience.
                                        </p>
                                        <p style="margin:0 0 20px;">
                                            Thank you for your attention.
                                        </p>

                                        <!-- Button -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
                                            <tr>
                                                <td bgcolor="#004080" style="border-radius:4px;">
                                                    <a href="https://ogprocurementsystem.toyotaobengroup.com" 
                                                    style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
                                                    View & Approve PR
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td align="center" style="padding: 14px 20px; background-color:#f4f6f8; font-size:12px; color:#888888;">
                                        This is an automated message. Please do not reply.
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>

            </body>
            </html>
            ';

            
                $queued = queueMail(
                        $pdo,
                        $userId,
                        $department_email,
                        $department_fullname,
                        $email_subject,
                        $email_body
                    );
            // $result = sendMail(
            //     $department_email, // recipient email
            //     $department_fullname, // recipient name
            //     $email_subject,              // subject
            //     $email_body                  // HTML body
            // );

            // $status = $result ? 'sent' : 'failed';

            // // Insert into email_history table
            // $stmt = $pdo->prepare("
            //     INSERT INTO email_history 
            //     (sender_id, recipient_email, recipient_name, subject, body, status, created_at)
            //     VALUES (:sender_id, :recipient_email, :recipient_name, :subject, :body, :status, NOW())
            // ");

            // $stmt->execute([
            //     ':sender_id' => $userId, // Use the session user ID
            //     ':recipient_email' => $department_email,
            //     ':recipient_name'  => $department_fullname,
            //     ':subject'         => $email_subject,
            //     ':body'            => $email_body,
            //     ':status'          => $status
            // ]);
        }

        $statusUpdated = true;
    }
}

if ($canvassUpdated || $statusUpdated) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

// ini_set('display_errors', 1);
// error_reporting(E_ALL);
// // =============================
// // 1. Session and request inputs
// // =============================

// $department = $_SESSION['department'] ?? '';
// $dealer     = strtoupper($_SESSION['dealer'] ?? '');
// $role       = strtolower($_SESSION['role'] ?? '');
// $user_id    = $_SESSION['user_id'] ?? null;

// $draw   = $_GET['draw'] ?? 1;
// $start  = $_GET['start'] ?? 0;
// $length = $_GET['length'] ?? 10;
// $search = $_GET['search']['value'] ?? '';

// $allowedDealers = ['TNE', 'TMR', 'TSJ', 'TBK', 'TOT'];

// // =============================
// // 1. Get ALL special approvers
// // =============================
// try {
//     $stmt = $pdo->query("
//         SELECT position, user_id 
//         FROM users 
//         WHERE position IN (
//             'VP for Accounting',
//             'SVP for Finance and Admin',
//             'Purchasing Manager',
//             'General Manager',
//             'President',
//             'Vice Chairman',
//             'Chairman'
//         )
//     ");
//     $specialApprovers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

//     $vpforaccounting_userid   = $specialApprovers['VP for Accounting'] ?? null;
//     $svpforaccounting_userid  = $specialApprovers['SVP for Finance and Admin'] ?? null;
//     $purchasingmanager_userid = $specialApprovers['Purchasing Manager'] ?? null;
//     $generalmanager_userid    = $specialApprovers['General Manager'] ?? null;
//     $president_userid         = $specialApprovers['President'] ?? null;
//     $vc_userid                = $specialApprovers['Vice Chairman'] ?? null;
//     $chairman_userid          = $specialApprovers['Chairman'] ?? null;

//     // If your "assigned_department" means department name, set here:
//     $assigned_department = $department; 

// } catch (PDOException $e) {
//     error_log("DB Fetch Error: " . $e->getMessage());
//     echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve approvers.']);
//     exit;
// }

// // =============================
// // 2. Pusher notification function
// // =============================
// function sendApprovalNotification($pdo, $pusher, $targetUserId, $batchId, $prNumber, $dealer, $date)
// {
//     if (!$targetUserId) return; // Skip if no target

//     try {
//         $stmt = $pdo->prepare("
//             INSERT INTO notifications 
//             (user_id, title, message, type, is_read, link, created_at)
//             VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
//         ");
//         $stmt->execute([
//             ':user_id'    => $targetUserId,
//             ':title'      => "New Canvass Approval Pending ($dealer)",
//             ':message'    => "PR Number: $prNumber",
//             ':link'       => "view_canvass?batch_id=$batchId",
//             ':created_at' => $date
//         ]);

//         if ($pusher) {
//             $channel = "private-user-$targetUserId";
//             $data['notifications'] = [[
//                 'user_id'    => $targetUserId,
//                 'title'      => "New Canvass Approval Pending ($dealer)",
//                 'message'    => "PR Number: $prNumber",
//                 'type'       => 'info',
//                 'is_read'    => 0,
//                 'link'       => "view_canvass?batch_id=$batchId",
//                 'created_at' => $date
//             ]];
//             $pusher->trigger($channel, 'my-event', $data);
//         }
//     } catch (Exception $e) {
//         error_log("Notification Error: " . $e->getMessage());
//     }
// }

// // =============================
// // 3. WHERE clause construction
// // =============================
// $where  = "1=1";
// $params = [];

// if (in_array($dealer, $allowedDealers)) {
//     $where .= " AND u.dealer = :dealer";
//     $params[':dealer'] = $dealer;
// }

// $where .= " AND (
//     (ca.approver_1 = :user_id)
//     OR (ca.approver_2 = :user_id AND ca.checked_1 IS NOT NULL AND ca.checked_2 IS NULL)
//     OR (ca.approver_3 = :user_id AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL)
//     OR (ca.approver_4 = :user_id AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL AND ca.checked_4 IS NULL)
//     OR (ca.approver_5 = :user_id AND ca.checked_4 IS NOT NULL AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL AND ca.checked_5 IS NULL)
//     OR (ca.approver_6 = :user_id AND ca.checked_5 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL AND ca.checked_6 IS NULL)
//     OR (ca.approver_7 = :user_id AND ca.checked_6 IS NOT NULL AND ca.checked_5 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL AND ca.checked_7 IS NULL)
//     OR (ca.approver_8 = :user_id AND ca.checked_7 IS NOT NULL AND ca.checked_6 IS NOT NULL AND ca.checked_5 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL AND ca.checked_8 IS NULL)
//     OR (ca.approver_9 = :user_id AND ca.checked_8 IS NOT NULL AND ca.checked_7 IS NOT NULL AND ca.checked_6 IS NOT NULL AND ca.checked_5 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_3 IS NOT NULL AND ca.checked_2 IS NOT NULL AND ca.checked_1 IS NOT NULL)

//     /* Special approvers */
//     OR (ca.approver_2 = :assigned_department)
//     OR (ca.approver_3 = :purchasing_manager)
//     OR (ca.approver_3 = :general_manager)
//     OR (ca.approver_5 = :vpaccounting_id)
//     OR (ca.approver_6 = :svpaccounting_id)
//     OR (ca.approver_7 = :president_id)
//     OR (ca.approver_8 = :vc_id)
//     OR (ca.approver_9 = :chairman_id)
// )";

// $params[':user_id']             = $user_id;
// $params[':assigned_department'] = $assigned_department;
// $params[':purchasing_manager']  = $purchasingmanager_userid;
// $params[':general_manager']     = $generalmanager_userid;
// $params[':vpaccounting_id']     = $vpforaccounting_userid;
// $params[':svpaccounting_id']    = $svpforaccounting_userid;
// $params[':president_id']        = $president_userid;
// $params[':vc_id']               = $vc_userid;
// $params[':chairman_id']         = $chairman_userid;

// $where .= " AND (
//     (ca.canvass_type = 1 AND ca.checked_4 IS NULL)
//     OR (ca.canvass_type = 2 AND ca.checked_7 IS NULL)
//     OR (ca.canvass_type = 3 AND ca.checked_9 IS NULL)
// )";

// if (!empty($search)) {
//     $where .= " AND csb.pr_number LIKE :search";
//     $params[':search'] = "%$search%";
// }

// // =============================
// // 4. DataTables Counts
// // =============================
// $totalRecords = $pdo->query("SELECT COUNT(*) FROM canvass_sheet_batches")->fetchColumn();

// $filteredSQL = "
//     SELECT COUNT(DISTINCT csb.batch_id)
//     FROM canvass_sheet_batches csb
//     LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
//     LEFT JOIN users u ON ca.approver_1 = u.user_id
//     WHERE $where
// ";
// $filteredStmt = $pdo->prepare($filteredSQL);
// foreach ($params as $key => $val) {
//     $filteredStmt->bindValue($key, $val);
// }
// $filteredStmt->execute();
// $filteredRecords = $filteredStmt->fetchColumn();

// // =============================
// // 5. Paginated Data Fetch
// // =============================
// $dataSQL = "
//     SELECT 
//         csb.batch_id, 
//         csb.pr_number, 
//         csb.created_by,
//         csb.created_at,
//         u.full_name AS approver_name, 
//         u.department AS assigned_department,
//         u.dealer,
//         csb.notification_sent
//     FROM canvass_sheet_batches csb
//     LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
//     LEFT JOIN users u ON ca.approver_1 = u.user_id
//     WHERE $where
//     GROUP BY csb.batch_id
//     ORDER BY csb.batch_id DESC
//     LIMIT :start, :length
// ";
// $dataStmt = $pdo->prepare($dataSQL);
// foreach ($params as $key => $val) {
//     $dataStmt->bindValue($key, $val);
// }
// $dataStmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
// $dataStmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
// $dataStmt->execute();
// $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// // =============================
// // 6. Send Notifications
// // =============================
// $dateNow = date('Y-m-d H:i:s');
// foreach ($data as $row) {
//     if ($row['notification_sent'] == 0) {
//         sendApprovalNotification($pdo, $pusher ?? null, $vpforaccounting_userid, $row['batch_id'], $row['pr_number'], $dealer, $dateNow);
//         sendApprovalNotification($pdo, $pusher ?? null, $svpforaccounting_userid, $row['batch_id'], $row['pr_number'], $dealer, $dateNow);

//         $updateStmt = $pdo->prepare("UPDATE canvass_sheet_batches SET notification_sent = 1 WHERE batch_id = ?");
//         $updateStmt->execute([$row['batch_id']]);
//     }
// }

// // =============================
// // 7. JSON Response
// // =============================
// header('Content-Type: application/json');
// echo json_encode([
//     "draw" => intval($draw),
//     "recordsTotal" => (int)$totalRecords,
//     "recordsFiltered" => (int)$filteredRecords,
//     "data" => $data
// ]);








// $date = date('Y-m-d H:i:s');

// if ($updated && $position === 'President') {
//     $stmt2 = $pdo->prepare("UPDATE suppliers SET is_active = 1, is_approved = 1, accreditation_date = '$date' WHERE vendor_id = ?");
//     $stmt2->execute([$batchId]);
// }

// session_start();
// include '../../includes/db.php';

// header('Content-Type: application/json');

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $batchId = $_POST['batch_id'] ?? null;
//     $role = $_POST['role'] ?? null;
//     $submitterName = $_POST['name'] ?? null;
//     $userId = $_SESSION['user_id'] ?? null;

//     if (!$batchId || !$role || !$userId) {
//         echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
//         exit;
//     }

//     $column = '';
//     switch ($role) {
//         case 'checker':
//             $column = 'checked';
//             break;
//         case 'noter':
//             $column = 'noted';
//             break;
//         case 'approver':
//             $column = 'approved';
//             break;
//         default:
//             echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
//             exit;
//     }

//     $stmt = $pdo->prepare("UPDATE canvass_approvals SET {$column}_by = ?, {$column}_at = NOW() WHERE batch_id = ?");
//     $updated = $stmt->execute([$userId, $batchId]);

//     if ($updated) {
//         echo json_encode(['status' => 'success']);
//     } else {
//         echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
//     }
// } else {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid method.']);
// }
