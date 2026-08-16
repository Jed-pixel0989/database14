<?php
require_once __DIR__ . '/../../config/functions.php';
require_login();

$user = current_user();

// Support checking student from param if admin/registrar/department is viewing, else own record
$student_id = intval($_GET['student_id'] ?? ($user['student_id'] ?? 0));
if (!$student_id) {
    die("Student record not specified.");
}

$active_term = get_active_term();
$clearance = get_student_clearance_record($student_id, $active_term['id']);

if (!$clearance) {
    die("No clearance or enrollment record found for this term.");
}

$db = getDB();

// Fetch enrollments
$stmt_enr = $db->prepare("SELECT sch.*, sub.code, sub.title, sub.lecture_units, sub.lab_units, sub.total_units 
                         FROM student_enrollments se 
                         JOIN schedules sch ON se.schedule_id = sch.id 
                         JOIN subjects sub ON sch.subject_id = sub.id 
                         WHERE se.clearance_id = ?");
$stmt_enr->execute([$clearance['id']]);
$enrollments = $stmt_enr->fetchAll();

// Fetch assessment
$stmt_acc = $db->prepare("SELECT * FROM accounting_assessments WHERE clearance_id = ?");
$stmt_acc->execute([$clearance['id']]);
$assessment = $stmt_acc->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Registration - <?= htmlspecialchars($clearance['student_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= url('assets/css/offline-utilities.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>">
    <script src="<?= url('assets/js/offline-icons.js') ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .cor-title { font-family: 'Cinzel', Georgia, serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-4 sm:p-8 min-h-screen">

    <!-- Action Bar (Hidden on Print) -->
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-print bg-white p-4 rounded-xl shadow-md border border-slate-200">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
            <span class="text-sm font-bold text-slate-800">Official Certificate of Registration (COR)</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="javascript:window.history.back()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition-colors">
                &larr; Back
            </a>
            <button onclick="window.print()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-md transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Official Document Container -->
    <div class="cor-container max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl shadow-xl border border-slate-200 relative">
        
        <!-- Header -->
        <div class="text-center border-b-2 border-slate-900 pb-4 mb-6">
            <div class="text-xs tracking-widest uppercase font-bold text-slate-600">Republic of the Philippines</div>
            <h1 class="text-xl font-black text-slate-900 tracking-tight mt-0.5">SOUTH EAST ASIAN INSTITUTE OF TECHNOLOGY (SEAIT)</h1>
            <div class="text-xs text-slate-600 font-medium">SEAIT ENROLL &bull; Office of the Registrar & Comptroller</div>
            <h2 class="cor-title text-base sm:text-lg font-bold text-indigo-950 mt-3 uppercase tracking-wider">
                Certificate of Registration & Assessment Form
            </h2>
            <div class="text-xs font-semibold text-slate-700 mt-1">
                Academic Year <?= htmlspecialchars($active_term['academic_year']) ?> &bull; <?= htmlspecialchars($active_term['semester']) ?>
            </div>
        </div>

        <!-- Student Dossier Grid -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl mb-6 text-xs space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Student Number</span>
                    <span class="font-mono font-bold text-slate-900 text-sm"><?= htmlspecialchars($clearance['student_no']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Full Legal Name</span>
                    <span class="font-bold text-slate-900"><?= htmlspecialchars($clearance['last_name'] . ', ' . $clearance['first_name'] . (!empty($clearance['middle_name']) ? ' ' . $clearance['middle_name'] : '')) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Date of Birth & Age</span>
                    <span class="font-semibold text-slate-900"><?= !empty($clearance['birth_date']) ? date('M d, Y', strtotime($clearance['birth_date'])) : '—' ?> (<?= htmlspecialchars($clearance['age'] ?? '—') ?> yrs)</span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Sex & Civil Status</span>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($clearance['sex'] ?? '—') ?> &bull; <?= htmlspecialchars($clearance['civil_status'] ?? 'Single') ?></span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Program / Degree</span>
                    <span class="font-bold text-slate-900"><?= htmlspecialchars($clearance['program_code']) ?> - <?= htmlspecialchars($clearance['program_name']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Year & Semester</span>
                    <span class="font-bold text-slate-900">Year <?= htmlspecialchars($clearance['year_level']) ?> &bull; <?= htmlspecialchars($clearance['enrolling_semester'] ?? $active_term['semester']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Academic Status</span>
                    <span class="font-bold text-slate-900"><?= htmlspecialchars($clearance['academic_status']) ?> Student</span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Religion</span>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($clearance['religion'] ?? '—') ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-200">
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Place of Birth</span>
                    <span class="text-slate-900 font-medium"><?= htmlspecialchars($clearance['birth_place'] ?? 'Not Specified') ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Mobile Number</span>
                    <span class="font-mono font-semibold text-slate-900"><?= htmlspecialchars($clearance['contact_no'] ?? '—') ?></span>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Parent / Guardian</span>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($clearance['guardian_name'] ?? '—') ?> <span class="text-[10px] text-slate-500 font-mono">(<?= htmlspecialchars($clearance['guardian_contact'] ?? '') ?>)</span></span>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-200">
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Current Residential Address</span>
                <span class="text-slate-800"><?= htmlspecialchars($clearance['address'] ?? 'Not Specified') ?></span>
            </div>
        </div>

        <!-- Enrolled Subjects Table -->
        <div class="mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Class Schedule & Course Matrix</h3>
            <table class="w-full text-xs text-left border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 font-bold border-b border-slate-300">
                        <th class="p-2 border border-slate-300">Code</th>
                        <th class="p-2 border border-slate-300">Descriptive Title</th>
                        <th class="p-2 border border-slate-300 text-center">Sec</th>
                        <th class="p-2 border border-slate-300 text-center">Units</th>
                        <th class="p-2 border border-slate-300">Days & Time</th>
                        <th class="p-2 border border-slate-300">Room</th>
                        <th class="p-2 border border-slate-300">Instructor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_lec = 0;
                    $total_lab = 0;
                    $total_units = 0;
                    if (!empty($enrollments)):
                        foreach ($enrollments as $row):
                            $total_units += $row['total_units'];
                            $total_lec += $row['lecture_units'];
                            $total_lab += $row['lab_units'];
                    ?>
                        <tr class="border-b border-slate-200">
                            <td class="p-2 font-mono font-bold text-slate-900 border border-slate-300"><?= htmlspecialchars($row['code']) ?></td>
                            <td class="p-2 font-medium text-slate-800 border border-slate-300"><?= htmlspecialchars($row['title']) ?></td>
                            <td class="p-2 font-mono text-center border border-slate-300"><?= htmlspecialchars($row['section']) ?></td>
                            <td class="p-2 font-bold text-center border border-slate-300"><?= htmlspecialchars($row['total_units']) ?></td>
                            <td class="p-2 border border-slate-300"><?= htmlspecialchars($row['days']) ?> <?= date('g:i A', strtotime($row['time_start'])) ?>-<?= date('g:i A', strtotime($row['time_end'])) ?></td>
                            <td class="p-2 font-mono border border-slate-300"><?= htmlspecialchars($row['room']) ?></td>
                            <td class="p-2 text-slate-700 border border-slate-300"><?= htmlspecialchars($row['instructor']) ?></td>
                        </tr>
                    <?php 
                        endforeach; 
                    else:
                    ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-slate-500 italic">No class schedules enrolled for this term.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold border-t-2 border-slate-400 text-slate-900">
                        <td colspan="3" class="p-2 text-right">Total Enrolled Academic Units:</td>
                        <td class="p-2 text-center text-sm text-indigo-900"><?= $total_units ?></td>
                        <td colspan="3" class="p-2 text-slate-600 text-[11px] font-normal">Lecture: <?= $total_lec ?> hrs &bull; Lab: <?= $total_lab ?> hrs</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Financial Breakdown & Assessment -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8 text-xs">
            
            <!-- Assessment Items -->
            <div class="p-4 border border-slate-300 rounded-xl bg-slate-50">
                <h4 class="font-bold text-slate-900 uppercase text-[11px] mb-2 pb-1 border-b border-slate-200">Accounting Assessment</h4>
                <?php if ($assessment): ?>
                    <div class="space-y-1.5 text-slate-700">
                        <div class="flex justify-between"><span>Tuition (<?= $assessment['total_units'] ?> units):</span><span class="font-mono font-semibold"><?= format_currency($assessment['tuition_fee']) ?></span></div>
                        <div class="flex justify-between"><span>Laboratory Fee:</span><span class="font-mono font-semibold"><?= format_currency($assessment['lab_fee']) ?></span></div>
                        <div class="flex justify-between"><span>Miscellaneous Fee:</span><span class="font-mono font-semibold"><?= format_currency($assessment['misc_fee']) ?></span></div>
                        <div class="flex justify-between"><span>Registration & Others:</span><span class="font-mono font-semibold"><?= format_currency($assessment['registration_fee'] + $assessment['other_fees']) ?></span></div>
                        <div class="flex justify-between pt-1 border-t border-slate-300 font-bold text-slate-900"><span>Total Assessment:</span><span class="font-mono"><?= format_currency($assessment['total_assessment']) ?></span></div>
                        <div class="flex justify-between text-emerald-800 font-semibold"><span>Amount Paid:</span><span class="font-mono"><?= format_currency($assessment['amount_paid']) ?></span></div>
                        <div class="flex justify-between pt-1 border-t border-slate-300 font-bold text-slate-900 text-sm"><span>Balance:</span><span class="font-mono"><?= format_currency($assessment['balance']) ?></span></div>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 italic">No assessment generated.</p>
                <?php endif; ?>
            </div>

            <!-- Clearance Verification & Signatures -->
            <div class="p-4 border border-slate-300 rounded-xl bg-slate-50 flex flex-col justify-between">
                <div>
                    <h4 class="font-bold text-slate-900 uppercase text-[11px] mb-2 pb-1 border-b border-slate-200">Clearance Verification Summary</h4>
                    <div class="space-y-1 text-[11px] text-slate-700">
                        <?php foreach ($clearance['stages'] as $stg): ?>
                            <div class="flex justify-between">
                                <span>Step <?= $stg['step_number'] ?>: <?= htmlspecialchars($stg['stage_title']) ?></span>
                                <span class="font-bold <?= $stg['status'] === 'Cleared' ? 'text-emerald-700' : 'text-slate-500' ?>"><?= htmlspecialchars($stg['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-4 pt-2 border-t border-slate-300 text-[10px] text-slate-500">
                    <div>System Validation Code: <span class="font-mono font-bold text-slate-800">COR-<?= strtoupper(substr(md5($clearance['id'] . $clearance['student_no']), 0, 12)) ?></span></div>
                    <div>Date Verified: <?= date('F d, Y h:i A') ?></div>
                </div>
            </div>

        </div>

        <!-- Official Signatory Blocks -->
        <div class="grid grid-cols-3 gap-6 pt-6 border-t border-slate-300 text-center text-xs">
            <div>
                <div class="h-10 border-b border-slate-800 mx-auto w-3/4"></div>
                <span class="font-bold text-slate-900 block mt-1">Dr. Alan Turing</span>
                <span class="text-[10px] text-slate-600 block">Department Dean / Adviser</span>
            </div>
            <div>
                <div class="h-10 border-b border-slate-800 mx-auto w-3/4"></div>
                <span class="font-bold text-slate-900 block mt-1">Mr. Alexander Hamilton</span>
                <span class="text-[10px] text-slate-600 block">University Comptroller</span>
            </div>
            <div>
                <div class="h-10 border-b border-slate-800 mx-auto w-3/4"></div>
                <span class="font-bold text-slate-900 block mt-1">Atty. Harvey Specter</span>
                <span class="text-[10px] text-slate-600 block">University Registrar</span>
            </div>
        </div>

    </div>

</body>
</html>
