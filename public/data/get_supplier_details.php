<?php
include '../../includes/db.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT contact_person, contact_number, payment_terms 
                       FROM suppliers 
                       WHERE supplier_id = ?");
$stmt->execute([$id]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));