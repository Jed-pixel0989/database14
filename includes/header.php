<?php
require_once __DIR__ . '/../config/functions.php';
$user = current_user();
$active_term = get_active_term();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | ' : '' ?>SEAIT ENROLL - Multi-Step Clearance</title>
    <!-- Tailwind CSS (Online CDN + Offline Fallback in style.css) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                50: '#eef2ff',
                                100: '#e0e7ff',
                                200: '#c7d2fe',
                                300: '#a5b4fc',
                                400: '#818cf8',
                                500: '#6366f1',
                                600: '#4f46e5',
                                700: '#4338ca',
                                800: '#3730a3',
                                900: '#312e81',
                                950: '#1e1b4b',
                            }
                        }
                    }
                }
            };
        }
    </script>
    <!-- Custom Design System & Offline Utilities -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
    <!-- Lucide Icons (Online CDN + 100% Offline SVG Engine Fallback) -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= url('assets/js/offline-icons.js') ?>"></script>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <?php if (is_logged_in()): ?>
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <?php if (is_logged_in()): ?>
        <!-- Top Navbar -->
        <header class="h-16 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-4 sm:px-6 z-30 sticky top-0">
            
            <div class="flex items-center gap-3">
                <button id="sidebar-toggle" class="md:hidden p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 focus:outline-none">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-950/80 text-indigo-300 border border-indigo-700/50">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        A.Y. <?= htmlspecialchars($active_term['academic_year']) ?> &bull; <?= htmlspecialchars($active_term['semester']) ?>
                    </span>
                </div>
            </div>

            <!-- User Menu -->
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs font-bold text-slate-200"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></span>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-400"><?= htmlspecialchars($user['role']) ?></span>
                </div>

                <div class="relative" id="user-profile-menu-container">
                    <button type="button" id="user-profile-btn" onclick="toggleUserProfileDropdown(event)" class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 hover:from-indigo-500 hover:to-violet-400 flex items-center justify-center font-bold text-xs text-white shadow-lg ring-2 ring-slate-700 hover:ring-indigo-400 active:scale-95 transition-all focus:outline-none cursor-pointer select-none" aria-label="User profile menu" aria-expanded="false" title="Tap to toggle Actions Menu">
                        <span id="user-avatar-initials" class="pointer-events-none"><?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 2)) ?></span>
                    </button>

                    <!-- Dropdown Actions Menu -->
                    <div id="user-profile-dropdown" class="dropdown-menu absolute right-0 top-full mt-2 w-64 bg-slate-900 border border-slate-700/90 rounded-2xl shadow-2xl overflow-hidden py-1 z-[99999]" style="display: none;">
                        <!-- Profile Header -->
                        <div class="px-4 py-3 bg-slate-800/80 border-b border-slate-700/80">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-indigo-600/40 border border-indigo-500/40 flex items-center justify-center font-bold text-xs text-indigo-300 shrink-0">
                                    <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 2)) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-white truncate"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate"><?= htmlspecialchars($user['username']) ?></div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-md bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                                <span class="text-[10px] text-emerald-400 font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Online
                                </span>
                            </div>
                        </div>

                        <!-- Actions List -->
                        <div class="py-1 text-xs">
                            <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Account & Profile</div>
                            
                            <button type="button" onclick="openUserProfileModal(); closeUserProfileDropdown();" class="w-full text-left flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors cursor-pointer">
                                <i data-lucide="user" class="w-4 h-4 text-indigo-400"></i>
                                <span>My Profile Details</span>
                            </button>

                            <?php if ($user['role'] === 'student'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Student Actions</div>
                                
                                <button type="button" onclick="if (typeof openEnrollmentModal === 'function') { openEnrollmentModal(); } else { window.location.href = '<?= url('modules/student/dashboard.php') ?>'; } closeUserProfileDropdown();" class="w-full text-left flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors cursor-pointer">
                                    <i data-lucide="file-signature" class="w-4 h-4 text-emerald-400"></i>
                                    <span>Step 1: Enrollment Form</span>
                                </button>
                                <a href="<?= url('modules/student/dashboard.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Clearance Tracker</span>
                                </a>
                                <a href="<?= url('modules/student/schedule.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="calendar" class="w-4 h-4 text-cyan-400"></i>
                                    <span>My Class Schedule</span>
                                </a>
                                <a href="<?= url('modules/student/print_cor.php') ?>" target="_blank" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="printer" class="w-4 h-4 text-amber-400"></i>
                                    <span>Print Official COR</span>
                                </a>
                            <?php elseif ($user['role'] === 'department'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Department Actions</div>
                                <a href="<?= url('modules/department/initial_queue.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="user-check" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Step 1: Initial Clearance</span>
                                </a>
                                <a href="<?= url('modules/department/schedule_queue.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="clock" class="w-4 h-4 text-purple-400"></i>
                                    <span>Step 5: Final Scheduling</span>
                                </a>
                                <a href="<?= url('modules/department/schedules.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="calendar" class="w-4 h-4 text-cyan-400"></i>
                                    <span>Section Offerings Builder</span>
                                </a>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Admin Actions</div>
                                <a href="<?= url('modules/admin/dashboard.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="bar-chart-3" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Admin Command Center</span>
                                </a>
                                <a href="<?= url('modules/admin/users.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                                    <span>Staff & Signatories</span>
                                </a>
                                <a href="<?= url('modules/admin/students.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="users" class="w-4 h-4 text-cyan-400"></i>
                                    <span>Student Directory</span>
                                </a>
                            <?php elseif ($user['role'] === 'library'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Library Actions</div>
                                <a href="<?= url('modules/library/clearance_queue.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="book-open" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Step 2: Library Queue</span>
                                </a>
                            <?php elseif ($user['role'] === 'accounting'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Accounting Actions</div>
                                <a href="<?= url('modules/accounting/clearance_queue.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="credit-card" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Step 3: Financial Clearance</span>
                                </a>
                            <?php elseif ($user['role'] === 'registrar'): ?>
                                <div class="my-1 border-t border-slate-800"></div>
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Registrar Actions</div>
                                <a href="<?= url('modules/registrar/clearance_queue.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-200 hover:bg-slate-800/90 hover:text-white transition-colors">
                                    <i data-lucide="clipboard-check" class="w-4 h-4 text-indigo-400"></i>
                                    <span>Step 4: Load Evaluation</span>
                                </a>
                            <?php endif; ?>

                            <div class="my-1 border-t border-slate-800"></div>
                            <a href="<?= url('setup.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-slate-300 hover:bg-slate-800/90 hover:text-white transition-colors">
                                <i data-lucide="database" class="w-4 h-4 text-slate-400"></i>
                                <span>System Database</span>
                            </a>
                        </div>

                        <!-- Sign Out -->
                        <div class="border-t border-slate-700/80 bg-slate-950/40 py-1">
                            <a href="<?= url('logout.php') ?>" class="flex items-center gap-2.5 px-3.5 py-2 text-rose-400 hover:bg-rose-950/50 hover:text-rose-300 font-semibold transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4 text-rose-400"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>

                    <script>
                    function toggleUserProfileDropdown(event) {
                        if (event) {
                            if (typeof event.preventDefault === 'function') event.preventDefault();
                            if (typeof event.stopPropagation === 'function') event.stopPropagation();
                        }
                        const dropdown = document.getElementById('user-profile-dropdown');
                        const btn = document.getElementById('user-profile-btn');
                        if (!dropdown) return;
                        
                        const isHidden = dropdown.style.display === 'none' || !dropdown.classList.contains('show');
                        
                        if (isHidden) {
                            dropdown.classList.add('show');
                            dropdown.style.display = 'block';
                            if (btn) {
                                btn.classList.add('ring-indigo-400', 'scale-105');
                                btn.setAttribute('aria-expanded', 'true');
                            }
                            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                window.lucide.createIcons();
                            }
                        } else {
                            dropdown.classList.remove('show');
                            dropdown.style.display = 'none';
                            if (btn) {
                                btn.classList.remove('ring-indigo-400', 'scale-105');
                                btn.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }

                    function closeUserProfileDropdown() {
                        const dropdown = document.getElementById('user-profile-dropdown');
                        const btn = document.getElementById('user-profile-btn');
                        if (dropdown) {
                            dropdown.classList.remove('show');
                            dropdown.style.display = 'none';
                        }
                        if (btn) {
                            btn.classList.remove('ring-indigo-400', 'scale-105');
                            btn.setAttribute('aria-expanded', 'false');
                        }
                    }

                    function openUserProfileModal() {
                        if (typeof openModal === 'function') {
                            openModal('user-profile-modal');
                        } else {
                            const m = document.getElementById('user-profile-modal');
                            if (m) {
                                m.classList.add('active');
                                m.style.display = 'flex';
                            }
                        }
                    }
                    </script>
                </div>
            </div>
        </header>

        <!-- USER PROFILE DETAILS MODAL -->
        <div id="user-profile-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content max-w-md border border-indigo-500/40 rounded-2xl p-6 space-y-6 shadow-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-bold text-sm text-white shadow-md">
                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 2)) ?>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white"><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h3>
                            <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($user['username']) ?></p>
                        </div>
                    </div>
                    <button type="button" onclick="closeModal('user-profile-modal')" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="bg-slate-900/80 p-3.5 rounded-xl border border-slate-800 space-y-2">
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-500">System Role:</span>
                            <span class="font-bold text-indigo-400 uppercase tracking-wider"><?= htmlspecialchars($user['role']) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-500">Account Status:</span>
                            <span class="text-emerald-400 font-semibold flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Active
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span class="text-slate-500">Active Term:</span>
                            <span class="font-semibold text-white"><?= htmlspecialchars($active_term['academic_year']) ?> (<?= htmlspecialchars($active_term['semester']) ?>)</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                    <button type="button" onclick="closeModal('user-profile-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800">
                        Close
                    </button>
                    <a href="<?= url('logout.php') ?>" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-600/30 flex items-center gap-1.5 transition-all">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flash Message Alerts -->
        <div class="px-4 sm:px-6 pt-4">
            <?php if ($success = get_flash('success')): ?>
                <div class="p-4 mb-4 text-sm bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 rounded-xl flex items-center gap-3">
                    <span class="text-lg">✅</span>
                    <div class="flex-1 font-medium"><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error = get_flash('error')): ?>
                <div class="p-4 mb-4 text-sm bg-rose-950/70 border border-rose-500/40 text-rose-300 rounded-xl flex items-center gap-3">
                    <span class="text-lg">❌</span>
                    <div class="flex-1 font-medium"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Body -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
