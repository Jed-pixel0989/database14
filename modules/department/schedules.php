<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['department', 'admin']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// Handle adding schedule from department panel
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_schedule'])) {
    $subject_id = intval($_POST['subject_id']);
    $section = strtoupper(trim($_POST['section']));
    $days = strtoupper(trim($_POST['days']));
    $time_start = trim($_POST['time_start']);
    $time_end = trim($_POST['time_end']);
    $room = trim($_POST['room']);
    $instructor = trim($_POST['instructor']);
    $max_slots = intval($_POST['max_slots'] ?: 40);

    if ($subject_id && $section && $days && $time_start && $time_end && $room) {
        try {
            $stmt = $db->prepare("INSERT INTO schedules (academic_term_id, subject_id, section, days, time_start, time_end, room, instructor, max_slots, enrolled_slots) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$active_term['id'], $subject_id, $section, $days, $time_start, $time_end, $room, $instructor, $max_slots]);
            log_activity('SCHEDULE_ADDED', "Department added section: $section ($days $time_start-$time_end, Room: $room)", $user['id']);
            set_flash('success', "Class section $section successfully created and available for student scheduling!");
            header("Location: " . url('modules/department/schedules.php'));
            exit;
        } catch (Exception $e) {
            set_flash('error', "Failed to create schedule: " . $e->getMessage());
        }
    } else {
        set_flash('error', "Please fill in all required fields.");
    }
}

// Fetch subjects for department
$query_sub = "SELECT s.*, p.code as program_code, p.name as program_name 
              FROM subjects s 
              JOIN programs p ON s.program_id = p.id ";
$params_sub = [];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $query_sub .= " WHERE p.department_id = ? ";
    $params_sub[] = $user['department_id'];
}
$query_sub .= " ORDER BY p.code, s.year_level, s.code";
$stmt_sub = $db->prepare($query_sub);
$stmt_sub->execute($params_sub);
$subjects = $stmt_sub->fetchAll();

// Fetch schedules
$query_sch = "SELECT sch.*, sub.code, sub.title, sub.total_units, p.code as program_code 
              FROM schedules sch 
              JOIN subjects sub ON sch.subject_id = sub.id 
              JOIN programs p ON sub.program_id = p.id 
              WHERE sch.academic_term_id = ? ";
$params_sch = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $query_sch .= " AND p.department_id = ? ";
    $params_sch[] = $user['department_id'];
}
$query_sch .= " ORDER BY sub.code, sch.section";
$stmt_sch = $db->prepare($query_sch);
$stmt_sch->execute($params_sch);
$schedules = $stmt_sch->fetchAll();

$page_title = "Department Class Schedules & Sections";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Department Panel
                </span>
                <span class="text-xs text-slate-400">Class Section Offerings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="calendar-range" class="w-7 h-7 text-indigo-400"></i>
                Department Class Schedules & Section Offerings
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Create and manage class section offerings, time blocks, rooms, and slot limits for student enrollment assignments.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="openModal('add-sched-modal')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                + Create Class Schedule
            </button>
            <a href="<?= url('modules/department/schedule_queue.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
                Step 5 Queue &rarr;
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-semibold block">Total Section Offerings</span>
                <span class="text-2xl font-extrabold text-white font-mono"><?= count($schedules) ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-950/80 border border-indigo-500/40 text-indigo-400 flex items-center justify-center">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-semibold block">Curriculum Courses</span>
                <span class="text-2xl font-extrabold text-white font-mono"><?= count($subjects) ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-cyan-950/80 border border-cyan-500/40 text-cyan-400 flex items-center justify-center">
                <i data-lucide="book-open" class="w-5 h-5"></i>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-400 font-semibold block">Active Academic Term</span>
                <span class="text-sm font-bold text-white"><?= htmlspecialchars($active_term['academic_year']) ?></span>
                <div class="text-[11px] text-indigo-400"><?= htmlspecialchars($active_term['semester']) ?></div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-950/80 border border-purple-800 text-purple-400 flex items-center justify-center">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    <!-- Schedules Table -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Class Section Offerings & Capacity</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="sched-table" placeholder="Search section, code, instructor, room..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="sched-table">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Descriptive Title</th>
                        <th>Section</th>
                        <th>Days & Time</th>
                        <th>Room</th>
                        <th>Instructor</th>
                        <th class="text-center">Slots</th>
                        <th class="text-center">Units</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500 italic">No class section offerings created yet. Click "+ Create Class Schedule" above to add one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $sch): 
                            $is_full = ($sch['enrolled_slots'] >= $sch['max_slots']);
                        ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($sch['code']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($sch['title']) ?></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded bg-slate-800 font-mono text-xs font-bold text-slate-200 border border-slate-700">
                                        <?= htmlspecialchars($sch['section']) ?>
                                    </span>
                                </td>
                                <td class="text-xs text-slate-300">
                                    <strong class="text-indigo-300"><?= htmlspecialchars($sch['days']) ?></strong> &bull; 
                                    <?= date('g:i A', strtotime($sch['time_start'])) ?> - <?= date('g:i A', strtotime($sch['time_end'])) ?>
                                </td>
                                <td class="font-mono text-xs text-slate-300"><?= htmlspecialchars($sch['room']) ?></td>
                                <td class="text-xs text-slate-300"><?= htmlspecialchars($sch['instructor']) ?></td>
                                <td class="text-center">
                                    <span class="font-mono text-xs font-bold <?= $is_full ? 'text-rose-400' : 'text-emerald-400' ?>">
                                        <?= $sch['enrolled_slots'] ?> / <?= $sch['max_slots'] ?>
                                    </span>
                                </td>
                                <td class="text-center font-bold text-white"><?= htmlspecialchars($sch['total_units']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ======================================================== -->
<!-- MODAL: CREATE CLASS SECTION OFFERING -->
<!-- ======================================================== -->
<div id="add-sched-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Create Class Section Offering</h3>
                    <p class="text-xs text-slate-400">Add course section to active semester</p>
                </div>
            </div>
            <button onclick="closeModal('add-sched-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="schedules.php" class="space-y-4 text-xs">
            <input type="hidden" name="add_schedule" value="1">

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Select Subject Course *</label>
                <select name="subject_id" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code']) ?> - <?= htmlspecialchars($s['title']) ?> (Yr <?= htmlspecialchars($s['year_level']) ?>, <?= htmlspecialchars($s['total_units']) ?> Units)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Section Block Name *</label>
                    <input type="text" name="section" required placeholder="e.g. BSCS 2C or BSIT 1B" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Schedule Days *</label>
                    <select name="days" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="MWF">MWF (Mon / Wed / Fri)</option>
                        <option value="TTH">TTH (Tue / Thu)</option>
                        <option value="SAT">SAT (Saturday)</option>
                        <option value="DAILY">Daily (Mon-Fri)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Time Start *</label>
                    <input type="time" name="time_start" required value="08:00" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Time End *</label>
                    <input type="time" name="time_end" required value="09:30" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Room / Lab *</label>
                    <input type="text" name="room" required placeholder="e.g. CLAB 2" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div class="col-span-2">
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Instructor</label>
                    <input type="text" name="instructor" required placeholder="e.g. Prof. Alan Turing" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Maximum Student Capacity</label>
                <input type="number" name="max_slots" value="40" min="1" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('add-sched-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">
                    Create Section Offering
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
