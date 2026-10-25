// A fragment is visible to this page, but never sent in HTTP requests or referrers.
const form = document.getElementById('pollAccessForm');

function redeemAccess() {
    const token = new URLSearchParams(window.location.hash.slice(1)).get('token');
    if (!form || !token) return;

    window.history.replaceState(null, '', window.location.pathname + window.location.search);
    form.elements.namedItem('access_token').value = token;
    form.requestSubmit();
}

redeemAccess();
window.addEventListener('hashchange', redeemAccess);
