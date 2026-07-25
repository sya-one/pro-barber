<?php
require_once '../config/database.php';
session_start();
$db = (new Database())->getConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $full_name = trim($_POST['full_name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if customer exists by email
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        // Update password
        $stmt = $db->prepare("UPDATE customers SET password = ?, full_name = ?, phone = ? WHERE email = ?");
        $stmt->execute([$password, $full_name, $phone, $email]);
        $msg = "Password set successfully. You can now <a href='login.php'>log in</a>.";
    } else {
        // Insert new customer with password
        $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $email, $phone, $password]);
        $msg = "Account created. You can now <a href='login.php'>log in</a>.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Registration | The Professional Barbershop</title>
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: #000; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { background: #1B1B1B; color: white; border: 1px solid #0FA958; }
    </style>
</head>
<body>
<div class="card p-4" style="width: 400px;">
    <h4 class="text-center mb-3">Customer Registration</h4>
    <?php if ($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>
    <form method="post">
        <input type="text" name="full_name" class="form-control mb-2" placeholder="Full Name" required>
        <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
        <input type="text" name="phone" class="form-control mb-2" placeholder="Phone">
        <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
        <button type="submit" class="btn btn-success w-100">Register / Set Password</button>
    </form>
    <p class="mt-3 text-center"><a href="login.php">Already have a password? Log in</a></p>
</div>
</body>
</html>