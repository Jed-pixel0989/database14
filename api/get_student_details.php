<?php
// =======================================================
// API: Get Student Details & Clearance History
// =======================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$student_id = intval($_GET['student_id'] ?? 0);
if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit;
}

$db = getDB();
$active_term = get_active_term();

try {
    $clearance = get_student_clearance_record($student_id, $active_term['id']);

    if (!$clearance) {
        echo json_encode(['success' => false, 'message' => 'Clearance record not found.']);
        exit;
    }

    // Get Subject Loads if any
    $stmt_load = $db->prepare("SELECT s.* FROM student_subject_loads sload JOIN subjects s ON sload.subject_id = s.id WHERE sload.clearance_id = ?");
    $stmt_load->execute([$clearance['id']]);
    $subject_loads = $stmt_load->fetchAll();

    // Get Enrollments if any
    $stmt_enr = $db->prepare("SELECT sch.*, sub.code, sub.title, sub.total_units 
                             FROM student_enrollments se 
                             JOIN schedules sch ON se.schedule_id = sch.id 
                             JOIN subjects sub ON sch.subject_id = sub.id 
                             WHERE se.clearance_id = ?");
    $stmt_enr->execute([$clearance['id']]);
    $enrollments = $stmt_enr->fetchAll();

    // Get Assessments if any
    $stmt_acc = $db->prepare("SELECT * FROM accounting_assessments WHERE clearance_id = ?");
    $stmt_acc->execute([$clearance['id']]);
    $assessment = $stmt_acc->fetch();

    // Get Deficiencies if any
    $stmt_def = $db->prepare("SELECT * FROM deficiencies WHERE student_id = ? ORDER BY created_at DESC");
    $stmt_def->execute([$student_id]);
    $deficiencies = $stmt_def->fetchAll();

    echo json_encode([
        'success' => true,
        'clearance' => $clearance,
        'subject_loads' => $subject_loads,
        'enrollments' => $enrollments,
        'assessment' => $assessment,
        'deficiencies' => $deficiencies
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
