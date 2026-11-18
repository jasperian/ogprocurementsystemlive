<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';
require '../includes/pusher.php';

$dealer = $_SESSION['dealer'] ?? '';
$department = $_SESSION['department'] ?? '';
$status = 'For Canvass';
$fullname = $_SESSION['full_name'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

$response = ['status' => 'danger', 'message' => 'Something went wrong.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_number = trim($_POST['pr_number'] ?? '');
    $pr_department = trim($_POST['pr_department'] ?? '');
    $date = date('Y-m-d H:i:s');

    // Validate PR number and department
    if (empty($pr_number) || empty($pr_department)) {
        echo json_encode(['status' => 'danger', 'message' => 'PR number and department are required.']);
        exit;
    }

    // Validate PDF upload
    if (!isset($_FILES['vendorPdf']) || $_FILES['vendorPdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'danger', 'message' => 'PDF file is required and must be valid.']);
        exit;
    }

    // Check if uploaded file is PDF
    $pdfFile = $_FILES['vendorPdf'];
    $fileTmpPath = $pdfFile['tmp_name'];
    $fileMime = mime_content_type($fileTmpPath);
    if ($fileMime !== 'application/pdf') {
        echo json_encode(['status' => 'danger', 'message' => 'Invalid file type. Only PDF is allowed.']);
        exit;
    }

    // Check for duplicate PR number
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requisition WHERE pr_number = :pr_number");
    $stmt->execute([':pr_number' => $pr_number]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'danger', 'message' => 'PR number already exists.']);
        exit;
    }

    // Handle file upload securely
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeFileName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', basename($pdfFile['name']));
    $destPath = $uploadDir . time() . '_' . $safeFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        echo json_encode(['status' => 'danger', 'message' => 'Failed to upload PDF file.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT user_id, email_address, full_name
        FROM users 
        WHERE department = :department 
          AND dealer = :dealer 
          AND position = 'Manager' 
        LIMIT 1
    ");
    $stmt->execute([
        ':department' => $department,
        ':dealer'     => $dealer
    ]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $purchasingmanager_userid       = $user['user_id'];
        $email_address = $user['email_address'];
        $purchasingmanager_full_name     = $user['full_name'];
    }

    try {
        // Insert PR
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO purchase_requisition 
            (pr_number, pr_status, pr_department, pr_date, pr_file, pr_dealer) 
            VALUES (:pr_number, :status, :pr_department, :pr_date, :pr_file, :pr_dealer)
        ");
        $stmt->execute([
            ':pr_number'     => $pr_number,
            ':status'        => $status,
            ':pr_department' => $pr_department,
            ':pr_date'       => $date,
            ':pr_file'       => $destPath,
            ':pr_dealer'     => $dealer
        ]);

        // Insert Leadtime Tracking
        $stmt = $pdo->prepare("INSERT INTO leadtime_tracking (pr_number, received_date) VALUES (:pr_number, :date)");
        $stmt->execute([':pr_number' => $pr_number, ':date' => $date]);

        // Insert notification only if manager exists
        if ($purchasingmanager_userid) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, is_read, link, created_at)
                VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
            ");
            $stmt->execute([
                ':user_id'    => $purchasingmanager_userid,
                ':title'      => $fullname . ' added a new PR from ' . $pr_department,
                ':message'    => 'PR Number: ' . $pr_number,
                ':link'       => 'view_purchase_requisition?pr_number=' . $pr_number,
                ':created_at' => $date
            ]);

            // Define target user ID (e.g. manager's ID)
            $targetUserId = $purchasingmanager_userid;
            $channelName = 'private-user-' . $targetUserId;

            // Pusher Notification
            if (isset($pusher)) {
                $data['notifications'] = [[
                    'user_id'    => $purchasingmanager_userid,
                    'title'      => $fullname . ' added a new PR from ' . $pr_department,
                    'message'    => 'PR Number: ' . $pr_number,
                    'type'       => 'info',
                    'is_read'    => 0,
                    'link'       => 'view_purchase_requisition?pr_number=' . $pr_number,
                    'created_at' => $date
                ]];

                try {
                    $pusher->trigger($channelName, 'my-event', $data);
                } catch (Exception $e) {
                    error_log("Pusher error: " . $e->getMessage());
                }
            }

            $email_subject = $fullname . ' added a new PR from ' . $pr_department;

            $email_body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>' . htmlspecialchars($email_subject) . '</title>
                    <style>
                        body {
                            background-color: #f4f6f8;
                            margin: 0;
                            padding: 0;
                            font-family: "Segoe UI", Arial, sans-serif;
                            color: #2c3e50;
                        }
                        .email-wrapper {
                            max-width: 640px;
                            margin: 40px auto;
                            background: #ffffff;
                            border-radius: 8px;
                            overflow: hidden;
                            border: 1px solid #e0e0e0;
                            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        }
                        .accent-strip {
                            background-color: #004080;
                            height: 5px;
                        }
                        .email-header {
                            padding: 20px;
                            text-align: center;
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .email-header h1 {
                            font-size: 20px;
                            font-weight: 600;
                            margin: 0;
                            color: #004080;
                        }
                        .email-content {
                            padding: 24px 28px;
                            font-size: 15px;
                            line-height: 1.6;
                        }
                        .detail-card {
                            background: #fafbfc;
                            border: 1px solid #e6e9ec;
                            border-radius: 6px;
                            padding: 16px;
                            margin: 18px 0;
                        }
                        .detail-row {
                            display: flex;
                            justify-content: space-between;
                            padding: 6px 0;
                            font-size: 14px;
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .detail-row:last-child {
                            border-bottom: none;
                        }
                        .detail-label {
                            font-weight: 600;
                            color: #555;
                        }
                        .btn-primary {
                            display: inline-block;
                            background-color: #004080;
                            color: #ffffff !important;
                            padding: 10px 20px;
                            border-radius: 4px;
                            text-decoration: none;
                            font-size: 14px;
                            font-weight: 600;
                            margin-top: 10px;
                        }
                        .email-footer {
                            padding: 14px 20px;
                            background: #f4f6f8;
                            font-size: 12px;
                            color: #888888;
                            text-align: center;
                        }
                        @media only screen and (max-width: 480px) {
                            .detail-row {
                                flex-direction: column;
                                align-items: flex-start;
                            }
                            .btn-primary {
                                display: block;
                                text-align: center;
                                width: 100%;
                                margin-top: 20px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="email-wrapper">
                        <div class="accent-strip"></div>
                        
                        <div class="email-header">
                            <h1>Purchase Requisition Notification</h1>
                        </div>
                        
                        <div class="email-content">
                            <p><strong>' . htmlspecialchars($fullname) . '</strong> has submitted a new Purchase Requisition from <strong>' . htmlspecialchars($pr_department) . '</strong>.</p>
                            
                            <div class="detail-card">
                                <div class="detail-row">
                                    <span class="detail-label">PR Number </span>
                                    <span> ' . htmlspecialchars($pr_number) . '</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Department </span>
                                    <span> ' . htmlspecialchars($pr_department) . '</span>
                                </div>
                            </div>
                            
                            <a href="https://ogprocurementsystem.toyotaobengroup.com" class="btn-primary">
                                View Purchase Requisition
                            </a>
                        </div>
                        
                        <div class="email-footer">
                            This is an automated message. Please do not reply.
                        </div>
                    </div>
                </body>
                </html>
                ';

            function queueMail($pdo, $user_id, $to, $toName, $email_subject, $email_body, $altBody = '') {
                $stmt = $pdo->prepare("
                    INSERT INTO email_queue (sender_id, recipient_email, recipient_name, subject, body, alt_body)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                return $stmt->execute([
                    $user_id,
                    $to,
                    $toName,
                    $email_subject,
                    $email_body,
                    $altBody ?: strip_tags($email_body)
                ]);
            }
            
            // Queue the actual email
            if (queueMail(
                $pdo,
                $user_id,
                $email_address, // recipient email
                $purchasingmanager_full_name,             // recipient name
                $email_subject,               // subject
                $email_body // HTML body
            )) {
                // echo "Email queued successfully!";
            } else {
                // echo "Failed to queue email.";
            }
        }

        $pdo->commit();
        $response = ['status' => 'success', 'message' => 'PR added successfully!'];
    } catch (Exception $e) {
        $pdo->rollBack();
        $response = ['status' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
    }
}

echo json_encode($response);


// $dealer = $_SESSION['dealer'] ?? '';
// $department = $_SESSION['department'] ?? '';
// $status = 'For Canvass';
// $fullname = $_SESSION['full_name'] ?? '';

// $response = ['status' => 'danger', 'message' => 'Something went wrong.'];

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $pr_number = $_POST['pr_number'];
//     $pr_department = $_POST['pr_department'];
//     $date = date('Y-m-d H:i:s');

//     // Validate PDF upload
//     if (!isset($_FILES['vendorPdf']) || $_FILES['vendorPdf']['error'] !== 0) {
//         echo json_encode([
//             'status' => 'danger',
//             'message' => 'PDF file is required and must be valid.'
//         ]);
//         exit;
//     }

//     // Check for duplicate TIN
//     $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requisition WHERE pr_number = ?");
//     $stmt->execute([$pr_number]);
//     $exists = $stmt->fetchColumn();

//     if ($exists) {
//         echo json_encode([
//             'status' => 'danger',
//             'message' => 'PR number already exists.'
//         ]);
//         exit;
//     }

//     // // Handle file upload
//     $pdfFile = $_FILES['vendorPdf'];
//     $fileTmpPath = $pdfFile['tmp_name'];
//     $fileName = basename($pdfFile['name']);
//     $uploadDir = 'uploads/';
//     $destPath = $uploadDir . time() . '_' . $fileName;

//     if (!file_exists($uploadDir)) {
//         mkdir($uploadDir, 0777, true);
//     }

//     if (!move_uploaded_file($fileTmpPath, $destPath)) {
//         echo json_encode([
//             'status' => 'danger',
//             'message' => 'Failed to upload PDF file.'
//         ]);
//         exit;
//     }

//     // Get Noter in the same department (Purchasing)
//     $stmt = $pdo->prepare("
//     SELECT user_id
//     FROM users
//     WHERE department = :department 
//       AND dealer = :dealer
//       AND position = 'Manager'
//     LIMIT 1
//     ");
//     $stmt->execute([
//         ':department' => $department,
//         ':dealer'     => $dealer
//     ]);

//     // Fetch directly as single value
//     $purchasingmanager_userid = $stmt->fetchColumn() ?? null;

//     // Optionally cast to int if user_id is numeric
//     if ($purchasingmanager_userid !== null) {
//         $purchasingmanager_userid = (int) $purchasingmanager_userid;
//     }

//     try {
//         $stmt = $pdo->prepare("INSERT INTO purchase_requisition (pr_number, pr_status, pr_department, pr_date, pr_file, pr_dealer) VALUES (?, ?, ?, ?, ?, ?)");
//         $stmt->execute([$pr_number, $status, $pr_department, $date, $destPath, $dealer]);

//         $stmt = $pdo->prepare("INSERT INTO leadtime_tracking (pr_number, received_date) VALUES (?, ?)");
//         $stmt->execute([$pr_number, $date]);

//         $stmt = $pdo->prepare("
//             INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at)
//             VALUES (:user_id, :title, :message, :type, :is_read, :link, :created_at)
//         ");

//         $stmt->execute([
//             ':user_id'    => $purchasingmanager_userid,
//             ':title'      => $fullname . ' Added a New PR from ' . $pr_department,
//             ':message'    => 'PR Number: ' . $pr_number,
//             ':type'       => 'info',
//             ':is_read'    => 0,
//             ':link'       => 'view_purchase_requisition?pr_number=' . $pr_number,
//             ':created_at' => date('Y-m-d H:i:s')
//         ]);

//         // Optional: Also store the same structure in $data['notifications']
//         $data['notifications'] = json_encode([[
//             'user_id'    => $purchasingmanager_userid,
//             'title'      => $fullname . ' Added a New PR from ' . $pr_department,
//             'message'    => 'PR Number: ' . $pr_number,
//             'type'       => 'info',
//             'is_read'    => 0,
//             'link'       => 'view_purchase_requisition?pr_number=' . $pr_number,
//             'created_at' => date('Y-m-d H:i:s')
//         ]]);

//         $pusher->trigger('my-channel', 'my-event', $data);

//         $response = ['status' => 'success', 'message' => 'PR added successfully!'];
//     } catch (Exception $e) {
//         $response['message'] = 'Error: ' . $e->getMessage();
//     }
// }

// echo json_encode($response);
