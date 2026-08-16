<?php
// =======================================================
// Login & Student Account Registration Portal
// =======================================================

require_once __DIR__ . '/config/functions.php';

// If already logged in, redirect to index router
if (is_logged_in()) {
    header("Location: " . url('index.php'));
    exit;
}

$error = '';
$reg_error = '';
$active_tab = 'login'; // 'login' or 'register'

$db = getDB();
$active_term = get_active_term();
$programs = $db->query("SELECT * FROM programs ORDER BY code ASC")->fetchAll();

// =======================================================
// SECURITY: 5-ATTEMPT LOGIN LOCKOUT & 1-MINUTE TIMER
// =======================================================
$max_attempts = 5;
$lockout_duration = 60; // 60 seconds (1 minute)

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$now = time();
$is_locked = false;
$remaining_lockout = 0;

if (isset($_SESSION['lockout_until']) && $_SESSION['lockout_until'] > $now) {
    $is_locked = true;
    $remaining_lockout = $_SESSION['lockout_until'] - $now;
} elseif (isset($_SESSION['lockout_until']) && $_SESSION['lockout_until'] <= $now) {
    // Lockout expired, reset
    unset($_SESSION['lockout_until']);
    $_SESSION['login_attempts'] = 0;
}

// Handle Student Registration POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $active_tab = 'register';
    
    $full_name = trim($_POST['full_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    // Parse Full Name if single input provided
    if (!empty($full_name)) {
        $parts = preg_split('/\s+/', $full_name);
        if (count($parts) > 1) {
            $last_name = array_pop($parts);
            $first_name = implode(' ', $parts);
        } else {
            $first_name = $full_name;
            $last_name = '';
        }
    }

    $email = trim($_POST['email'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation - Full Name, Email, Mobile Number (Mandatory), Password
    if (empty($first_name) || empty($email) || empty($contact_no) || empty($password)) {
        $reg_error = 'Please fill in all basic requirements: Full Name, Email, Mobile Number (Mandatory), and Password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reg_error = 'Please provide a valid email address.';
    } elseif ($password !== $confirm_password) {
        $reg_error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $reg_error = 'Password must be at least 6 characters long.';
    } else {
        // Check uniqueness of email
        $stmt_check = $db->prepare("SELECT COUNT(*) as email_exists FROM users WHERE email = ?");
        $stmt_check->execute([$email]);
        $check = $stmt_check->fetch();

        if ($check['email_exists'] > 0) {
            $reg_error = 'Email address is already registered. Please sign in instead.';
        } else {
            try {
                $db->beginTransaction();

                // Auto-generate next Student Number: e.g. 2026-0006
                $stmt_max = $db->query("SELECT MAX(id) as max_id FROM students");
                $next_id = intval($stmt_max->fetch()['max_id'] ?? 0) + 1;
                $student_no = date('Y') . '-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
                $username = $student_no;

                // Ensure username uniqueness
                $chk_u = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $chk_u->execute([$username]);
                if ($chk_u->fetchColumn() > 0) {
                    $username = $student_no . '_' . rand(10, 99);
                }

                $combined_name = trim("$first_name $last_name");
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // 1. Create User Account
                $stmt_u = $db->prepare("INSERT INTO users (username, email, password, role, full_name, status) 
                                        VALUES (?, ?, ?, 'student', ?, 'active')");
                $stmt_u->execute([$username, $email, $password_hash, $combined_name]);
                $user_id = $db->lastInsertId();

                // 2. Create Student Profile (default to BSIT or BSCS, to be finalized in Step 1)
                $default_program = $db->query("SELECT id FROM programs WHERE code IN ('BSIT', 'BSCS') ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
                $stmt_s = $db->prepare("INSERT INTO students (user_id, student_no, first_name, middle_name, last_name, program_id, year_level, academic_status, contact_no, enrolling_semester, enrollment_status) 
                                        VALUES (?, ?, ?, '', ?, ?, 1, 'Regular', ?, '1st Semester', 'In Clearance')");
                $stmt_s->execute([$user_id, $student_no, $first_name, $last_name, $default_program, $contact_no]);
                $student_id = $db->lastInsertId();

                // 3. Initiate 5-Stage Clearance Pipeline
                init_student_clearance($student_id, $active_term['id']);

                $db->commit();

                // Reset failed attempts
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['lockout_until']);

                // Auto-login registered student
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'student';
                $_SESSION['full_name'] = $combined_name;
                $_SESSION['student_id'] = $student_id;
                $_SESSION['student_no'] = $student_no;
                $_SESSION['program_id'] = $default_program;

                log_activity('STUDENT_REGISTERED', "Student $combined_name ($student_no) created an account and initiated clearance.", $user_id);
                set_flash('success', "Welcome $combined_name! Your student account has been created (Student No: $student_no). Please complete your Step 1 Enrollment details on your dashboard.");

                header("Location: " . url('modules/student/dashboard.php'));
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $reg_error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

// Handle Form POST Login
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'login')) {
    $active_tab = 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($is_locked) {
        $error = "Security Lockout: Too many failed login attempts. Please wait {$remaining_lockout} second(s) before trying again.";
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $db->prepare("SELECT u.*, s.id as student_id, s.student_no, s.program_id, p.code as program_code
                              FROM users u 
                              LEFT JOIN students s ON u.id = s.user_id 
                              LEFT JOIN programs p ON s.program_id = p.id
                              WHERE (u.username = ? OR u.email = ?) AND u.status = 'active' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Reset security attempts on success
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['lockout_until']);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['student_no'] = $user['student_no'];
            $_SESSION['program_id'] = $user['program_id'];
            $_SESSION['program_code'] = $user['program_code'];

            log_activity('LOGIN_SUCCESS', "User {$user['username']} logged in successfully.", $user['id']);

            header("Location: " . url('index.php'));
            exit;
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            
            if ($_SESSION['login_attempts'] >= $max_attempts) {
                $_SESSION['lockout_until'] = time() + $lockout_duration;
                $_SESSION['login_attempts'] = 0;
                $is_locked = true;
                $remaining_lockout = $lockout_duration;
                $error = "Security Alert: 5 consecutive failed login attempts. Login access is locked for 1 minute.";
                log_activity('LOGIN_LOCKOUT', "Login locked for 60s due to 5 failed attempts for: $username");
            } else {
                $attempts_left = $max_attempts - $_SESSION['login_attempts'];
                $error = "Invalid username/email or password. {$attempts_left} attempt(s) remaining before a 1-minute security lockout.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEAIT ENROLL - Multi-Step Clearance & Enrollment Portal</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= url('assets/js/offline-icons.js') ?>"></script>
</head>
<body class="bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-950 min-h-screen flex items-center justify-center p-4 sm:p-6 text-slate-100">

    <div class="max-w-5xl w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        
        <!-- Left: Branding & Multi-Step Workflow Overview -->
        <div class="lg:col-span-5 space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-black text-2xl text-white shadow-xl shadow-indigo-600/30">
                    S
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white">SEAIT ENROLL</h1>
                    <div class="text-xs text-indigo-400 font-semibold uppercase tracking-wider">Multi-Step Clearance System</div>
                </div>
            </div>

            <div>
                <h2 class="text-3xl font-extrabold text-white leading-tight">
                    SEAIT ENROLL & Digital Clearance System
                </h2>
                <p class="text-xs sm:text-sm text-slate-400 mt-2">
                    South East Asian Institute of Technology (SEAIT) transparent 5-stage sequential signing pipeline connecting students, department advisers, library, accounting, and registrar in real time.
                </p>
            </div>

            <!-- Workflow Visual Mini-Cards -->
            <div class="space-y-2.5 text-xs">
                <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 p-3 rounded-xl">
                    <span class="w-6 h-6 rounded-lg bg-indigo-900/60 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center shrink-0">1</span>
                    <div class="text-slate-300"><strong class="text-white">Department Initial:</strong> Academic status & prerequisites validation</div>
                </div>
                <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 p-3 rounded-xl">
                    <span class="w-6 h-6 rounded-lg bg-indigo-900/60 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center shrink-0">2</span>
                    <div class="text-slate-300"><strong class="text-white">Library:</strong> Circulation & overdue book clearance</div>
                </div>
                <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 p-3 rounded-xl">
                    <span class="w-6 h-6 rounded-lg bg-indigo-900/60 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center shrink-0">3</span>
                    <div class="text-slate-300"><strong class="text-white">Accounting:</strong> Tuition assessment & downpayment validation</div>
                </div>
                <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 p-3 rounded-xl">
                    <span class="w-6 h-6 rounded-lg bg-indigo-900/60 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center shrink-0">4</span>
                    <div class="text-slate-300"><strong class="text-white">Registrar:</strong> Curriculum evaluation & subject load approval</div>
                </div>
                <div class="flex items-center gap-3 bg-slate-900/60 border border-slate-800 p-3 rounded-xl">
                    <span class="w-6 h-6 rounded-lg bg-indigo-900/60 border border-indigo-500/40 text-indigo-300 font-bold flex items-center justify-center shrink-0">5</span>
                    <div class="text-slate-300"><strong class="text-white">Department Final:</strong> Class section scheduling & official Certificate of Reg. (COR)</div>
                </div>
            </div>
        </div>

        <!-- Right: Auth Card (Switches between Sign In and Create Account) -->
        <div class="lg:col-span-7 bg-slate-900/80 backdrop-blur-2xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
            
            <!-- UPPER LEFT CARD SWITCHER BUTTON -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                <button type="button" id="card-switch-btn" onclick="toggleAuthCard()" class="px-3.5 py-1.5 rounded-xl bg-indigo-950/90 border border-indigo-500/50 hover:bg-indigo-900 text-indigo-300 hover:text-white text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
                    <i id="card-switch-icon" data-lucide="user-plus" class="w-4 h-4 text-indigo-400"></i>
                    <span id="card-switch-label">Create Account</span>
                </button>

                <div class="text-[11px] text-slate-400 font-medium">
                    Term: <span class="text-slate-200 font-semibold"><?= htmlspecialchars($active_term['academic_year']) ?></span>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- 1. SIGN IN FORM VIEW -->
            <!-- ============================================== -->
            <div id="signin-view" class="<?= $active_tab === 'register' ? 'hidden' : 'block' ?> space-y-5">
                <div>
                    <h3 class="text-xl font-extrabold text-white">Sign In to Your Account</h3>
                    <p class="text-xs text-slate-400 mt-1">Enter your credentials to access your portal</p>
                </div>

                <!-- Lockout Alert Banner -->
                <div id="lockout-alert-box" class="p-4 text-xs bg-amber-950/90 border border-amber-500/60 text-amber-200 rounded-2xl space-y-2.5 <?= $is_locked ? 'block' : 'hidden' ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-bold text-amber-300">
                            <i data-lucide="shield-alert" class="w-4 h-4 text-amber-400"></i>
                            <span>Login Access Temporarily Locked</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-md bg-amber-900/80 font-mono font-bold text-amber-300 text-[11px] flex items-center gap-1 border border-amber-500/30">
                            <i data-lucide="timer" class="w-3.5 h-3.5 text-amber-400"></i>
                            <span id="countdown-display"><?= $remaining_lockout ?></span>s
                        </span>
                    </div>
                    <p class="text-slate-300 text-[11px] leading-relaxed">
                        You have exceeded the maximum limit of <strong class="text-amber-300">5 failed login attempts</strong>. Form inputs are locked. Please wait for the timer to expire.
                    </p>
                    <div class="w-full bg-slate-900/90 h-1.5 rounded-full overflow-hidden border border-slate-700/60">
                        <div id="countdown-progress" class="bg-gradient-to-r from-amber-500 to-amber-300 h-full transition-all duration-1000" style="width: <?= $is_locked ? min(100, ($remaining_lockout / 60) * 100) : 100 ?>%;"></div>
                    </div>
                </div>

                <?php if ($error && !$is_locked): ?>
                    <div class="p-3 text-xs bg-rose-950/70 border border-rose-500/40 text-rose-300 rounded-xl flex items-center gap-2">
                        <span>❌</span>
                        <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success = get_flash('success')): ?>
                    <div class="p-3 text-xs bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 rounded-xl flex items-center gap-2">
                        <span>✅</span>
                        <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="signin-form" class="space-y-4">
                    <input type="hidden" name="action" value="login">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Username or Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <input type="text" id="signin-username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Enter username or email" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl py-2.5 pl-9 pr-3 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors <?= $is_locked ? 'opacity-50 cursor-not-allowed bg-slate-900' : '' ?>" <?= $is_locked ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Password</label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <input type="password" id="signin-password" name="password" required placeholder="••••••••" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl py-2.5 pl-9 pr-10 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors <?= $is_locked ? 'opacity-50 cursor-not-allowed bg-slate-900' : '' ?>" <?= $is_locked ? 'disabled' : '' ?>>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-400 transition-colors cursor-pointer select-none" onmousedown="showPassword('signin-password', this)" onmouseup="hidePassword('signin-password', this)" onmouseleave="hidePassword('signin-password', this)" ontouchstart="showPassword('signin-password', this)" ontouchend="hidePassword('signin-password', this)" title="Hold to view password">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="signin-submit-btn" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform hover:-translate-y-0.5 text-xs sm:text-sm flex items-center justify-center gap-2 <?= $is_locked ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>" <?= $is_locked ? 'disabled' : '' ?>>
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Sign In
                    </button>
                </form>

                <div class="text-center pt-2 space-y-1.5">
                    <p class="text-xs text-slate-400">
                        New student? 
                        <button type="button" onclick="toggleAuthCard('register')" class="text-indigo-400 hover:underline font-semibold ml-1">
                            Create a student account &rarr;
                        </button>
                    </p>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- 2. CREATE ACCOUNT FORM VIEW (STUDENT SIGN UP) -->
            <!-- ============================================== -->
            <div id="register-view" class="<?= $active_tab === 'register' ? 'block' : 'hidden' ?> space-y-4">
                <div>
                    <h3 class="text-xl font-extrabold text-white">Create Student Account</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Quick registration &bull; enter your basic requirements to launch your clearance</p>
                </div>

                <?php if ($reg_error): ?>
                    <div class="p-3 text-xs bg-rose-950/70 border border-rose-500/40 text-rose-300 rounded-xl flex items-center gap-2">
                        <span>❌</span>
                        <span class="font-medium"><?= htmlspecialchars($reg_error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="register">

                    <!-- Full Name -->
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1 flex items-center gap-1">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-indigo-400"></i>
                            <span>Full Name</span>
                            <span class="text-rose-400 font-bold">*</span>
                        </label>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="e.g. Juan Dela Cruz" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1 flex items-center gap-1">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-indigo-400"></i>
                            <span>Email Address</span>
                            <span class="text-rose-400 font-bold">*</span>
                        </label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="e.g. juan.delacruz@gmail.com" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>

                    <!-- Mobile Number (Mandatory) -->
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1 flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <i data-lucide="phone" class="w-3.5 h-3.5 text-indigo-400"></i>
                                <span>Mobile Number</span>
                                <span class="text-rose-400 font-bold">*</span>
                            </span>
                            <span class="text-[10px] text-amber-400 uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-amber-950/60 border border-amber-500/30">Mandatory</span>
                        </label>
                        <input type="tel" name="contact_no" required value="<?= htmlspecialchars($_POST['contact_no'] ?? '') ?>" placeholder="e.g. 0917-123-4567" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>

                    <!-- Password & Confirm Password -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-300 mb-1 flex items-center gap-1">
                                <i data-lucide="lock" class="w-3.5 h-3.5 text-indigo-400"></i>
                                <span>Password</span>
                                <span class="text-rose-400 font-bold">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="reg-password" name="password" required placeholder="Min 6 characters" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 pr-10 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-400 transition-colors cursor-pointer select-none" onmousedown="showPassword('reg-password', this)" onmouseup="hidePassword('reg-password', this)" onmouseleave="hidePassword('reg-password', this)" ontouchstart="showPassword('reg-password', this)" ontouchend="hidePassword('reg-password', this)" title="Hold to view password">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-300 mb-1 flex items-center gap-1">
                                <i data-lucide="lock" class="w-3.5 h-3.5 text-indigo-400"></i>
                                <span>Confirm Password</span>
                                <span class="text-rose-400 font-bold">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Confirm password" class="w-full bg-slate-950/60 border border-slate-700/80 rounded-xl p-3 pr-10 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors">
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-400 transition-colors cursor-pointer select-none" onmousedown="showPassword('reg-confirm-password', this)" onmouseup="hidePassword('reg-confirm-password', this)" onmouseleave="hidePassword('reg-confirm-password', this)" ontouchstart="showPassword('reg-confirm-password', this)" ontouchend="hidePassword('reg-confirm-password', this)" title="Hold to view password">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-indigo-950/40 border border-indigo-500/30 rounded-xl text-[11px] text-indigo-200 flex items-start gap-2">
                        <i data-lucide="info" class="w-3.5 h-3.5 text-indigo-400 shrink-0 mt-0.5"></i>
                        <span>Course selection (BSIT / BSCS), Year Level, Semester, Address, and Parent/Guardian information are completed in <strong>Step 1 Enrollment Form</strong> on your dashboard.</span>
                    </div>

                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-600/30 transition-all transform hover:-translate-y-0.5 text-xs sm:text-sm flex items-center justify-center gap-2 mt-2 cursor-pointer">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                        Complete Registration & Launch Clearance
                    </button>
                </form>

                <div class="text-center pt-1">
                    <p class="text-xs text-slate-400">
                        Already have an account? 
                        <button type="button" onclick="toggleAuthCard('login')" class="text-indigo-400 hover:underline font-semibold ml-1">
                            Sign In here &rarr;
                        </button>
                    </p>
                </div>
            </div>

        </div>

    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        function toggleAuthCard(forceView) {
            const signinView = document.getElementById('signin-view');
            const registerView = document.getElementById('register-view');
            const switchLabel = document.getElementById('card-switch-label');
            const switchIcon = document.getElementById('card-switch-icon');

            const isCurrentlySignIn = !signinView.classList.contains('hidden');
            const targetView = forceView || (isCurrentlySignIn ? 'register' : 'login');

            if (targetView === 'register') {
                signinView.classList.add('hidden');
                registerView.classList.remove('hidden');
                switchLabel.innerText = '← Sign In';
                switchIcon.setAttribute('data-lucide', 'arrow-left');
            } else {
                registerView.classList.add('hidden');
                signinView.classList.remove('hidden');
                switchLabel.innerText = 'Create Account';
                switchIcon.setAttribute('data-lucide', 'user-plus');
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Show/Hide Password on Hold
        function showPassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input) {
                input.type = 'text';
                const iconSize = inputId === 'signin-password' ? 'w-4 h-4' : 'w-3.5 h-3.5';
                btn.innerHTML = '<i data-lucide="eye-off" class="' + iconSize + ' text-indigo-400"></i>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

        function hidePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input && input.type === 'text') {
                input.type = 'password';
                const iconSize = inputId === 'signin-password' ? 'w-4 h-4' : 'w-3.5 h-3.5';
                btn.innerHTML = '<i data-lucide="eye" class="' + iconSize + '"></i>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

        // ========================================================
        // LIVE LOCKOUT COUNTDOWN & AUTO-UNLOCK TIMER
        // ========================================================
        let remainingLockout = <?= intval($remaining_lockout) ?>;
        const totalLockout = 60;
        let lockoutInterval = null;

        function startLockoutTimer(seconds) {
            remainingLockout = seconds;
            const alertBox = document.getElementById('lockout-alert-box');
            const countdownDisplay = document.getElementById('countdown-display');
            const countdownProgress = document.getElementById('countdown-progress');
            const usernameInput = document.getElementById('signin-username');
            const passwordInput = document.getElementById('signin-password');
            const submitBtn = document.getElementById('signin-submit-btn');

            if (remainingLockout <= 0) {
                unlockLoginForm();
                return;
            }

            if (alertBox) alertBox.classList.remove('hidden');
            if (usernameInput) {
                usernameInput.disabled = true;
                usernameInput.classList.add('opacity-50', 'cursor-not-allowed', 'bg-slate-900');
            }
            if (passwordInput) {
                passwordInput.disabled = true;
                passwordInput.classList.add('opacity-50', 'cursor-not-allowed', 'bg-slate-900');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }

            clearInterval(lockoutInterval);
            lockoutInterval = setInterval(() => {
                remainingLockout--;
                if (countdownDisplay) {
                    countdownDisplay.innerText = Math.max(0, remainingLockout);
                }
                if (countdownProgress) {
                    const pct = Math.max(0, (remainingLockout / totalLockout) * 100);
                    countdownProgress.style.width = pct + '%';
                }

                if (remainingLockout <= 0) {
                    clearInterval(lockoutInterval);
                    unlockLoginForm();
                }
            }, 1000);
        }

        function unlockLoginForm() {
            const alertBox = document.getElementById('lockout-alert-box');
            const usernameInput = document.getElementById('signin-username');
            const passwordInput = document.getElementById('signin-password');
            const submitBtn = document.getElementById('signin-submit-btn');

            if (usernameInput) {
                usernameInput.disabled = false;
                usernameInput.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-slate-900');
            }
            if (passwordInput) {
                passwordInput.disabled = false;
                passwordInput.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-slate-900');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }

            if (alertBox) {
                alertBox.className = "p-3.5 text-xs bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 rounded-2xl flex items-center gap-2.5 transition-all";
                alertBox.innerHTML = '<i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0"></i><span class="font-semibold text-emerald-200">Lockout period has ended. You may now enter your credentials to sign in.</span>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }

        // Auto-start lockout timer on page load if locked
        if (remainingLockout > 0) {
            document.addEventListener('DOMContentLoaded', () => {
                startLockoutTimer(remainingLockout);
            });
        }

        // Initialize state on page load if active_tab is register
        <?php if ($active_tab === 'register'): ?>
            document.addEventListener('DOMContentLoaded', () => {
                toggleAuthCard('register');
            });
        <?php endif; ?>
    </script>
</body>
</html>
