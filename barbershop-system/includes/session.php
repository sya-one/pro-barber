<?php
session_start();

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    // Clear session and redirect to login with timeout message
    session_unset();
    session_destroy();
    
    // Redirect to login page at the root of the project
    $login_url = '/barbershop-system/login.php?timeout=1';
    header("Location: $login_url");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();