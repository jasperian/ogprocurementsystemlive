<?php
include 'includes/pusher.php'; // Include Composer autoload if Pusher is installed via Composer
require '../includes/db.php';

$date = date('Y-m-d H:i:s');
// 2️⃣ Fetch all users from Purchasing department
// $stmtUsers = $pdo->prepare("SELECT user_id FROM users WHERE department = 'PURCHASING'");
// $stmtUsers->execute();
// $purchasingUsers = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

// // ✅ 2. Prepare the statement for inserting into notification_recipients
// $stmtRec = $pdo->prepare("
//     INSERT INTO notification_recipients1 (notification_id, user_id, is_read)
//     VALUES (:notification_id, :user_id, 0)
// ");

// // ✅ 3. Loop through Purchasing users
// foreach ($purchasingUsers as $userId) {
//     // Insert recipient record
//     $stmtRec->execute([
//         ':notification_id' => 11,  // Use the actual notification ID
//         ':user_id'         => $userId
//     ]);

//     // Trigger Pusher per recipient
//     if ($pusher) {
//         $channel = "private-user-$userId";
//         $data['notifications'] = [[
//             'notification_id' => 11,
//             'title'      => 'Pending Supplier Approval from ',
//             'message'    => 'Supplier Name: ',
//             'type'       => 'info',
//             'is_read'    => 0,
//             'link'       => 'supplier_verification?vendor_id=',
//             'created_at' => $date
//         ]];
//         $pusher->trigger($channel, 'my-event', $data);
//     }
// }

// $channel = "private-user-$userId";
// Notification data
$data = [
    'notifications' => [
        [
            'user_id' => 2,
            'title' => 'New supplier officially accredited by TNE.',
            'message' => 'Supplier Name: ABC Corp',
            'type' => 'info',
            'is_read' => 0,
            'link' => 'view_supplier?vendor_id=VN000390',
            'created_at' => $date 
        ]
    ]
];

// 'user_id' => $purchasingmanager_userid,
//                 'title' => 'New Purchase Requisition -' . $pr_department,
//                 'message' => 'A new PR has been created: ' . $pr_number,
//                 'type' => 'info',
//                 'is_read' => 0,
//                 'link' => 'view_supplier?vendor_id=VN000390',
//                 'created_at' => date('Y-m-d H:i:s')

// Trigger Pusher event
$pusher->trigger('private-user-2', 'my-event', $data);
// 'private-user-' . $targetUserId
echo "Notification sent successfully!";
echo $data['notifications'][0]['message']; // Output the message for debugging
