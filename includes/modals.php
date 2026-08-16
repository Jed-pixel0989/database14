<!-- Clearance Sign / Approve Modal -->
<div id="sign-modal" class="modal-overlay">
    <div class="modal-content p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-950/80 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
                    <i data-lucide="check" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Approve & Sign Clearance</h3>
                    <p class="text-xs text-slate-400" id="sign-student-name">Student Verification</p>
                </div>
            </div>
            <button onclick="closeModal('sign-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="sign-form" action="<?= url('api/sign_clearance.php') ?>" method="POST" onsubmit="submitClearanceAction(event, this)">
            <input type="hidden" name="clearance_stage_id" id="sign-stage-id" value="">
            <input type="hidden" name="action_type" value="approve">

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Remarks / Notes (Optional)</label>
                <textarea name="remarks" id="sign-remarks" rows="3" placeholder="e.g., Cleared for enrollment, no academic deficiencies..." class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('sign-modal')" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                    Confirm & Sign Off
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Clearance Flag / Reject / Deficiency Modal -->
<div id="flag-modal" class="modal-overlay">
    <div class="modal-content p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-950/80 border border-rose-500/40 flex items-center justify-center text-rose-400">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Flag Deficiency / Hold Clearance</h3>
                    <p class="text-xs text-slate-400" id="flag-student-name">Student Verification</p>
                </div>
            </div>
            <button onclick="closeModal('flag-modal')" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="flag-form" action="<?= url('api/sign_clearance.php') ?>" method="POST" onsubmit="submitClearanceAction(event, this)">
            <input type="hidden" name="clearance_stage_id" id="flag-stage-id" value="">
            <input type="hidden" name="action_type" value="flag">

            <div class="mb-3">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Deficiency Title</label>
                <input type="text" name="title" required placeholder="e.g., Unreturned Library Book: CS Algorithms, Unpaid Balance" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Detailed Instructions for Student</label>
                <textarea name="remarks" required rows="3" placeholder="Explain what the student must settle or submit to clear this hold..." class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('flag-modal')" class="px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-xs font-semibold hover:bg-slate-800 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-600/30 transition-all">
                    Flag & Issue Hold
                </button>
            </div>
        </form>
    </div>
</div>
