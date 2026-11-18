<?php
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username      = trim($_POST['username']);
    $password      = $_POST['password'];
    $full_name     = trim($_POST['full_name']);
    $role          = $_POST['role'];
    $position      = $_POST['position'];
    $department    = $_POST['department'];
    $dealer        = $_POST['dealer'];
    $is_depthead   = isset($_POST['is_depthead']) ? 1 : 0;

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Invalid username format.";
    } else {
        $user_signature = null;
        $user_pfp = null;

        if (isset($_FILES['user_signature']) && $_FILES['user_signature']['error'] === UPLOAD_ERR_OK) {
            $sig_dir = 'assets/images/signature/';
            $sig_name = basename($_FILES['user_signature']['name']);
            $sig_path = $sig_dir . uniqid() . '_' . $sig_name;

            if (!is_dir($sig_dir)) mkdir($sig_dir, 0777, true);

            move_uploaded_file($_FILES['user_signature']['tmp_name'], $sig_path);
            $user_signature = $sig_path;
        }

        if (isset($_FILES['user_pfp']) && $_FILES['user_pfp']['error'] === UPLOAD_ERR_OK) {
            $pfp_dir = 'assets/images/pfp/';
            $pfp_name = basename($_FILES['user_pfp']['name']);
            $pfp_path = $pfp_dir . uniqid() . '_' . $pfp_name;

            if (!is_dir($pfp_dir)) mkdir($pfp_dir, 0777, true);

            move_uploaded_file($_FILES['user_pfp']['tmp_name'], $pfp_path);
            $user_pfp = $pfp_path;
        }


        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users 
            (username, password, full_name, role, position, user_signature, user_pfp, department, dealer, is_depthead)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $username,
            $hashed,
            $full_name,
            $role,
            $position,
            $user_signature,
            $user_pfp,
            $department,
            $dealer,
            $is_depthead
        ]);

        if ($result) {
            $success = "Registered successfully.";
        } else {
            $error = "Registration failed. Username may already exist.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow rounded-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">User Registration</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php elseif (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input name="username" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input name="password" type="password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input name="full_name" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="">-- Select Role --</option>
                                    <option value="preparer">Preparer</option>
                                    <option value="checker">Checker</option>
                                    <option value="noter">Noter</option>
                                    <option value="approver">Approver</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Position</label>
                                <select name="position" class="form-select" required>
                                    <option value="">-- Select Position --</option>
                                    <option>Purchasing Staff</option>
                                    <option>Manager</option>
                                    <option>General Manager</option>
                                    <option>Administrator</option>
                                    <option>VP for Accounting</option>
                                    <option>SVP for Finance and Admin</option>
                                    <option>President</option>
                                    <option>Vice Chairman</option>
                                    <option>Chairman</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Department Name</label>
                                <input name="department" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dealer</label>
                                <select name="dealer" class="form-select" required>
                                    <option value="">-- Select Dealer --</option>
                                    <option value="TNE">TNE</option>
                                    <option value="TBK">TBK</option>
                                    <option value="TOT">TOT</option>
                                    <option value="TMR">TMR</option>
                                    <option value="TSJ">TSJ</option>
                                    <option value="OG">OG</option>
                                </select>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" name="is_depthead" class="form-check-input" id="isDepthead">
                                <label class="form-check-label" for="isDepthead">Is Department Head?</label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">User Signature (optional)</label>
                                <input type="file" name="user_signature" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Picture (optional)</label>
                                <input type="file" name="user_pfp" class="form-control" accept=".jpg,.jpeg,.png">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>