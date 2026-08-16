<?php
// =======================================================
// API: Sign or Flag Clearance Stage
// =======================================================

if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user = current_user();
$stage_id = intval($_POST['clearance_stage_id'] ?? 0);
$action_type = trim($_POST['action_type'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$title = trim($_POST['title'] ?? '');

if (!$stage_id || !in_array($action_type, ['approve', 'flag'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
    exit;
}

$db = getDB();

try {
    // 1. Fetch stage and clearance record
    $stmt = $db->prepare("SELECT cs.*, cr.id as clearance_id, cr.student_id, cr.current_step, cr.academic_term_id,
                                 s.first_name, s.last_name, s.student_no
                          FROM clearance_stages cs
                          JOIN clearance_requests cr ON cs.clearance_id = cr.id
                          JOIN students s ON cr.student_id = s.id
                          WHERE cs.id = ?");
    $stmt->execute([$stage_id]);
    $stage = $stmt->fetch();

    if (!$stage) {
        echo json_encode(['success' => false, 'message' => 'Clearance stage not found.']);
        exit;
    }

    $clearance_id = $stage['clearance_id'];
    $step_num = $stage['step_number'];
    $student_id = $stage['student_id'];
    $student_name = $stage['first_name'] . ' ' . $stage['last_name'];

    // 2. Validate role permission for the stage
    $allowed_roles = [
        1 => ['admin', 'department'],
        2 => ['admin', 'library'],
        3 => ['admin', 'accounting'],
        4 => ['admin', 'registrar'],
        5 => ['admin', 'department']
    ];

    if (!in_array($user['role'], $allowed_roles[$step_num] ?? [])) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to sign Step ' . $step_num]);
        exit;
    }

    if ($action_type === 'approve') {
        // Enforce Step 3 Accounting clearance before Step 5 can be approved
        if ($step_num == 5) {
            $stmt_chk3 = $db->prepare("SELECT status FROM clearance_stages WHERE clearance_id = ? AND step_number = 3");
            $stmt_chk3->execute([$clearance_id]);
            $stg3_status = $stmt_chk3->fetchColumn();

            if ($stg3_status !== 'Cleared') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Mandatory Requirement: Step 3 (Accounting Clearance) has not been signed. Step 5 and official enrollment cannot proceed.'
                ]);
                exit;
            }
        }

        // Mark stage cleared
        $stmt = $db->prepare("UPDATE clearance_stages 
                              SET status = 'Cleared', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$user['id'], $user['full_name'], $remarks, $stage_id]);

        // If Step 1 (Department Initial Clearance & Enrollment Form Approval), update verified enrollment details if provided
        if ($step_num == 1) {
            $upd_prog = intval($_POST['program_id'] ?? 0);
            $upd_year = intval($_POST['year_level'] ?? 0);
            $upd_sem = trim($_POST['enrolling_semester'] ?? '');
            $upd_stat = trim($_POST['academic_status'] ?? '');

            $upd_fields = [];
            $upd_vals = [];
            if ($upd_prog > 0) { $upd_fields[] = "program_id = ?"; $upd_vals[] = $upd_prog; }
            if ($upd_year > 0) { $upd_fields[] = "year_level = ?"; $upd_vals[] = $upd_year; }
            if (in_array($upd_sem, ['1st Semester', '2nd Semester'])) { $upd_fields[] = "enrolling_semester = ?"; $upd_vals[] = $upd_sem; }
            if (in_array($upd_stat, ['Regular', 'Irregular'])) { $upd_fields[] = "academic_status = ?"; $upd_vals[] = $upd_stat; }

            if (!empty($upd_fields)) {
                $upd_sql = "UPDATE students SET " . implode(', ', $upd_fields) . " WHERE id = ?";
                $upd_vals[] = $student_id;
                $db->prepare($upd_sql)->execute($upd_vals);
            }
        }

        // Advance clearance request step if current
        $next_step = $step_num + 1;
        $overall_status = ($next_step > 5) ? 'Cleared' : 'In Progress';
        
        $stmt_cr = $db->prepare("UPDATE clearance_requests 
                                 SET current_step = GREATEST(current_step, ?), overall_status = ? 
                                 WHERE id = ?");
        $stmt_cr->execute([$next_step, $overall_status, $clearance_id]);

        // If step 5 was approved, mark student as Enrolled
        if ($step_num == 5) {
            $db->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE id = ?")->execute([$student_id]);
            $db->prepare("UPDATE clearance_requests SET overall_status = 'Enrolled' WHERE id = ?")->execute([$clearance_id]);
        }

        log_activity('CLEARANCE_SIGNED', "Signed Step $step_num ({$stage['stage_title']}) for $student_name ($stage[student_no])", $user['id']);

        echo json_encode([
            'success' => true, 
            'message' => "Successfully approved and signed Step {$step_num} Enrollment Clearance for {$student_name}!"
        ]);

    } elseif ($action_type === 'flag') {
        // Mark stage flagged
        $stmt = $db->prepare("UPDATE clearance_stages 
                              SET status = 'Flagged', officer_user_id = ?, officer_name = ?, remarks = ?, signed_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$user['id'], $user['full_name'], $remarks, $stage_id]);

        // Update main clearance to Action Required
        $db->prepare("UPDATE clearance_requests SET overall_status = 'Action Required' WHERE id = ?")->execute([$clearance_id]);

        // Create deficiency entry
        $dept_code = $stage['stage_code'];
        $stmt_def = $db->prepare("INSERT INTO deficiencies (clearance_stage_id, student_id, department_code, title, description, status, created_by_user_id) 
                                 VALUES (?, ?, ?, ?, ?, 'Open', ?)");
        $stmt_def->execute([$stage_id, $student_id, $dept_code, $title, $remarks, $user['id']]);

        log_activity('CLEARANCE_FLAGGED', "Flagged Step $step_num deficiency: '$title' for $student_name ($stage[student_no])", $user['id']);

        echo json_encode([
            'success' => true, 
            'message' => "Clearance flagged with deficiency: '{$title}'."
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
