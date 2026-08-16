<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'registrar', 'department']);

$db = getDB();
$programs = $db->query("SELECT p.*, d.name as department_name FROM programs p JOIN departments d ON p.department_id = d.id ORDER BY p.code")->fetchAll();

$selected_program_id = intval($_GET['program_id'] ?? ($programs[0]['id'] ?? 1));

// Fetch subjects for selected program
$stmt = $db->prepare("SELECT * FROM subjects WHERE program_id = ? ORDER BY year_level ASC, semester ASC, code ASC");
$stmt->execute([$selected_program_id]);
$subjects = $stmt->fetchAll();

// Group subjects by Year & Semester
$curriculum = [];
foreach ($subjects as $sub) {
    $key = "Year " . $sub['year_level'] . " - " . $sub['semester'];
    $curriculum[$key][] = $sub;
}

$page_title = "Programs & Curriculum Checklist";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="layers" class="w-7 h-7 text-indigo-400"></i>
                Academic Programs & Curriculum Catalog
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Official institutional program curriculum checklists, prerequisite chains, and unit structures.
            </p>
        </div>

        <a href="<?= url('modules/registrar/clearance_queue.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            &larr; Back to Step 4 Evaluation Queue
        </a>
    </div>

    <!-- Program Selector Tabs -->
    <div class="flex flex-wrap gap-2">
        <?php foreach ($programs as $prog): ?>
            <a href="?program_id=<?= $prog['id'] ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $prog['id'] == $selected_program_id ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-900 border border-slate-800 text-slate-300 hover:bg-slate-800' ?>">
                <?= htmlspecialchars($prog['code']) ?> &bull; <?= htmlspecialchars($prog['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Curriculum Tables per Year/Sem -->
    <div class="space-y-6">
        <?php if (empty($curriculum)): ?>
            <div class="glass-card p-12 text-center text-slate-500">
                No subjects registered for this program.
            </div>
        <?php else: ?>
            <?php foreach ($curriculum as $term_title => $term_subs): ?>
                <div class="glass-card p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            <?= htmlspecialchars($term_title) ?>
                        </h2>
                        <span class="text-xs font-semibold text-slate-400">
                            <?= count($term_subs) ?> Subjects &bull; <?= array_sum(array_column($term_subs, 'total_units')) ?> Total Units
                        </span>
                    </div>

                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Descriptive Title</th>
                                    <th class="text-center">Lec Units</th>
                                    <th class="text-center">Lab Units</th>
                                    <th class="text-center">Total Units</th>
                                    <th>Tuition / Unit</th>
                                    <th>Lab Fee</th>
                                    <th>Prerequisites</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($term_subs as $s): ?>
                                    <tr>
                                        <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($s['code']) ?></td>
                                        <td class="font-semibold text-white"><?= htmlspecialchars($s['title']) ?></td>
                                        <td class="text-center text-slate-400"><?= htmlspecialchars($s['lecture_units']) ?></td>
                                        <td class="text-center text-slate-400"><?= htmlspecialchars($s['lab_units']) ?></td>
                                        <td class="text-center font-bold text-white"><?= htmlspecialchars($s['total_units']) ?></td>
                                        <td class="font-mono text-slate-300"><?= format_currency($s['tuition_rate_per_unit']) ?></td>
                                        <td class="font-mono text-slate-300"><?= format_currency($s['lab_fee']) ?></td>
                                        <td>
                                            <span class="px-2 py-0.5 rounded text-[11px] <?= $s['prerequisites'] !== 'None' ? 'bg-amber-950/80 text-amber-300 border border-amber-800' : 'text-slate-500' ?>">
                                                <?= htmlspecialchars($s['prerequisites']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
