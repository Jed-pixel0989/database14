<?php
require_once __DIR__ . '/../../config/functions.php';
require_role(['admin', 'accounting']);

$user = current_user();
$active_term = get_active_term();
$db = getDB();

// Handle Payment Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['process_payment'])) {
    $clearance_id = intval($_POST['clearance_id']);
    $student_id = intval($_POST['student_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $or_number = trim($_POST['or_number']);
    $stage_id = intval($_POST['stage_id']);
    $sign_clearance = isset($_POST['sign_clearance']) && $_POST['sign_clearance'] == 1;

    // Fetch assessment
    $stmt_acc = $db->prepare("SELECT * FROM accounting_assessments WHERE clearance_id = ?");
    $stmt_acc->execute([$clearance_id]);
    $acc = $stmt_acc->fetch();

    if ($acc) {
        $new_total_paid = floatval($acc['amount_paid']) + $amount_paid;
        $total_assess = floatval($acc['total_assessment']);
        $new_bal = max(0, $total_assess - $new_total_paid);
        $pay_status = ($new_bal <= 0) ? 'Fully Paid' : 'Partial Downpayment';

        $stmt_upd = $db->prepare("UPDATE accounting_assessments 
                                  SET amount_paid = ?, balance = ?, payment_status = ?, or_number = ?, payment_date = NOW(), assessed_by_user_id = ? 
                                  WHERE id = ?");
        $stmt_upd->execute([$new_total_paid, $new_bal, $pay_status, $or_number, $user['id'], $acc['id']]);
    } else {
        // Create initial assessment if not yet present
        $total_assess = 14600.00; // standard default
        $new_bal = max(0, $total_assess - $amount_paid);
        $pay_status = ($new_bal <= 0) ? 'Fully Paid' : 'Partial Downpayment';

        $stmt_ins = $db->prepare("INSERT INTO accounting_assessments 
                                  (clearance_id, student_id, academic_term_id, total_units, tuition_fee, lab_fee, misc_fee, registration_fee, other_fees, total_assessment, amount_paid, balance, payment_status, or_number, payment_date, assessed_by_user_id) 
                                  VALUES (?, ?, ?, 15, 6750.00, 4500.00, 2500.00, 500.00, 350.00, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt_ins->execute([$clearance_id, $student_id, $active_term['id'], $total_assess, $amount_paid, $new_bal, $pay_status, $or_number, $user['id']]);
    }

    if ($sign_clearance) {
        $stmt_stg = $db->prepare("UPDATE clearance_stages 
                                  SET status = 'Cleared', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() 
                                  WHERE id = ?");
        $stmt_stg->execute([$user['id'], $user['full_name'], "Downpayment / Payment verified. OR# $or_number. Cleared.", $stage_id]);

        $db->prepare("UPDATE clearance_requests SET current_step = GREATEST(current_step, 4), overall_status = 'In Progress' WHERE id = ?")
           ->execute([$clearance_id]);
    }

    log_activity('PAYMENT_PROCESSED', "Processed payment of ₱$amount_paid (OR# $or_number) for Student ID $student_id", $user['id']);
    set_flash('success', "Payment of ₱" . number_format($amount_paid, 2) . " (OR# $or_number) processed successfully!");
    header("Location: " . url('modules/accounting/clearance_queue.php'));
    exit;
}

// Fetch students for Step 3 (Accounting Clearance)
$query = "SELECT cs.id as stage_id, cs.status as stage_status, cs.signed_at, cs.remarks,
                 cr.id as clearance_id, cr.current_step, cr.overall_status,
                 s.id as student_id, s.student_no, s.first_name, s.last_name, s.year_level,
                 p.code as program_code,
                 acc.total_assessment, acc.amount_paid, acc.balance, acc.payment_status, acc.or_number,
                 (SELECT status FROM clearance_stages WHERE clearance_id = cr.id AND step_number = 2) as step2_status
          FROM clearance_stages cs
          JOIN clearance_requests cr ON cs.clearance_id = cr.id
          JOIN students s ON cr.student_id = s.id
          JOIN programs p ON s.program_id = p.id
          LEFT JOIN accounting_assessments acc ON cr.id = acc.clearance_id
          WHERE cs.step_number = 3 AND cr.academic_term_id = ?
          ORDER BY (CASE WHEN cs.status = 'Pending' AND cr.current_step >= 3 THEN 1 WHEN cs.status = 'Flagged' THEN 2 ELSE 3 END), s.last_name ASC";

$stmt = $db->prepare($query);
$stmt->execute([$active_term['id']]);
$students = $stmt->fetchAll();

$page_title = "Step 3: Accounting & Financial Clearance Queue";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-950 text-indigo-300 border border-indigo-500/40">
                    Step 3 of 5
                </span>
                <span class="text-xs text-slate-400">Prerequisite to Registrar Subject Load Evaluation</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="credit-card" class="w-7 h-7 text-indigo-400"></i>
                Accounting & Financial Clearance Queue
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Verify student tuition assessments, validate required enrollment downpayments, record official receipts, and sign off financial clearance.
            </p>
        </div>

        <a href="<?= url('modules/accounting/fee_assessment.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            <i data-lucide="receipt" class="w-4 h-4 text-emerald-400"></i>
            View All Billing Records &rarr;
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-950/80 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Total Students</div>
                <div class="text-lg font-bold text-white"><?= count($students) ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-950/80 border border-amber-500/40 flex items-center justify-center text-amber-400">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Pending Financial Sign-off</div>
                <div class="text-lg font-bold text-amber-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Pending' && $s['current_step'] >= 3)) ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Financially Cleared</div>
                <div class="text-lg font-bold text-emerald-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Cleared')) ?></div>
            </div>
        </div>

        <div class="glass-card p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-950/80 border border-rose-500/40 flex items-center justify-center text-rose-400">
                <i data-lucide="alert-octagon" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-xs text-slate-400 font-medium">Flagged Holds</div>
                <div class="text-lg font-bold text-rose-400"><?= count(array_filter($students, fn($s) => $s['stage_status'] === 'Flagged')) ?></div>
            </div>
        </div>
    </div>

    <!-- Student Table -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Financial Clearance Queue</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="acc-table" placeholder="Search student name, ID, course..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="acc-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Total Assessed</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Payment Status</th>
                        <th>Step 3 Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-slate-500 italic">No records in accounting queue.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): 
                            $is_step2_cleared = ($row['step2_status'] === 'Cleared');
                        ?>
                            <tr>
                                <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                                <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?></td>
                                <td class="font-mono font-semibold text-white"><?= $row['total_assessment'] ? format_currency($row['total_assessment']) : '₱14,600.00' ?></td>
                                <td class="font-mono text-emerald-400 font-semibold"><?= $row['amount_paid'] ? format_currency($row['amount_paid']) : '₱0.00' ?></td>
                                <td class="font-mono font-bold <?= ($row['balance'] ?? 14600) > 0 ? 'text-amber-400' : 'text-emerald-400' ?>"><?= $row['balance'] !== null ? format_currency($row['balance']) : '₱14,600.00' ?></td>
                                <td><?= get_status_badge($row['payment_status'] ?? 'Unpaid') ?></td>
                                <td><?= get_status_badge($row['stage_status']) ?></td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="triggerPaymentModal(<?= $row['clearance_id'] ?>, <?= $row['student_id'] ?>, <?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])) ?>', '<?= htmlspecialchars($row['student_no']) ?>', <?= floatval($row['balance'] ?? 14600) ?>)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg shadow transition-all flex items-center gap-1">
                                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                                            Record Payment
                                        </button>

                                        <?php if ($row['stage_status'] !== 'Cleared'): ?>
                                            <button onclick="triggerSignModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_no'] . ')')) ?>')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg shadow transition-all flex items-center gap-1">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                Sign Off
                                            </button>
                                            <button onclick="triggerFlagModal(<?= $row['stage_id'] ?>, '<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_no'] . ')')) ?>')" class="px-3 py-1.5 bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-semibold rounded-lg shadow transition-all flex items-center gap-1">
                                                <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                                                Hold
                                            </button>
                                        <?php else: ?>
                                            <span class="text-xs text-emerald-400 font-semibold flex items-center gap-1">
                                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                                Cleared
                                            </span>
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

<!-- Payment Modal -->
<div id="payment-modal" class="modal-overlay">
    <div class="modal-content p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="receipt" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Record Tuition / Downpayment</h3>
                    <p class="text-xs text-slate-400" id="pay-student-name">Student</p>
                </div>
            </div>
            <button onclick="closeModal('payment-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="clearance_queue.php" class="space-y-4">
            <input type="hidden" name="process_payment" value="1">
            <input type="hidden" name="clearance_id" id="pay-clearance-id" value="">
            <input type="hidden" name="student_id" id="pay-student-id" value="">
            <input type="hidden" name="stage_id" id="pay-stage-id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Official Receipt (OR) Number</label>
                <input type="text" name="or_number" required placeholder="e.g. OR-89102" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Payment Amount (₱)</label>
                <input type="number" step="0.01" name="amount_paid" id="pay-amount-input" required placeholder="3500.00" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs font-mono font-bold text-white focus:outline-none focus:border-indigo-500">
            </div>

            <label class="flex items-center gap-2 p-3 bg-slate-900 rounded-xl border border-slate-800 cursor-pointer">
                <input type="checkbox" name="sign_clearance" value="1" checked class="rounded text-emerald-600 focus:ring-emerald-500 bg-slate-800 border-slate-600">
                <span class="text-xs text-slate-200 font-semibold">Automatically Sign & Clear Step 3 (Accounting Clearance) upon payment</span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('payment-modal')" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30">
                    Process Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Include Modal Templates -->
<?php include __DIR__ . '/../../includes/modals.php'; ?>

<script>
function triggerPaymentModal(clearanceId, studentId, stageId, studentName, studentNo, balance) {
    document.getElementById('pay-clearance-id').value = clearanceId;
    document.getElementById('pay-student-id').value = studentId;
    document.getElementById('pay-stage-id').value = stageId;
    document.getElementById('pay-student-name').innerText = studentName + ' (' + studentNo + ')';
    document.getElementById('pay-amount-input').value = balance > 0 ? (balance >= 3500 ? '3500.00' : balance.toFixed(2)) : '3500.00';
    openModal('payment-modal');
}

function triggerSignModal(stageId, studentName) {
    document.getElementById('sign-stage-id').value = stageId;
    document.getElementById('sign-student-name').innerText = 'Step 3 Accounting Clearance: ' + studentName;
    document.getElementById('sign-remarks').value = 'Required minimum downpayment verified. Cleared for registrar evaluation.';
    openModal('sign-modal');
}

function triggerFlagModal(stageId, studentName) {
    document.getElementById('flag-stage-id').value = stageId;
    document.getElementById('flag-student-name').innerText = 'Flag Financial Hold: ' + studentName;
    openModal('flag-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
