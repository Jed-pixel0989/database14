<?php
// =======================================================
// API: Resolve Open Deficiency
// =======================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user = current_user();
$deficiency_id = intval($_POST['deficiency_id'] ?? 0);

if (!$deficiency_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid deficiency ID.']);
    exit;
}

$db = getDB();

try {
    $stmt = $db->prepare("SELECT d.*, cs.clearance_id, cs.step_number, s.first_name, s.last_name 
                          FROM deficiencies d
                          JOIN clearance_stages cs ON d.clearance_stage_id = cs.id
                          JOIN students s ON d.student_id = s.id
                          WHERE d.id = ?");
    $stmt->execute([$deficiency_id]);
    $def = $stmt->fetch();

    if (!$def) {
        echo json_encode(['success' => false, 'message' => 'Deficiency record not found.']);
        exit;
    }

    // Mark deficiency resolved
    $db->prepare("UPDATE deficiencies SET status = 'Resolved', resolved_at = NOW() WHERE id = ?")->execute([$deficiency_id]);

    // Check if there are remaining open deficiencies for this clearance stage
    $stmt_rem = $db->prepare("SELECT COUNT(*) FROM deficiencies WHERE clearance_stage_id = ? AND status = 'Open'");
    $stmt_rem->execute([$def['clearance_stage_id']]);
    $rem_count = $stmt_rem->fetchColumn();

    if ($rem_count == 0) {
        // Stage has no more deficiencies, update stage back to Pending or Cleared
        $db->prepare("UPDATE clearance_stages SET status = 'Pending' WHERE id = ?")->execute([$def['clearance_stage_id']]);
        $db->prepare("UPDATE clearance_requests SET overall_status = 'In Progress' WHERE id = ?")->execute([$def['clearance_id']]);
    }

    log_activity('DEFICIENCY_RESOLVED', "Resolved deficiency #$deficiency_id: {$def['title']} for student {$def['first_name']} {$def['last_name']}", $user['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Deficiency marked as Resolved successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
