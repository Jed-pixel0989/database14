<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('admin');

$db = getDB();

// Handle adding subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $program_id = intval($_POST['program_id']);
    $code = strtoupper(trim($_POST['code']));
    $title = trim($_POST['title']);
    $lecture_units = intval($_POST['lecture_units']);
    $lab_units = intval($_POST['lab_units']);
    $total_units = $lecture_units + $lab_units;
    $tuition_rate_per_unit = floatval($_POST['tuition_rate_per_unit'] ?: 450.00);
    $lab_fee = floatval($_POST['lab_fee'] ?: 0.00);
    $prerequisites = trim($_POST['prerequisites'] ?: 'None');
    $year_level = intval($_POST['year_level']);
    $semester = trim($_POST['semester']);

    try {
        $stmt = $db->prepare("INSERT INTO subjects (program_id, code, title, lecture_units, lab_units, total_units, tuition_rate_per_unit, lab_fee, prerequisites, year_level, semester) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$program_id, $code, $title, $lecture_units, $lab_units, $total_units, $tuition_rate_per_unit, $lab_fee, $prerequisites, $year_level, $semester]);
        log_activity('SUBJECT_ADDED', "Added new course: $code - $title", $_SESSION['user_id'] ?? null);
        set_flash('success', "Subject $code ($title) added successfully!");
        header("Location: " . url('modules/admin/subjects.php'));
        exit;
    } catch (Exception $e) {
        set_flash('error', "Failed to add subject: " . $e->getMessage());
    }
}

$subjects = $db->query("SELECT s.*, p.code as program_code FROM subjects s JOIN programs p ON s.program_id = p.id ORDER BY p.code, s.year_level, s.code")->fetchAll();
$programs = $db->query("SELECT * FROM programs ORDER BY code")->fetchAll();

$page_title = "Subjects & Curriculum Catalog";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="book-open" class="w-7 h-7 text-indigo-400"></i>
                Curriculum Subjects & Course Masterlist
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Manage subject codes, unit weights, tuition rates, laboratory fees, and prerequisites.
            </p>
        </div>

        <button onclick="openModal('add-subject-modal')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            Add New Subject
        </button>
    </div>

    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Institutional Course Catalog (<?= count($subjects) ?> Subjects)</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="subj-table" placeholder="Search subject code, title, program..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="subj-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Descriptive Title</th>
                        <th>Program</th>
                        <th>Year & Sem</th>
                        <th class="text-center">Lec</th>
                        <th class="text-center">Lab</th>
                        <th class="text-center">Total Units</th>
                        <th>Tuition Rate</th>
                        <th>Lab Fee</th>
                        <th>Prerequisites</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($s['code']) ?></td>
                            <td class="font-semibold text-white"><?= htmlspecialchars($s['title']) ?></td>
                            <td class="font-medium text-slate-300"><?= htmlspecialchars($s['program_code']) ?></td>
                            <td class="text-xs text-slate-300">Yr <?= htmlspecialchars($s['year_level']) ?> &bull; <?= htmlspecialchars($s['semester']) ?></td>
                            <td class="text-center text-slate-400"><?= htmlspecialchars($s['lecture_units']) ?></td>
                            <td class="text-center text-slate-400"><?= htmlspecialchars($s['lab_units']) ?></td>
                            <td class="text-center font-bold text-white"><?= htmlspecialchars($s['total_units']) ?></td>
                            <td class="font-mono text-slate-300 text-xs"><?= format_currency($s['tuition_rate_per_unit']) ?>/unit</td>
                            <td class="font-mono text-slate-300 text-xs"><?= format_currency($s['lab_fee']) ?></td>
                            <td class="text-xs text-slate-400"><?= htmlspecialchars($s['prerequisites']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add Subject Modal -->
<div id="add-subject-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="book-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Create New Course Subject</h3>
                    <p class="text-xs text-slate-400">Define curriculum course parameters</p>
                </div>
            </div>
            <button onclick="closeModal('add-subject-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="subjects.php" class="space-y-4 text-xs">
            <input type="hidden" name="add_subject" value="1">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Subject Code</label>
                    <input type="text" name="code" required placeholder="e.g. CS301" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Program</label>
                    <select name="program_id" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Descriptive Title</label>
                <input type="text" name="title" required placeholder="e.g. Software Engineering & Design Patterns" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-4 gap-2">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Lec Units</label>
                    <input type="number" name="lecture_units" value="2" min="0" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white text-center focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Lab Units</label>
                    <input type="number" name="lab_units" value="1" min="0" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white text-center focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Year Level</label>
                    <select name="year_level" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3" selected>3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Semester</label>
                    <select name="semester" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="1st Semester">1st Sem</option>
                        <option value="2nd Semester">2nd Sem</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Lab Fee (₱)</label>
                    <input type="number" step="0.01" name="lab_fee" value="1500.00" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Prerequisites</label>
                    <input type="text" name="prerequisites" placeholder="e.g. CS201, CS202" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('add-subject-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">
                    Save Subject
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
