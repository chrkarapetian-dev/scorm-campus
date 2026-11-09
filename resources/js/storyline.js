// Observe actual package activity; saved statistics are calculated by the backend.
const SHARED_CLIP = 'video_5uQbYqRF4Ug';
const LITERATURE_REFERENCES = ['Константинов В.М., Ю.Б. Челидзе', 'Ясаманов Н.А.', 'Jennifer Elliott An Introduction', 'Голубев Г.Н. Геоэкология', 'Global Reporting Initiative', 'Armenia - Human Development Index'];

export function createStorylineTracker({ frames, store, onChange }) {
    let watchTimer;
    const lastSlides = new Map();
    function recordSlide(item, slide) {
        if (!item || lastSlides.get(item.id) === slide) return;
        lastSlides.set(item.id, slide);
        store.update(item, { slide });
    }
    function trackVideo(item, video) {
        if (video.dataset.usumProgressHook) return;
        const source = video.currentSrc || video.src || '';
        if (!source.includes('.mp4') || source.includes(SHARED_CLIP)) return;
        video.dataset.usumProgressHook = '1';
        let lastSaved = 0;
        const update = force => {
            if (!force && Date.now() - lastSaved < 4000) return;
            if (!Number.isFinite(video.duration) || video.duration <= 0) return;
            lastSaved = Date.now();
            const ranges = [];
            for (let i = 0; i < video.played.length; i++) ranges.push([video.played.start(i), video.played.end(i)]);
            if (ranges.length) store.update(item, { video: { duration: video.duration, ranges } });
        };
        video.addEventListener('timeupdate', () => update(false));
        video.addEventListener('pause', () => update(true));
        video.addEventListener('ended', () => update(true));
    }
    // Package frames are same-origin, so their DOM can be scanned for the video
    // and for the two text-only slides that send no slide message.
    function scan(item, win, visited = new Set()) {
        if (!win || visited.has(win)) return;

        visited.add(win);

        try {
            win.document.querySelectorAll('video').forEach(video => trackVideo(item, video));

            const slideText = String(win.document.body?.innerText || '');

            if (slideText.includes('Այս բաժնում տեղադրված են դասին առնչվող ուսումնական նյութերը')) recordSlide(item, '6m4h0Gy3mUh');
            else if (slideText.includes('Գրականություն') && LITERATURE_REFERENCES.some(reference => slideText.includes(reference))) recordSlide(item, '6eZpibFOcqO');
            else if (/Հարց\s*\d+/.test(slideText) && slideText.includes('Պատասխանել')) recordSlide(item, '6aymNm6ZUBv');

            win.document.querySelectorAll('iframe').forEach(frame => {
                try {
                    scan(item, frame.contentWindow, visited);
                } catch {}
            });
        } catch {}
    }

    function watch(item) {
        clearInterval(watchTimer);
        watchTimer = setInterval(() => {
            for (const frame of frames) {
                if (frame.dataset.courseId !== item.id) continue;
                try { scan(item, frame.contentWindow); } catch {}
            }
        }, 1500);
    }
    return { read: item => store.read(item), clear: async item => { clearInterval(watchTimer); lastSlides.delete(item.id); await store.clear(item); }, recordSlide, scan, watch, sync: () => {} };
}
