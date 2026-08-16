<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'department']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// 1. AUTO-HEALING SYNC: Ensure all students have valid program and clearance record for active term
$db->query("UPDATE students SET program_id = (SELECT id FROM programs WHERE code = 'BSIT' LIMIT 1) WHERE program_id IS NULL OR program_id = 0");

$auto_sync_sql = "SELECT s.id 
                  FROM students s 
                  JOIN programs p ON s.program_id = p.id 
                  WHERE s.id NOT IN (SELECT student_id FROM clearance_requests WHERE academic_term_id = ?)";
$auto_sync_params = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $auto_sync_sql .= " AND p.department_id = ?";
    $auto_sync_params[] = $user['department_id'];
}
$stmt_sync = $db->prepare($auto_sync_sql);
$stmt_sync->execute($auto_sync_params);
$to_init_ids = $stmt_sync->fetchAll(PDO::FETCH_COLUMN);

foreach ($to_init_ids as $sid) {
    init_student_clearance($sid, $active_term['id']);
}

// Handle Direct Step 1 Approval Form POST (Guaranteed 100% Reliable Execution)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['direct_approve_step1'])) {
    $stage_id = intval($_POST['clearance_stage_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? 'Enrollment form verified and student cleared by Department Dean.');
    $upd_prog = intval($_POST['program_id'] ?? 0);
    $upd_year = intval($_POST['year_level'] ?? 0);
    $upd_sem = trim($_POST['enrolling_semester'] ?? '');
    $upd_stat = trim($_POST['academic_status'] ?? '');

    if ($stage_id > 0) {
        $stmt_stg = $db->prepare("SELECT cs.*, cr.student_id, cr.id as clearance_id, s.first_name, s.last_name, s.student_no 
                                  FROM clearance_stages cs 
                                  JOIN clearance_requests cr ON cs.clearance_id = cr.id 
                                  JOIN students s ON cr.student_id = s.id 
                                  WHERE cs.id = ?");
        $stmt_stg->execute([$stage_id]);
        $stg_rec = $stmt_stg->fetch();

        if ($stg_rec) {
            // Update stage to Cleared
            $db->prepare("UPDATE clearance_stages SET status = 'Cleared', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() WHERE id = ?")
               ->execute([$user['id'], $user['full_name'], $remarks, $stage_id]);

            // Update student verified details
            if ($upd_prog > 0 || $upd_year > 0 || !empty($upd_sem) || !empty($upd_stat)) {
                $upd_fields = [];
                $upd_vals = [];
                if ($upd_prog > 0) { $upd_fields[] = "program_id = ?"; $upd_vals[] = $upd_prog; }
                if ($upd_year > 0) { $upd_fields[] = "year_level = ?"; $upd_vals[] = $upd_year; }
                if (in_array($upd_sem, ['1st Semester', '2nd Semester'])) { $upd_fields[] = "enrolling_semester = ?"; $upd_vals[] = $upd_sem; }
                if (in_array($upd_stat, ['Regular', 'Irregular'])) { $upd_fields[] = "academic_status = ?"; $upd_vals[] = $upd_stat; }
                if (!empty($upd_fields)) {
                    $upd_vals[] = $stg_rec['student_id'];
                    $db->prepare("UPDATE students SET " . implode(', ', $upd_fields) . " WHERE id = ?")->execute($upd_vals);
                }
            }

            // Advance current_step to 2 (Library Clearance)
            $db->prepare("UPDATE clearance_requests SET current_step = GREATEST(current_step, 2), overall_status = 'In Progress' WHERE id = ?")
               ->execute([$stg_rec['clearance_id']]);

            log_activity('CLEARANCE_SIGNED', "Signed Step 1 (Department Initial) for {$stg_rec['first_name']} {$stg_rec['last_name']} ({$stg_rec['student_no']})", $user['id']);
            set_flash('success', "Successfully confirmed approval and signed Step 1 Enrollment Clearance for {$stg_rec['first_name']} {$stg_rec['last_name']}! Student advanced to Step 2 (Library).");
            header("Location: " . url('modules/department/initial_queue.php'));
            exit;
        }
    }
}

// Handle Direct Student Inclusion in Step 1 Queue
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['init_student_clearance'])) {
    $init_student_id = intval($_POST['student_id'] ?? 0);
    if ($init_student_id > 0) {
        $res = init_student_clearance($init_student_id, $active_term['id']);
        if ($res) {
            set_flash('success', 'Student successfully included in Step 1 clearance queue for Academic Year ' . $active_term['academic_year'] . ' (' . $active_term['semester'] . ').');
        } else {
            set_flash('error', 'Student is already initialized for this term or could not be included.');
        }
        header("Location: " . url('modules/department/initial_queue.php'));
        exit;
    }
}

