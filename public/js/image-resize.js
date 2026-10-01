// Downscales photos picked on phones before upload so camera images stay well under the server upload limit.
(function () {
    const MAX_SIDE = 1600;
    const QUALITY = 0.82;
    const MIN_BYTES = 1024 * 1024;

    if (typeof DataTransfer === 'undefined' || !window.HTMLCanvasElement) {
        return;
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
            img.src = url;
        });
    }

    async function shrink(file) {
        if (!/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type) || file.size < MIN_BYTES) {
            return file;
        }
        const img = await loadImage(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
        if (!blob || blob.size >= file.size) {
            return file;
        }
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    }

    const generations = new WeakMap();
    const pendingForms = new Map();

    function setBusy(form, busy) {
        if (!form) {
            return;
        }
        const state = pendingForms.get(form) || { count: 0, deferred: null };
        state.count += busy ? 1 : -1;
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(b => { b.disabled = state.count > 0; });
        if (state.count > 0) {
            pendingForms.set(form, state);
            return;
        }
        pendingForms.delete(form);
        if (state.deferred !== null) {
            form.requestSubmit(state.deferred || undefined);
        }
    }

    document.addEventListener('submit', (e) => {
        const state = pendingForms.get(e.target);
        if (state) {
            e.preventDefault();
            e.stopPropagation();
            state.deferred = e.submitter || false;
        }
    }, true);

    document.addEventListener('change', async (e) => {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || input.dataset.resized === '1' || !input.files || !input.files.length) {
            return;
        }
        const generation = (generations.get(input) || 0) + 1;
        generations.set(input, generation);
        const originals = Array.from(input.files);
        const form = input.form;
        setBusy(form, true);
        try {
            const files = await Promise.all(originals.map(f => shrink(f).catch(() => f)));
            if (generations.get(input) === generation && files.some((f, i) => f !== originals[i])) {
                const dt = new DataTransfer();
                files.forEach(f => dt.items.add(f));
                input.files = dt.files;
                input.dataset.resized = '1';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                delete input.dataset.resized;
            }
        } finally {
            setBusy(form, false);
        }
    }, true);
})();
