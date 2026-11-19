<?php
require '../includes/db.php';
require '../includes/helpers.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit;
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    list($selector, $validator) = explode(':', $_COOKIE['rememberme']);

    $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
    $stmt->execute([$selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
        // Valid token — restore session
        $_SESSION['user_id'] = $token['user_id'];

        // Optional: fetch and store username
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$token['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['username'] = $user['username'] ?? 'Unknown';

        header("Location: dashboard");
        exit;
    } else {
        // Invalid or expired token
        setcookie('rememberme', '', time() - 3600, '/', '', true, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT user_id, username, full_name, role, department, dealer, password, position FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department'] = $user['department']; 
        $_SESSION['position'] = $user['position'];
        $_SESSION['dealer'] = $user['dealer'];

        if (!empty($_POST['remember'])) {
            $selector = bin2hex(random_bytes(6));
            $validator = bin2hex(random_bytes(32));
            $hashed_validator = hash('sha256', $validator);
            $expires = date('Y-m-d H:i:s', time() + 864000);

            $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires)
                VALUES (?, ?, ?, ?)")
                ->execute([$user['user_id'], $selector, $hashed_validator, $expires]);

            setcookie('rememberme', "$selector:$validator", time() + 864000, '/', '', true, true);
        }

        $user_id = (int) $user['user_id'];

if ($user_id == 95 || $user_id == 86) {
    header("Location: add_budget_code");
    exit;
} elseif (
    strtolower($user['position']) === 'accounting' || 
    in_array($user_id, range(97, 101))
) {
    header("Location: add_budget_code_aff");
    exit;
} else {
    header("Location: dashboard");
    exit;
}
        exit;
    } else {
        $login_error = "Invalid login credentials.";
    }
}




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f5f7">
    <title>Sign in | OG Procurement System</title>
    <link rel="icon" href="assets/images/icon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="login-shell">
        <div class="login-card">
            <div class="login-brand">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="Oben Group Procurement System">
                </div>
            </div>

            <section class="login-content" aria-labelledby="login-title">
                <header>
                    <h1 id="login-title">Sign in</h1>
                    <p>Use your procurement account to continue.</p>
                </header>

                <?php if (!empty($login_error)): ?>
                    <p class="login-error" role="alert"><?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form method="post">
                    <div class="login-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username"
                            autocapitalize="none" spellcheck="false" required
                            value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-password">
                            <input id="password" name="password" type="password" autocomplete="current-password" required>
                            <button id="password-toggle" type="button" aria-controls="password" hidden>Show password</button>
                        </div>
                    </div>

                    <label class="login-remember">
                        <input name="remember" type="checkbox" <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
                        Remember me
                    </label>

                    <button class="login-submit" type="submit">Sign in</button>
                </form>
            </section>
        </div>
    </main>

    <script>
        const password = document.getElementById('password');
        const passwordToggle = document.getElementById('password-toggle');

        passwordToggle.hidden = false;
        passwordToggle.addEventListener('click', () => {
            const showPassword = password.type === 'password';
            password.type = showPassword ? 'text' : 'password';
            passwordToggle.textContent = showPassword ? 'Hide password' : 'Show password';
        });
    </script>
</body>
</html>
