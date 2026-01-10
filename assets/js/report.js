/**
 * Poll Report Functionality
 * Shared module for reporting polls
 */

import { api, escapeHtml, showToast, setButtonLoading, clearButtonLoading } from './app.js';
import { t } from './i18n.js';

// Report reasons
const getReportReasons = () => [
    { value: 'spam', label: t('report_reason_spam') },
    { value: 'harassment', label: t('report_reason_harassment') },
    { value: 'doxxing', label: t('report_reason_doxxing') },
    { value: 'illegal', label: t('report_reason_illegal') },
    { value: 'impersonation', label: t('report_reason_impersonation') },
    { value: 'phishing', label: t('report_reason_phishing') },
    { value: 'copyright', label: t('report_reason_copyright') },
    { value: 'other', label: t('report_reason_other') },
];

/**
 * Initialize the report button
 * @param {string} publicId - The poll's public ID
 */
export function initReportButton(publicId) {
    const reportBtn = document.getElementById('reportPollBtn');
    if (!reportBtn) return;

    reportBtn.addEventListener('click', () => showReportModal(publicId));
}

/**
 * Show the report modal
 * @param {string} publicId - The poll's public ID
 */
async function showReportModal(publicId) {
    if (!publicId) return;

    const overlay = document.createElement('div');
    overlay.className = 'report-modal-overlay';

    const reasonsHtml = getReportReasons().map(reason => `
        <label class="report-reason">
            <input type="radio" name="report_reason" value="${reason.value}">
            <span>${escapeHtml(reason.label)}</span>
        </label>
    `).join('');

    overlay.innerHTML = `
        <div class="report-modal">
            <div class="report-modal-header">
                <h3>${t('report_this_poll')}</h3>
            </div>
            <div class="report-modal-body">
                <p>${t('report_guidelines')}</p>
                <div class="report-reasons">
                    ${reasonsHtml}
                </div>
                <div class="report-note-group">
                    <label>${t('report_details')} <span class="optional">${t('report_optional')}</span></label>
                    <textarea id="reportNote" placeholder="${t('report_placeholder')}"></textarea>
                </div>
            </div>
            <div class="report-modal-actions">
                <button type="button" class="btn btn-secondary btn-cancel">${t('cancel')}</button>
                <button type="button" class="btn btn-primary btn-submit">${t('submit_report')}</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const cancelBtn = overlay.querySelector('.btn-cancel');
    const submitBtn = overlay.querySelector('.btn-submit');
    const noteInput = overlay.querySelector('#reportNote');

    const close = () => {
        overlay.remove();
    };

    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
    });

    const handleKeydown = (e) => {
        if (e.key === 'Escape') {
            document.removeEventListener('keydown', handleKeydown);
            close();
        }
    };
    document.addEventListener('keydown', handleKeydown);

    submitBtn.addEventListener('click', async () => {
        const selectedReason = overlay.querySelector('input[name="report_reason"]:checked');
        if (!selectedReason) {
            showToast(t('select_report_reason'), 'error');
            return;
        }

        const reason = selectedReason.value;
        const note = noteInput.value.trim();

        // Require note for "other" reason
        if (reason === 'other' && !note) {
            showToast(t('provide_report_details'), 'error');
            noteInput.focus();
            return;
        }

        try {
            setButtonLoading(submitBtn);
            await api.post(`/api/polls/${publicId}/report`, { reason, note });
            close();
            showToast(t('report_submitted'), 'success');
        } catch (err) {
            clearButtonLoading(submitBtn);
            showToast(err.message || t('error_loading'), 'error');
        }
    });

    // Focus the first radio button
    overlay.querySelector('input[name="report_reason"]')?.focus();
}
