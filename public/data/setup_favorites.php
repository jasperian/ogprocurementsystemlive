<?php
require '../../init.php';

// This script creates the pr_favorites table if it doesn't exist
$sql = "
CREATE TABLE IF NOT EXISTS pr_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pr_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, pr_number),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)
";

try {
    $pdo->exec($sql);
    echo json_encode(['success' => true, 'message' => 'pr_favorites table created/verified']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
