<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('student');

$user = current_user();
$student_id = $user['student_id'];
$active_term = get_active_term();
$db = getDB();

// Ensure student clearance record exists
init_student_clearance($student_id, $active_term['id']);

// Handle Student Enrollment Information Form Submission (Step 1)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_enrollment_form'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $sex = in_array($_POST['sex'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['sex'] : 'Male';
    $religion = trim($_POST['religion'] ?? '');
    $civil_status = in_array($_POST['civil_status'] ?? '', ['Single', 'Married', 'Widowed', 'Separated']) ? $_POST['civil_status'] : 'Single';
    $birth_place = trim($_POST['birth_place'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');
    $year_level = intval($_POST['year_level'] ?? 1);
    $program_id = intval($_POST['program_id'] ?? 1); // 1 = BSCS, 2 = BSIT
    $enrolling_semester = trim($_POST['enrolling_semester'] ?? '1st Semester');
    $academic_status = trim($_POST['academic_status'] ?? 'Regular');
    $contact_no = trim($_POST['contact_no'] ?? '');

    // Validation for mandatories
    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($address) || empty($guardian_name) || empty($guardian_contact) || empty($contact_no)) {
        set_flash('error', 'Please fill in all mandatory fields.');
    } elseif (!in_array($program_id, [1, 2])) {
        // Enforce BSIT and Computer Science (BSCS) only
        set_flash('error', 'Chosen course must be BSIT or Computer Science (BSCS).');
    } elseif (!in_array($enrolling_semester, ['1st Semester', '2nd Semester'])) {
        set_flash('error', 'Invalid semester chosen.');
    } elseif (!in_array($academic_status, ['Regular', 'Irregular'])) {
        set_flash('error', 'Invalid academic status chosen.');
    } else {
        try {
            $stmt_upd = $db->prepare("UPDATE students SET 
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                birth_date = ?,
                age = ?,
                sex = ?,
                religion = ?,
                civil_status = ?,
                birth_place = ?,
                address = ?,
                guardian_name = ?,
                guardian_contact = ?,
                year_level = ?,
                program_id = ?,
                enrolling_semester = ?,
                academic_status = ?,
                contact_no = ?
                WHERE id = ?");
            $stmt_upd->execute([
                $first_name, $middle_name, $last_name, $birth_date, $age, $sex,
                $religion, $civil_status, $birth_place, $address,
                $guardian_name, $guardian_contact, $year_level, $program_id,
                $enrolling_semester, $academic_status, $contact_no, $student_id
            ]);

            // Update user table full name
            $db->prepare("UPDATE users SET full_name = ? WHERE id = ?")
               ->execute(["$first_name $last_name", $user['id']]);

            // Ensure clearance pipeline exists & update Step 1 stage remarks
            $clearance_tmp = get_student_clearance_record($student_id, $active_term['id']);
            if (!$clearance_tmp) {
                init_student_clearance($student_id, $active_term['id']);
                $clearance_tmp = get_student_clearance_record($student_id, $active_term['id']);
            }

            if ($clearance_tmp) {
                $stmt_chk_stg1 = $db->prepare("SELECT status FROM clearance_stages WHERE clearance_id = ? AND step_number = 1");
                $stmt_chk_stg1->execute([$clearance_tmp['id']]);
                $stg1_status = $stmt_chk_stg1->fetchColumn();

                if ($stg1_status !== 'Cleared') {
                    $db->prepare("UPDATE clearance_stages SET status = 'Pending', remarks = 'Enrollment form submitted by student. Ready for Department review & approval.' WHERE clearance_id = ? AND step_number = 1")
                       ->execute([$clearance_tmp['id']]);
                    $db->prepare("UPDATE clearance_requests SET overall_status = 'In Progress' WHERE id = ?")
                       ->execute([$clearance_tmp['id']]);
                }
            }

            log_activity('ENROLLMENT_INFO_SUBMITTED', "Student $first_name $last_name submitted enrollment form for Year $year_level ($enrolling_semester).", $user['id']);

            set_flash('success', 'Enrollment Information Form successfully submitted! Your form is now in the Department Initial Clearance Queue for review and approval.');
            header("Location: " . url('modules/student/dashboard.php'));
            exit;
        } catch (Exception $e) {
            set_flash('error', 'Failed to save enrollment details: ' . $e->getMessage());
        }
    }
}

$clearance = get_student_clearance_record($student_id, $active_term['id']);

// Fetch subject loads (Step 4)
$stmt_load = $db->prepare("SELECT s.* FROM student_subject_loads sload JOIN subjects s ON sload.subject_id = s.id WHERE sload.clearance_id = ?");
$stmt_load->execute([$clearance['id']]);
$subject_loads = $stmt_load->fetchAll();

// Fetch scheduled enrollments (Step 5)
$stmt_enr = $db->prepare("SELECT sch.*, sub.code, sub.title, sub.total_units 
                         FROM student_enrollments se 
                         JOIN schedules sch ON se.schedule_id = sch.id 
                         JOIN subjects sub ON sch.subject_id = sub.id 
                         WHERE se.clearance_id = ?");
$stmt_enr->execute([$clearance['id']]);
$enrollments = $stmt_enr->fetchAll();

// Fetch Accounting Assessment
$stmt_acc = $db->prepare("SELECT * FROM accounting_assessments WHERE clearance_id = ?");
$stmt_acc->execute([$clearance['id']]);
$assessment = $stmt_acc->fetch();

// Fetch Deficiencies
$stmt_def = $db->prepare("SELECT * FROM deficiencies WHERE student_id = ? ORDER BY created_at DESC");
$stmt_def->execute([$student_id]);
$deficiencies = $stmt_def->fetchAll();

// Fetch BSCS and BSIT programs for modal dropdown
$stmt_progs = $db->query("SELECT * FROM programs WHERE code IN ('BSCS', 'BSIT') ORDER BY code ASC");
$eligible_programs = $stmt_progs->fetchAll();

$page_title = "Student Clearance Dashboard";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-8 max-w-7xl mx-auto">
    
    <!-- Welcome Header & Profile Card -->
    <div class="glass-card p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="space-y-2 z-10">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Student Portal
                </span>
                <?= get_status_badge($clearance['overall_status']) ?>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                Welcome, <?= htmlspecialchars($clearance['first_name'] . ' ' . $clearance['last_name']) ?>!
            </h1>
            <p class="text-xs sm:text-sm text-slate-400">
                Student No: <span class="font-mono text-white font-semibold"><?= htmlspecialchars($clearance['student_no']) ?></span> &bull; 
                Program: <span class="text-indigo-300 font-semibold"><?= htmlspecialchars($clearance['program_code']) ?> (<?= htmlspecialchars($clearance['program_name']) ?>)</span> &bull; 
                Year Level: <span class="text-white font-semibold"><?= htmlspecialchars($clearance['year_level']) ?></span> &bull; 
                Semester: <span class="text-indigo-300 font-semibold"><?= htmlspecialchars($clearance['enrolling_semester'] ?? '1st Semester') ?></span> &bull; 
                Status: <span class="text-slate-300 font-semibold"><?= htmlspecialchars($clearance['academic_status']) ?></span>
            </p>
        </div>


        <!-- Decorative background glow -->
        <div class="absolute -right-10 -top-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Active Deficiencies Alert (if any) -->
    <?php if (!empty($deficiencies)): ?>
        <?php 
        $open_deficiencies = array_filter($deficiencies, fn($d) => $d['status'] === 'Open');
        if (!empty($open_deficiencies)):
        ?>
            <div class="bg-rose-950/50 border border-rose-500/40 rounded-2xl p-5 shadow-xl">
                <div class="flex items-center gap-2 text-rose-300 font-bold text-sm mb-3">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400"></i>
                    Action Required: You Have Active Clearance Holds
                </div>
                <div class="space-y-2">
                    <?php foreach ($open_deficiencies as $def): ?>
                        <div class="bg-slate-900/80 p-3.5 rounded-xl border border-rose-500/30 flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold text-white text-xs"><?= htmlspecialchars($def['title']) ?></div>
                                <div class="text-xs text-slate-300 mt-1"><?= nl2br(htmlspecialchars($def['description'])) ?></div>
                                <div class="text-[10px] text-slate-500 mt-1">Issued: <?= format_date($def['created_at']) ?></div>
                            </div>
                            <span class="px-2 py-1 rounded text-[10px] font-bold bg-rose-900/60 text-rose-300 border border-rose-500/40 shrink-0">
                                Open Hold
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 5-STEP CLEARANCE SIGNING PIPELINE (VISUAL STEPPER) -->
    <div class="glass-card p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="route" class="w-5 h-5 text-indigo-400"></i>
                    Enrollment Clearance Progression Tracker
                </h2>
                <p class="text-xs text-slate-400">Sequential signing pipeline required for official enrollment &bull; <strong class="text-indigo-300">Tap Step 1 to fill enrollment form</strong></p>
            </div>
            <div class="text-left sm:text-right">
                <span class="text-xs font-semibold text-slate-400">Current Phase:</span>
                <div class="text-sm font-bold text-indigo-400">
                    <?php
                    $step_names = [
                        1 => '1. Department Initial Clearance',
                        2 => '2. Library Clearance',
                        3 => '3. Accounting Clearance',
                        4 => '4. Registrar Load Evaluation',
                        5 => '5. Department Final Scheduling',
                        6 => '🎉 Officially Enrolled!'
                    ];
                    echo $step_names[$clearance['current_step']] ?? 'In Progress';
                    ?>
                </div>
            </div>
        </div>

        <!-- Stepper Node Line -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 pt-2">
            <?php 
            $icons = [
                1 => 'user-check',
                2 => 'book-open',
                3 => 'credit-card',
                4 => 'clipboard-check',
                5 => 'clock'
            ];

            foreach ($clearance['stages'] as $stg): 
                $num = $stg['step_number'];
                $is_cleared = ($stg['status'] === 'Cleared');
                $is_flagged = ($stg['status'] === 'Flagged');
                $is_current = ($clearance['current_step'] == $num && !$is_cleared);
                
                $node_class = $is_cleared ? 'completed' : ($is_flagged ? 'flagged' : ($is_current ? 'active' : 'pending'));
                $is_step_1 = ($num === 1);
                $card_click = $is_step_1 ? 'onclick="openEnrollmentModal()"' : '';
                $card_cursor = $is_step_1 ? 'cursor-pointer hover:border-indigo-400 hover:bg-slate-900/90 hover:scale-[1.02] active:scale-[0.99] group/card ring-1 ring-indigo-500/30' : 'hover:border-slate-700';
            ?>
                <div <?= $card_click ?> class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between space-y-3 transition-all <?= $card_cursor ?>" <?= $is_step_1 ? 'title="Click to fill or update your Enrollment Information Form"' : '' ?>>
                    <div class="flex items-center justify-between">
                        <div class="step-node <?= $node_class ?>">
                            <?php if ($is_cleared): ?>
                                <i data-lucide="check" class="w-6 h-6"></i>
                            <?php elseif ($is_flagged): ?>
                                <i data-lucide="x" class="w-6 h-6"></i>
                            <?php else: ?>
                                <i data-lucide="<?= $icons[$num] ?>" class="w-5 h-5"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <?php if ($is_step_1): ?>
                                <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-950 text-indigo-300 border border-indigo-500/40 group-hover/card:bg-indigo-600 group-hover/card:text-white transition-colors flex items-center gap-1">
                                    <i data-lucide="edit-3" class="w-2.5 h-2.5"></i>
                                    Tap Form
                                </span>
                            <?php endif; ?>
                            <span class="text-[11px] font-bold <?= $is_cleared ? 'text-emerald-400' : ($is_flagged ? 'text-rose-400' : ($is_current ? 'text-indigo-400 animate-pulse' : 'text-slate-500')) ?>">
                                Step <?= $num ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="font-bold text-xs text-white leading-snug"><?= htmlspecialchars($stg['stage_title']) ?></div>
                        <div class="mt-1">
                            <?= get_status_badge($stg['status']) ?>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800/80 space-y-1">
                        <?php if ($is_step_1 && !$is_cleared): ?>
                            <div class="text-indigo-300 font-semibold flex items-center gap-1">
                                <i data-lucide="file-text" class="w-3 h-3 text-indigo-400"></i>
                                <span><?= !empty($clearance['birth_date']) ? 'Form Ready &bull; Tap to Edit' : '👉 Tap to Fill Form' ?></span>
                            </div>
                            <div class="text-slate-500 text-[10px]">Awaiting Department Approval</div>
                        <?php elseif ($is_cleared): ?>
                            <div class="text-emerald-400 font-semibold truncate">Signed: <?= htmlspecialchars($stg['officer_name'] ?? 'Officer') ?></div>
                            <div class="text-slate-500 text-[10px]"><?= format_date($stg['signed_at']) ?></div>
                            <?php if ($stg['remarks']): ?>
                                <div class="text-slate-300 italic text-[10px] bg-slate-950/60 p-1.5 rounded">"<?= htmlspecialchars($stg['remarks']) ?>"</div>
                            <?php endif; ?>
                        <?php elseif ($is_flagged): ?>
                            <div class="text-rose-400 font-semibold">Hold Issued</div>
                            <div class="text-slate-300 text-[10px]"><?= htmlspecialchars($stg['remarks'] ?? 'Deficiency flagged.') ?></div>
                        <?php elseif ($is_current): ?>
                            <div class="text-indigo-300 font-semibold">Awaiting Signatory Review</div>
                        <?php else: ?>
                            <div class="text-slate-500">Prerequisite to Step <?= $num - 1 ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Details Grid: Evaluated Subject Load & Accounting Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Subject Load / Enrolled Subjects -->
        <div class="lg:col-span-7 space-y-6">
            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i data-lucide="book-open" class="w-4 h-4 text-indigo-400"></i>
                            <?= !empty($enrollments) ? 'Officially Enrolled Class Schedules' : 'Evaluated Subject Load (Registrar Step 4)' ?>
                        </h3>
                        <p class="text-xs text-slate-400">
                            <?= !empty($enrollments) ? 'Final sections and room assignments' : 'Approved subjects for enrollment this semester' ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($enrollments)): ?>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Section</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th>Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $tot_units = 0;
                                foreach ($enrollments as $enr): 
                                    $tot_units += $enr['total_units'];
                                ?>
                                    <tr>
                                        <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($enr['code']) ?></td>
                                        <td class="text-white font-medium"><?= htmlspecialchars($enr['title']) ?></td>
                                        <td><span class="px-2 py-0.5 rounded bg-slate-800 font-mono text-xs"><?= htmlspecialchars($enr['section']) ?></span></td>
                                        <td class="text-xs text-slate-300"><?= htmlspecialchars($enr['days']) ?> <?= date('g:i A', strtotime($enr['time_start'])) ?>-<?= date('g:i A', strtotime($enr['time_end'])) ?></td>
                                        <td class="text-xs font-mono text-slate-300"><?= htmlspecialchars($enr['room']) ?></td>
                                        <td class="font-bold text-white text-center"><?= htmlspecialchars($enr['total_units']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-900/80 font-bold">
                                    <td colspan="5" class="text-right text-slate-400">Total Enrolled Units:</td>
                                    <td class="text-center text-indigo-400"><?= $tot_units ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php elseif (!empty($subject_loads)): ?>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Descriptive Title</th>
                                    <th>Lec</th>
                                    <th>Lab</th>
                                    <th>Total Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $load_units = 0;
                                foreach ($subject_loads as $sub): 
                                    $load_units += $sub['total_units'];
                                ?>
                                    <tr>
                                        <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($sub['code']) ?></td>
                                        <td class="text-white font-medium"><?= htmlspecialchars($sub['title']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($sub['lecture_units']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($sub['lab_units']) ?></td>
                                        <td class="text-center font-bold text-white"><?= htmlspecialchars($sub['total_units']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-900/80 font-bold">
                                    <td colspan="4" class="text-right text-slate-400">Total Evaluated Units:</td>
                                    <td class="text-center text-indigo-400"><?= $load_units ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 bg-slate-900/40 rounded-xl border border-slate-800">
                        <i data-lucide="clock" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p class="text-xs">Subject load will be officially evaluated once you reach <strong class="text-slate-300">Step 4 (Registrar)</strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Financial Assessment Card (Accounting) -->
        <div class="lg:col-span-5 space-y-6">
            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4 text-emerald-400"></i>
                            Financial Assessment & Fees
                        </h3>
                        <p class="text-xs text-slate-400">Billing summary by Accounting Office</p>
                    </div>
                    <?php if ($assessment): ?>
                        <?= get_status_badge($assessment['payment_status']) ?>
                    <?php endif; ?>
                </div>

                <?php if ($assessment): ?>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between py-2 border-b border-slate-800 text-slate-300">
                            <span>Tuition Fee (<?= $assessment['total_units'] ?> units):</span>
                            <span class="font-mono font-semibold text-white"><?= format_currency($assessment['tuition_fee']) ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800 text-slate-300">
                            <span>Laboratory Fees:</span>
                            <span class="font-mono font-semibold text-white"><?= format_currency($assessment['lab_fee']) ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800 text-slate-300">
                            <span>Miscellaneous Fees:</span>
                            <span class="font-mono font-semibold text-white"><?= format_currency($assessment['misc_fee']) ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800 text-slate-300">
                            <span>Registration & Other Fees:</span>
                            <span class="font-mono font-semibold text-white"><?= format_currency($assessment['registration_fee'] + $assessment['other_fees']) ?></span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-700 text-sm font-bold text-white">
                            <span>Total Assessment:</span>
                            <span class="font-mono text-indigo-400"><?= format_currency($assessment['total_assessment']) ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800 text-emerald-400 font-semibold">
                            <span>Amount Paid:</span>
                            <span class="font-mono"><?= format_currency($assessment['amount_paid']) ?></span>
                        </div>
                        <div class="flex justify-between py-2 text-sm font-bold <?= $assessment['balance'] > 0 ? 'text-amber-400' : 'text-emerald-400' ?>">
                            <span>Remaining Balance:</span>
                            <span class="font-mono"><?= format_currency($assessment['balance']) ?></span>
                        </div>

                        <?php if ($assessment['or_number']): ?>
                            <div class="mt-4 p-3 bg-slate-900 rounded-xl border border-slate-800 text-[11px] text-slate-400">
                                <span class="text-slate-500">Latest Official Receipt:</span> <strong class="text-white font-mono"><?= htmlspecialchars($assessment['or_number']) ?></strong> &bull; <?= format_date($assessment['payment_date'], 'M d, Y') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 bg-slate-900/40 rounded-xl border border-slate-800">
                        <i data-lucide="credit-card" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p class="text-xs">Fees will be computed during <strong class="text-slate-300">Step 3 & 4</strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- ENROLLMENT APPLICATION FORM MODAL (STEP 1) -->
<div id="enrollment-form-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content max-w-3xl border border-indigo-500/40 rounded-2xl p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto shadow-2xl">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950/80 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="file-signature" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-white">Student Enrollment & Clearance Form</h3>
                    <p class="text-xs text-slate-400">Step 1 Department Initial Clearance Submission &bull; Academic Year <?= htmlspecialchars($active_term['academic_year']) ?></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('enrollment-form-modal')" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-3.5 bg-indigo-950/40 border border-indigo-500/30 rounded-xl text-xs text-indigo-200 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-indigo-400 shrink-0 mt-0.5"></i>
            <span>Please provide complete and accurate information below. All marked fields (<span class="text-rose-400 font-bold">*</span>) are mandatory. Upon submission, your Department Dean / Program Adviser will review and approve your Step 1 clearance.</span>
        </div>

        <!-- Form -->
        <form method="POST" action="<?= url('modules/student/dashboard.php') ?>" class="space-y-6">
            <input type="hidden" name="submit_enrollment_form" value="1">

            <!-- Section 1: Personal Details -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                    <i data-lucide="user" class="w-3.5 h-3.5"></i>
                    1. Student Personal Information
                </h4>
                
                <!-- Names -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">First Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($clearance['first_name'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($clearance['middle_name'] ?? '') ?>" placeholder="Optional" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Last Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($clearance['last_name'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- Date of Birth, Age, Sex -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Date of Birth <span class="text-rose-400">*</span></label>
                        <input type="date" id="student_birth_date" name="birth_date" required value="<?= htmlspecialchars($clearance['birth_date'] ?? '2004-01-01') ?>" onchange="calculateAge(this.value)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Age <span class="text-rose-400">*</span></label>
                        <input type="number" id="student_age" name="age" required min="10" max="100" value="<?= htmlspecialchars($clearance['age'] ?? '20') ?>" placeholder="e.g. 20" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Sex <span class="text-rose-400">*</span></label>
                        <select name="sex" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="Male" <?= ($clearance['sex'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($clearance['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($clearance['sex'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <!-- Civil Status, Religion, Mobile -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Civil Status <span class="text-rose-400">*</span></label>
                        <select name="civil_status" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="Single" <?= ($clearance['civil_status'] ?? 'Single') === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= ($clearance['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Widowed" <?= ($clearance['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                            <option value="Separated" <?= ($clearance['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Religion <span class="text-rose-400">*</span></label>
                        <input type="text" name="religion" required value="<?= htmlspecialchars($clearance['religion'] ?? 'Roman Catholic') ?>" placeholder="e.g. Roman Catholic, Islam, Born Again" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Student Mobile Number <span class="text-rose-400">*</span></label>
                        <input type="text" name="contact_no" required value="<?= htmlspecialchars($clearance['contact_no'] ?? '') ?>" placeholder="e.g. 0917-123-4567" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- Birth Place & Residential Address -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Place of Birth <span class="text-rose-400">*</span></label>
                        <input type="text" name="birth_place" required value="<?= htmlspecialchars($clearance['birth_place'] ?? '') ?>" placeholder="e.g. Manila, Philippines" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Current Residential Address <span class="text-rose-400">*</span></label>
                        <textarea name="address" required rows="2" placeholder="House/Unit No., Street, Barangay, City/Municipality, Province" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"><?= htmlspecialchars($clearance['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Parent / Guardian Information -->
            <div class="space-y-4 pt-2 border-t border-slate-800">
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                    <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                    2. Parent / Guardian Details
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Parent / Guardian Full Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="guardian_name" required value="<?= htmlspecialchars($clearance['guardian_name'] ?? '') ?>" placeholder="e.g. Maria Dela Cruz" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Parent / Guardian Contact Number <span class="text-rose-400">*</span></label>
                        <input type="text" name="guardian_contact" required value="<?= htmlspecialchars($clearance['guardian_contact'] ?? '') ?>" placeholder="e.g. 0918-987-6543" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Section 3: Academic Program & Enrollment Selections -->
            <div class="space-y-4 pt-2 border-t border-slate-800">
                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                    <i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i>
                    3. Academic Program & Enrollment Level
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Chosen Course / Degree Program <span class="text-rose-400">*</span></label>
                        <select name="program_id" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-semibold">
                            <option value="2" <?= ($clearance['program_id'] == 2 || $clearance['program_code'] === 'BSIT') ? 'selected' : '' ?>>BSIT - Bachelor of Science in Information Technology</option>
                            <option value="1" <?= ($clearance['program_id'] == 1 || $clearance['program_code'] === 'BSCS') ? 'selected' : '' ?>>BSCS - Bachelor of Science in Computer Science</option>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">Note: Available degree programs are BSIT and Computer Science (BSCS) only.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Enrolling for What Year Level <span class="text-rose-400">*</span></label>
                        <select name="year_level" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="1" <?= $clearance['year_level'] == 1 ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= $clearance['year_level'] == 2 ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= $clearance['year_level'] == 3 ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= $clearance['year_level'] == 4 ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Enrolling Semester <span class="text-rose-400">*</span></label>
                        <select name="enrolling_semester" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="1st Semester" <?= ($clearance['enrolling_semester'] ?? '1st Semester') === '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= ($clearance['enrolling_semester'] ?? '') === '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Academic Status <span class="text-rose-400">*</span></label>
                        <select name="academic_status" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="Regular" <?= ($clearance['academic_status'] ?? 'Regular') === 'Regular' ? 'selected' : '' ?>>Regular Student</option>
                            <option value="Irregular" <?= ($clearance['academic_status'] ?? '') === 'Irregular' ? 'selected' : '' ?>>Irregular Student</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeEnrollmentModal()" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Submit Enrollment Details to Department
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEnrollmentModal() {
    const modal = document.getElementById('enrollment-form-modal');
    if (modal) {
        modal.classList.add('active');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeEnrollmentModal() {
    const modal = document.getElementById('enrollment-form-modal');
    if (modal) {
        modal.classList.remove('active');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function calculateAge(dob) {
    if (!dob) return;
    const birthDate = new Date(dob);
    const difference = Date.now() - birthDate.getTime();
    const ageDate = new Date(difference);
    const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
    const ageInput = document.getElementById('student_age');
    if (ageInput && !isNaN(calculatedAge)) {
        ageInput.value = calculatedAge;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('enrollment-form-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeEnrollmentModal();
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
