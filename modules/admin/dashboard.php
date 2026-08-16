<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('admin');

$db = getDB();
$active_term = get_active_term();

// Metrics
$total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$enrolled_count = $db->query("SELECT COUNT(*) FROM students WHERE enrollment_status = 'Enrolled'")->fetchColumn();
$in_clearance_count = $db->query("SELECT COUNT(*) FROM students WHERE enrollment_status = 'In Clearance'")->fetchColumn();
$total_subjects = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$total_schedules = $db->query("SELECT COUNT(*) FROM schedules WHERE academic_term_id = {$active_term['id']}")->fetchColumn();

// Financial metrics
$tot_assessment = $db->query("SELECT SUM(total_assessment) FROM accounting_assessments WHERE academic_term_id = {$active_term['id']}")->fetchColumn() ?: 0;
$tot_collected = $db->query("SELECT SUM(amount_paid) FROM accounting_assessments WHERE academic_term_id = {$active_term['id']}")->fetchColumn() ?: 0;

// Clearance Funnel per Step
$step_counts = [];
for ($i = 1; $i <= 5; $i++) {
    $stmt_c = $db->prepare("SELECT COUNT(*) FROM clearance_stages cs 
                            JOIN clearance_requests cr ON cs.clearance_id = cr.id 
                            WHERE cs.step_number = ? AND cs.status = 'Cleared' AND cr.academic_term_id = ?");
    $stmt_c->execute([$i, $active_term['id']]);
    $step_counts[$i] = $stmt_c->fetchColumn();
}

// Recent Audit Logs
$recent_logs = $db->query("SELECT a.*, u.username, u.full_name, u.role 
                           FROM activity_logs a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

$page_title = "Admin Master Dashboard";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-8 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-950 text-purple-300 border border-purple-800">
                    Administrator
                </span>
                <span class="text-xs text-slate-400">Institutional Master Console</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="layout-dashboard" class="w-7 h-7 text-indigo-400"></i>
                SEAIT ENROLL & Clearance Command Center
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Real-time operational monitoring of student clearances, curriculum evaluations, financial collections, and enrollment locks.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= url('setup.php') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
                <i data-lucide="database" class="w-4 h-4 text-indigo-400"></i>
                Database Reset / Setup
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <div class="glass-card p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Total Enrolled Students</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-950/80 border border-emerald-500/40 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-white"><?= $enrolled_count ?> / <?= $total_students ?></div>
            <div class="text-[11px] text-emerald-400 font-semibold flex items-center gap-1">
                <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
                <?= $total_students > 0 ? round(($enrolled_count / $total_students) * 100) : 0 ?>% Completion Rate
            </div>
        </div>

        <div class="glass-card p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">In Clearance Pipeline</span>
                <div class="w-8 h-8 rounded-lg bg-amber-950/80 border border-amber-500/40 text-amber-400 flex items-center justify-center">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-amber-400"><?= $in_clearance_count ?></div>
            <div class="text-[11px] text-slate-400">
                Active in Stages 1 to 5
            </div>
        </div>

        <div class="glass-card p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Total Tuition Collected</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-950/80 border border-indigo-500/40 text-indigo-400 flex items-center justify-center">
                    <i data-lucide="dollar-sign" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-white font-mono"><?= format_currency($tot_collected) ?></div>
            <div class="text-[11px] text-slate-400">
                Out of <?= format_currency($tot_assessment) ?> Assessed
            </div>
        </div>

        <div class="glass-card p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Course Offerings & Sections</span>
                <div class="w-8 h-8 rounded-lg bg-violet-950/80 border border-violet-500/40 text-violet-400 flex items-center justify-center">
                    <i data-lucide="book-marked" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-white"><?= $total_schedules ?></div>
            <div class="text-[11px] text-slate-400">
                <?= $total_subjects ?> Curriculum Subjects Cataloged
            </div>
        </div>

    </div>

    <!-- 5-STAGE CLEARANCE FUNNEL PROGRESSION -->
    <div class="glass-card p-6 sm:p-8 space-y-6">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="git-merge" class="w-5 h-5 text-indigo-400"></i>
                5-Stage Clearance & Enrollment Signing Funnel (Live Term Progress)
            </h2>
            <p class="text-xs text-slate-400">Number of students cleared through each mandatory department milestone</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <?php 
            $stage_meta = [
                1 => ['title' => '1. Department Initial', 'desc' => 'Academic standing & prerequisites', 'icon' => 'user-check', 'color' => 'from-indigo-600 to-blue-600'],
                2 => ['title' => '2. Library Clearance', 'desc' => 'Books & library fines', 'icon' => 'book-open', 'color' => 'from-blue-600 to-cyan-600'],
                3 => ['title' => '3. Accounting Office', 'desc' => 'Downpayment & fee validation', 'icon' => 'credit-card', 'color' => 'from-cyan-600 to-teal-600'],
                4 => ['title' => '4. Registrar Evaluation', 'desc' => 'Subject load evaluation', 'icon' => 'clipboard-check', 'color' => 'from-teal-600 to-emerald-600'],
                5 => ['title' => '5. Dept Scheduling', 'desc' => 'Class sectioning & COR lock', 'icon' => 'award', 'color' => 'from-emerald-600 to-green-600']
            ];

            foreach ($stage_meta as $step_num => $meta): 
                $count = $step_counts[$step_num] ?? 0;
                $pct = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
            ?>
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Step <?= $step_num ?></span>
                        <span class="font-mono text-xs font-bold text-emerald-400"><?= $count ?> Cleared</span>
                    </div>

                    <h3 class="text-sm font-bold text-white"><?= htmlspecialchars($meta['title']) ?></h3>
                    <p class="text-[11px] text-slate-400"><?= htmlspecialchars($meta['desc']) ?></p>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div class="bg-gradient-to-r <?= $meta['color'] ?> h-2 rounded-full transition-all duration-500" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="text-[10px] text-slate-500 text-right"><?= $pct ?>% cleared</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Two Column Grid: Quick Modules & Activity Log -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Quick Management Navigation -->
        <div class="lg:col-span-6 space-y-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="command" class="w-4 h-4 text-indigo-400"></i>
                Administrative Module Shortcuts
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="<?= url('modules/admin/students.php') ?>" class="glass-card p-4 hover:border-indigo-500/50 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/30 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sm text-white">Student Masterlist</div>
                            <div class="text-xs text-slate-400">Manage all student records</div>
                        </div>
                    </div>
                </a>

                <a href="<?= url('modules/admin/subjects.php') ?>" class="glass-card p-4 hover:border-indigo-500/50 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-violet-950 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition-transform">
                            <i data-lucide="book-open" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sm text-white">Subjects & Courses</div>
                            <div class="text-xs text-slate-400">Curriculum & prerequisites</div>
                        </div>
                    </div>
                </a>

                <a href="<?= url('modules/admin/schedules.php') ?>" class="glass-card p-4 hover:border-indigo-500/50 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-950 border border-cyan-500/30 flex items-center justify-center text-cyan-400 group-hover:scale-110 transition-transform">
                            <i data-lucide="calendar" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sm text-white">Class Schedules</div>
                            <div class="text-xs text-slate-400">Section slots & rooms</div>
                        </div>
                    </div>
                </a>

                <a href="<?= url('modules/admin/users.php') ?>" class="glass-card p-4 hover:border-indigo-500/50 transition-all group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-950 border border-amber-500/30 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform">
                            <i data-lucide="shield-check" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sm text-white">Staff & Signatories</div>
                            <div class="text-xs text-slate-400">Manage user accounts & roles</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Right: Recent Activity Stream -->
        <div class="lg:col-span-6 glass-card p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-emerald-400"></i>
                    Recent Clearance & Activity Stream
                </h2>
                <a href="<?= url('modules/admin/audit_logs.php') ?>" class="text-xs text-indigo-400 hover:underline">View All &rarr;</a>
            </div>

            <div class="space-y-3">
                <?php if (empty($recent_logs)): ?>
                    <p class="text-xs text-slate-500 italic">No activity recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-slate-900/60 border border-slate-800 text-xs">
                            <div class="w-7 h-7 rounded-lg bg-slate-800 flex items-center justify-center shrink-0 text-slate-400 mt-0.5">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-slate-200 truncate"><?= htmlspecialchars($log['action']) ?></div>
                                <div class="text-slate-400 text-[11px] truncate"><?= htmlspecialchars($log['details']) ?></div>
                            </div>
                            <div class="text-[10px] text-slate-500 shrink-0 font-mono">
                                <?= date('h:i A', strtotime($log['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
