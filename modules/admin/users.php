<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('admin');

$db = getDB();
$current_admin = current_user();

// Handle Form Actions: Add, Edit, Reset Password, Toggle Status, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD USER
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?: 'password123';
        $role = trim($_POST['role'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $department_id = intval($_POST['department_id'] ?: 0) ?: null;

        if (empty($username) || empty($email) || empty($role) || empty($full_name)) {
            set_flash('error', 'Please fill in all required fields.');
        } else {
            // Check uniqueness
            $stmt_check = $db->prepare("SELECT (SELECT COUNT(*) FROM users WHERE username = ?) as u_count, (SELECT COUNT(*) FROM users WHERE email = ?) as e_count");
            $stmt_check->execute([$username, $email]);
            $counts = $stmt_check->fetch();

            if ($counts['u_count'] > 0) {
                set_flash('error', "Username '$username' is already taken.");
            } elseif ($counts['e_count'] > 0) {
                set_flash('error', "Email '$email' is already in use.");
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, full_name, department_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$username, $email, $hash, $role, $full_name, $department_id]);
                    log_activity('USER_CREATED', "Admin created $role account for $full_name ($username)", $current_admin['id']);
                    set_flash('success', "New " . strtoupper($role) . " account created for $full_name ($username)!");
                } catch (Exception $e) {
                    set_flash('error', "Failed to create user: " . $e->getMessage());
                }
            }
        }
        header("Location: " . url('modules/admin/users.php'));
        exit;
    }

    // 2. EDIT USER
    if ($action === 'edit_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $department_id = intval($_POST['department_id'] ?: 0) ?: null;
        $status = trim($_POST['status'] ?? 'active');

        if ($user_id && $full_name && $email && $role) {
            try {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, department_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $role, $department_id, $status, $user_id]);
                log_activity('USER_UPDATED', "Admin updated user #$user_id ($full_name)", $current_admin['id']);
                set_flash('success', "User $full_name updated successfully!");
            } catch (Exception $e) {
                set_flash('error', "Failed to update user: " . $e->getMessage());
            }
        }
        header("Location: " . url('modules/admin/users.php'));
        exit;
    }

    // 3. RESET PASSWORD
    if ($action === 'reset_password') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?: 'password123';

        if ($user_id) {
            try {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $user_id]);
                log_activity('PASSWORD_RESET', "Admin reset password for user #$user_id", $current_admin['id']);
                set_flash('success', "Password for user #$user_id has been reset successfully!");
            } catch (Exception $e) {
                set_flash('error', "Failed to reset password: " . $e->getMessage());
            }
        }
        header("Location: " . url('modules/admin/users.php'));
        exit;
    }

    // 4. TOGGLE STATUS (Active / Inactive)
    if ($action === 'toggle_status') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $current_status = trim($_POST['current_status'] ?? 'active');
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        if ($user_id && $user_id !== $current_admin['id']) {
            try {
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                log_activity('STATUS_TOGGLED', "Admin changed status of user #$user_id to $new_status", $current_admin['id']);
                set_flash('success', "User account is now $new_status.");
            } catch (Exception $e) {
                set_flash('error', "Failed to toggle status: " . $e->getMessage());
            }
        }
        header("Location: " . url('modules/admin/users.php'));
        exit;
    }
}

// Role filter (Excluding students from Staff & Users interface)
$filter_role = trim($_GET['role'] ?? 'all');
$query = "SELECT u.*, d.name as department_name, d.code as department_code 
          FROM users u 
          LEFT JOIN departments d ON u.department_id = d.id 
          WHERE u.role != 'student' ";