// 2. Fetch all student clearance & enrollment records for Step 1 from Database
$query = "SELECT cs.id as stage_id, cs.status as stage_status, cs.signed_at, cs.remarks, cs.officer_name,
                 cr.id as clearance_id, cr.current_step, cr.overall_status,
                 s.id as student_id, s.student_no, s.first_name, s.middle_name, s.last_name,
                 s.birth_date, s.age, s.sex, s.religion, s.civil_status, s.birth_place, s.address,
                 s.guardian_name, s.guardian_contact, s.enrolling_semester,
                 s.contact_no, s.year_level, s.academic_status, s.program_id,
                 p.code as program_code, p.name as program_name, p.department_id,
                 u.email as student_email
          FROM clearance_stages cs
          JOIN clearance_requests cr ON cs.clearance_id = cr.id
          JOIN students s ON cr.student_id = s.id
          JOIN programs p ON s.program_id = p.id
          LEFT JOIN users u ON s.user_id = u.id
          WHERE cs.step_number = 1 AND cr.academic_term_id = ? ";

$params = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $query .= " AND (p.department_id = ? OR p.code IN ('BSIT', 'BSCS')) ";
    $params[] = $user['department_id'];
}
$query .= " ORDER BY (CASE WHEN cs.status = 'Pending' AND s.birth_date IS NOT NULL AND s.birth_date != '' THEN 1 WHEN cs.status = 'Pending' THEN 2 WHEN cs.status = 'Flagged' THEN 3 ELSE 4 END), s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Build Safe JSON-ready Lookup Array
$students_lookup = [];
foreach ($students as $row) {
    $has_form = !empty($row['birth_date']) && !empty($row['guardian_name']) && !empty($row['address']);
    $students_lookup[$row['stage_id']] = [
        'stage_id' => intval($row['stage_id']),
        'student_id' => intval($row['student_id']),
        'student_no' => $row['student_no'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'] ?? '',
        'last_name' => $row['last_name'],
        'full_name' => trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']),
        'birth_date' => $row['birth_date'] ? date('F d, Y', strtotime($row['birth_date'])) : 'Not Provided',
        'raw_birth_date' => $row['birth_date'] ?? '',
        'age' => $row['age'] ?? 'N/A',
        'sex' => $row['sex'] ?? 'Male',
        'religion' => $row['religion'] ?? 'N/A',
        'civil_status' => $row['civil_status'] ?? 'Single',
        'birth_place' => $row['birth_place'] ?? 'Not Specified',
        'contact_no' => $row['contact_no'] ?? 'N/A',
        'email' => $row['student_email'] ?? 'N/A',
        'address' => $row['address'] ?? 'N/A',
        'guardian_name' => $row['guardian_name'] ?? 'N/A',
        'guardian_contact' => $row['guardian_contact'] ?? 'N/A',
        'program_id' => intval($row['program_id']),
        'program_code' => $row['program_code'],
        'program' => $row['program_code'] . ' - ' . $row['program_name'],
        'year_level' => intval($row['year_level']),
        'semester' => $row['enrolling_semester'] ?? '1st Semester',
        'academic_status' => $row['academic_status'] ?? 'Regular',
        'has_submitted_form' => $has_form,
        'status' => $row['stage_status'],
        'remarks' => $row['remarks'] ?? '',
        'officer_name' => $row['officer_name'] ?? '',
        'signed_at' => $row['signed_at'] ? date('M d, Y h:i A', strtotime($row['signed_at'])) : ''
    ];
}

// Fetch uninitialized department students who can be included
$uninit_query = "SELECT s.id, s.student_no, s.first_name, s.middle_name, s.last_name, s.contact_no,
                        p.code as program_code, p.name as program_name, s.year_level, s.academic_status
                 FROM students s
                 JOIN programs p ON s.program_id = p.id
                 WHERE s.id NOT IN (SELECT student_id FROM clearance_requests WHERE academic_term_id = ?) ";
$uninit_params = [$active_term['id']];
if ($user['role'] === 'department' && !empty($user['department_id'])) {
    $uninit_query .= " AND p.department_id = ? ";
    $uninit_params[] = $user['department_id'];
}
$uninit_query .= " ORDER BY s.last_name ASC";
$stmt_uninit = $db->prepare($uninit_query);
$stmt_uninit->execute($uninit_params);
$uninitialized_students = $stmt_uninit->fetchAll();

// Calculate counts
$ready_for_approval_count = count(array_filter($students, function($s) {
    $has_form = !empty($s['birth_date']) && !empty($s['guardian_name']) && !empty($s['address']);
    return $has_form && $s['stage_status'] === 'Pending';
}));
$awaiting_submission_count = count(array_filter($students, function($s) {
    $has_form = !empty($s['birth_date']) && !empty($s['guardian_name']) && !empty($s['address']);
    return !$has_form && $s['stage_status'] === 'Pending';
}));
$cleared_count = count(array_filter($students, fn($s) => $s['stage_status'] === 'Cleared'));
$flagged_count = count(array_filter($students, fn($s) => $s['stage_status'] === 'Flagged'));

$page_title = "Step 1: Department Initial Clearance & Enrollment Approval Queue";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Step 1 of 5
                </span>
                <span class="text-xs text-slate-400">Department Initial Clearance & Enrollment Form Verification</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="user-check" class="w-7 h-7 text-indigo-400"></i>
                Department Initial Clearance & Enrollment Queue
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Review submitted student enrollment forms (BSIT & BSCS), verify demographic details, and confirm official Step 1 clearance approval.
            </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="window.location.reload()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all cursor-pointer" title="Fetch latest enrollment submissions from database">
                <i data-lucide="refresh-cw" class="w-4 h-4 text-indigo-400"></i>
                <span>Refresh Database</span>
            </button>

            <?php if (!empty($uninitialized_students)): ?>
                <button type="button" onclick="openModal('include-student-modal')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all cursor-pointer">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Include Student (<?= count($uninitialized_students) ?> Available)</span>
                </button>
            <?php endif; ?>

            <a href="<?= url('modules/department/schedule_queue.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
                <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i>
                Go to Step 5 (Final Scheduling) &rarr;
            </a>
        </div>
    </div>

    <!-- Live Stats Banner -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="glass-card p-4 flex items-center gap-4 border-l-4 border-l-emerald-500">
            <div class="w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400 shadow-md">
                <i data-lucide="file-check-2" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Ready for Approval</div>
                <div class="text-lg font-bold text-emerald-400 flex items-center gap-1.5">
                    <?= $ready_for_approval_count ?>
                    <?php if ($ready_for_approval_count > 0): ?>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 animate-pulse">Action Needed</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-400">
                <i data-lucide="hourglass" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Awaiting Student Form</div>
                <div class="text-lg font-bold text-amber-400"><?= $awaiting_submission_count ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-950/80 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Approved / Cleared</div>
                <div class="text-lg font-bold text-white"><?= $cleared_count ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-950/80 border border-rose-500/40 flex items-center justify-center text-rose-400">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Flagged Holds</div>
                <div class="text-lg font-bold text-rose-400"><?= $flagged_count ?></div>
            </div>
        </div>
    </div>

    <!-- Student Table Card -->
    <div class="glass-card p-6 space-y-4">
        
        <!-- Filter Tabs & Search Header -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-slate-800">
            <!-- Tabs -->
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" onclick="filterStep1Queue('all', this)" class="filter-tab-btn active px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 text-white shadow-md transition-all cursor-pointer">
                    All Records (<?= count($students) ?>)
                </button>
                <button type="button" onclick="filterStep1Queue('ready', this)" class="filter-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition-all cursor-pointer flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Submitted Forms (<?= $ready_for_approval_count ?>)
                </button>
                <button type="button" onclick="filterStep1Queue('pending_form', this)" class="filter-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition-all cursor-pointer">
                    Awaiting Student Form (<?= $awaiting_submission_count ?>)
                </button>
                <button type="button" onclick="filterStep1Queue('cleared', this)" class="filter-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition-all cursor-pointer">
                    Cleared (<?= $cleared_count ?>)
                </button>
                <?php if ($flagged_count > 0): ?>
                    <button type="button" onclick="filterStep1Queue('flagged', this)" class="filter-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-rose-300 hover:bg-rose-950/60 transition-all cursor-pointer">
                        Flagged (<?= $flagged_count ?>)
                    </button>
                <?php endif; ?>
            </div>

            <!-- Course Program filter & Search input -->
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <select id="program-filter-select" onchange="filterProgramRows(this.value)" class="bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-semibold">
                    <option value="ALL">All Degree Programs</option>
                    <option value="BSIT">BSIT - Info Tech</option>
                    <option value="BSCS">BSCS - Computer Science</option>
                </select>
                <div class="w-full sm:w-64">
                    <input type="text" data-table-search="dept-table" placeholder="Search student name, ID, course..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="dept-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Full Name</th>
                        <th>Program</th>
                        <th>Year & Sem</th>
                        <th>Classification</th>
                        <th>Enrollment Form Status</th>
                        <th>Step 1 Status</th>
                        <th class="text-right">Department Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500 italic">No student clearance records found in Step 1 queue for this term.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): 
                            $has_submitted_form = !empty($row['birth_date']) && !empty($row['guardian_name']) && !empty($row['address']);
                            $filter_category = 'all';
                            if ($row['stage_status'] === 'Cleared') {
                                $filter_category = 'cleared';
                            } elseif ($row['stage_status'] === 'Flagged') {
                                $filter_category = 'flagged';
                            } elseif ($has_submitted_form) {
                                $filter_category = 'ready';
                            } else {
                                $filter_category = 'pending_form';
                            }
                            $stage_id = intval($row['stage_id']);
                        ?>
                            <!-- Main Student Row -->
                            <tr id="student-row-<?= $stage_id ?>" data-category="<?= $filter_category ?>" data-program="<?= htmlspecialchars($row['program_code']) ?>">
                                <td class="font-mono font-bold text-indigo-400">
                                    <button type="button" onclick="toggleStudentReviewPanel(<?= $stage_id ?>)" class="hover:underline text-indigo-400 font-bold cursor-pointer flex items-center gap-1" title="Click to review full enrollment dossier">
                                        <span><?= htmlspecialchars($row['student_no']) ?></span>
                                        <i data-lucide="chevron-down" id="chevron-<?= $stage_id ?>" class="w-3.5 h-3.5 text-slate-500 transition-transform"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . (!empty($row['middle_name']) ? ' ' . $row['middle_name'] : '')) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($row['contact_no'] ?? 'No contact') ?></div>
                                </td>
                                <td>
                                    <span class="font-bold text-indigo-300 px-2 py-0.5 rounded bg-indigo-950/60 border border-indigo-500/30"><?= htmlspecialchars($row['program_code']) ?></span>
                                </td>
                                <td class="text-slate-300">
                                    <span class="font-semibold text-white">Year <?= htmlspecialchars($row['year_level']) ?></span> &bull; 
                                    <span class="text-xs text-indigo-300"><?= htmlspecialchars($row['enrolling_semester'] ?? '1st Sem') ?></span>
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                        <?= htmlspecialchars($row['academic_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_submitted_form): ?>
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-500/40 shadow-sm">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                                Form Submitted
                                            </span>
                                            <div class="text-[10px] text-slate-400 truncate max-w-[180px]" title="<?= htmlspecialchars($row['address']) ?>">
                                                DOB: <?= $row['birth_date'] ? date('M d, Y', strtotime($row['birth_date'])) : '—' ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-950/80 text-amber-300 border border-amber-500/40">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            Awaiting Student Details
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= get_status_badge($row['stage_status']) ?></td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" onclick="toggleStudentReviewPanel(<?= $stage_id ?>)" class="px-3.5 py-1.5 <?= ($row['stage_status'] !== 'Cleared' && $has_submitted_form) ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/30' : 'bg-indigo-600 hover:bg-indigo-500 text-white' ?> text-xs font-bold rounded-xl flex items-center gap-1.5 transition-all cursor-pointer">
                                            <i data-lucide="<?= ($row['stage_status'] === 'Cleared') ? 'file-check' : 'file-signature' ?>" class="w-3.5 h-3.5 pointer-events-none"></i>
                                            <span class="pointer-events-none"><?= ($row['stage_status'] === 'Cleared') ? 'View Form' : ($has_submitted_form ? 'Review & Approve' : 'Review Form') ?></span>
                                        </button>

                                        <?php if ($row['stage_status'] !== 'Cleared'): ?>
                                            <button type="button" onclick="triggerFlagModal(<?= $stage_id ?>)" class="p-1.5 bg-rose-950/80 hover:bg-rose-900 text-rose-300 hover:text-white rounded-lg border border-rose-500/40 transition-all cursor-pointer" title="Flag Academic Hold">
                                                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-400 pointer-events-none"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- IN-ROW EXPANDABLE REVIEW & CONFIRMED APPROVAL PANEL -->
                            <tr id="review-panel-<?= $stage_id ?>" class="review-panel-row bg-slate-900/95 border-b border-indigo-500/40" style="display: none;" data-category="<?= $filter_category ?>" data-program="<?= htmlspecialchars($row['program_code']) ?>">
                                <td colspan="8" class="p-4 sm:p-6">
                                    <div class="glass-card border border-indigo-500/40 rounded-2xl p-5 sm:p-6 space-y-5 shadow-2xl bg-slate-900">
                                        
                                        <!-- Header of Panel -->
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/50 flex items-center justify-center text-indigo-400 shadow-md">
                                                    <i data-lucide="file-signature" class="w-5 h-5"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                                                        <span><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . (!empty($row['middle_name']) ? ' ' . $row['middle_name'] : '')) ?></span>
                                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40"><?= htmlspecialchars($row['program_code']) ?></span>
                                                    </h3>
                                                    <p class="text-xs text-slate-400">
                                                        Student No: <span class="font-mono text-indigo-300 font-bold"><?= htmlspecialchars($row['student_no']) ?></span> &bull; 
                                                        Academic Term: <span class="text-slate-200 font-semibold"><?= htmlspecialchars($active_term['academic_year']) ?> (<?= htmlspecialchars($active_term['semester']) ?>)</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <button type="button" onclick="toggleStudentReviewPanel(<?= $stage_id ?>)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 flex items-center gap-1 cursor-pointer self-start sm:self-auto">
                                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                                <span>Close Review</span>
                                            </button>
                                        </div>

                                        <!-- Status Banner -->
                                        <?php if ($has_submitted_form): ?>
                                            <div class="p-3.5 rounded-xl text-xs flex items-center gap-2.5 bg-emerald-950/80 border border-emerald-500/40 text-emerald-200">
                                                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0"></i>
                                                <div>
                                                    <span class="font-bold text-white block">Official Enrollment Form Submitted by Student</span>
                                                    <span>Demographics, guardian details, and degree selection received from student dashboard. Ready for official department sign-off.</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3.5 rounded-xl text-xs flex items-center gap-2.5 bg-amber-950/80 border border-amber-500/40 text-amber-200">
                                                <i data-lucide="clock" class="w-5 h-5 text-amber-400 shrink-0"></i>
                                                <div>
                                                    <span class="font-bold text-white block">Awaiting Student Submission</span>
                                                    <span>The student has not yet submitted the complete Step 1 Enrollment Form. Basic records shown below.</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Full Student Demographic Profile Grid -->
                                        <div class="space-y-3">
                                            <h4 class="font-bold text-indigo-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                                <i data-lucide="user" class="w-3.5 h-3.5"></i>
                                                1. Personal Demographics & Contact Information
                                            </h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs bg-slate-950/80 p-4 rounded-xl border border-slate-800">
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Date of Birth</span>
                                                    <span class="text-white font-semibold"><?= $row['birth_date'] ? date('F d, Y', strtotime($row['birth_date'])) : 'Not Provided' ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Age</span>
                                                    <span class="text-white font-semibold"><?= !empty($row['age']) ? $row['age'] . ' yrs old' : '—' ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Sex</span>
                                                    <span class="text-white font-semibold"><?= htmlspecialchars($row['sex'] ?? 'Male') ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Civil Status</span>
                                                    <span class="text-white font-semibold"><?= htmlspecialchars($row['civil_status'] ?? 'Single') ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Religion</span>
                                                    <span class="text-white font-semibold"><?= htmlspecialchars($row['religion'] ?? '—') ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Mobile Number</span>
                                                    <span class="text-indigo-300 font-mono font-semibold"><?= htmlspecialchars($row['contact_no'] ?? '—') ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Place of Birth</span>
                                                    <span class="text-slate-200 font-semibold"><?= htmlspecialchars($row['birth_place'] ?? '—') ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block text-[10px] uppercase">Current Residential Address</span>
                                                    <span class="text-slate-200"><?= htmlspecialchars($row['address'] ?? '—') ?></span>
                                                </div>
                                                <div class="sm:col-span-2 pt-2 border-t border-slate-800">
                                                    <span class="text-slate-500 block text-[10px] uppercase">Parent / Guardian Name</span>
                                                    <span class="text-white font-semibold"><?= htmlspecialchars($row['guardian_name'] ?? '—') ?></span>
                                                </div>
                                                <div class="sm:col-span-2 pt-2 border-t border-slate-800">
                                                    <span class="text-slate-500 block text-[10px] uppercase">Parent / Guardian Contact</span>
                                                    <span class="text-indigo-300 font-mono font-semibold"><?= htmlspecialchars($row['guardian_contact'] ?? '—') ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Official Approval Form -->
                                        <form method="POST" action="<?= url('modules/department/initial_queue.php') ?>" class="space-y-4 pt-2">
                                            <input type="hidden" name="direct_approve_step1" value="1">
                                            <input type="hidden" name="clearance_stage_id" value="<?= $stage_id ?>">

                                            <div class="bg-slate-950/80 p-4 rounded-xl border border-slate-800 space-y-3">
                                                <h4 class="font-bold text-indigo-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                                                    <i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i>
                                                    2. Verified Academic Degree Program & Enrollment Level
                                                </h4>
                                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-slate-400 font-semibold mb-1">Approved Degree Program <span class="text-rose-400">*</span></label>
                                                        <select name="program_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-semibold">
                                                            <option value="2" <?= ($row['program_code'] === 'BSIT') ? 'selected' : '' ?>>BSIT - Info Tech</option>
                                                            <option value="1" <?= ($row['program_code'] === 'BSCS') ? 'selected' : '' ?>>BSCS - Computer Science</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-slate-400 font-semibold mb-1">Year Level <span class="text-rose-400">*</span></label>
                                                        <select name="year_level" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                                            <option value="1" <?= ($row['year_level'] == 1) ? 'selected' : '' ?>>1st Year</option>
                                                            <option value="2" <?= ($row['year_level'] == 2) ? 'selected' : '' ?>>2nd Year</option>
                                                            <option value="3" <?= ($row['year_level'] == 3) ? 'selected' : '' ?>>3rd Year</option>
                                                            <option value="4" <?= ($row['year_level'] == 4) ? 'selected' : '' ?>>4th Year</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-slate-400 font-semibold mb-1">Enrolling Semester <span class="text-rose-400">*</span></label>
                                                        <select name="enrolling_semester" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                                            <option value="1st Semester" <?= ($row['enrolling_semester'] === '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                                                            <option value="2nd Semester" <?= ($row['enrolling_semester'] === '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase text-slate-400 font-semibold mb-1">Academic Status <span class="text-rose-400">*</span></label>
                                                        <select name="academic_status" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                                            <option value="Regular" <?= ($row['academic_status'] === 'Regular') ? 'selected' : '' ?>>Regular Student</option>
                                                            <option value="Irregular" <?= ($row['academic_status'] === 'Irregular') ? 'selected' : '' ?>>Irregular Student</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="bg-indigo-950/40 border border-indigo-500/30 rounded-xl p-4 space-y-2">
                                                <label class="block text-[10px] uppercase font-bold text-indigo-300">Department Officer Clearance Remarks & Endorsement <span class="text-rose-400">*</span></label>
                                                <input type="text" name="remarks" value="<?= htmlspecialchars(!empty($row['remarks']) ? $row['remarks'] : 'Enrollment form verified & approved by Department Dean. Cleared for Step 2 Library.') ?>" required class="w-full bg-slate-950 border border-indigo-500/40 rounded-xl p-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-400">
                                            </div>

                                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-800">
                                                <div>
                                                    <?php if ($row['stage_status'] === 'Cleared'): ?>
                                                        <span class="text-xs text-emerald-400 font-bold flex items-center gap-1.5">
                                                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
                                                            Officially Signed & Approved by <?= htmlspecialchars($row['officer_name'] ?? 'Department Dean') ?> on <?= date('M d, Y h:i A', strtotime($row['signed_at'])) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-400 flex items-center gap-1.5">
                                                            <i data-lucide="shield-check" class="w-4 h-4 text-indigo-400"></i>
                                                            Approving will mark Step 1 as Cleared & advance student to Step 2 (Library Clearance).
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="flex items-center gap-3">
                                                    <button type="button" onclick="toggleStudentReviewPanel(<?= $stage_id ?>)" class="px-4 py-2 border border-slate-700 text-slate-300 text-xs font-semibold rounded-xl hover:bg-slate-800 transition-colors">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-600/40 flex items-center gap-2 transition-all cursor-pointer">
                                                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                                        <span><?= ($row['stage_status'] === 'Cleared') ? 'Re-confirm Step 1 Signature' : 'Confirm & Officially Sign Step 1 Clearance' ?></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

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

<!-- MODAL: INCLUDE STUDENT IN STEP 1 CLEARANCE QUEUE -->
<div id="include-student-modal" class="modal-overlay hidden">
    <div class="modal-content max-w-xl border border-indigo-500/40 rounded-2xl p-6 space-y-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950/80 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Include Student in Step 1 Queue</h3>
                    <p class="text-xs text-slate-400">Initialize clearance pipeline for student in Academic Term <?= htmlspecialchars($active_term['academic_year']) ?></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('include-student-modal')" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('modules/department/initial_queue.php') ?>" class="space-y-4 text-xs">
            <input type="hidden" name="init_student_clearance" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Select Department Student <span class="text-rose-400">*</span></label>
                <select name="student_id" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="">-- Choose Student to Initialize --</option>
                    <?php foreach ($uninitialized_students as $un): ?>
                        <option value="<?= $un['id'] ?>">
                            <?= htmlspecialchars($un['student_no']) ?> &bull; <?= htmlspecialchars($un['last_name'] . ', ' . $un['first_name']) ?> (<?= htmlspecialchars($un['program_code']) ?> - Yr <?= htmlspecialchars($un['year_level']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="p-3 bg-indigo-950/40 border border-indigo-500/30 rounded-xl text-indigo-300 text-[11px] flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-400 shrink-0 mt-0.5"></i>
                <span>Including this student will create their 5-stage clearance pipeline (Step 1 Department &rarr; Step 2 Library &rarr; Step 3 Accounting &rarr; Step 4 Registrar &rarr; Step 5 Final Scheduling).</span>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeModal('include-student-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Initialize & Include in Step 1
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Include Common Modal Templates -->
<?php include __DIR__ . '/../../includes/modals.php'; ?>

<script>
// Filter functions
let currentTabCategory = 'all';

function filterStep1Queue(category, btn) {
    currentTabCategory = category;
    document.querySelectorAll('.filter-tab-btn').forEach(b => {
        b.classList.remove('active', 'bg-indigo-600', 'text-white');
        b.classList.add('bg-slate-800', 'text-slate-300');
    });
    btn.classList.add('active', 'bg-indigo-600', 'text-white');
    btn.classList.remove('bg-slate-800', 'text-slate-300');

    applyCombinedFilters();
}

function filterProgramRows(programCode) {
    applyCombinedFilters();
}

function applyCombinedFilters() {
    const selectedProg = document.getElementById('program-filter-select').value;
    const rows = document.querySelectorAll('#dept-table tbody tr[data-category]');
    
    rows.forEach(r => {
        const cat = r.getAttribute('data-category');
        const prog = r.getAttribute('data-program');
        
        let matchCat = (currentTabCategory === 'all') || (cat === currentTabCategory);
        let matchProg = (selectedProg === 'ALL') || (prog === selectedProg);

        if (matchCat && matchProg) {
            r.style.display = r.classList.contains('review-panel-row') ? (r.getAttribute('data-open') === 'true' ? '' : 'none') : '';
        } else {
            r.style.display = 'none';
        }
    });
}

// TOGGLE EXPANDABLE IN-ROW REVIEW & CONFIRMED APPROVAL PANEL
window.toggleStudentReviewPanel = function(stageId) {
    const panel = document.getElementById('review-panel-' + stageId);
    const chevron = document.getElementById('chevron-' + stageId);
    if (!panel) return;

    const isHidden = panel.style.display === 'none' || panel.classList.contains('hidden');

    // Close all other open panels first
    document.querySelectorAll('.review-panel-row').forEach(p => {
        if (p !== panel) {
            p.style.display = 'none';
            p.classList.add('hidden');
            p.setAttribute('data-open', 'false');
        }
    });
    document.querySelectorAll('[id^="chevron-"]').forEach(ch => {
        if (ch !== chevron) ch.style.transform = 'rotate(0deg)';
    });

    if (isHidden) {
        panel.style.display = '';
        panel.classList.remove('hidden');
        panel.setAttribute('data-open', 'true');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        
        // Smooth scroll into view
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        panel.style.display = 'none';
        panel.classList.add('hidden');
        panel.setAttribute('data-open', 'false');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
};

window.triggerFlagModal = function(stageId) {
    document.getElementById('flag-stage-id').value = stageId;
    document.getElementById('flag-student-name').innerText = 'Flag Academic / Enrollment Hold (Stage ID: ' + stageId + ')';
    document.getElementById('flag-title').value = 'Step 1 Enrollment Form Revision Needed';
    document.getElementById('flag-remarks').value = 'Please review and update your enrollment form information on your dashboard.';
    openModal('flag-modal');
};

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const targetStage = urlParams.get('stage_id') || urlParams.get('review_stage_id');
    if (targetStage && window.toggleStudentReviewPanel) {
        window.toggleStudentReviewPanel(parseInt(targetStage));
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
