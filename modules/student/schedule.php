<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('student');

$user = current_user();
$student_id = $user['student_id'];
$active_term = get_active_term();
$clearance = get_student_clearance_record($student_id, $active_term['id']);

$db = getDB();
$stmt = $db->prepare("SELECT sch.*, sub.code, sub.title, sub.lecture_units, sub.lab_units, sub.total_units 
                     FROM student_enrollments se 
                     JOIN schedules sch ON se.schedule_id = sch.id 
                     JOIN subjects sub ON sch.subject_id = sub.id 
                     WHERE se.clearance_id = ? 
                     ORDER BY sch.days, sch.time_start");
$stmt->execute([$clearance['id'] ?? 0]);
$schedules = $stmt->fetchAll();

$page_title = "My Class Schedule";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="calendar-days" class="w-6 h-6 text-indigo-400"></i>
                Official Class Timetable
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                A.Y. <?= htmlspecialchars($active_term['academic_year']) ?> &bull; <?= htmlspecialchars($active_term['semester']) ?>
            </p>
        </div>

        <?php if (!empty($schedules)): ?>
            <a href="<?= url('modules/student/print_cor.php') ?>" target="_blank" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition-all">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print Registration Certificate (COR)
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($schedules)): ?>
        <div class="glass-card p-12 text-center text-slate-400 space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-indigo-950/80 border border-indigo-500/40 text-indigo-400 flex items-center justify-center mx-auto shadow-inner">
                <i data-lucide="calendar-x" class="w-8 h-8"></i>
            </div>
            <h3 class="text-lg font-bold text-white">No Enrolled Schedule Yet</h3>
            <p class="text-xs max-w-md mx-auto text-slate-400">
                Your schedule will appear here after completing <strong class="text-indigo-400">Step 5: Department Advising & Final Scheduling</strong>.
            </p>
            <a href="<?= url('modules/student/dashboard.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-all">
                Check Clearance Progress &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($schedules as $item): ?>
                <div class="glass-card p-5 space-y-4 border-l-4 border-l-indigo-500 hover:border-l-indigo-400 transition-all">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="font-mono font-bold text-xs text-indigo-400 bg-indigo-950/80 px-2 py-0.5 rounded border border-indigo-800/60">
                                <?= htmlspecialchars($item['code']) ?>
                            </span>
                            <span class="ml-2 font-mono text-xs font-bold text-slate-300 bg-slate-800 px-2 py-0.5 rounded">
                                <?= htmlspecialchars($item['section']) ?>
                            </span>
                        </div>
                        <span class="text-xs font-bold text-white"><?= htmlspecialchars($item['total_units']) ?> Units</span>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-white line-clamp-1"><?= htmlspecialchars($item['title']) ?></h4>
                        <div class="text-xs text-slate-400 mt-1 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-slate-500"></i>
                            <?= htmlspecialchars($item['instructor']) ?>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 grid grid-cols-2 gap-2 text-xs">
                        <div class="bg-slate-900/80 p-2 rounded-lg">
                            <span class="text-[10px] text-slate-500 block uppercase font-bold">Schedule</span>
                            <span class="font-semibold text-white"><?= htmlspecialchars($item['days']) ?></span>
                            <span class="text-[10px] text-indigo-300 block"><?= date('g:i A', strtotime($item['time_start'])) ?> - <?= date('g:i A', strtotime($item['time_end'])) ?></span>
                        </div>
                        <div class="bg-slate-900/80 p-2 rounded-lg">
                            <span class="text-[10px] text-slate-500 block uppercase font-bold">Room</span>
                            <span class="font-mono font-semibold text-white truncate block"><?= htmlspecialchars($item['room']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
