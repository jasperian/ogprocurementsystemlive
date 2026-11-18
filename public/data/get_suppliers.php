<?php
include '../../includes/db.php';

try {
    $stmt = $pdo->query("SELECT supplier_id, supplier_name, contact_person, contact_number, payment_terms, mobile_number FROM suppliers WHERE is_active = 1 AND is_approved = 1 AND is_rejected = 0 ORDER BY supplier_name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($suppliers);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
