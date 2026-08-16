<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'department']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

$query = "SELECT s.*, p.code as program_code, p.name as program_name,
                 cr.id as clearance_id, cr.current_step, cr.overall_status
          FROM students s
          JOIN programs p ON s.program_id = p.id
          LEFT JOIN clearance_requests cr ON s.id = cr.student_id AND cr.academic_term_id = ?
          WHERE 1=1 ";

$params = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $query .= " AND (p.department_id = ? OR p.code IN ('BSIT', 'BSCS')) ";
    $params[] = $user['department_id'];
}
$query .= " ORDER BY s.year_level ASC, s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = "Department Students Directory";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="users" class="w-7 h-7 text-indigo-400"></i>
                Department Student Directory
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Manage and review clearance statuses for all enrolled and incoming students in your department.
            </p>
        </div>
    </div>

    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">All Department Students</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="dept-students-table" placeholder="Search student name, ID, course..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="dept-students-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Academic Standing</th>
                        <th>Clearance Phase</th>
                        <th>Enrollment Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500 italic">No students found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?></td>
                                <td class="text-slate-300">Yr <?= htmlspecialchars($row['year_level']) ?></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-300">
                                        <?= htmlspecialchars($row['academic_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-800">
                                        Step <?= $row['current_step'] ?? 1 ?> of 5
                                    </span>
                                </td>
                                <td><?= get_status_badge($row['enrollment_status']) ?></td>
                                <td class="text-right">
                                    <?php if ($row['enrollment_status'] === 'Enrolled'): ?>
                                        <a href="<?= url('modules/student/print_cor.php?student_id=' . $row['id']) ?>" target="_blank" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 inline-flex items-center gap-1">
                                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                            COR
                                        </a>
                                    <?php elseif (($row['current_step'] ?? 1) == 1): ?>
                                        <a href="<?= url('modules/department/initial_queue.php') ?>" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow inline-flex items-center gap-1">
                                            <i data-lucide="file-signature" class="w-3.5 h-3.5"></i>
                                            Review Step 1
                                        </a>
                                    <?php elseif (($row['current_step'] ?? 1) >= 5): ?>
                                        <a href="<?= url('modules/department/schedule_queue.php?schedule_student_id=' . $row['id']) ?>" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow inline-flex items-center gap-1">
                                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                            Schedule
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('modules/department/schedule_queue.php?schedule_student_id=' . $row['id']) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 inline-flex items-center gap-1">
                                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                            Step <?= $row['current_step'] ?> Queue
                                        </a>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
