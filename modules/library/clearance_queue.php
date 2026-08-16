<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'library']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// Fetch students for Step 2 (Library Clearance)
$query = "SELECT cs.id as stage_id, cs.status as stage_status, cs.signed_at, cs.remarks,
                 cr.id as clearance_id, cr.current_step, cr.overall_status,
                 s.id as student_id, s.student_no, s.first_name, s.last_name, s.year_level,
                 p.code as program_code, p.name as program_name,
                 (SELECT status FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 1) as step1_status
          FROM clearance_stages cs
          JOIN clearance_requests cr ON cs.clearance_id = cr.id
          JOIN students s ON cr.student_id = s.id
          JOIN programs p ON s.program_id = p.id
          WHERE cs.step_number = 2 AND cr.academic_term_id = ?
          ORDER BY (CASE WHEN cs.status = 'Pending' AND cr.current_step >= 2 THEN 1 WHEN cs.status = 'Flagged' THEN 2 ELSE 3 END), s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute([$active_term['id']]);
$students = $stmt->fetchAll();

$page_title = "Step 2: Library Clearance Queue";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Step 2 of 5
                </span>
                <span class="text-xs text-slate-400">Prerequisite to Accounting Clearance</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="book-open" class="w-7 h-7 text-indigo-400"></i>
                Library Clearance Signatory Queue
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Verify that students have returned all borrowed library materials, settled unreturned book fees, and have no overdue accounts.
            </p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-400">
                <i data-lucide="hourglass" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Pending Library Review</div>
                <div class="text-lg font-bold text-amber-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Pending' && $s['current_step'] >= 2)) ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Cleared Students</div>
                <div class="text-lg font-bold text-emerald-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Cleared')) ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-950/80 border border-rose-500/40 flex items-center justify-center text-rose-400">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Flagged Book Holds</div>
                <div class="text-lg font-bold text-rose-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Flagged')) ?></div>
            </div>
        </div>
    </div>

    <!-- Student Table -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Students Queue (Term: <?= htmlspecialchars($active_term['academic_year']) ?> <?= htmlspecialchars($active_term['semester']) ?>)</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="lib-table" placeholder="Search student name, ID, course..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="lib-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Step 1 Status</th>
                        <th>Library Status</th>
                        <th>Remarks / Hold Reason</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-500 italic">No records in library queue.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): 
                            $is_step1_cleared = ($row['step1_status'] === 'Cleared');
                        ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold <?= $is_step1_cleared ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-slate-800 text-slate-400' ?>">
                                        <?= $is_step1_cleared ? 'Step 1 Cleared' : 'Step 1 Incomplete' ?>
                                    </span>
                                </td>
                                <td><?= get_status_badge($row['stage_status']) ?></td>
                                <td class="text-xs text-slate-400 max-w-xs truncate">
                                    <?= $row['remarks'] ? htmlspecialchars($row['remarks']) : '—' ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($row['stage_status'] !== 'Cleared'): ?>
                                            <button onclick="triggerSignModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_no'] . ')')) ?>')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow transition-all flex items-center gap-1">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                Sign & Clear
                                            </button>
                                            <button onclick="triggerFlagModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_no'] . ')')) ?>')" class="px-3 py-1.5 bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-semibold rounded-lg shadow transition-all flex items-center gap-1">
                                                <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                                                Flag Hold
                                            </button>
                                        <?php else: ?>
                                            <span class="text-xs text-emerald-400 font-semibold flex items-center gap-1">
                                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                                Cleared
                                            </span>
                                            <button onclick="triggerSignModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])) ?>')" title="Edit Remarks" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
                                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
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
function triggerSignModal(stageId, studentName) {
    document.getElementById('sign-stage-id').value = stageId;
    document.getElementById('sign-student-name').innerText = 'Step 2 Library Clearance: ' + studentName;
    document.getElementById('sign-remarks').value = 'No overdue books, unpaid fines, or pending property returns.';
    openModal('sign-modal');
}

function triggerFlagModal(stageId, studentName) {
    document.getElementById('flag-stage-id').value = stageId;
    document.getElementById('flag-student-name').innerText = 'Flag Library Deficiency: ' + studentName;
    openModal('flag-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
