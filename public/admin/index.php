<?php
include '../../includes/db.php';
require '../../includes/helpers.php';
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

        header("Location: admin-dashboard");
        exit;
    } else {
        // Invalid or expired token
        setcookie('rememberme', '', time() - 3600, '/', '', true, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT user_id, username, full_name, role, department, dealer, password, position FROM users WHERE username = ? AND is_active = 1 AND position = 'Administrator'");
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

        header("Location: ../admin_dashboard");
        exit;
    } else {
        $login_error = "Invalid login credentials.";
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<!-- [Head] start -->

<head>
    <title>Login | OGPurchasing</title>
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Able Pro is trending dashboard template made using Bootstrap 5 design framework. Able Pro is available in Bootstrap, React, CodeIgniter, Angular,  and .net Technologies.">
    <meta name="keywords" content="Bootstrap admin template, Dashboard UI Kit, Dashboard Template, Backend Panel, react dashboard, angular dashboard">
    <meta name="author" content="Phoenixcoded">

    <!-- [Favicon] icon -->
    <link rel="icon" href="../assets/images/favicon.svg" type="image/x-icon"> <!-- [Font] Family -->
    <link rel="stylesheet" href="../assets/fonts/inter/inter.css" id="main-font-link" />
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css">
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="../assets/fonts/feather.css">
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css">
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="../assets/fonts/material.css">
    <!-- [Template CSS Files] -->
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link">
    <link rel="stylesheet" href="../assets/css/style-preset.css">
</head>
<!-- [Head] end -->
<!-- [Body] Start -->

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <div class="auth-main">
        
        <div class="auth-wrapper v1">
            
            <div class="auth-form">
            <img src="../assets/images/logo.png" class="img-fluid logo-md" alt="logo" style="width: 300px;"/>

                <div class="card my-5">
                    <div class="card-body">
                        <div class="text-center" hidden>
                            <a href="#"><img src="../assets/images/logo-dark.svg" alt="img"></a>
                            <div class="d-grid my-3">
                                <button type="button" class="btn mt-2 btn-light-primary bg-light text-muted">
                                    <img src="../assets/images/authentication/facebook.svg" alt="img"> <span>
                                        Sign In with Facebook</span>
                                </button>
                                <button type="button" class="btn mt-2 btn-light-primary bg-light text-muted">
                                    <img src="../assets/images/authentication/twitter.svg" alt="img"> <span> Sign
                                        In with Twitter</span>
                                </button>
                                <button type="button" class="btn mt-2 btn-light-primary bg-light text-muted">
                                    <img src="../assets/images/authentication/google.svg" alt="img"> <span> Sign
                                        In with Google</span>
                                </button>
                            </div>
                        </div>
                        <div class="saprator my-3" hidden>
                            <span>OR</span>
                        </div>



                        <h4 class="text-center f-w-500 mb-3">Login with your username</h4>
                        <?php if (!empty($login_error)): ?>
                            <div class="error-message text-center"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="form-group mb-3">
                                <input type="text" class="form-control" id="floatingInput" placeholder="Username" name="username" required>
                            </div>
                            <div class="form-group mb-3">
                                <input type="password" class="form-control" id="floatingInput1" placeholder="Password" name="password" required>
                            </div>
                            <div class="d-flex mt-1 justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input input-primary" type="checkbox" id="customCheckc1" name="remember">
                                    <label class="form-check-label text-muted" for="customCheckc1">Remember me?</label>
                                </div>
                                <h6 class="text-secondary f-w-400 mb-0" hidden>Forgot Password?</h6>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- <form method="post">
        <input name="username" placeholder="Username" required>
        <input name="password" type="password" placeholder="Password" required>
        <label><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit">Login</button>
    </form> -->

    <!-- [ Main Content ] end -->
    <!-- Required Js -->
    <script src="assets/js/plugins/popper.min.js"></script>
    <script src="assets/js/plugins/simplebar.min.js"></script>
    <script src="assets/js/plugins/bootstrap.min.js"></script>
    <script src="assets/js/fonts/custom-font.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/plugins/feather.min.js"></script>
    <script>
        change_box_container('false');
    </script>
    <script>
        layout_caption_change('true');
    </script>
    <script>
        layout_rtl_change('false');
    </script>
    <script>
        preset_change("preset-1");
    </script>
</body>
<!-- [Body] end -->

</html>