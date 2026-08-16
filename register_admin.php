<?php
// =======================================================
// Admin Account Registration & Password Reset Utility
// =======================================================

require_once __DIR__ . '/config/functions.php';

$db = getDB();
$message = '';
$message_type = '';

// 1. Handle Create / Update Admin POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($password) < 4) {
        $message = 'Password must be at least 4 characters long.';
        $message_type = 'error';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Check if username already exists
            $stmt = $db->prepare("SELECT id, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing user to Admin with new password
                $stmt_up = $db->prepare("UPDATE users SET password = ?, email = ?, full_name = ?, role = 'admin', status = 'active' WHERE id = ?");
                $stmt_up->execute([$hash, $email, $full_name, $existing['id']]);
                $message = "Admin account '$username' updated with your new password! You can now log in.";
                $message_type = 'success';
            } else {
                // Insert brand new admin
                $stmt_in = $db->prepare("INSERT INTO users (username, email, password, role, full_name, status) VALUES (?, ?, ?, 'admin', ?, 'active')");
                $stmt_in->execute([$username, $email, $hash, $full_name]);
                $message = "New Admin account '$username' created successfully! You can now log in.";
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 2. Handle 1-Click Default Admin Reset POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['reset_default_admin'])) {
    try {
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin_exists = $stmt->fetch();

        if ($admin_exists) {
            $db->prepare("UPDATE users SET password = ?, status = 'active', role = 'admin' WHERE username = 'admin'")->execute([$hash]);
        } else {
            $db->prepare("INSERT INTO users (username, email, password, role, full_name, status) VALUES ('admin', 'admin@seait.edu', ?, 'admin', 'System Administrator', 'active')")->execute([$hash]);
        }
        $message = "Default Admin reset! Username: <b>admin</b> | Password: <b>password123</b>";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Reset error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch all existing admins
$admins = $db->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account Setup - SEAIT ENROLL</title>
    <!-- Tailwind CSS (Online CDN + Offline Fallback) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <script src="<?= url('assets/js/offline-icons.js') ?>"></script>
</head>
<body class="bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-950 min-h-screen text-slate-100 flex items-center justify-center p-4 sm:p-6">

    <div class="max-w-2xl w-full bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-10 space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center font-black text-2xl text-white shadow-xl shadow-purple-600/30">
                    ⚙️
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-white">Administrator Account Manager</h1>
                    <p class="text-xs text-purple-400 font-semibold">SEAIT ENROLL & Clearance System</p>
                </div>
            </div>
            <a href="login.php" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-all">
                &larr; Back to Login
            </a>
        </div>

        <!-- Alert Notification -->
        <?php if ($message): ?>
            <div class="p-4 rounded-xl text-xs sm:text-sm flex items-start gap-3 <?= $message_type === 'success' ? 'bg-emerald-950/70 border border-emerald-500/40 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/40 text-rose-300' ?>">
                <span><?= $message_type === 'success' ? '✅' : '❌' ?></span>
                <div class="flex-1"><?= $message ?></div>
            </div>
        <?php endif; ?>

        <!-- Quick 1-Click Default Reset Card -->
        <div class="bg-indigo-950/40 border border-indigo-500/30 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="font-bold text-white text-xs sm:text-sm">⚡ 1-Click Default Admin Recovery</div>
                <div class="text-xs text-slate-400 mt-0.5">
                    Resets username <code class="text-indigo-300 font-mono font-bold">admin</code> with password <code class="text-indigo-300 font-mono font-bold">password123</code>
                </div>
            </div>
            <form method="POST" action="register_admin.php" class="shrink-0">
                <button type="submit" name="reset_default_admin" class="w-full sm:w-auto px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
                    Reset to 'admin' / 'password123'
                </button>
            </form>
        </div>

        <!-- Create New Admin Form -->
        <div class="space-y-4 pt-2">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">
                Register or Update Admin Account
            </h2>

            <form method="POST" action="register_admin.php" class="space-y-4 text-xs">
                <input type="hidden" name="create_admin" value="1">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Username *</label>
                        <input type="text" name="username" required placeholder="e.g. admin or myadmin" value="admin" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white font-mono focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Email Address *</label>
                        <input type="email" name="email" required placeholder="admin@seait.edu" value="admin@seait.edu" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-300 mb-1">Full Name & Title</label>
                    <input type="text" name="full_name" required placeholder="System Administrator" value="System Administrator" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">New Password *</label>
                        <input type="password" name="password" required placeholder="Enter password" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1">Confirm Password *</label>
                        <input type="password" name="confirm_password" required placeholder="Confirm password" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-purple-500">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                    <a href="login.php" class="w-full sm:w-auto text-center px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800 transition-colors text-xs font-semibold">
                        Go to Login Page &rarr;
                    </a>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold shadow-lg shadow-purple-600/30 transition-all text-xs">
                        Save / Register Admin Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Admin Accounts List -->
        <div class="pt-4 border-t border-slate-800 space-y-2">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Admin Accounts in System:</div>
            <div class="space-y-1.5">
                <?php foreach ($admins as $adm): ?>
                    <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full <?= $adm['status'] === 'active' ? 'bg-emerald-400' : 'bg-rose-400' ?>"></span>
                            <span class="font-mono font-bold text-purple-300"><?= htmlspecialchars($adm['username']) ?></span>
                            <span class="text-slate-400">(<?= htmlspecialchars($adm['full_name']) ?>)</span>
                        </div>
                        <span class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($adm['email']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</body>
</html>
