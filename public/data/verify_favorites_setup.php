<?php
require '../../init.php';

// This script verifies the favorites feature is properly set up
$checks = [
    'Database Connected' => false,
    'pr_favorites Table Exists' => false,
    'User Session Available' => false,
    'Favorites Table Readable' => false
];

try {
    // Check database connection
    if ($pdo) {
        $checks['Database Connected'] = true;
    }

    // Check if pr_favorites table exists
    $sql = "SHOW TABLES LIKE 'pr_favorites'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $checks['pr_favorites Table Exists'] = true;
    }

    // Check session
    if (isset($_SESSION['user_id'])) {
        $checks['User Session Available'] = true;
    }

    // Try to read from pr_favorites
    $sql = "SELECT COUNT(*) as count FROM pr_favorites LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $checks['Favorites Table Readable'] = true;

} catch (PDOException $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Favorites Feature - Setup Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; }
        .success { border-color: #28a745; background: #f0f9ff; }
        .error { border-color: #dc3545; background: #fff5f5; }
        .warning { border-color: #ffc107; background: #fffbf0; }
        .status { font-weight: bold; }
        .success .status { color: #28a745; }
        .error .status { color: #dc3545; }
        .warning .status { color: #ffc107; }
        button { padding: 10px 20px; margin-top: 10px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Favorites Feature - Setup Verification</h1>
    
    <?php if (isset($error)): ?>
        <div class="check error">
            <strong>Database Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="checks">
        <?php foreach ($checks as $name => $status): ?>
            <div class="check <?php echo $status ? 'success' : 'error'; ?>">
                <span class="status"><?php echo $status ? '✓' : '✗'; ?></span> <?php echo htmlspecialchars($name); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Setup Instructions</h2>
    <ol>
        <li>Ensure database is connected ✓</li>
        <li>If "pr_favorites Table Exists" is ✗, click "Create Favorites Table" below</li>
        <li>Login to ensure "User Session Available" shows ✓</li>
        <li>Once all checks pass, refresh your dashboard</li>
    </ol>

    <form method="POST">
        <button name="action" value="create_table" type="submit">Create/Verify Favorites Table</button>
    </form>

    <?php
    if ($_POST['action'] === 'create_table') {
        try {
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
            $pdo->exec($sql);
            echo '<div class="check success"><span class="status">✓</span> pr_favorites table created/verified successfully!</div>';
        } catch (Exception $e) {
            echo '<div class="check error"><span class="status">✗</span> Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    ?>
</body>
</html>
