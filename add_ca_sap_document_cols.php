<?php
require 'includes/db.php';

$columns = $pdo->query("SHOW COLUMNS FROM cash_advance")->fetchAll(PDO::FETCH_COLUMN);

$newColumns = [
    'sap_document_number' => "ALTER TABLE cash_advance ADD sap_document_number VARCHAR(255) NULL AFTER po_amount",
    'sap_document_by' => "ALTER TABLE cash_advance ADD sap_document_by VARCHAR(255) NULL AFTER sap_document_number",
    'sap_document_at' => "ALTER TABLE cash_advance ADD sap_document_at DATETIME NULL AFTER sap_document_by",
];

foreach ($newColumns as $column => $sql) {
    if (!in_array($column, $columns, true)) {
        $pdo->query($sql);
        echo "Successfully added {$column} column.\n";
    } else {
        echo "{$column} column already exists.\n";
    }
}
