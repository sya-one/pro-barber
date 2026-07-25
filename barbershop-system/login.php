<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
$database = new Database();
$db = $database->getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid request.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Remember me
            if (!empty($_POST['remember'])) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, "/");
                $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
            }
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Redirect based on role
            switch ($user['role']) {
                case 'admin': header("Location: /admin/dashboard.php"); break;
                case 'barber': header("Location: /barbershop-system/barber/dashboard.php"); break;
                case 'receptionist': header("Location: /barbershop-system/reception/dashboard.php"); break;
            }
            exit;
        } else {
            $error = "Invalid credentials or account inactive.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/barbershop-system/assets/images/default-avatar.png">
    <title>Login | The Professional Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #000; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: rgba(27,27,27,0.85); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; width: 100%; max-width: 400px; color: white; border: 1px solid #0FA958; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <img src="/barbershop-system/assets/images/logo.png" width="80" alt="Logo">
        <h3>The Professional 🟢 Barbershop</h3>
    </div>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username" required>
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">Remember Me</label>
        </div>
        <button type="submit" class="btn btn-primary w-100" style="background:#0FA958; border:none;">Login</button>
    </form>
</div>
</body>
</html>