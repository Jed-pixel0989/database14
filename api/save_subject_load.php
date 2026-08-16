<?php
// =======================================================
// API: Save Subject Load Evaluation (Registrar Step 4)
// =======================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user = current_user();
if (!in_array($user['role'], ['admin', 'registrar'])) {
    echo json_encode(['success' => false, 'message' => 'Only Registrar officers can evaluate and assign subject loads.']);
    exit;
}

$clearance_id = intval($_POST['clearance_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$subject_ids = $_POST['subject_ids'] ?? [];
$approve_step = isset($_POST['approve_step']) && $_POST['approve_step'] == 1;
$remarks = trim($_POST['remarks'] ?? 'Subject load evaluated and approved.');

if (!$clearance_id || !$student_id || empty($subject_ids)) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one subject to evaluate.']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    // 1. Clear previous subject loads for this clearance
    $stmt_del = $db->prepare("DELETE FROM student_subject_loads WHERE clearance_id = ?");
    $stmt_del->execute([$clearance_id]);

    // 2. Insert new subject load records
    $stmt_ins = $db->prepare("INSERT INTO student_subject_loads (clearance_id, student_id, subject_id, is_allowed, evaluated_by_user_id) 
                              VALUES (?, ?, ?, 1, ?)");
    
    $total_units = 0;
    $tuition_total = 0;
    $lab_total = 0;

    foreach ($subject_ids as $sub_id) {
        $sub_id = intval($sub_id);
        $stmt_ins->execute([$clearance_id, $student_id, $sub_id, $user['id']]);

        // Get subject unit info
        $stmt_sub = $db->prepare("SELECT total_units, tuition_rate_per_unit, lab_fee FROM subjects WHERE id = ?");
        $stmt_sub->execute([$sub_id]);
        $subj = $stmt_sub->fetch();
        if ($subj) {
            $units = intval($subj['total_units']);
            $total_units += $units;
            $tuition_total += ($units * floatval($subj['tuition_rate_per_unit']));
            $lab_total += floatval($subj['lab_fee']);
        }
    }

    // 3. Update or create Accounting Assessment
    $term = get_active_term();
    $misc_fee = 2500.00;
    $registration_fee = 500.00;
    $other_fees = 350.00;
    $total_assessment = $tuition_total + $lab_total + $misc_fee + $registration_fee + $other_fees;

    $stmt_chk_acc = $db->prepare("SELECT id, amount_paid FROM accounting_assessments WHERE clearance_id = ?");
    $stmt_chk_acc->execute([$clearance_id]);
    $acc_rec = $stmt_chk_acc->fetch();

    if ($acc_rec) {
        $amount_paid = floatval($acc_rec['amount_paid']);
        $balance = max(0, $total_assessment - $amount_paid);
        $status = ($balance <= 0) ? 'Fully Paid' : (($amount_paid > 0) ? 'Partial Downpayment' : 'Unpaid');

        $stmt_upd_acc = $db->prepare("UPDATE accounting_assessments 
                                     SET total_units = ?, tuition_fee = ?, lab_fee = ?, total_assessment = ?, balance = ?, payment_status = ? 
                                     WHERE id = ?");
        $stmt_upd_acc->execute([$total_units, $tuition_total, $lab_total, $total_assessment, $balance, $status, $acc_rec['id']]);
    } else {
        $balance = $total_assessment;
        $stmt_ins_acc = $db->prepare("INSERT INTO accounting_assessments (clearance_id, student_id, academic_term_id, total_units, tuition_fee, lab_fee, misc_fee, registration_fee, other_fees, total_assessment, amount_paid, balance, payment_status)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'Unpaid')");
        $stmt_ins_acc->execute([$clearance_id, $student_id, $term['id'], $total_units, $tuition_total, $lab_total, $misc_fee, $registration_fee, $other_fees, $total_assessment, $balance]);
    }

    // 4. If approve_step is checked, sign off Step 4 (Registrar)
    if ($approve_step) {
        $stmt_stg = $db->prepare("UPDATE clearance_stages 
                                 SET status = 'Cleared', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() 
                                 WHERE clearance_id = ? AND step_number = 4");
        $stmt_stg->execute([$user['id'], $user['full_name'], $remarks, $clearance_id]);

        // Advance clearance request to step 5 (Department Final Scheduling)
        $db->prepare("UPDATE clearance_requests SET current_step = GREATEST(current_step, 5), overall_status = 'In Progress' WHERE id = ?")
           ->execute([$clearance_id]);
    }

    $db->commit();

    log_activity('SUBJECT_LOAD_EVALUATED', "Evaluated $total_units units for student ID $student_id (Clearance #$clearance_id)", $user['id']);

    echo json_encode([
        'success' => true,
        'message' => "Subject load evaluation saved ($total_units Total Units evaluated). Step 4 updated."
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Evaluation failed: ' . $e->getMessage()]);
}
