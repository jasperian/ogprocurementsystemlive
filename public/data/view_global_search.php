<?php
include '../../includes/db.php';
header("Content-Type: text/html; charset=UTF-8");
session_start();

$dealer = $_SESSION['dealer'] ?? '';
$keyword = $_GET['q'] ?? '';
$results = [];

if (!empty($keyword)) {
    // 1. Search Suppliers
    $sql = "SELECT supplier_id, supplier_name, vendor_id, contact_number, tin_number FROM suppliers 
            WHERE supplier_name LIKE :keyword OR contact_person LIKE :keyword OR tin_number LIKE :keyword OR vendor_id LIKE :keyword";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => "%$keyword%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'Supplier',
            'name' => $row['supplier_name'],
            'link' => "view_supplier?vendor_id=" . $row['vendor_id']
        ];
    }

    // 2. Search Items
    $sql = "SELECT i.item_id, i.item_name, s.supplier_name 
            FROM items i 
            JOIN suppliers s ON i.supplier_id = s.supplier_id 
            WHERE i.item_name LIKE :keyword OR i.description LIKE :keyword";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => "%$keyword%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'Item',
            'name' => $row['item_name'],
            'link' => "items",
        ];
    }

    // 3. Search PRs
    $sql = "SELECT pr_id, pr_number, pr_status 
        FROM purchase_requisition 
        WHERE pr_dealer = :dealer
          AND (pr_number LIKE :keyword OR pr_status LIKE :keyword)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'dealer'  => $dealer,
        'keyword' => "%$keyword%"
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'Purchase Requisition',
            'name' => "PR #: " . $row['pr_number'],
            'link' => "view_purchase_requisition?pr_number=" . $row['pr_number'],
        ];
    }
     // 3. Search PRs
    // $sql = "SELECT pr_id, pr_number, pr_status FROM purchase_requisition 
    //         WHERE pr_number LIKE :keyword OR pr_status LIKE :keyword";
    // $stmt = $pdo->prepare($sql);
    // $stmt->execute(['keyword' => "%$keyword%"]);
    // while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    //     $results[] = [
    //         'type' => 'Purchase Requisition',
    //         'name' => "PR #: " . $row['pr_number'],
    //         'link' => "view_purchase_requisition?pr_number=" . $row['pr_number'],
    //     ];
    // }

    // 4. PO
    $sql = "SELECT po_id, po_number, pr_number FROM purchase_order 
            WHERE po_number LIKE :keyword";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => "%$keyword%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'Purchase Order',
            'name' => "PO #: " . $row['po_number'],
            'link' => "view_purchase_requisition?pr_number=" . $row['pr_number'],
        ];
    }

    // 5. canvass
    $sql = "SELECT batch_id, pr_number FROM canvass_sheet_batches 
            WHERE pr_number LIKE :keyword";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => "%$keyword%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'type' => 'Canvass',
            'name' => "Canvass #: " . $row['pr_number'],
            'link' => "canvass_view?pr_number=" . $row['pr_number'],
        ];
    }
}

// Output results
foreach ($results as $r) {
    echo "<li>";
    echo "<a href='{$r['link']}'>" . htmlspecialchars($r['name']) . "</a>";
    echo "<small>" . htmlspecialchars($r['type']) . "</small>";
    echo "</li>";
}
