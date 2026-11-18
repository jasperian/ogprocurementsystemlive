<?php
include '../../includes/db.php';
require '../includes/pusher.php';
require_once '../includes/mailer.php';

session_start();

// Log POST and SESSION for debugging
file_put_contents('debug_post.log', print_r([
    '_POST' => $_POST
], true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- INPUTS ---
        $suppliers                = $_POST['suppliers'] ?? [];
        $pr_number                = trim($_POST['pr_number'] ?? '');
        $notes                    = trim($_POST['notes'] ?? '');
        $assigned_department      = $_POST['assigned_department'] ?? null;
        $dealer                   = $_SESSION['dealer'] ?? null;

        $userid                   = $_SESSION['user_id'] ?? null;
        $created_by               = $_SESSION['full_name'] ?? 'System';

        $generalmanager_userid    = $_POST['generalmanager_userid'] ?? null;
        $purchasingmanager_userid = $_POST['purchasingmanager_userid'] ?? null;
        $batch_id                 = $_POST['batch_id'] ?? null;

        $renovation_related       = isset($_POST['renovationRelated']) ? 1 : 0;

        // --- BASIC VALIDATION ---
        if (
            !$pr_number || !$dealer || !$userid ||
            !$generalmanager_userid || !$purchasingmanager_userid ||
            !$assigned_department || !$batch_id
        ) {
            throw new Exception("Missing required fields");
        }

        // --- DB TRANSACTION ---
        $pdo->beginTransaction();

        // --- UPDATE BATCH ---
        $stmt = $pdo->prepare("
            UPDATE canvass_sheet_batches
            SET pr_number = ?, notes = ?, updated_by = ?, updated_at = NOW()
            WHERE batch_id = ?
        ");
        $stmt->execute([$pr_number, $notes, $created_by, $batch_id]);

        // --- CLEAR OLD ITEMS ---
        $stmt = $pdo->prepare("DELETE FROM canvass_items WHERE batch_id = ?");
        $stmt->execute([$batch_id]);

        

        // --- SUPPLIER ITEMS ---
        $total_price = 0;
        foreach ($suppliers as $supplier) {
            $supplier_id = $supplier['supplier_id'] ?? null;
            $items       = $supplier['items'] ?? [];

            if (!$supplier_id || !is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $item_id = $item['item_id'] ?? null;
                $qty     = (int) ($item['qty'] ?? 0);
                $price   = (float) ($item['price'] ?? 0);

                if (!$item_id || $qty <= 0 || $price < 0) {
                    continue;
                }

                $total_price += $qty * $price;

                $stmt = $pdo->prepare("
                    INSERT INTO canvass_items (supplier_id, item_id, qty, unit_price, batch_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$supplier_id, $item_id, $qty, $price, $batch_id]);
            }
        }

        $date = date('Y-m-d H:i:s');

        // --- UPDATE PURCHASE REQUISITION ---
        // NOTE: Only update canvass_updated, not always reset pr_status
        $stmt = $pdo->prepare("
            UPDATE purchase_requisition
            SET canvass_created = ?,
            pr_status = 'For Approval'
            WHERE pr_number = ?
        ");
        $stmt->execute([$date, $pr_number]);

        // --- ADD OR UPDATE COMMENT ---
        $stmt = $pdo->prepare("
            INSERT INTO recommendations_suggestions (batch_id, pr_number, comments, user_id, created_date)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE comments = VALUES(comments), user_id = VALUES(user_id), updated_at = NOW()
        ");
        $stmt->execute([$batch_id, $pr_number, $notes, $userid]);

        // --- LEAD TIME ---
        $stmt = $pdo->prepare("
            UPDATE leadtime_tracking
            SET for_canvass_date = NOW()
            WHERE pr_number = ?
        ");
        $stmt->execute([$pr_number]);

        // --- FETCH APPROVERS ---
        $stmt = $pdo->query("
            SELECT position, user_id
            FROM users
            WHERE position IN (
                'VP for Accounting',
                'SVP for Finance and Admin',
                'President',
                'Chairman',
                'Vice Chairman'
            )
        ");
        $managers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $vpforaccounting_userid  = $managers['VP for Accounting'] ?? null;
        $svpforaccounting_userid = $managers['SVP for Finance and Admin'] ?? null;
        $president_userid        = $managers['President'] ?? null;
        $chairman_userid         = $managers['Chairman'] ?? null;
        $vicechairman_userid     = $managers['Vice Chairman'] ?? null;

        // --- DETERMINE APPROVAL ROUTE ---
        $approver_5 = $approver_6 = $approver_7 = $approver_8 = $approver_9 = null;
        $canvass_type = 1;

        if ($total_price > 300000 || $renovation_related == 1) {
            $approver_5 = $vpforaccounting_userid;
            $approver_6 = $svpforaccounting_userid;
            $approver_7 = $president_userid;
            $approver_9 = $chairman_userid;
            $canvass_type = 3;
        } elseif ($total_price > 100000) {
            $approver_5 = $vpforaccounting_userid;
            $approver_6 = $svpforaccounting_userid;
            $approver_7 = $president_userid;
            $canvass_type = 2;
        }

        $stmt = $pdo->prepare("
            SELECT pr_department
            FROM purchase_requisition
            WHERE pr_number = ?
        ");
        $stmt->execute([$pr_number]);
        $pr_department = $stmt->fetchColumn();

        // --- UPDATE APPROVALS ---
        $stmt = $pdo->prepare("
            UPDATE canvass_approvals
            SET 
                checked_2 = NULL,
                checked_3 = NULL,
                checked_4 = NULL,
                checked_5 = NULL,
                checked_6 = NULL,
                checked_7 = NULL,
                checked_8 = NULL,
                checked_9 = NULL,
                assigned_dealer = :dealer,
                checked_1 = :checked,
                canvass_type = :ctype,
                updated_at = NOW()
            WHERE batch_id = :batch_id
        ");

        $stmt->execute([
            ':batch_id' => $batch_id,
            ':dealer'   => $dealer,
            ':checked'  => $date,
            ':ctype'    => $canvass_type
        ]);

        $stmt = $pdo->prepare("
             SELECT user_id
             FROM users
             WHERE department = ? AND dealer = ? AND position = 'Manager'
        ");
        $stmt->execute([$assigned_department, $dealer]);
        $manager_user_id = $stmt->fetchColumn();

        // --- NOTIFICATION ---
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at)
            VALUES (:uid, :title, :msg, 'danger', 0, :link, :created)
        ");
        $stmt->execute([
            ':uid'    => $manager_user_id,
            ':title'  => "New canvass pending approval.",
            ':msg'    => "PR Number: $pr_number",
            ':link'   => "canvass_view?pr_number=$pr_number",
            ':created' => $date
        ]);

        // --- PUSHER ---
        if (isset($pusher) && $pusher) {
            $channel = "private-user-$manager_user_id";
            $data['notifications'] = [[
                'user_id'    => $manager_user_id,
                'title'      => 'New canvass pending approval.',
                'message'    => 'PR Number: ' . $pr_number,
                'type'       => 'danger',
                'is_read'    => 0,
                'link'       => 'canvass_view?pr_number=' . $pr_number,
                'created_at' => $date
            ]];
            $pusher->trigger($channel, 'my-event', $data);
        }

        // --- EMAIL NOTIFICATION ---
        // $stmt = $pdo->prepare("
        //         SELECT email_address, full_name 
        //         FROM users 
        //         WHERE user_id = :user_id
        //     ");
        // $stmt->execute([':user_id' => $assigned_department]); // here $assigned_department is the ID
        // $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // $department_email    = $row['email_address'] ?? '';
        // $department_fullname = $row['full_name'] ?? '';

        // $email_subject = "OGPS: " . htmlspecialchars($pr_number) . " for Approval from " . htmlspecialchars($dealer);

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
        //                                     Purchase Requisition Notification
        //                                 </h1>
        //                             </td>
        //                         </tr>

        //                         <!-- Body content -->
        //                         <tr>
        //                             <td style="padding: 24px 28px; font-size:15px; line-height:1.6; color:#333333;">
        //                                 <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
        //                                 <p style="margin:0 0 15px;">
        //                                     You have a Purchase Request (PR) pending your approval in OGPS.
        //                                 </p>

        //                                 <!-- Details table -->
        //                                 <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin: 15px 0;">
        //                                     <tr>
        //                                         <td style="font-weight:600; color:#555;">PR Number</td>
        //                                         <td style="text-align:right;">' . htmlspecialchars($pr_number) . '</td>
        //                                     </tr>
        //                                     <tr>
        //                                         <td style="font-weight:600; color:#555;">Department</td>
        //                                         <td style="text-align:right;">' . htmlspecialchars($pr_department) . '</td>
        //                                     </tr>
        //                                 </table>

        //                                 <p style="margin:0 0 15px;">
        //                                     Please log in to the system to review and approve the request at your earliest convenience.
        //                                 </p>
        //                                 <p style="margin:0 0 20px;">
        //                                     Thank you for your attention.
        //                                 </p>

        //                                 <!-- Button -->
        //                                 <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
        //                                     <tr>
        //                                         <td bgcolor="#004080" style="border-radius:4px;">
        //                                             <a href="view_purchase_requisition?pr_number=' . urlencode($pr_number) . '" 
        //                                             style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">
        //                                             View & Approve PR
        //                                             </a>
        //                                         </td>
        //                                     </tr>
        //                                 </table>
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

        // // Send using your existing function
        // $result = sendMail(
        //     $department_email, // recipient email
        //     $department_fullname, // recipient name
        //     $email_subject,              // subject
        //     $email_body                  // HTML body
        // );

        // --- COMMIT ---
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Saved with PR Number: $pr_number"]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}



// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     try {
//         $suppliers = $_POST['suppliers'] ?? [];
//         $pr_number = $_POST['pr_number'] ?? null;
//         $notes = $_POST['notes'] ?? null;
//         $assigned_department = $_POST['assigned_department'] ?? '';
//         $dealer = $_SESSION['dealer'] ?? null;

//         $userid = $_SESSION['user_id'] ?? null;
//         $created_by = $_SESSION['full_name'] ?? 'System';

//         $generalmanager_userid = $_POST['generalmanager_userid'] ?? null;
//         $purchasingmanager_userid = $_POST['purchasingmanager_userid'] ?? null;

//         $renovation_related = isset($_POST['renovationRelated']) ? 1 : 0;

//         if (!$pr_number || !$dealer || !$userid || !$generalmanager_userid || !$purchasingmanager_userid) {
//             echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
//             exit;
//         }

//         // Check for duplicate PR Number
//         $stmt = $pdo->prepare("SELECT COUNT(*) FROM canvass_sheet_batches WHERE pr_number = ?");
//         $stmt->execute([$pr_number]);
//         if ($stmt->fetchColumn() > 0) {
//             echo json_encode(['status' => 'error', 'message' => 'PR Number already exists']);
//             exit;
//         }

//         // Begin transaction
//         $pdo->beginTransaction();

//         // Insert into canvass_sheet_batches
//         $stmt = $pdo->prepare("INSERT INTO canvass_sheet_batches (pr_number, notes, created_by) VALUES (?, ?, ?)");
//         $stmt->execute([$pr_number, $notes, $created_by]);
//         $batch_id = $pdo->lastInsertId();

//         $total_price = 0;

//         // Loop through suppliers and insert items into canvass_items
//         foreach ($suppliers as $formId => $supplier) {
//             $supplier_id = $supplier['supplier_id'] ?? null;
//             $items = $supplier['items'] ?? [];

//             // $total_price = 0;

//             foreach ($items as $item) {
//                 $item_id = $item['item_id'] ?? null;
//                 $qty = $item['qty'] ?? 0;
//                 $price = $item['price'] ?? 0;

//                 $total_price += $qty * $price;

//                 $stmt = $pdo->prepare("INSERT INTO canvass_items (supplier_id, item_id, qty, unit_price, batch_id) VALUES (?, ?, ?, ?, ?)");
//                 $stmt->execute([$supplier_id, $item_id, $qty, $price, $batch_id]);
//             }
//         }

//         $date = date('Y-m-d H:i:s');

//         // Update purchase requisition
//         $stmt = $pdo->prepare("UPDATE purchase_requisition SET pr_status = 'For Approval', batch_id = ?, canvass_created = ? WHERE pr_number = ?");
//         $stmt->execute([$batch_id, $date, $pr_number]);

//         // add Comments
//         $stmt = $pdo->prepare("INSERT INTO recommendations_suggestions (batch_id, pr_number, comments, user_id) VALUES (?, ?, ?, ?)");
//         $stmt->execute([$batch_id, $pr_number, $notes, $userid]);

//         // Lead Time
//         $stmt = $pdo->prepare("UPDATE leadtime_tracking SET for_canvass_date = NOW() WHERE pr_number = ?");
//         $stmt->execute([$pr_number]);


//         // Determine approvers based on the total price
//         $approver_3 = null;
//         $approver_4 = null;
//         $approver_5 = null;
//         $approver_6 = null;
//         $approver_8 = null;
//         $approver_9 = null;
//         $canvass_type = 1; // Default to 1 for regular canvass

//         try {
//             // Fetch VP and SVP (positions only)
//             $stmt = $pdo->query("
//                 SELECT position, user_id 
//                 FROM users 
//                 WHERE position IN (
//                 'VP for Accounting',
//                 'SVP for Finance and Admin',
//                 'President',
//                 'Chairman',
//                 'Vice Chairman'
//             )
//             ");
//             $managers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

//             $vpforaccounting_userid  = $managers['VP for Accounting'] ?? null;
//             $svpforaccounting_userid = $managers['SVP for Finance and Admin'] ?? null;
//             $president_userid = $managers['President'] ?? null;
//             $chairman_userid = $managers['Chairman'] ?? null;
//             $vicechairman_userid = $managers['Vice Chairman'] ?? null;
//         } catch (PDOException $e) {
//             error_log("DB Fetch Error: " . $e->getMessage());
//             echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve manager data.']);
//             exit;
//         }


//         if ($total_price > 300000 || $renovation_related == 1) {
//             // Assign approvers if the total price is greater than 100,000
//             $approver_5 = $vpforaccounting_userid; // VP Account
//             $approver_6 = $svpforaccounting_userid; // SVP Finance and Admin
//             $approver_7 = $president_userid; // President
//             $approver_9 = $chairman_userid; // Chairman
//             $canvass_type = 3; // Set canvass type to 3 for high-value canvass
//         } elseif ($total_price > 100000) {
//             $approver_5 = $vpforaccounting_userid; // VP Account
//             $approver_6 = $svpforaccounting_userid; // SVP Finance and Admin
//             $approver_7 = $president_userid; // President
//             $canvass_type = 2; // Set canvass type to 2 for high-value canvass
//         }

//         // if ($renovation_related == 1) {
//         //     $approver_9 = '16'; // Chairman Renovation
//         //     $canvass_type = 3;
//         // }

//         // Insert into canvass_approvals
//         $stmt = $pdo->prepare("INSERT INTO canvass_approvals (
//         batch_id, approver_1, approver_2, approver_3, approver_4, approver_5, approver_6, approver_7, approver_8, approver_9, assigned_dealer, checked_1, canvass_type
//         ) VALUES (
//             :vendor_id, :approver_1, :approver_2, :approver_3, :approver_4, :approver_5, :approver_6, :approver_7, :approver_8, :approver_9, :assigned_dealer, :checked_1, :canvass_type
//         )");

//         if (in_array(strtoupper($dealer), ['TBK', 'TNE', 'TOT'])) {
//             $stmt->execute([
//                 ':vendor_id'            => $batch_id,
//                 ':approver_1'           => $userid, // User ID of the person creating the PR
//                 ':approver_2'           => $assigned_department, // Department User ID
//                 ':approver_3'           => $purchasingmanager_userid, // Purchasing Manager User ID
//                 ':approver_4'           => $generalmanager_userid, // General Manager User ID
//                 ':approver_5'           => $approver_5, // VP Account
//                 ':approver_6'           => $approver_6, // SVP Finance and Admin     
//                 ':approver_7'           => $approver_7, // President User ID
//                 ':approver_8'           => null, // <--- add this
//                 ':approver_9'           => $approver_9, // Chairman Renovation User ID
//                 ':assigned_dealer'      => $dealer,
//                 ':checked_1'            => date('Y-m-d H:i:s'), // Set checked_1 to current time
//                 ':canvass_type'         => $canvass_type
//             ]);
//         } elseif (in_array(strtoupper($dealer), ['TMR', 'TSJ'])) {
//             $stmt->execute([
//                 ':vendor_id'            => $batch_id,
//                 ':approver_1'           => $userid, // User ID of the person creating the PR
//                 ':approver_2'           => $assigned_department, // Department User ID
//                 ':approver_3'           => $purchasingmanager_userid, // Purchasing Manager User ID
//                 ':approver_4'           => $generalmanager_userid, // General Manager User ID
//                 ':approver_5'           => $approver_5, // VP Account
//                 ':approver_6'           => $approver_6, // SVP Finance and Admin     
//                 ':approver_7'           => $approver_7, // President User ID
//                 ':approver_8'           => $vicechairman_userid, // <--- add this
//                 ':approver_9'           => $approver_9, // Chairman Renovation User ID
//                 ':assigned_dealer'      => $dealer,
//                 ':checked_1'            => date('Y-m-d H:i:s'), // Set checked_1 to current time
//                 ':canvass_type'         => $canvass_type
//             ]);
//         }

//         // Insert into notifications table
//         $stmt = $pdo->prepare("
//             INSERT INTO notifications 
//             (user_id, title, message, type, is_read, link, created_at)
//             VALUES (:user_id, :title, :message, 'danger', 0, :link, :created_at)
//         ");
//         $stmt->execute([
//             ':user_id'    => $assigned_department,
//             ':title'      => "New canvass pending approval.",
//             ':message'    => "PR Number: $pr_number",
//             ':link'       => "canvass_view?pr_number=$pr_number",
//             ':created_at' => $date
//         ]);

//         // Pusher real-time notification
//         if ($pusher) {
//             $channel = "private-user-$assigned_department";
//             $data['notifications'] = [[
//                 'user_id'    => $assigned_department,
//                 'title'      => 'New canvass pending approval.',
//                 'message'    => 'PR Number: ' . $pr_number,
//                 'type'       => 'danger',
//                 'is_read'    => 0,
//                 'link'       => 'canvass_view?pr_number=' . $pr_number,
//                 'created_at' => $date
//             ]];
//             $pusher->trigger($channel, 'my-event', $data);
//         }

//         // Commit transaction
//         $pdo->commit();

//         echo json_encode(['status' => 'success', 'message' => 'Saved with PR Number: ' . $pr_number]);
//     } catch (PDOException $e) {
//         $pdo->rollBack();
//         echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
//     }
// } else {
//     http_response_code(400);
//     echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
// }
