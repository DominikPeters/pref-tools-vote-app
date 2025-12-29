/**
 * Dashboard JavaScript
 */

import { api, showToast, setButtonLoading, clearButtonLoading, basePath } from './app.js';

document.addEventListener('DOMContentLoaded', () => {
    // Handle duplicate poll buttons
    document.querySelectorAll('.duplicate-poll-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const publicId = btn.dataset.publicId;
            const adminToken = btn.dataset.adminToken;

            try {
                setButtonLoading(btn);
                const result = await api.post(`/api/polls/${publicId}/admin/${adminToken}/duplicate`);
                showToast('Poll duplicated successfully!', 'success');
                // Redirect to the new poll's admin page
                window.location.href = result.admin_url;
            } catch (err) {
                clearButtonLoading(btn);
                showToast('Failed to duplicate poll: ' + err.message, 'error');
            }
        });
    });
});
