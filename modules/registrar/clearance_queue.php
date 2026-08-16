<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'registrar']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// Handle evaluating a specific student
$selected_student_id = intval($_GET['eval_student_id'] ?? 0);
$selected_student = null;
$curriculum_subjects = [];
$assigned_subject_ids = [];

if ($selected_student_id) {
    $stmt_st = $db->prepare("SELECT s.*, cr.id as clearance_id, cr.current_step, p.code as program_code, p.name as program_name, p.id as prog_id
                             FROM students s 
                             JOIN clearance_requests cr ON s.id = cr.student_id AND cr.academic_term_id = ?
                             JOIN programs p ON s.program_id = p.id
                             WHERE s.id = ?");
    $stmt_st->execute([$active_term['id'], $selected_student_id]);
    $selected_student = $stmt_st->fetch();

    if ($selected_student) {
        // Fetch curriculum subjects for this student's program and year level
        $stmt_sub = $db->prepare("SELECT * FROM subjects WHERE program_id = ? AND semester = ? ORDER BY year_level ASC, code ASC");
        $stmt_sub->execute([$selected_student['prog_id'], $active_term['semester']]);
        $curriculum_subjects = $stmt_sub->fetchAll();

        // Fetch already evaluated subject IDs if any
        $stmt_load = $db->prepare("SELECT subject_id FROM student_subject_loads WHERE clearance_id = ?");
        $stmt_load->execute([$selected_student['clearance_id']]);
        $assigned_subject_ids = $stmt_load->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Fetch all students for Step 4 (Registrar)
$query = "SELECT cs.id as stage_id, cs.status as stage_status, cs.signed_at, cs.remarks,
                 cr.id as clearance_id, cr.current_step, cr.overall_status,
                 s.id as student_id, s.student_no, s.first_name, s.last_name, s.year_level, s.academic_status,
                 p.code as program_code, p.name as program_name,
                 (SELECT COUNT(*) FROM student_subject_loads sload WHERE sload.clearance_id = cr.id) as evaluated_count,
                 (SELECT status FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 3) as step3_status
          FROM clearance_stages cs
          JOIN clearance_requests cr ON cs.clearance_id = cr.id
          JOIN students s ON cr.student_id = s.id
          JOIN programs p ON s.program_id = p.id
          WHERE cs.step_number = 4 AND cr.academic_term_id = ?
          ORDER BY (CASE WHEN cs.status = 'Pending' AND cr.current_step >= 4 THEN 1 WHEN cs.status = 'Flagged' THEN 2 ELSE 3 END), s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute([$active_term['id']]);
$students = $stmt->fetchAll();

$page_title = "Step 4: Registrar Clearance & Subject Load Evaluation";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Step 4 of 5
                </span>
                <span class="text-xs text-slate-400">Prerequisite to Step 5 (Department Final Scheduling)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="clipboard-check" class="w-7 h-7 text-indigo-400"></i>
                Registrar Clearance & Subject Load Evaluation
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Review curriculum requirements, evaluate allowable academic subject loads, and sign off clearance to allow Department section scheduling.
            </p>
        </div>

        <a href="<?= url('modules/registrar/curriculum.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            <i data-lucide="layers" class="w-4 h-4 text-indigo-400"></i>
            Programs & Curriculum &rarr;
        </a>
    </div>

    <?php if ($selected_student): ?>
        <!-- SUBJECT LOAD EVALUATION WORKBENCH FOR SELECTED STUDENT -->
        <div class="glass-card p-6 sm:p-8 space-y-6 border border-indigo-500/40">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-800 gap-4">
                <div>
                    <span class="text-xs text-indigo-400 font-bold uppercase tracking-wider">Curriculum Evaluation Workbench</span>
                    <h2 class="text-xl font-bold text-white">
                        <?= htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']) ?>
                        <span class="text-slate-400 font-mono text-sm font-normal">(<?= htmlspecialchars($selected_student['student_no']) ?>)</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Program: <strong class="text-slate-200"><?= htmlspecialchars($selected_student['program_code']) ?></strong> &bull; 
                        Year Level: <strong class="text-slate-200"><?= htmlspecialchars($selected_student['year_level']) ?></strong> &bull; 
                        Standing: <strong class="text-slate-200"><?= htmlspecialchars($selected_student['academic_status']) ?></strong>
                    </p>
                </div>
                <a href="<?= url('modules/registrar/clearance_queue.php') ?>" class="text-xs text-slate-400 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700 hover:bg-slate-800">
                    &times; Close Workbench
                </a>
            </div>

            <form id="save-load-form" action="<?= url('api/save_subject_load.php') ?>" method="POST" onsubmit="submitClearanceAction(event, this)" class="space-y-6">
                <input type="hidden" name="clearance_id" value="<?= $selected_student['clearance_id'] ?>">
                <input type="hidden" name="student_id" value="<?= $selected_student['id'] ?>">

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                <i data-lucide="check-square" class="w-4 h-4 text-indigo-400"></i>
                                Select Allowable Subjects for <?= htmlspecialchars($active_term['semester']) ?>:
                            </h3>
                            <p class="text-xs text-slate-400">Check the subjects the student is qualified and cleared to take.</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400">Total Units Selected:</span>
                            <span id="selected-units-counter" class="text-base font-bold text-indigo-400 font-mono ml-1">0</span>
                        </div>
                    </div>

                    <?php if (empty($curriculum_subjects)): ?>
                        <div class="p-6 text-center text-slate-400 bg-slate-900/60 rounded-xl border border-slate-800">
                            No curriculum subjects found for this program in <?= htmlspecialchars($active_term['semester']) ?>.
                        </div>
                    <?php else: ?>
                        <div class="data-table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="w-12 text-center">Allow</th>
                                        <th>Subject Code</th>
                                        <th>Descriptive Title</th>
                                        <th class="text-center">Year Level</th>
                                        <th class="text-center">Lec</th>
                                        <th class="text-center">Lab</th>
                                        <th class="text-center">Total Units</th>
                                        <th>Prerequisites</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($curriculum_subjects as $sub): 
                                        $checked = in_array($sub['id'], $assigned_subject_ids) || (empty($assigned_subject_ids) && $sub['year_level'] == $selected_student['year_level']);
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="subject_ids[]" value="<?= $sub['id'] ?>" data-units="<?= $sub['total_units'] ?>" <?= $checked ? 'checked' : '' ?> class="subject-checkbox rounded text-indigo-600 focus:ring-indigo-500 bg-slate-800 border-slate-600" onchange="calculateUnits()">
                                            </td>
                                            <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($sub['code']) ?></td>
                                            <td class="font-medium text-white"><?= htmlspecialchars($sub['title']) ?></td>
                                            <td class="text-center text-slate-300">Year <?= htmlspecialchars($sub['year_level']) ?></td>
                                            <td class="text-center text-slate-400"><?= htmlspecialchars($sub['lecture_units']) ?></td>
                                            <td class="text-center text-slate-400"><?= htmlspecialchars($sub['lab_units']) ?></td>
                                            <td class="text-center font-bold text-white"><?= htmlspecialchars($sub['total_units']) ?></td>
                                            <td class="text-xs text-slate-400"><?= htmlspecialchars($sub['prerequisites']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl space-y-3">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="approve_step" value="1" checked class="rounded text-emerald-600 focus:ring-emerald-500 bg-slate-800 border-slate-600">
                        <span class="text-xs font-bold text-emerald-400">Sign and Approve Step 4 (Registrar Clearance) & Advance Student to Step 5 (Department Scheduling)</span>
                    </label>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Registrar Evaluation Notes</label>
                        <input type="text" name="remarks" value="Full curriculum subject load evaluated and approved for current semester." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="<?= url('modules/registrar/clearance_queue.php') ?>" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Evaluation & Sign Step 4
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Master Step 4 Queue Table -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Registrar Clearance & Evaluation Queue</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="reg-table" placeholder="Search student name, ID..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="reg-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Step 3 (Accounting)</th>
                        <th>Evaluated Subjects</th>
                        <th>Step 4 Status</th>
                        <th>Signed By</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500 italic">No records in registrar queue.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): 
                            $is_step3_cleared = ($row['step3_status'] === 'Cleared');
                            $is_cleared = ($row['stage_status'] === 'Cleared');
                        ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?> (Yr <?= htmlspecialchars($row['year_level']) ?>)</td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold <?= $is_step3_cleared ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-slate-800 text-slate-400' ?>">
                                        <?= $is_step3_cleared ? 'Step 3 Cleared' : 'Step 3 Incomplete' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs font-semibold <?= $row['evaluated_count'] > 0 ? 'text-indigo-300' : 'text-slate-500' ?>">
                                        <?= $row['evaluated_count'] ?> Subjects Tagged
                                    </span>
                                </td>
                                <td><?= get_status_badge($row['stage_status']) ?></td>
                                <td class="text-xs text-slate-400">
                                    <?= $row['signed_at'] ? format_date($row['signed_at']) : '—' ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="?eval_student_id=<?= $row['student_id'] ?>" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow-md shadow-indigo-600/25 transition-all flex items-center gap-1.5 inline-flex">
                                            <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                            <?= $is_cleared ? 'Re-Evaluate' : 'Evaluate Load' ?> &rarr;
                                        </a>
                                        <?php if (!$is_cleared): ?>
                                            <button onclick="triggerFlagModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_no'] . ')')) ?>')" class="px-2.5 py-1.5 bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-semibold rounded-lg shadow">
                                                Hold
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Include Modal Templates -->
<?php include __DIR__ . '/../../includes/modals.php'; ?>

<script>
function calculateUnits() {
    let total = 0;
    const checkboxes = document.querySelectorAll('.subject-checkbox:checked');
    checkboxes.forEach(cb => {
        total += parseInt(cb.getAttribute('data-units') || 0);
    });
    const counter = document.getElementById('selected-units-counter');
    if (counter) {
        counter.innerText = total;
    }
}
document.addEventListener('DOMContentLoaded', calculateUnits);

function triggerFlagModal(stageId, studentName) {
    document.getElementById('flag-stage-id').value = stageId;
    document.getElementById('flag-student-name').innerText = 'Flag Academic Credential Hold: ' + studentName;
    openModal('flag-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
