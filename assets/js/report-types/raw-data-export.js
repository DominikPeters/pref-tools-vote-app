/**
 * Raw Data Export Report Renderer
 * Provides preview, copy, and download functionality for PrefLib format exports
 * Data is fetched on-demand to avoid storing large exports in the database cache
 */

import { escapeHtml, api } from '../app.js';

// Icons
const downloadIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
const copyIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
const eyeIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const eyeOffIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
const checkIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
const spinnerIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

/**
 * Get human-readable description for PrefLib format codes
 */
function getFormatDescription(dataType) {
    const descriptions = {
        'SOC': 'Strict Orders - Complete (full rankings, no ties)',
        'SOI': 'Strict Orders - Incomplete (partial rankings, no ties)',
        'TOI': 'Tie Orders - Incomplete (partial rankings with ties)',
        'CAT': 'Categorical Preferences (approval/ratings)',
        'PB': 'Pabulib Format (Participatory Budgeting)',
    };
    return descriptions[dataType] || dataType;
}

export function renderRawDataExport(container, data, config, context = {}) {
    if (!data.supported) {
        container.innerHTML = `<p class="report-error">${escapeHtml(data.error || 'Export not supported for this question type.')}</p>`;
        return;
    }

    const { data_type, file_name, total_responses } = data;
    const { publicId, adminToken, reportId } = context;

    // Generate a unique ID for this instance
    const instanceId = 'raw-export-' + Math.random().toString(36).substr(2, 9);

    const html = `
        <div class="report-raw-export">
            <div class="export-info">
                <span class="export-badge" data-tooltip="Preflib Format: ${getFormatDescription(data_type)}">${escapeHtml(data_type)} format</span>
                <span class="export-stats">${total_responses} response${total_responses !== 1 ? 's' : ''}</span>
            </div>

            <div class="export-actions">
                <button type="button" class="btn btn-primary btn-small" id="${instanceId}-download-btn">
                    ${downloadIcon}
                    Download
                </button>
                <button type="button" class="btn btn-secondary btn-small" id="${instanceId}-copy-btn">
                    ${copyIcon}
                    Copy
                </button>
                <button type="button" class="btn btn-secondary btn-small" id="${instanceId}-preview-btn">
                    ${eyeIcon}
                    Preview
                </button>
            </div>

            <div class="export-preview" id="${instanceId}-preview" style="display: none;">
                <pre class="export-data"></pre>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Elements
    const previewBtn = container.querySelector(`#${instanceId}-preview-btn`);
    const copyBtn = container.querySelector(`#${instanceId}-copy-btn`);
    const downloadBtn = container.querySelector(`#${instanceId}-download-btn`);
    const previewDiv = container.querySelector(`#${instanceId}-preview`);
    const previewPre = previewDiv.querySelector('.export-data');

    // Cached export data (fetched on first action)
    let cachedExportData = null;
    let fetchPromise = null;

    // Fetch export data on demand (only once)
    async function getExportData() {
        if (cachedExportData !== null) {
            return cachedExportData;
        }

        // If already fetching, wait for that request
        if (fetchPromise) {
            return fetchPromise;
        }

        // Start fetch - use admin_token query param if available for private reports
        fetchPromise = (async () => {
            let url = `/api/polls/${publicId}/reports/${reportId}/export`;
            if (adminToken) {
                url += `?admin_token=${encodeURIComponent(adminToken)}`;
            }
            const result = await api.get(url);
            cachedExportData = result.data;
            return cachedExportData;
        })();

        return fetchPromise;
    }

    // Set button loading state
    function setLoading(btn, loading, originalHtml) {
        if (loading) {
            btn.disabled = true;
            btn.innerHTML = spinnerIcon + ' Loading...';
        } else {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Preview toggle
    let previewVisible = false;
    previewBtn.addEventListener('click', async () => {
        if (!previewVisible) {
            // Show preview - need to fetch data first
            const originalHtml = previewBtn.innerHTML;
            setLoading(previewBtn, true);
            try {
                const exportData = await getExportData();
                previewPre.textContent = exportData;
                previewDiv.style.display = 'block';
                previewVisible = true;
                previewBtn.innerHTML = eyeOffIcon + ' Hide';
            } catch (err) {
                console.error('Failed to fetch export:', err);
                previewBtn.innerHTML = originalHtml;
            }
            previewBtn.disabled = false;
        } else {
            // Hide preview
            previewDiv.style.display = 'none';
            previewVisible = false;
            previewBtn.innerHTML = eyeIcon + ' Preview';
        }
    });

    // Copy to clipboard
    copyBtn.addEventListener('click', async () => {
        const originalHtml = copyBtn.innerHTML;
        setLoading(copyBtn, true);
        try {
            const exportData = await getExportData();
            await navigator.clipboard.writeText(exportData);
            copyBtn.innerHTML = checkIcon + ' Copied!';
            copyBtn.classList.add('btn-success');
            copyBtn.disabled = false;
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.classList.remove('btn-success');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
            copyBtn.textContent = 'Failed';
            copyBtn.disabled = false;
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
            }, 2000);
        }
    });

    // Download file
    downloadBtn.addEventListener('click', async () => {
        const originalHtml = downloadBtn.innerHTML;
        setLoading(downloadBtn, true);
        try {
            const exportData = await getExportData();
            const blob = new Blob([exportData], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = file_name;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Failed to download:', err);
        }
        downloadBtn.disabled = false;
        downloadBtn.innerHTML = originalHtml;
    });
}
