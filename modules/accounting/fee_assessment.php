<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'accounting']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

$stmt = $db->prepare("SELECT acc.*, s.student_no, s.first_name, s.last_name, s.year_level, p.code as program_code,
                             cr.overall_status
                      FROM accounting_assessments acc
                      JOIN students s ON acc.student_id = s.id
                      JOIN programs p ON s.program_id = p.id
                      JOIN clearance_requests cr ON acc.clearance_id = cr.id
                      WHERE acc.academic_term_id = ?
                      ORDER BY acc.updated_at DESC");
$stmt->execute([$active_term['id']]);
$assessments = $stmt->fetchAll();

$page_title = "Billing & Financial Assessments";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="receipt" class="w-7 h-7 text-emerald-400"></i>
                Tuition Assessments & Billing Ledger
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Official financial ledger for Academic Term: <?= htmlspecialchars($active_term['academic_year']) ?> &bull; <?= htmlspecialchars($active_term['semester']) ?>
            </p>
        </div>

        <a href="<?= url('modules/accounting/clearance_queue.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            &larr; Back to Step 3 Clearance Queue
        </a>
    </div>

    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Student Billing Records</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="ledger-table" placeholder="Search student name, OR#, ID..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="ledger-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Units</th>
                        <th>Tuition Fee</th>
                        <th>Lab Fees</th>
                        <th>Total Assessed</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Latest OR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assessments)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-8 text-slate-500 italic">No assessment records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assessments as $row): ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?></td>
                                <td class="text-center font-bold text-white"><?= htmlspecialchars($row['total_units']) ?></td>
                                <td class="font-mono text-slate-300"><?= format_currency($row['tuition_fee']) ?></td>
                                <td class="font-mono text-slate-300"><?= format_currency($row['lab_fee']) ?></td>
                                <td class="font-mono font-bold text-white"><?= format_currency($row['total_assessment']) ?></td>
                                <td class="font-mono text-emerald-400 font-semibold"><?= format_currency($row['amount_paid']) ?></td>
                                <td class="font-mono font-bold <?= $row['balance'] > 0 ? 'text-amber-400' : 'text-emerald-400' ?>"><?= format_currency($row['balance']) ?></td>
                                <td><?= get_status_badge($row['payment_status']) ?></td>
                                <td class="font-mono text-xs text-slate-400"><?= $row['or_number'] ? htmlspecialchars($row['or_number']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
