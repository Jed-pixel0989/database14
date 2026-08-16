<?php
require_once __DIR__ . '/../../config/functions.php';
require_role('admin');

$db = getDB();

$logs = $db->query("SELECT a.*, u.username, u.full_name, u.role 
                    FROM activity_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.id DESC LIMIT 200")->fetchAll();

$page_title = "Audit & Activity Logs";
include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                <i data-lucide="scroll-text" class="w-7 h-7 text-indigo-400"></i>
                System Audit Trail & Activity Logs
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Immutable event trail of student clearances, approvals, holds, payments, and system operations.
            </p>
        </div>

        <a href="<?= url('modules/admin/dashboard.php') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            &larr; Back to Dashboard
        </a>
    </div>

    <div class="glass-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-white">Recent 200 Events</h2>
            <div class="w-full sm:w-72">
                <input type="text" data-table-search="logs-table" placeholder="Search action, user, details..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table" id="logs-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Event Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-500 italic">No activity logs recorded.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="font-mono text-xs text-slate-400 whitespace-nowrap"><?= format_date($log['created_at']) ?></td>
                                <td class="font-semibold text-white"><?= htmlspecialchars($log['full_name'] ?? ($log['username'] ?? 'System')) ?></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300">
                                        <?= htmlspecialchars($log['role'] ?? 'System') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-bold text-indigo-400">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="text-xs text-slate-300"><?= htmlspecialchars($log['details']) ?></td>
                                <td class="font-mono text-[11px] text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
