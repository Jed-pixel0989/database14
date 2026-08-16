<?php
// =======================================================
// Logout Handler
// =======================================================

require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    log_activity('LOGOUT', "User logged out", $_SESSION['user_id'] ?? null);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: " . url('login.php'));
exit;
