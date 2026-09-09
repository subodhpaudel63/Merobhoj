/* ============================================================
   SHARED CONFIRMATION POPUP — the same popup container the
   admin panel uses (delete-confirm-modal), packaged so every
   dashboard can use it without loading the admin stylesheet.

   Loaded on client & public pages via footer.php. The admin,
   staff, chef and rider panels already ship an identical copy
   inside assets/js/adminscript.js (styles in adminstyle.css);
   this file is the standalone version for everywhere else,
   with styles in assets/css/confirm_modal.css.

   Callback style (same API as the admin panel):

       openDeleteConfirm({
           title: 'Delete Order?',
           message: 'It will be permanently deleted.',
           confirmText: 'Delete',              // optional
           type: 'danger' | 'success',         // optional (default danger)
           showInput: true,                    // optional (textarea)
           onConfirm: function (inputVal) {}   // runs when confirmed
       });

   Promise style (handy for async/await code):

       const ok = await confirmAction({ title: 'Cancel?', message: '...' });
       if (!ok) return;

   For plain POST forms use:
       <form onsubmit="return confirmFormDelete(event, 'Title', 'Message')">
   ============================================================ */

window.openDeleteConfirm = function (options) {
    const opts = options || {};
    const type = opts.type || 'danger'; // 'danger' or 'success'

    const existing = document.getElementById('deleteConfirmModal');
    if (existing) existing.remove();

    const iconColor = type === 'success' ? '#10b981' : '#dc2626';
    const btnClass = type === 'success' ? 'btn-success-confirm' : 'btn-delete-confirm';

    // Icon SVG based on type
    const svgIcon = type === 'success'
        ? `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>`
        : `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>`;

    const modalHtml = `
        <div id="deleteConfirmModal" class="delete-confirm-overlay">
            <div class="delete-confirm-modal">
                <div class="icon-wrapper${type === 'success' ? ' success' : ''}">
                    ${svgIcon}
                </div>
                <h3></h3>
                <p></p>
                ${opts.showInput ? `<textarea id="confirmInput" class="mb-input" placeholder="${opts.inputPlaceholder || ''}"></textarea>` : ''}
                <div class="delete-confirm-buttons">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="button" class="${btnClass}"></button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modal = document.getElementById('deleteConfirmModal');
    modal.querySelector('h3').textContent = opts.title || 'Are you sure?';
    modal.querySelector('p').textContent = opts.message
        || 'This action cannot be undone.';

    const confirmBtn = modal.querySelector(`.${btnClass}`);
    confirmBtn.textContent = opts.confirmText || (type === 'success' ? 'Confirm' : 'Delete');

    confirmBtn.addEventListener('click', function () {
        const inputVal = opts.showInput ? document.getElementById('confirmInput').value.trim() : '';
        if (opts.showInput && opts.requireInput && !inputVal) {
            alert('Please provide a reason.');
            return;
        }
        closeDeleteConfirm();
        if (typeof opts.onConfirm === 'function') opts.onConfirm(inputVal);
    });

    const cancelBtn = modal.querySelector('.btn-cancel');
    cancelBtn.addEventListener('click', function () {
        closeDeleteConfirm();
    });

    modal.addEventListener('click', function (e) {
        if (e.target === this) closeDeleteConfirm();
    });
};

window.closeDeleteConfirm = function () {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease-in-out';
        setTimeout(() => modal.remove(), 300);
    }
};

/* Confirmation wrapper for plain POST forms */
window.confirmFormDelete = function (event, title, message) {
    event.preventDefault();
    const form = event.target;
    openDeleteConfirm({
        title: title,
        message: message,
        onConfirm: function () { form.submit(); }
    });
    return false;
};

/* Promise-based wrapper: resolves true when confirmed, false when the
   popup is dismissed (Cancel button, overlay click or Escape key).
   Falls back to the native confirm() if the modal is unavailable. */
window.confirmAction = function (options) {
    return new Promise(function (resolve) {
        if (typeof window.openDeleteConfirm !== 'function') {
            const fallbackOpts = options || {};
            resolve(window.confirm((fallbackOpts.title ? fallbackOpts.title + '\n\n' : '') + (fallbackOpts.message || 'Are you sure?')));
            return;
        }

        let settled = false;
        const opts = Object.assign({}, options || {});
        const userOnConfirm = opts.onConfirm;
        opts.onConfirm = function (inputVal) {
            settled = true;
            resolve(true);
            if (typeof userOnConfirm === 'function') userOnConfirm(inputVal);
        };

        openDeleteConfirm(opts);

        // Cancel / Escape / overlay-click all funnel through
        // closeDeleteConfirm(), which removes the modal from the DOM —
        // watch for that removal to resolve false.
        const observer = new MutationObserver(function () {
            if (settled) {
                observer.disconnect();
                return;
            }
            if (!document.getElementById('deleteConfirmModal')) {
                settled = true;
                observer.disconnect();
                resolve(false);
            }
        });
        observer.observe(document.body, { childList: true });
    });
};

/* Close the confirmation popup with Escape */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDeleteConfirm();
});

