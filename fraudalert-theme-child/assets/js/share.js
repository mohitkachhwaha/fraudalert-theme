document.addEventListener('DOMContentLoaded', () => {
    const copyBtn = document.querySelector('.fa-share-copy');
    if (!copyBtn) return;

    copyBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const url = copyBtn.getAttribute('data-url');
        const originalText = copyBtn.getAttribute('data-label');
        const labelSpan = copyBtn.querySelector('.btn-label');

        try {
            await navigator.clipboard.writeText(url);
            if (labelSpan) {
                labelSpan.textContent = 'Copied!';
                copyBtn.classList.add('copied');
                setTimeout(() => {
                    labelSpan.textContent = originalText;
                    copyBtn.classList.remove('copied');
                }, 2000);
            }
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    });
});
