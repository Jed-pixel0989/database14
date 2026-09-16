<?php
// =======================================================
// Global Functions and Helpers
// =======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Build the application URL from the current request. This works from the
// domain root, an InfinityFree htdocs subdirectory, or a custom domain.
$is_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$protocol = $is_https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$base_path = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
if ($base_path === '.' || $base_path === '/') {
    $base_path = '';
}
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($protocol . $host . $base_path, '/') . '/');
}

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'] ?? null,
        'username'      => $_SESSION['username'] ?? '',
        'email'         => $_SESSION['email'] ?? '',
        'role'          => $_SESSION['role'] ?? '',
        'full_name'     => $_SESSION['full_name'] ?? '',
        'department_id' => $_SESSION['department_id'] ?? null,
        'student_id'    => $_SESSION['student_id'] ?? null,
        'student_no'    => $_SESSION['student_no'] ?? null,
        'program_id'    => $_SESSION['program_id'] ?? null,
        'program_code'  => $_SESSION['program_code'] ?? null,
    ];
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header("Location: " . url('login.php'));
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed_roles)) {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo '<div class="max-w-xl mx-auto mt-20 text-center card p-8">';
        echo '<div class="text-6xl mb-4">🚫</div>';
        echo '<h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Access Denied</h1>';
        echo '<p class="text-slate-600 dark:text-slate-400 mb-6">You do not have permission to view this department page. Your role: <strong class="capitalize font-semibold text-indigo-600">' . htmlspecialchars($role) . '</strong></p>';
        echo '<a href="' . url('index.php') . '" class="btn-primary inline-flex items-center gap-2">Return to My Dashboard</a>';
        echo '</div>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function get_active_term() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM academic_terms WHERE is_active = 1 LIMIT 1");
    $term = $stmt->fetch();
    if (!$term) {
        // Fallback default
        return [
            'id' => 1,
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'is_active' => 1
        ];
    }
    return $term;
}

function log_activity($action, $details = null, $user_id = null) {
    try {
        $db = getDB();
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Ignore log fail silently
    }
}

function get_student_clearance_record($student_id, $term_id = null) {
    $db = getDB();
    if (!$term_id) {
        $active_term = get_active_term();
        $term_id = $active_term['id'];
    }

    $stmt = $db->prepare("SELECT cr.*, s.student_no, s.first_name, s.middle_name, s.last_name, s.birth_date, s.age, s.sex, s.religion, s.civil_status, s.birth_place, s.address,
                                 s.guardian_name, s.guardian_contact, s.enrolling_semester, s.contact_no,
                                 s.year_level, s.academic_status, s.enrollment_status, s.program_id,
                                 p.code as program_code, p.name as program_name, d.name as department_name, d.id as department_id
                          FROM clearance_requests cr
                          JOIN students s ON cr.student_id = s.id
                          JOIN programs p ON s.program_id = p.id
                          JOIN departments d ON p.department_id = d.id
                          WHERE cr.student_id = ? AND cr.academic_term_id = ?
                          LIMIT 1");
    $stmt->execute([$student_id, $term_id]);
    $clearance = $stmt->fetch();

    if ($clearance) {
        // Fetch all 5 stages
        $stmt_stages = $db->prepare("SELECT * FROM clearance_stages WHERE clearance_id = ? ORDER BY step_number ASC");
        $stmt_stages->execute([$clearance['id']]);
        $clearance['stages'] = $stmt_stages->fetchAll();
    }

    return $clearance;
}

function init_student_clearance($student_id, $term_id) {
    $db = getDB();
    
    // Check if clearance already exists
    $stmt = $db->prepare("SELECT id FROM clearance_requests WHERE student_id = ? AND academic_term_id = ?");
    $stmt->execute([$student_id, $term_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        return $existing['id'];
    }

    // Create main clearance request
    $stmt = $db->prepare("INSERT INTO clearance_requests (student_id, academic_term_id, current_step, overall_status) VALUES (?, ?, 1, 'In Progress')");
    $stmt->execute([$student_id, $term_id]);
    $clearance_id = $db->lastInsertId();

    // Define 5 Steps
    $stages = [
        [1, 'dept_initial', 'Department Initial Clearance'],
        [2, 'library', 'Library Clearance'],
        [3, 'accounting', 'Accounting & Financial Clearance'],
        [4, 'registrar', 'Registrar Subject Load Evaluation'],
        [5, 'dept_final', 'Department Advising & Scheduling']
    ];

    $stmt_stage = $db->prepare("INSERT INTO clearance_stages (clearance_id, step_number, stage_code, stage_title, status) VALUES (?, ?, ?, ?, 'Pending')");
    foreach ($stages as $st) {
        $stmt_stage->execute([$clearance_id, $st[0], $st[1], $st[2]]);
    }

    log_activity('CLEARANCE_INITIATED', "Clearance Request #$clearance_id created for student ID $student_id", $_SESSION['user_id'] ?? null);

    return $clearance_id;
}

function format_currency($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function format_date($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

function get_status_badge($status) {
    $status = trim($status);
    switch (strtolower($status)) {
        case 'cleared':
        case 'completed':
        case 'enrolled':
        case 'resolved':
        case 'fully paid':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>' . htmlspecialchars($status) . '</span>';
        case 'in progress':
        case 'partial downpayment':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>' . htmlspecialchars($status) . '</span>';
        case 'pending':
        case 'unpaid':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700"><span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>' . htmlspecialchars($status) . '</span>';
        case 'flagged':
        case 'action required':
        case 'open':
        case 'rejected':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>' . htmlspecialchars($status) . '</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">' . htmlspecialchars($status) . '</span>';
    }
}

function set_flash($key, $message) {
    $_SESSION['flash_' . $key] = $message;
}

function get_flash($key) {
    if (isset($_SESSION['flash_' . $key])) {
        $msg = $_SESSION['flash_' . $key];
        unset($_SESSION['flash_' . $key]);
        return $msg;
    }
    return null;
}
