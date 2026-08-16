<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'department']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// Fetch students for Step 5 (Department Final Scheduling)
// A student is ready for Step 5 if Step 3 (Accounting) AND Step 4 (Registrar) are Cleared
$query = "SELECT cs.id as stage_id, cs.status as stage_status, cs.signed_at, cs.remarks,
                 cr.id as clearance_id, cr.current_step, cr.overall_status,
                 s.id as student_id, s.student_no, s.first_name, s.last_name, s.year_level, s.academic_status,
                 p.code as program_code, p.name as program_name,
                 (SELECT status FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 3) as step3_status,
                 (SELECT officer_name FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 3) as step3_officer,
                 (SELECT signed_at FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 3) as step3_signed_at,
                 (SELECT status FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 4) as step4_status,
                 (SELECT COUNT(*) FROM student_subject_loads sload WHERE sload.clearance_id = cr.id) as evaluated_subjects_count,
                 (SELECT COUNT(*) FROM student_enrollments se WHERE se.clearance_id = cr.id) as enrolled_schedules_count
          FROM clearance_stages cs
          JOIN clearance_requests cr ON cs.clearance_id = cr.id
          JOIN students s ON cr.student_id = s.id
          JOIN programs p ON s.program_id = p.id
          WHERE cs.step_number = 5 AND cr.academic_term_id = ? ";

$params = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $query .= " AND p.department_id = ? ";
    $params[] = $user['department_id'];
}
$query .= " ORDER BY (CASE WHEN cs.status = 'Pending' AND cr.current_step >= 5 THEN 1 WHEN cs.status = 'Cleared' THEN 2 ELSE 3 END), s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Handle viewing specific student scheduler
$selected_student_id = intval($_GET['schedule_student_id'] ?? 0);
$selected_student = null;
$selected_student_step3 = null;
$is_accounting_cleared = false;
$evaluated_subjects = [];
$available_schedules = [];

