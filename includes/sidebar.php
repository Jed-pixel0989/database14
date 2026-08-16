<?php
require_once __DIR__ . '/../config/functions.php';
$user = current_user();
$role = $user['role'] ?? '';
$current_page = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<aside id="main-sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between shrink-0 transition-transform -translate-x-full md:translate-x-0 fixed md:static inset-y-0 left-0 z-40">
    
    <div>
        <!-- Brand Logo & Header -->
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <a href="<?= url('index.php') ?>" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-black text-xl text-white shadow-lg shadow-indigo-600/30 shrink-0">
                    S
                </div>
                <div class="overflow-hidden">
                    <div class="font-extrabold text-white text-sm tracking-tight leading-none">SEAIT ENROLL</div>
                    <div class="text-[10px] text-indigo-400 font-semibold uppercase tracking-wider mt-0.5">Clearance Portal</div>
                </div>
            </a>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5 text-xs font-semibold">

            <div class="px-3 py-2 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                <?= ucfirst($role) ?> Portal
            </div>

            <?php if ($role === 'student'): ?>
                <a href="<?= url('modules/student/dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'student/dashboard') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    Clearance Tracker
                </a>
                <a href="<?= url('modules/student/schedule.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'student/schedule') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    My Class Schedule
                </a>
                <a href="<?= url('modules/student/print_cor.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'student/print_cor') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="file-badge-2" class="w-4 h-4"></i>
                    Certificate of Reg. (COR)
                </a>

            <?php elseif ($role === 'department'): ?>
                <a href="<?= url('modules/department/initial_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'department/initial_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                    Step 1: Initial Clearance
                </a>
                <a href="<?= url('modules/department/schedule_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'department/schedule_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    Step 5: Final Scheduling
                </a>
                <a href="<?= url('modules/department/schedules.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'department/schedules') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="calendar-range" class="w-4 h-4"></i>
                    Class Section Schedules
                </a>
                <a href="<?= url('modules/department/students.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'department/students') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Department Students
                </a>

            <?php elseif ($role === 'library'): ?>
                <a href="<?= url('modules/library/clearance_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'library/clearance_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                    Step 2: Library Clearance
                </a>

            <?php elseif ($role === 'accounting'): ?>
                <a href="<?= url('modules/accounting/clearance_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'accounting/clearance_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    Step 3: Accounting Clearance
                </a>
                <a href="<?= url('modules/accounting/fee_assessment.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'accounting/fee_assessment') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    Tuition & Fee Records
                </a>

            <?php elseif ($role === 'registrar'): ?>
                <a href="<?= url('modules/registrar/clearance_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'registrar/clearance_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    Step 4: Registrar & Load Eval
                </a>
                <a href="<?= url('modules/registrar/curriculum.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'registrar/curriculum') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    Curriculum & Programs
                </a>

            <?php elseif ($role === 'admin'): ?>
                <a href="<?= url('modules/admin/dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/dashboard') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    Master Dashboard
                </a>
                <a href="<?= url('modules/department/initial_queue.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'department/initial_queue') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="user-check" class="w-4 h-4 text-indigo-400"></i>
                    Step 1: Initial Clearance
                </a>
                <a href="<?= url('modules/admin/students.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/students') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Student Directory
                </a>
                <a href="<?= url('modules/admin/subjects.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/subjects') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                    Subjects & Curriculum
                </a>
                <a href="<?= url('modules/admin/schedules.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/schedules') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="calendar-range" class="w-4 h-4"></i>
                    Class Schedules
                </a>
                <a href="<?= url('modules/admin/users.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/users') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    Staff & Users
                </a>
                <a href="<?= url('modules/admin/audit_logs.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= strpos($current_page, 'admin/audit_logs') !== false ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800' ?> transition-all">
                    <i data-lucide="scroll-text" class="w-4 h-4"></i>
                    Activity Logs
                </a>
            <?php endif; ?>

        </nav>
    </div>

    <!-- User Profile Footer -->
    <div class="p-4 border-t border-slate-800">
        <div onclick="openUserProfileModal()" class="bg-slate-800/60 hover:bg-slate-800 p-3 rounded-xl border border-slate-700/60 hover:border-indigo-500/40 flex items-center justify-between cursor-pointer transition-all group" title="Click to view Account Profile">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-600/30 border border-indigo-500/30 flex items-center justify-center font-bold text-xs text-indigo-300 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-white truncate"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></div>
                    <div class="text-[10px] text-slate-400 capitalize truncate"><?= htmlspecialchars($user['role']) ?></div>
                </div>
            </div>
            <a href="<?= url('logout.php') ?>" onclick="event.stopPropagation()" title="Log out" class="text-slate-400 hover:text-rose-400 p-1.5 rounded-lg hover:bg-slate-700/50 transition-colors">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

</aside>
