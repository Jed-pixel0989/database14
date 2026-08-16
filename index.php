<?php
// =======================================================
// Main Entrypoint / Role Router
// =======================================================

require_once __DIR__ . '/config/functions.php';

if (!is_logged_in()) {
    header("Location: " . url('login.php'));
    exit;
}

$role = $_SESSION['role'] ?? '';

switch ($role) {
    case 'student':
        header("Location: " . url('modules/student/dashboard.php'));
        break;
    case 'department':
        header("Location: " . url('modules/department/initial_queue.php'));
        break;
    case 'library':
        header("Location: " . url('modules/library/clearance_queue.php'));
        break;
    case 'accounting':
        header("Location: " . url('modules/accounting/clearance_queue.php'));
        break;
    case 'registrar':
        header("Location: " . url('modules/registrar/clearance_queue.php'));
        break;
    case 'admin':
        header("Location: " . url('modules/admin/dashboard.php'));
        break;
    default:
        header("Location: " . url('login.php'));
        break;
}
exit;
