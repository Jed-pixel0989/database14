// =======================================================
// Main JavaScript Application Helper
// =======================================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize search filters
    initTableSearch();
});

// Toast notification helper
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    const toast = document.createElement('div');
    
    const bgColors = {
        success: 'bg-emerald-900/90 border-emerald-500/50 text-emerald-100',
        error: 'bg-rose-900/90 border-rose-500/50 text-rose-100',
        info: 'bg-blue-900/90 border-blue-500/50 text-blue-100',
        warning: 'bg-amber-900/90 border-amber-500/50 text-amber-100'
    };

    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };

    toast.className = `flex items-center gap-3 px-4 py-3 rounded-xl border backdrop-blur-md shadow-2xl transition-all transform translate-y-2 opacity-0 text-sm font-medium ${bgColors[type] || bgColors.info}`;
    toast.innerHTML = `
        <span class="text-base">${icons[type] || '🔔'}</span>
        <div class="flex-1">${message}</div>
        <button onclick="this.parentElement.remove()" class="text-xs opacity-70 hover:opacity-100">&times;</button>
    `;

    toastContainer.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 10);

    // Auto remove
    setTimeout(() => {
        toast.classList.add('opacity-0', '-translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed top-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-auto';
    document.body.appendChild(container);
    return container;
}

// Modal open/close helpers
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active', 'show');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        modal.style.pointerEvents = 'auto';
        modal.style.zIndex = '999999';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active', 'show');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        modal.style.pointerEvents = 'none';
        document.body.style.overflow = '';
    }
}

// Quick Table Live Search
function initTableSearch() {
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-table-search');
        const table = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('input', () => {
            const term = input.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}

// Clearance Action Handler (AJAX)
async function submitClearanceAction(event, form) {
    event.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...`;

    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(result.message || 'Action could not be completed.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (err) {
        showToast('Network or server error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// User Profile Dropdown Toggle Helpers
function toggleUserProfileDropdown(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const dropdown = document.getElementById('user-profile-dropdown');
    const btn = document.getElementById('user-profile-btn');
    if (dropdown) {
        const isShown = dropdown.classList.contains('show') || dropdown.style.display === 'block';
        if (isShown) {
            dropdown.classList.remove('show');
            dropdown.style.display = 'none';
            if (btn) {
                btn.classList.remove('ring-indigo-400', 'scale-105');
                btn.setAttribute('aria-expanded', 'false');
            }
        } else {
            dropdown.classList.add('show');
            dropdown.style.display = 'block';
            if (btn) {
                btn.classList.add('ring-indigo-400', 'scale-105');
                btn.setAttribute('aria-expanded', 'true');
            }
            if (window.lucide && window.lucide.createIcons) {
                window.lucide.createIcons();
            }
        }
    }
}

function closeUserProfileDropdown() {
    const dropdown = document.getElementById('user-profile-dropdown');
    const btn = document.getElementById('user-profile-btn');
    if (dropdown) {
        dropdown.classList.remove('show');
        dropdown.style.display = 'none';
    }
    if (btn) {
        btn.classList.remove('ring-indigo-400', 'scale-105');
        btn.setAttribute('aria-expanded', 'false');
    }
}

function openUserProfileModal() {
    openModal('user-profile-modal');
}

// Global click & touch listeners to close dropdown when clicking outside or pressing Escape
document.addEventListener('click', (e) => {
    const container = document.getElementById('user-profile-menu-container');
    if (container && !container.contains(e.target)) {
        closeUserProfileDropdown();
    }
});

document.addEventListener('touchstart', (e) => {
    const container = document.getElementById('user-profile-menu-container');
    if (container && !container.contains(e.target)) {
        closeUserProfileDropdown();
    }
}, { passive: true });

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeUserProfileDropdown();
    }
});
