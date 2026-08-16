<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('admin');

$db = getDB();
$active_term = get_active_term();

// Handle New Student Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $student_no = trim($_POST['student_no']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $program_id = intval($_POST['program_id']);
    $year_level = intval($_POST['year_level']);
    $academic_status = trim($_POST['academic_status']);
    $contact_no = trim($_POST['contact_no']);

    try {
        $db->beginTransaction();

        // 1. Create user account
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt_u = $db->prepare("INSERT INTO users (username, email, password, role, full_name, status) VALUES (?, ?, ?, 'student', ?, 'active')");
        $stmt_u->execute([$student_no, $email, $password_hash, "$first_name $last_name"]);
        $user_id = $db->lastInsertId();

        // 2. Create student record
        $stmt_s = $db->prepare("INSERT INTO students (user_id, student_no, first_name, middle_name, last_name, program_id, year_level, academic_status, contact_no, enrollment_status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Clearance')");
        $stmt_s->execute([$user_id, $student_no, $first_name, $middle_name, $last_name, $program_id, $year_level, $academic_status, $contact_no]);
        $new_student_id = $db->lastInsertId();

        // 3. Initiate clearance
        init_student_clearance($new_student_id, $active_term['id']);

        $db->commit();
        log_activity('STUDENT_ADDED', "Added new student $first_name $last_name ($student_no)", $user['id']);
        set_flash('success', "Student $first_name $last_name ($student_no) created and clearance initiated!");
        header("Location: " . url('modules/admin/students.php'));
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', "Failed to add student: " . $e->getMessage());
    }
}

// Fetch all students
$students = $db->query("SELECT s.*, p.code as program_code, p.name as program_name,
                               cr.id as clearance_id, cr.current_step, cr.overall_status
                        FROM students s 
                        JOIN programs p ON s.program_id = p.id 
                        LEFT JOIN clearance_requests cr ON s.id = cr.student_id AND cr.academic_term_id = {$active_term['id']}
                        ORDER BY s.id DESC")->fetchAll();

$programs = $db->query("SELECT * FROM programs ORDER BY code")->fetchAll();

$page_title = "Manage Students Masterlist";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="users" class="w-7 h-7 text-indigo-400"></i>
                Student Directory & Master Records
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Manage university student roster, enrollments, and clearance stages.
            </p>
        </div>

        <button onclick="openModal('add-student-modal')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            Add New Student
        </button>
    </div>

    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">All Registered Students</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="admin-stud-table" placeholder="Search student name, ID, course..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="admin-stud-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Academic Status</th>
                        <th>Clearance Phase</th>
                        <th>Enrollment Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row): ?>
                        <tr>
                            <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($row['student_no']) ?></td>
                            <td class="font-semibold text-white"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
                            <td class="font-medium text-slate-300"><?= htmlspecialchars($row['program_code']) ?></td>
                            <td class="text-slate-300">Year <?= htmlspecialchars($row['year_level']) ?></td>
                            <td>
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-300">
                                    <?= htmlspecialchars($row['academic_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-950 text-indigo-300 border border-indigo-800">
                                    Step <?= $row['current_step'] ?? 1 ?> of 5
                                </span>
                            </td>
                            <td><?= get_status_badge($row['enrollment_status']) ?></td>
                            <td class="text-right">
                                <?php if ($row['enrollment_status'] === 'Enrolled'): ?>
                                    <a href="<?= url('modules/student/print_cor.php?student_id=' . $row['id']) ?>" target="_blank" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 inline-flex items-center gap-1">
                                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                        COR
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add Student Modal -->
<div id="add-student-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Enroll New Student</h3>
                    <p class="text-xs text-slate-400">Create profile and launch clearance pipeline</p>
                </div>
            </div>
            <button onclick="closeModal('add-student-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="students.php" class="space-y-4 text-xs">
            <input type="hidden" name="add_student" value="1">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Student Number</label>
                    <input type="text" name="student_no" required placeholder="e.g. 2026-0006" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Email Address</label>
                    <input type="email" name="email" required placeholder="student@student.edu" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">First Name</label>
                    <input type="text" name="first_name" required placeholder="Juan" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Middle Name</label>
                    <input type="text" name="middle_name" placeholder="Protacio" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Last Name</label>
                    <input type="text" name="last_name" required placeholder="Dela Cruz" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Program / Course</label>
                    <select name="program_id" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Year Level</label>
                    <select name="year_level" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="1">1st Year</option>
                        <option value="2" selected>2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Academic Status</label>
                    <select name="academic_status" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="Regular">Regular</option>
                        <option value="Irregular">Irregular</option>
                        <option value="Probationary">Probationary</option>
                        <option value="Graduating">Graduating</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Contact Phone</label>
                <input type="text" name="contact_no" placeholder="0917-000-0000" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <p class="text-[11px] text-slate-400 italic">
                * Note: Default login password for student will be <code class="text-indigo-300 font-mono">password123</code>.
            </p>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('add-student-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">
                    Create & Initiate Clearance
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