$params = [];
if ($filter_role !== 'all') {
    $query .= " AND u.role = ? ";
    $params[] = $filter_role;
}
$query .= " ORDER BY (CASE WHEN u.role = 'admin' THEN 1 WHEN u.role = 'registrar' THEN 2 WHEN u.role = 'department' THEN 3 WHEN u.role = 'accounting' THEN 4 WHEN u.role = 'library' THEN 5 ELSE 6 END), u.id ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Role counts for staff tabs
$role_counts = $db->query("SELECT role, COUNT(*) as cnt FROM users WHERE role != 'student' GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_staff = array_sum($role_counts);

$departments = $db->query("SELECT * FROM departments ORDER BY code")->fetchAll();

$page_title = "Staff & Signatories User Accounts";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-950 text-purple-300 border border-purple-800">
                    Administrator
                </span>
                <span class="text-xs text-slate-400">Institutional Access Control</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 flex items-center gap-2">
                <i data-lucide="shield-check" class="w-7 h-7 text-indigo-400"></i>
                Signatories & User Management
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Create and manage operational user accounts for <strong>Registrar</strong>, <strong>Library</strong>, <strong>Department Heads</strong>, <strong>Accounting</strong>, and <strong>Administrators</strong>.
            </p>
        </div>

        <button onclick="openModal('add-user-modal')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            Add New User Account
        </button>
    </div>

    <!-- Quick Role Cards / Signatory Roles Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
        
        <!-- Registrar -->
        <div class="glass-card p-4 space-y-2 border-t-2 border-indigo-500">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                    <i data-lucide="clipboard-check" class="w-4 h-4 text-indigo-400"></i>
                    Registrar
                </span>
                <span class="px-2 py-0.5 rounded-full bg-indigo-950 text-indigo-300 font-mono font-bold"><?= $role_counts['registrar'] ?? 0 ?></span>
            </div>
            <div class="text-[11px] text-slate-400 leading-tight">
                Signs <strong>Step 4</strong> (Curriculum evaluation & allowable subject load).
            </div>
        </div>

        <!-- Library -->
        <div class="glass-card p-4 space-y-2 border-t-2 border-cyan-500">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                    <i data-lucide="book-open" class="w-4 h-4 text-cyan-400"></i>
                    Library
                </span>
                <span class="px-2 py-0.5 rounded-full bg-cyan-950 text-cyan-300 font-mono font-bold"><?= $role_counts['library'] ?? 0 ?></span>
            </div>
            <div class="text-[11px] text-slate-400 leading-tight">
                Signs <strong>Step 2</strong> (Circulation, unreturned books, & fines).
            </div>
        </div>

        <!-- Department -->
        <div class="glass-card p-4 space-y-2 border-t-2 border-blue-500">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                    <i data-lucide="building-2" class="w-4 h-4 text-blue-400"></i>
                    Department
                </span>
                <span class="px-2 py-0.5 rounded-full bg-blue-950 text-blue-300 font-mono font-bold"><?= $role_counts['department'] ?? 0 ?></span>
            </div>
            <div class="text-[11px] text-slate-400 leading-tight">
                Signs <strong>Step 1</strong> (Initial) & <strong>Step 5</strong> (Section scheduling).
            </div>
        </div>

        <!-- Accounting -->
        <div class="glass-card p-4 space-y-2 border-t-2 border-emerald-500">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                    <i data-lucide="credit-card" class="w-4 h-4 text-emerald-400"></i>
                    Accounting
                </span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-300 font-mono font-bold"><?= $role_counts['accounting'] ?? 0 ?></span>
            </div>
            <div class="text-[11px] text-slate-400 leading-tight">
                Signs <strong>Step 3</strong> (Tuition assessment & downpayment validation).
            </div>
        </div>

        <!-- Admin -->
        <div class="glass-card p-4 space-y-2 border-t-2 border-purple-500">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                    <i data-lucide="shield" class="w-4 h-4 text-purple-400"></i>
                    Admin
                </span>
                <span class="px-2 py-0.5 rounded-full bg-purple-950 text-purple-300 font-mono font-bold"><?= $role_counts['admin'] ?? 0 ?></span>
            </div>
            <div class="text-[11px] text-slate-400 leading-tight">
                Full institutional command, user provisioning, & curriculum control.
            </div>
        </div>

    </div>

    <!-- Role Filter Tabs & Search -->
    <div class="glass-card p-6 space-y-4">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            
            <!-- Filter Tabs -->
            <div class="flex flex-wrap gap-1.5 text-xs">
                <a href="?role=all" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    All Staff (<?= $total_staff ?>)
                </a>
                <a href="?role=registrar" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'registrar' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    Registrar (<?= $role_counts['registrar'] ?? 0 ?>)
                </a>
                <a href="?role=library" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'library' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    Library (<?= $role_counts['library'] ?? 0 ?>)
                </a>
                <a href="?role=department" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'department' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    Department (<?= $role_counts['department'] ?? 0 ?>)
                </a>
                <a href="?role=accounting" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'accounting' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    Accounting (<?= $role_counts['accounting'] ?? 0 ?>)
                </a>
                <a href="?role=admin" class="px-3 py-1.5 rounded-xl font-bold transition-all <?= $filter_role === 'admin' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' ?>">
                    Admin (<?= $role_counts['admin'] ?? 0 ?>)
                </a>
            </div>

            <!-- Live Table Search -->
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="users-table" placeholder="Search user, role, name, email..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <!-- Users Master Table -->
        <div class="data-table-container">
            <table class="data-table" id="users-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name & Title</th>
                        <th>Role & Authority</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $role_badge = [
                            'admin' => 'bg-purple-950 text-purple-300 border-purple-800',
                            'registrar' => 'bg-indigo-950 text-indigo-300 border-indigo-800',
                            'department' => 'bg-blue-950 text-blue-300 border-blue-800',
                            'accounting' => 'bg-emerald-950 text-emerald-300 border-emerald-800',
                            'library' => 'bg-cyan-950 text-cyan-300 border-cyan-800',
                            'student' => 'bg-slate-800 text-slate-400 border-slate-700'
                        ][$u['role']] ?? 'bg-slate-800 text-slate-300 border-slate-700';

                        $auth_label = [
                            'admin' => 'Full Administrative Access',
                            'registrar' => 'Step 4 Signatory (Subject Load)',
                            'department' => 'Steps 1 & 5 Signatory (Advising/Sched)',
                            'accounting' => 'Step 3 Signatory (Financial)',
                            'library' => 'Step 2 Signatory (Library)',
                            'student' => 'Student Access'
                        ][$u['role']] ?? 'Standard Access';
                    ?>
                        <tr>
                            <td class="font-mono font-bold text-indigo-400"><?= htmlspecialchars($u['username']) ?></td>
                            <td>
                                <div class="font-semibold text-white"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= $auth_label ?></div>
                            </td>
                            <td>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border <?= $role_badge ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td class="text-slate-300 text-xs">
                                <?= $u['department_name'] ? htmlspecialchars($u['department_code'] . ' - ' . $u['department_name']) : '<span class="text-slate-500 italic">Institution-wide</span>' ?>
                            </td>
                            <td class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= get_status_badge($u['status']) ?></td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    
                                    <!-- Edit Button -->
                                    <button onclick='openEditModal(<?= json_encode($u) ?>)' title="Edit User" class="p-1.5 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white transition-colors">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Reset Password Button -->
                                    <button onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Reset Password" class="p-1.5 rounded-lg bg-slate-800 hover:bg-amber-600 text-slate-300 hover:text-white transition-colors">
                                        <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Toggle Status Button -->
                                    <?php if ($u['id'] !== $current_admin['id']): ?>
                                        <form method="POST" action="users.php" class="inline" onsubmit="return confirm('Toggle status for <?= htmlspecialchars($u['username']) ?>?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                            <button type="submit" title="<?= $u['status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white transition-colors">
                                                <i data-lucide="<?= $u['status'] === 'active' ? 'user-x' : 'user-check' ?>" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ======================================================== -->
<!-- MODAL: ADD USER ACCOUNT (Registrar, Library, Dept, Accounting, Admin) -->
<!-- ======================================================== -->
<div id="add-user-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Create Staff / Signatory Account</h3>
                    <p class="text-xs text-slate-400">Assign role and clearance authority</p>
                </div>
            </div>
            <button onclick="closeModal('add-user-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_user">

            <!-- System Role Selection Cards -->
            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-2">Select User Role & Authority *</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-indigo-500 cursor-pointer flex items-start gap-2">
                        <input type="radio" name="role" value="registrar" required class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <div class="font-bold text-white text-xs">📋 Registrar</div>
                            <div class="text-[10px] text-slate-400">Step 4 Subject Load Eval</div>
                        </div>
                    </label>

                    <label class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-cyan-500 cursor-pointer flex items-start gap-2">
                        <input type="radio" name="role" value="library" required class="mt-0.5 text-cyan-600 focus:ring-cyan-500">
                        <div>
                            <div class="font-bold text-white text-xs">📚 Library</div>
                            <div class="text-[10px] text-slate-400">Step 2 Book Clearance</div>
                        </div>
                    </label>

                    <label class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-blue-500 cursor-pointer flex items-start gap-2">
                        <input type="radio" name="role" value="department" required class="mt-0.5 text-blue-600 focus:ring-blue-500">
                        <div>
                            <div class="font-bold text-white text-xs">🏛️ Department Head</div>
                            <div class="text-[10px] text-slate-400">Steps 1 & 5 Signing/Sched</div>
                        </div>
                    </label>

                    <label class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-emerald-500 cursor-pointer flex items-start gap-2">
                        <input type="radio" name="role" value="accounting" required class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <div class="font-bold text-white text-xs">💳 Accounting</div>
                            <div class="text-[10px] text-slate-400">Step 3 Tuition Clearance</div>
                        </div>
                    </label>

                    <label class="p-2.5 rounded-xl bg-slate-900 border border-slate-700 hover:border-purple-500 cursor-pointer flex items-start gap-2 col-span-2">
                        <input type="radio" name="role" value="admin" required class="mt-0.5 text-purple-600 focus:ring-purple-500">
                        <div>
                            <div class="font-bold text-white text-xs">⚙️ System Administrator</div>
                            <div class="text-[10px] text-slate-400">Full system control, curriculum, schedules, and user management</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Username *</label>
                    <input type="text" name="username" required placeholder="e.g. reg_officer1" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Email Address *</label>
                    <input type="email" name="email" required placeholder="staff@seait.edu" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Full Name & Title *</label>
                <input type="text" name="full_name" required placeholder="e.g. Prof. Alan Turing, Ph.D." class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Department (For Dept Heads)</label>
                    <select name="department_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">None / Institution-wide</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['code']) ?> - <?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Initial Password</label>
                    <input type="password" name="password" placeholder="Default: password123" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('add-user-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">
                    Create User Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT USER ACCOUNT -->
<!-- ======================================================== -->
<div id="edit-user-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-950 border border-indigo-500/40 flex items-center justify-center text-indigo-400">
                    <i data-lucide="edit" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Edit User Profile</h3>
                    <p class="text-xs text-slate-400" id="edit-user-sub">Update profile details</p>
                </div>
            </div>
            <button onclick="closeModal('edit-user-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit-user-id">

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Full Name & Title</label>
                <input type="text" name="full_name" id="edit-full-name" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Email Address</label>
                    <input type="email" name="email" id="edit-email" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Role</label>
                    <select name="role" id="edit-role" required class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="registrar">Registrar (Step 4)</option>
                        <option value="library">Library (Step 2)</option>
                        <option value="department">Department Head (Step 1 & 5)</option>
                        <option value="accounting">Accounting (Step 3)</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Department</label>
                    <select name="department_id" id="edit-dept-id" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">None / Institution-wide</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['code']) ?> - <?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" id="edit-status" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('edit-user-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: RESET PASSWORD -->
<!-- ======================================================== -->
<div id="reset-pwd-modal" class="modal-overlay">
    <div class="modal-content p-6 max-w-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-950 border border-amber-500/40 flex items-center justify-center text-amber-400">
                    <i data-lucide="key" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Reset User Password</h3>
                    <p class="text-xs text-slate-400" id="reset-pwd-sub">Set new password</p>
                </div>
            </div>
            <button onclick="closeModal('reset-pwd-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="users.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-user-id">

            <div>
                <label class="block font-semibold text-slate-300 uppercase tracking-wider mb-1">New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password" value="password123" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:border-amber-500">
                <p class="text-[11px] text-slate-500 mt-1">Default suggested: <code class="text-amber-300 font-mono">password123</code></p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeModal('reset-pwd-modal')" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold shadow-lg shadow-amber-600/30">
                    Confirm Reset
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(userData) {
    document.getElementById('edit-user-id').value = userData.id;
    document.getElementById('edit-full-name').value = userData.full_name;
    document.getElementById('edit-email').value = userData.email;
    document.getElementById('edit-role').value = userData.role;
    document.getElementById('edit-dept-id').value = userData.department_id || '';
    document.getElementById('edit-status').value = userData.status;
    document.getElementById('edit-user-sub').innerText = 'Editing: ' + userData.username;
    openModal('edit-user-modal');
}

function openResetPasswordModal(userId, username) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-pwd-sub').innerText = 'For account: ' + username;
    openModal('reset-pwd-modal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