if ($selected_student_id) {
    $stmt_st = $db->prepare("SELECT s.*, cr.id as clearance_id, p.code as program_code, p.name as program_name 
                             FROM students s 
                             JOIN clearance_requests cr ON s.id = cr.student_id AND cr.academic_term_id = ?
                             JOIN programs p ON s.program_id = p.id
                             WHERE s.id = ?");
    $stmt_st->execute([$active_term['id'], $selected_student_id]);
    $selected_student = $stmt_st->fetch();

    if ($selected_student) {
        // Fetch Step 3 (Accounting) status
        $stmt_stg3 = $db->prepare("SELECT * FROM clearance_stages WHERE clearance_id = ? AND step_number = 3");
        $stmt_stg3->execute([$selected_student['clearance_id']]);
        $selected_student_step3 = $stmt_stg3->fetch();
        $is_accounting_cleared = ($selected_student_step3 && $selected_student_step3['status'] === 'Cleared' && !empty($selected_student_step3['signed_at']));

        // Fetch evaluated subjects from Step 4
        $stmt_subj = $db->prepare("SELECT s.*, sload.is_allowed 
                                  FROM student_subject_loads sload 
                                  JOIN subjects s ON sload.subject_id = s.id 
                                  WHERE sload.clearance_id = ?");
        $stmt_subj->execute([$selected_student['clearance_id']]);
        $evaluated_subjects = $stmt_subj->fetchAll();

        // Fetch available schedules for these subjects in this term
        $subj_ids = array_column($evaluated_subjects, 'id');
        if (!empty($subj_ids)) {
            $in_clause = implode(',', array_fill(0, count($subj_ids), '?'));
            $stmt_sch = $db->prepare("SELECT sch.*, sub.code, sub.title, sub.total_units 
                                      FROM schedules sch 
                                      JOIN subjects sub ON sch.subject_id = sub.id 
                                      WHERE sch.academic_term_id = ? AND sch.subject_id IN ($in_clause)
                                      ORDER BY sub.code, sch.section");
            $stmt_sch->execute(array_merge([$active_term['id']], $subj_ids));
            $available_schedules = $stmt_sch->fetchAll();
        }

        // Fetch already enrolled schedule ids if any
        $stmt_cur_enr = $db->prepare("SELECT schedule_id FROM student_enrollments WHERE clearance_id = ?");
        $stmt_cur_enr->execute([$selected_student['clearance_id']]);
        $enrolled_ids = $stmt_cur_enr->fetchAll(PDO::FETCH_COLUMN);
    }
}

$page_title = "Step 5: Department Advising & Final Scheduling";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Step 5 of 5 (Final Step)
                </span>
                <span class="text-xs text-emerald-400 font-semibold">&bull; Final Enrollment & Schedule Lock</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="clock" class="w-7 h-7 text-indigo-400"></i>
                Department Final Scheduling & Advising
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Assign class sections and schedules to students who have completed all preliminary clearance steps and subject load evaluation.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= url('modules/department/schedules.php') ?>" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                + Create Class Schedule
            </a>
            <a href="<?= url('modules/department/initial_queue.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
                &larr; Step 1 Queue
            </a>
        </div>
    </div>

    <?php if ($selected_student): ?>
        <!-- SECTION / SCHEDULE ASSIGNMENT WORKBENCH FOR SELECTED STUDENT -->
        <div class="glass-card p-6 sm:p-8 space-y-6 border border-indigo-500/40">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-800 gap-4">
                <div>
                    <span class="text-xs text-indigo-400 font-bold uppercase tracking-wider">Schedule Assignment Workbench</span>
                    <h2 class="text-xl font-bold text-white">
                        <?= htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']) ?>
                        <span class="text-slate-400 font-mono text-sm font-normal">(<?= htmlspecialchars($selected_student['student_no']) ?>)</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Program: <strong class="text-slate-200"><?= htmlspecialchars($selected_student['program_code']) ?></strong> &bull; 
                        Year Level: <strong class="text-slate-200"><?= htmlspecialchars($selected_student['year_level']) ?></strong> &bull; 
                        Clearance ID: <strong class="font-mono text-indigo-300">#<?= htmlspecialchars($selected_student['clearance_id']) ?></strong>
                    </p>
                </div>
                <a href="<?= url('modules/department/schedule_queue.php') ?>" class="text-xs text-slate-400 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700 hover:bg-slate-800">
                    &times; Close Workbench
                </a>
            </div>

            <?php if (!$is_accounting_cleared): ?>
                <div class="p-5 text-amber-200 bg-amber-950/80 border border-amber-500/50 rounded-2xl space-y-2">
                    <div class="flex items-center gap-2.5 font-bold text-sm text-amber-300">
                        <i data-lucide="shield-alert" class="w-5 h-5 text-amber-400"></i>
                        <span>Mandatory Pre-requisite Incomplete: Step 3 (Accounting Clearance) is Not Signed</span>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Department Advising & Final Scheduling (Step 5) is strictly locked because this student has not yet been cleared by the <strong class="text-white">Accounting Office (Step 3)</strong>. The student must settle their tuition assessment downpayment and obtain official Accounting clearance before class schedules can be assigned or official enrollment finalized.
                    </p>
                    <div class="text-xs font-semibold text-amber-400 pt-1 flex items-center gap-2">
                        <span>Accounting Clearance Status:</span>
                        <span class="px-2 py-0.5 rounded bg-slate-900 font-mono text-rose-300 border border-rose-500/40 font-bold">
                            <?= htmlspecialchars($selected_student_step3['status'] ?? 'Pending') ?> (Not Signed)
                        </span>
                    </div>
                </div>
            <?php elseif (empty($evaluated_subjects)): ?>
                <div class="p-6 text-center text-amber-300 bg-amber-950/40 border border-amber-500/30 rounded-xl">
                    <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2 text-amber-400"></i>
                    <p class="text-sm font-bold">No Evaluated Subject Load Found for this Student</p>
                    <p class="text-xs text-slate-400 mt-1">The student must first have their subject load evaluated and approved by the <strong class="text-white">Registrar (Step 4)</strong>.</p>
                </div>
            <?php else: ?>
                <form id="assign-schedule-form" action="<?= url('api/assign_schedule.php') ?>" method="POST" onsubmit="submitClearanceAction(event, this)" class="space-y-6">
                    <input type="hidden" name="clearance_id" value="<?= $selected_student['clearance_id'] ?>">
                    <input type="hidden" name="student_id" value="<?= $selected_student['id'] ?>">

                    <div>
                        <h3 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
                            <i data-lucide="list-checks" class="w-4 h-4 text-indigo-400"></i>
                            Select Class Sections for Evaluated Subjects:
                        </h3>
                        <p class="text-xs text-slate-400 mb-4">Choose the appropriate section and time slot for each approved course offering.</p>

                        <div class="space-y-4">
                            <?php foreach ($evaluated_subjects as $sub): 
                                $matching_schedules = array_filter($available_schedules, fn($sch) => $sch['subject_id'] == $sub['id']);
                            ?>
                                <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono font-bold text-xs bg-indigo-950 text-indigo-300 px-2 py-1 rounded border border-indigo-800">
                                                <?= htmlspecialchars($sub['code']) ?>
                                            </span>
                                            <span class="font-bold text-sm text-white"><?= htmlspecialchars($sub['title']) ?></span>
                                        </div>
                                        <span class="text-xs font-semibold text-slate-400"><?= htmlspecialchars($sub['total_units']) ?> Units</span>
                                    </div>

                                    <?php if (empty($matching_schedules)): ?>
                                        <div class="text-xs text-rose-400 italic">No class schedules available for this subject yet.</div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <?php foreach ($matching_schedules as $sch): 
                                                $is_checked = in_array($sch['id'], $enrolled_ids ?? []);
                                                $is_full = ($sch['enrolled_slots'] >= $sch['max_slots']);
                                            ?>
                                                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-700 bg-slate-800/60 hover:bg-slate-800 cursor-pointer transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-950/40">
                                                    <input type="radio" name="schedule_for_subject_<?= $sub['id'] ?>" value="<?= $sch['id'] ?>" name="schedule_ids[]" <?= $is_checked ? 'checked' : '' ?> class="mt-1 text-indigo-600 focus:ring-indigo-500 bg-slate-900 border-slate-600" onchange="updateSelectedSchedules()">
                                                    <div class="flex-1 text-xs">
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-bold text-white"><?= htmlspecialchars($sch['section']) ?></span>
                                                            <span class="text-[10px] text-slate-400 font-mono"><?= $sch['enrolled_slots'] ?>/<?= $sch['max_slots'] ?> Slots</span>
                                                        </div>
                                                        <div class="text-slate-300 font-semibold mt-0.5"><?= htmlspecialchars($sch['days']) ?> &bull; <?= date('g:i A', strtotime($sch['time_start'])) ?> - <?= date('g:i A', strtotime($sch['time_end'])) ?></div>
                                                        <div class="text-[11px] text-slate-400 mt-0.5">Room: <span class="font-mono text-slate-300"><?= htmlspecialchars($sch['room']) ?></span> &bull; <?= htmlspecialchars($sch['instructor']) ?></div>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Hidden inputs container for schedule_ids array -->
                    <div id="hidden-schedules-container"></div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Adviser Remarks / Final Enrollment Notes</label>
                        <textarea name="remarks" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">Class schedule assigned and officially enrolled.</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="<?= url('modules/department/schedule_queue.php') ?>" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-600 text-white text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition-all">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                            Finalize Schedule & Enroll Student Officially
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Master Step 5 Queue Table -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Students Awaiting Step 5 Scheduling & Sectioning</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="step5-table" placeholder="Search student name, ID..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="step5-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Step 3 (Accounting)</th>
                        <th>Step 4 (Registrar Load)</th>
                        <th>Step 5 Status</th>
                        <th>Signed By</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500 italic">No students currently in the Step 5 queue.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): 
                            $is_acct_signed = ($row['step3_status'] === 'Cleared');
                            $is_ready = ($is_acct_signed && $row['current_step'] >= 5);
                            $is_enrolled = ($row['stage_status'] === 'Cleared');
                        ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?> (Yr <?= htmlspecialchars($row['year_level']) ?>)</td>
                                <td>
                                    <?php if ($is_acct_signed): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 inline-flex items-center gap-1">
                                            <i data-lucide="check" class="w-3 h-3 text-emerald-400"></i>
                                            Signed / Cleared
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-950/80 text-rose-300 border border-rose-500/40 inline-flex items-center gap-1">
                                            <i data-lucide="alert-circle" class="w-3 h-3 text-rose-400"></i>
                                            Not Signed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['evaluated_subjects_count'] > 0): ?>
                                        <span class="text-xs font-semibold text-emerald-400 flex items-center gap-1">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            <?= $row['evaluated_subjects_count'] ?> Subjects Evaluated
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500">Pending Eval</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= get_status_badge($row['stage_status']) ?></td>
                                <td class="text-xs text-slate-400">
                                    <?= $row['signed_at'] ? format_date($row['signed_at']) : '—' ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($is_enrolled): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="<?= url('modules/student/print_cor.php?student_id=' . $row['student_id']) ?>" target="_blank" class="px-3 py-1.5 bg-emerald-600/80 hover:bg-emerald-600 text-white text-xs font-semibold rounded-lg shadow transition-all flex items-center gap-1">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                                View COR
                                            </a>
                                            <a href="?schedule_student_id=<?= $row['student_id'] ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-all">
                                                Edit Schedule
                                            </a>
                                        </div>
                                    <?php elseif (!$is_acct_signed): ?>
                                        <button disabled class="px-3 py-1.5 bg-slate-900 text-slate-500 text-xs font-semibold rounded-lg border border-slate-800 cursor-not-allowed opacity-60 flex items-center gap-1.5 ml-auto" title="Step 3 Accounting must be signed before Step 5 scheduling">
                                            <i data-lucide="lock" class="w-3.5 h-3.5 text-rose-400"></i>
                                            <span>Accounting Required</span>
                                        </button>
                                    <?php elseif ($is_ready): ?>
                                        <a href="?schedule_student_id=<?= $row['student_id'] ?>" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-all flex items-center gap-1.5 ml-auto">
                                            <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i>
                                            <span>Assign Schedule &rarr;</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500 italic">Waiting Previous Steps</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function updateSelectedSchedules() {
    const container = document.getElementById('hidden-schedules-container');
    if (!container) return;
    container.innerHTML = '';

    const selectedRadios = document.querySelectorAll('input[type="radio"]:checked');
    selectedRadios.forEach(radio => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'schedule_ids[]';
        input.value = radio.value;
        container.appendChild(input);
    });
}
document.addEventListener('DOMContentLoaded', updateSelectedSchedules);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
