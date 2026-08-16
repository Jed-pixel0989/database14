<?php
// =======================================================
// One-Click Database Setup & System Installer
// =======================================================

define('SETUP_DB_HOST', 'localhost');
define('SETUP_DB_USER', 'root');
define('SETUP_DB_PASS', '');
define('SETUP_DB_NAME', 'enrollment_db');
define('SETUP_DB_PORT', 3306);

$messages = [];
$success = false;
$sql_file = __DIR__ . '/database/database.sql';

if (isset($_POST['install']) || isset($_GET['auto']) || php_sapi_name() === 'cli') {
    try {
        // Connect to MySQL server without database
        $pdo = new PDO("mysql:host=" . SETUP_DB_HOST . ";port=" . SETUP_DB_PORT . ";charset=utf8mb4", SETUP_DB_USER, SETUP_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $messages[] = ['type' => 'success', 'text' => 'Connected to MySQL server successfully.'];

        // 1. Clean drop and recreate database to clear any stale data
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP DATABASE IF EXISTS `" . SETUP_DB_NAME . "`");
        $pdo->exec("CREATE DATABASE `" . SETUP_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . SETUP_DB_NAME . "`");

        if (!file_exists($sql_file)) {
            throw new Exception("SQL file not found at: " . $sql_file);
        }

        $sql_content = file_get_contents($sql_file);

        // Strip single line comments
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sql_content));
        $clean_sql = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '--') === 0) {
                continue;
            }
            $clean_sql .= $line . "\n";
        }

        // Split queries by semicolon followed by newline/end of block
        $queries = preg_split('/;\s*[\r\n]+/m', $clean_sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $messages[] = ['type' => 'success', 'text' => 'Database `enrollment_db` created and tables imported with seed data successfully!'];
        $messages[] = ['type' => 'success', 'text' => '6 Role Accounts, 19 Subjects, 16 Class Schedules, and 5 Sample Students generated!'];
        $success = true;

    } catch (Exception $e) {
        $messages[] = ['type' => 'error', 'text' => 'Installation Failed: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS (Online CDN + Offline Fallback) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/offline-icons.js"></script>
</head>
<body class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 min-h-screen text-slate-100 flex items-center justify-center p-4 sm:p-6">

    <div class="max-w-3xl w-full bg-slate-800/80 backdrop-blur-xl border border-slate-700/80 rounded-2xl shadow-2xl p-6 sm:p-10">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/30 border border-indigo-500/40 text-indigo-400 mb-4 shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">SEAIT ENROLL & Clearance System</h1>
            <p class="text-slate-400 mt-2 text-sm">South East Asian Institute of Technology &bull; Automated Database Installer</p>
        </div>

        <!-- Notification Alerts -->
        <?php foreach ($messages as $msg): ?>
            <div class="mb-4 p-4 rounded-xl text-sm flex items-start gap-3 <?= $msg['type'] === 'success' ? 'bg-emerald-950/60 border border-emerald-500/40 text-emerald-300' : 'bg-rose-950/60 border border-rose-500/40 text-rose-300' ?>">
                <span class="text-lg"><?= $msg['type'] === 'success' ? '✅' : '❌' ?></span>
                <div class="flex-1 font-medium"><?= htmlspecialchars($msg['text']) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <!-- Success State & Credentials Info -->
            <div class="bg-indigo-950/40 border border-indigo-500/30 rounded-xl p-5 mb-6">
                <h2 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                    <span class="text-indigo-400">⚡</span> Quick Demo Test Accounts (Password for all: <code class="bg-indigo-900/80 px-2 py-0.5 rounded text-indigo-200">password123</code>)
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-indigo-300 text-sm">1. Department Dean (CCS)</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">dept_ccs</code></div>
                        <div class="text-slate-400">Role: Signs Step 1 & Step 5 (Scheduling)</div>
                    </div>
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-indigo-300 text-sm">2. Library Officer</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">librarian</code></div>
                        <div class="text-slate-400">Role: Signs Step 2 (Book Clearance)</div>
                    </div>
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-indigo-300 text-sm">3. Accounting Officer</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">accounting</code></div>
                        <div class="text-slate-400">Role: Signs Step 3 (Tuition & Fees)</div>
                    </div>
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-indigo-300 text-sm">4. Chief Registrar</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">registrar</code></div>
                        <div class="text-slate-400">Role: Signs Step 4 (Subject Load Eval)</div>
                    </div>
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-emerald-400 text-sm">5. Student (Step 1 Pending)</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">2026-0001</code> (Juan Dela Cruz)</div>
                        <div class="text-slate-400">Track full 5-step progress live</div>
                    </div>
                    <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/60">
                        <div class="font-bold text-purple-400 text-sm">6. Master Administrator</div>
                        <div class="text-slate-400">Username: <code class="text-white font-mono">admin</code></div>
                        <div class="text-slate-400">Full control & curriculum management</div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="login.php" class="w-full sm:w-auto text-center px-6 py-3.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/25 transition-all transform hover:-translate-y-0.5">
                    Launch Login Portal &rarr;
                </a>
                <form method="POST" class="inline">
                    <button type="submit" name="install" class="w-full sm:w-auto px-6 py-3.5 bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold rounded-xl transition-all">
                        Re-run Setup & Fresh Reset
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- Pre-Install Form -->
            <div class="bg-slate-900/60 border border-slate-700/60 rounded-xl p-6 mb-6">
                <h2 class="text-base font-semibold text-white mb-2">Target Database Configuration:</h2>
                <ul class="space-y-1 text-sm text-slate-300 font-mono">
                    <li><span class="text-slate-500">Host:</span> localhost:3306</li>
                    <li><span class="text-slate-500">User:</span> root</li>
                    <li><span class="text-slate-500">Password:</span> (blank default)</li>
                    <li><span class="text-slate-500">Database Name:</span> enrollment_db</li>
                </ul>
                <p class="text-xs text-slate-400 mt-4">
                    Clicking install will automatically create the database, all relations, programs, curriculum, class schedules, and accounts for all 5 signatory clearance stages.
                </p>
            </div>

            <form method="POST">
                <button type="submit" name="install" class="w-full py-4 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/25 transition-all transform hover:-translate-y-0.5 text-base flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Install Database & Initialize System Now
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center text-xs text-slate-500">
            South East Asian Institute of Technology &bull; SEAIT ENROLL &bull; Database14
        </div>
    </div>

</body>
</html>
