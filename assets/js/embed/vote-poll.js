// Stable URL for previously copied embed snippets. New snippets use a hashed
// bundle directly. Keep this loader classic so external sites need no CORS setup.
(() => {
    if (customElements.get('vote-poll') || document.querySelector('script[data-pref-vote-loader]')) return;
    const script = document.createElement('script');
    script.src = new URL('../../../embed-script.php', document.currentScript.src).href;
    script.dataset.prefVoteLoader = 'true';
    document.head.appendChild(script);
})();
