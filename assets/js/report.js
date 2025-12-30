/**
 * Poll Report Functionality
 * Shared module for reporting polls
 */

import { api, escapeHtml, showToast, setButtonLoading, clearButtonLoading } from './app.js';

// Report reasons
const REPORT_REASONS = [
    { value: 'spam', label: 'Spam or misleading content' },
    { value: 'harassment', label: 'Harassment or hate speech' },
    { value: 'doxxing', label: 'Personal information exposure (doxxing)' },
    { value: 'illegal', label: 'Illegal activity or content' },
    { value: 'impersonation', label: 'Impersonation or fraud' },
    { value: 'phishing', label: 'Malware or phishing attempt' },
    { value: 'copyright', label: 'Copyright or trademark violation' },
    { value: 'other', label: 'Other' },
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

    const reasonsHtml = REPORT_REASONS.map(reason => `
        <label class="report-reason">
            <input type="radio" name="report_reason" value="${reason.value}">
            <span>${escapeHtml(reason.label)}</span>
        </label>
    `).join('');

    overlay.innerHTML = `
        <div class="report-modal">
            <div class="report-modal-header">
                <h3>Report this poll</h3>
            </div>
            <div class="report-modal-body">
                <p>If you believe this poll violates our guidelines, please let us know.</p>
                <div class="report-reasons">
                    ${reasonsHtml}
                </div>
                <div class="report-note-group">
                    <label>Additional details <span class="optional">(optional)</span></label>
                    <textarea id="reportNote" placeholder="Please provide any additional context that might help us review this report..."></textarea>
                </div>
            </div>
            <div class="report-modal-actions">
                <button type="button" class="btn btn-secondary btn-cancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-submit">Submit Report</button>
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
            showToast('Please select a reason for reporting', 'error');
            return;
        }

        const reason = selectedReason.value;
        const note = noteInput.value.trim();

        // Require note for "other" reason
        if (reason === 'other' && !note) {
            showToast('Please provide details for your report', 'error');
            noteInput.focus();
            return;
        }

        try {
            setButtonLoading(submitBtn);
            await api.post(`/api/polls/${publicId}/report`, { reason, note });
            close();
            showToast('Thank you for your report. We will review it shortly.', 'success');
        } catch (err) {
            clearButtonLoading(submitBtn);
            showToast(err.message || 'Failed to submit report', 'error');
        }
    });

    // Focus the first radio button
    overlay.querySelector('input[name="report_reason"]')?.focus();
}
