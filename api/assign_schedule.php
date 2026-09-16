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
$schedule_ids = is_array($schedule_ids) ? array_values(array_unique(array_filter(array_map('intval', $schedule_ids)))) : [];
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

    // Lock the clearance and selected blocks while validating. This prevents
    // two advisers from enrolling the last slot in the same block at once.
    $stmt_owner = $db->prepare('SELECT id FROM clearance_requests WHERE id = ? AND student_id = ? FOR UPDATE');
    $stmt_owner->execute([$clearance_id, $student_id]);
    if (!$stmt_owner->fetch()) {
        throw new RuntimeException('The selected clearance does not belong to this student.');
    }

    $placeholders = implode(',', array_fill(0, count($schedule_ids), '?'));
    $stmt_blocks = $db->prepare("SELECT id, max_slots, enrolled_slots FROM schedules WHERE id IN ($placeholders) FOR UPDATE");
    $stmt_blocks->execute($schedule_ids);
    $blocks = $stmt_blocks->fetchAll();
    if (count($blocks) !== count($schedule_ids)) {
        throw new RuntimeException('One or more selected schedule blocks no longer exist. Please refresh and try again.');
    }

    // Existing assignments are being replaced, so their slots become free
    // before the new block selection is checked.
    $stmt_old = $db->prepare('SELECT schedule_id FROM student_enrollments WHERE clearance_id = ?');
    $stmt_old->execute([$clearance_id]);
    $old_schedule_ids = array_map('intval', $stmt_old->fetchAll(PDO::FETCH_COLUMN));
    $old_counts = array_count_values($old_schedule_ids);
    foreach ($blocks as &$block) {
        $block['available_slots'] = (int) $block['max_slots'] - (int) $block['enrolled_slots'] + ($old_counts[(int) $block['id']] ?? 0);
        if ($block['available_slots'] < 1) {
            throw new RuntimeException('The selected block is already full. Please choose another section.');
        }
    }
    unset($block);

    foreach ($old_counts as $old_schedule_id => $old_count) {
        $db->prepare('UPDATE schedules SET enrolled_slots = GREATEST(0, enrolled_slots - ?) WHERE id = ?')
            ->execute([$old_count, $old_schedule_id]);
    }

    // 1. Clear previous enrollments for this clearance
    $stmt_del = $db->prepare("DELETE FROM student_enrollments WHERE clearance_id = ?");
    $stmt_del->execute([$clearance_id]);

    // 2. Insert new schedule enrollments and increment slots
    $stmt_ins = $db->prepare("INSERT INTO student_enrollments (clearance_id, student_id, schedule_id, enrolled_by_user_id, status) 
                              VALUES (?, ?, ?, ?, 'Enrolled')");
    $stmt_slot = $db->prepare('UPDATE schedules SET enrolled_slots = enrolled_slots + 1 WHERE id = ? AND enrolled_slots < max_slots');

    foreach ($schedule_ids as $sch_id) {
        $stmt_ins->execute([$clearance_id, $student_id, $sch_id, $user['id']]);
        $stmt_slot->execute([$sch_id]);
        if ($stmt_slot->rowCount() !== 1) {
            throw new RuntimeException('A selected block became full while saving. No changes were kept.');
        }
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
