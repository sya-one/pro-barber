<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
session_start();

$db = (new Database())->getConnection();
$error = '';
$step = 'login'; // stages: login, setup_2fa, verify_2fa

// If user already fully logged in, redirect to dashboard
if (isset($_SESSION['customer_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle TOTP setup form submission (first-time setup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_2fa'])) {
    $customer_id = $_SESSION['pending_customer_id'] ?? 0;
    if (!$customer_id) die('Invalid session');

    $code = trim($_POST['totp_code']);
    $secret = $_SESSION['pending_totp_secret'] ?? '';

    require_once '../libs/PHPGangsta/GoogleAuthenticator.php';
    $ga = new PHPGangsta_GoogleAuthenticator();
    $checkResult = $ga->verifyCode($secret, $code, 2); // 2 = 60 seconds tolerance

    if ($checkResult) {
        // Save secret and enable 2FA
        $stmt = $db->prepare("UPDATE customers SET totp_secret = ?, two_factor_enabled = 1 WHERE id = ?");
        $stmt->execute([$secret, $customer_id]);

        // Fully log in
        $stmt = $db->prepare("SELECT full_name FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $cust = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_name'] = $cust['full_name'];
        unset($_SESSION['pending_customer_id'], $_SESSION['pending_totp_secret']);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Invalid verification code. Please try again.';
        $step = 'setup_2fa';
    }
}

// Handle TOTP verification form (existing 2FA user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $customer_id = $_SESSION['pending_customer_id'] ?? 0;
    if (!$customer_id) die('Invalid session');

    $code = trim($_POST['totp_code']);

    $stmt = $db->prepare("SELECT totp_secret, full_name FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cust || empty($cust['totp_secret'])) {
        die('2FA not set up.');
    }

    require_once '../libs/PHPGangsta/GoogleAuthenticator.php';
    $ga = new PHPGangsta_GoogleAuthenticator();
    $checkResult = $ga->verifyCode($cust['totp_secret'], $code, 2);

    if ($checkResult) {
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_name'] = $cust['full_name'];
        unset($_SESSION['pending_customer_id']);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Invalid verification code. Please try again.';
        $step = 'verify_2fa';
    }
}

// Handle initial login (email/password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, full_name, password, totp_secret, two_factor_enabled FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cust && password_verify($password, $cust['password'])) {
        if (!empty($cust['totp_secret']) && $cust['two_factor_enabled']) {
            // 2FA already set up → ask for code
            $_SESSION['pending_customer_id'] = $cust['id'];
            $step = 'verify_2fa';
        } else {
            // First login → redirect to 2FA setup
            require_once '../libs/PHPGangsta/GoogleAuthenticator.php';
            $ga = new PHPGangsta_GoogleAuthenticator();
            $secret = $ga->createSecret();
            $_SESSION['pending_customer_id'] = $cust['id'];
            $_SESSION['pending_totp_secret'] = $secret;
            $step = 'setup_2fa';
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login | The Professional Barbershop</title>
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #000; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { background: #1B1B1B; color: white; border: 1px solid #0FA958; width: 400px; }
    </style>
</head>
<body>
<div class="card p-4">
    <h4 class="text-center mb-3">Customer Login</h4>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <?php if ($step === 'login'): ?>
        <!-- Standard email/password form -->
        <form method="post">
            <input type="hidden" name="login" value="1">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Log In</button>
        </form>

    <?php elseif ($step === 'setup_2fa'): ?>
        <!-- First-time 2FA setup -->
        <h5>Set Up Two-Factor Authentication</h5>
        <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
        <?php
            require_once '../libs/PHPGangsta/GoogleAuthenticator.php';
            $ga = new PHPGangsta_GoogleAuthenticator();
            $qrUrl = $ga->getQRCodeGoogleUrl('The Professional Barbershop', $_SESSION['pending_totp_secret']);
        ?>
        <div class="text-center mb-3">
            <img src="<?= $qrUrl ?>" alt="QR Code" style="max-width: 200px;">
        </div>
        <p>Or enter this secret manually: <code><?= $_SESSION['pending_totp_secret'] ?></code></p>
        <form method="post">
            <input type="hidden" name="setup_2fa" value="1">
            <div class="mb-3">
                <input type="text" name="totp_code" class="form-control" placeholder="Enter 6-digit code" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Verify & Enable</button>
        </form>

    <?php elseif ($step === 'verify_2fa'): ?>
        <!-- Existing 2FA user verification -->
        <h5>Two-Factor Authentication</h5>
        <p>Enter the code from your authenticator app.</p>
        <form method="post">
            <input type="hidden" name="verify_2fa" value="1">
            <div class="mb-3">
                <input type="text" name="totp_code" class="form-control" placeholder="6-digit code" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Verify</button>
        </form>
    <?php endif; ?>

    <p class="mt-3 text-center"><a href="register.php">Set password / Register</a></p>
</div>
</body>
</html>