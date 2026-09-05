<script nonce="{{ Vite::cspNonce() }}">
    (() => {
        let saved = null;
        try { saved = localStorage.getItem('finacourt-theme'); } catch (_) {}
        const theme = saved === 'light' || saved === 'dark'
            ? saved
            : 'light';
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#071f17' : '#146d4a');
    })();
</script>
