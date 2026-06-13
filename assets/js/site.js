(() => {
    const boot = document.querySelector('[data-boot-screen]');
    if (boot) {
        window.setTimeout(() => boot.classList.add('is-done'), 900);
        boot.addEventListener('click', () => boot.classList.add('is-done'));
    }

    const streams = document.querySelectorAll('.stream-lines');
    const fakeLines = [
        '[tick] packets look decorative today',
        '[ok] project cache warm',
        '[info] terminal theme stable',
        '[warn] caffeine tank below optimal threshold',
        '[ok] no mainframes harmed'
    ];

    streams.forEach((stream) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        window.setInterval(() => {
            const line = document.createElement('div');
            const prompt = document.createElement('span');
            prompt.className = 'prompt';
            prompt.textContent = '>';
            line.appendChild(prompt);
            line.append(' ' + fakeLines[Math.floor(Math.random() * fakeLines.length)]);
            stream.appendChild(line);
            while (stream.children.length > 9) stream.removeChild(stream.firstElementChild);
        }, 6500 + Math.floor(Math.random() * 2400));
    });
})();
