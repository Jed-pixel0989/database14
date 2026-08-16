<?php
// =======================================================
// API: Assign Class Schedule & Section (Department Step 5)
// =======================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user = current_user();
if (!in_array($user['role'], ['admin', 'department'])) {
    echo json_encode(['success' => false, 'message' => 'Only Department Advisers/Deans can finalize schedule assignments.']);
    exit;
}

$clearance_id = intval($_POST['clearance_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$schedule_ids = $_POST['schedule_ids'] ?? [];
$remarks = trim($_POST['remarks'] ?? 'Section assignment and schedule finalized. Officially enrolled.');

if (!$clearance_id || !$student_id || empty($schedule_ids)) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one class schedule section.']);
    exit;
}

$db = getDB();

// =======================================================
// MANDATORY PRE-REQUISITE CHECK: STEP 3 ACCOUNTING CLEARANCE
// =======================================================
$stmt_chk3 = $db->prepare("SELECT status, signed_at FROM clearance_stages WHERE clearance_id = ? AND step_number = 3");
$stmt_chk3->execute([$clearance_id]);
$stage3 = $stmt_chk3->fetch();

if (!$stage3 || $stage3['status'] !== 'Cleared' || empty($stage3['signed_at'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Mandatory Security Requirement: Step 3 (Accounting & Financial Clearance) has not been signed. Students cannot proceed to Step 5 or be officially enrolled until Accounting signs their clearance.'
    ]);
    exit;
}

// Verify Step 4 (Registrar Subject Load Evaluation) is also Cleared
$stmt_chk4 = $db->prepare("SELECT status, signed_at FROM clearance_stages WHERE clearance_id = ? AND step_number = 4");
$stmt_chk4->execute([$clearance_id]);
$stage4 = $stmt_chk4->fetch();

if (!$stage4 || $stage4['status'] !== 'Cleared') {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot proceed to Step 5: Step 4 (Registrar Subject Load Evaluation) must be evaluated and approved first.'
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Clear previous enrollments for this clearance
    $stmt_del = $db->prepare("DELETE FROM student_enrollments WHERE clearance_id = ?");
    $stmt_del->execute([$clearance_id]);

    // 2. Insert new schedule enrollments and increment slots
    $stmt_ins = $db->prepare("INSERT INTO student_enrollments (clearance_id, student_id, schedule_id, enrolled_by_user_id, status) 
                              VALUES (?, ?, ?, ?, 'Enrolled')");
    $stmt_slot = $db->prepare("UPDATE schedules SET enrolled_slots = enrolled_slots + 1 WHERE id = ?");

    foreach ($schedule_ids as $sch_id) {
        $sch_id = intval($sch_id);
        $stmt_ins->execute([$clearance_id, $student_id, $sch_id, $user['id']]);
        $stmt_slot->execute([$sch_id]);
    }

    // 3. Mark Step 5 (Department Final Advising & Scheduling) as Cleared
    $stmt_stg = $db->prepare("UPDATE clearance_stages 
                             SET status = 'Cleared', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() 
                             WHERE clearance_id = ? AND step_number = 5");
    $stmt_stg->execute([$user['id'], $user['full_name'], $remarks, $clearance_id]);

    // 4. Update overall status to Enrolled
    $db->prepare("UPDATE clearance_requests SET current_step = 6, overall_status = 'Enrolled' WHERE id = ?")
       ->execute([$clearance_id]);

    $db->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = ?")
       ->execute([$student_id]);

    $db->commit();

    log_activity('ENROLLMENT_FINALIZED', "Finalized schedule & officially enrolled student ID $student_id (Clearance #$clearance_id)", $user['id']);

    echo json_encode([
        'success' => true,
        'message' => "Schedule assigned successfully! Student is now officially Enrolled and Certificate of Registration is generated."
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Scheduling failed: ' . $e->getMessage()]);
}
