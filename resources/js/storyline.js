// Learner progress for the embedded Articulate Storyline course. There are no
// accounts, so everything lives in this browser's localStorage. The SCORM API
// writes `cmi.*` values under a per-course prefix; this module reads them, plus
// slide messages and the video element, into one summary per course.

const STORE = 'usum-storyline-progress-v1';
const PASSING_SCORE = 70;
// Every package holds this short clip next to its lesson video, which is the one tracked.
const SHARED_CLIP = 'video_5uQbYqRF4Ug';
// The literature slide sends no message, so it is recognised by a reference only it lists.
const LITERATURE_REFERENCES = ['Константинов В.М., Ю.Б. Челидзе', 'Ясаманов Н.А.', 'Jennifer Elliott An Introduction', 'Голубев Г.Н. Геоэкология', 'Global Reporting Initiative', 'Armenia - Human Development Index'];
const RESULTS_SLIDE = '6SfrhhbiYCV';
const READING_SECTIONS = ['materials', 'literature'];

const scormPrefix = item => `usum-scorm-${item.id}-`;

function readAll() {
    try {
        return JSON.parse(localStorage.getItem(STORE) || '{}');
    } catch {
        return {};
    }
}

function scorePercent(score, min, max) {
    const value = Number(score), low = Number(min || 0), high = Number(max || 100);

    return Number.isFinite(value) && high > low ? Math.max(0, Math.min(100, Math.round((value - low) / (high - low) * 100))) : null;
}

function interactionScore(item) {
    const prefix = scormPrefix(item), answers = [];

    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);

        if (!key?.startsWith(prefix) || !/^cmi\.interactions\.\d+\.result$/.test(key.slice(prefix.length))) continue;

        const result = String(localStorage.getItem(key) || '').toLowerCase();

        if (result === 'correct') answers.push(1);
        else if (result === 'incorrect') answers.push(0);
    }

    return answers.length ? Math.round(answers.reduce((a, b) => a + b, 0) / answers.length * 100) : null;
}

export function createStorylineTracker({ sections, frames, onChange }) {
    let watchTimer = null;

    const sectionForLocation = location => sections.find(x => location?.includes(x.slide)) || null;
    const sectionForSlide = slide => sections.find(x => slide === x.slide || slide === x.resultsSlide) || null;

    function read(item) {
        const stored = readAll()[item?.id] || {};

        return { sections: {}, scorm: {}, quiz: null, updatedAt: null, ...stored, sections: { ...(stored.sections || {}) }, scorm: { ...(stored.scorm || {}) } };
    }

    function save(item, progress, notify = true) {
        if (!item) return;

        const all = readAll();
        progress.updatedAt = Date.now();
        all[item.id] = progress;
        localStorage.setItem(STORE, JSON.stringify(all));

        if (notify) onChange();
    }

    function clear(item) {
        const all = readAll();
        delete all[item.id];
        localStorage.setItem(STORE, JSON.stringify(all));

        const prefix = scormPrefix(item);

        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);

            if (key?.startsWith(prefix)) localStorage.removeItem(key);
        }
    }

    function recordSlide(item, slide) {
        if (!slide) return;

        const progress = read(item), next = sectionForSlide(String(slide)), prior = sections.find(x => x.id === progress.activeSection);

        if (progress.lastSlide === String(slide) && progress.activeSection === next?.id && (!next || progress.sections[next.id]?.visited)) return;

        if (prior && READING_SECTIONS.includes(prior.id) && prior.id !== next?.id) {
            progress.sections[prior.id] = { ...(progress.sections[prior.id] || {}), visited: true, completed: true, passedAt: progress.sections[prior.id]?.passedAt || Date.now() };
        }

        progress.lastSlide = String(slide);
        progress.activeSection = next?.id || null;

        if (next) {
            const previous = progress.sections[next.id] || {};
            progress.sections[next.id] = { ...previous, visited: true, openedAt: previous.openedAt || Date.now() };
        }

        if (next?.id === 'quiz' && String(slide) === next.resultsSlide) {
            const raw = progress.scorm['cmi.core.score.raw'], min = progress.scorm['cmi.core.score.min'], max = progress.scorm['cmi.core.score.max'];
            const score = scorePercent(raw, min, max) ?? interactionScore(item);
            const status = String(progress.scorm['cmi.core.lesson_status'] || '').toLowerCase();
            const passed = status === 'passed' || (score !== null && score >= PASSING_SCORE);

            if (score !== null) progress.quiz = { score, passed, at: Date.now(), source: raw ? 'storyline' : 'interactions' };

            progress.sections.quiz = { completed: passed, passedAt: passed ? Date.now() : null, score };
        }

        save(item, progress);
    }

    function sync(item, notify = true) {
        if (!item) return;

        const progress = read(item), prefix = scormPrefix(item), get = key => localStorage.getItem(prefix + key) || '';

        for (const key of ['cmi.core.lesson_status', 'cmi.core.score.raw', 'cmi.core.score.min', 'cmi.core.score.max', 'cmi.core.lesson_location', 'cmi.core.total_time']) {
            const value = get(key);

            if (value) progress.scorm[key] = value;
        }

        const location = progress.scorm['cmi.core.lesson_location'] || '', section = sectionForLocation(location), oldSection = sectionForLocation(progress.lastLocation);

        if (oldSection && READING_SECTIONS.includes(oldSection.id) && section?.id !== oldSection.id) {
            const prior = progress.sections[oldSection.id] || {};
            progress.sections[oldSection.id] = { ...prior, completed: true, passedAt: prior.passedAt || Date.now() };
        }

        if (section && !progress.lastSlide) {
            progress.activeSection = section.id;
            progress.sections[section.id] = { ...(progress.sections[section.id] || {}), visited: true, openedAt: progress.sections[section.id]?.openedAt || Date.now() };
        }

        progress.lastLocation = location;

        const raw = progress.scorm['cmi.core.score.raw'], min = progress.scorm['cmi.core.score.min'], max = progress.scorm['cmi.core.score.max'];
        const pct = scorePercent(raw, min, max) ?? interactionScore(item);
        const status = String(progress.scorm['cmi.core.lesson_status'] || '').toLowerCase();
        const finished = ['passed', 'failed', 'completed'].includes(status);

        if (pct !== null && (!progress.quiz || finished)) {
            const passed = status === 'passed' || pct >= PASSING_SCORE;
            progress.quiz = { score: pct, passed, at: Date.now(), source: raw ? 'storyline' : 'interactions' };

            if (location.includes(RESULTS_SLIDE) || progress.lastSlide === RESULTS_SLIDE || finished) {
                progress.sections.quiz = { completed: passed, passedAt: passed ? Date.now() : null, score: pct };
            }
        }

        if (status === 'passed' && progress.quiz && !progress.quiz.passed) {
            progress.quiz.passed = true;
            progress.sections.quiz = { ...(progress.sections.quiz || {}), completed: true, passedAt: Date.now(), score: progress.quiz.score };
        }

        save(item, progress, notify);
    }

    function trackVideo(item, video) {
        if (!video || video.dataset.usumProgressHook) return;

        const source = video.currentSrc || video.src || '';

        if (!source.includes('.mp4') || source.includes(SHARED_CLIP)) return;

        video.dataset.usumProgressHook = '1';
        let lastSaved = 0;

        const update = force => {
            if (!force && Date.now() - lastSaved < 4000) return;

            lastSaved = Date.now();

            const progress = read(item), duration = Number.isFinite(video.duration) ? video.duration : 0;
            let watched = 0;

            try {
                for (let i = 0; i < video.played.length; i++) watched += video.played.end(i) - video.played.start(i);
            } catch {}

            const prior = progress.sections.video || {}, sameVideo = prior.source === 'storyline-main-video';
            const seconds = Math.max(sameVideo ? (Number(prior.seconds) || 0) : 0, Math.floor(watched));
            const completed = (sameVideo && Boolean(prior.completed)) || (duration > 0 && watched >= duration * .9);

            progress.sections.video = {
                ...prior,
                source: 'storyline-main-video',
                seconds,
                duration: Math.round(duration),
                percent: duration ? Math.min(100, Math.round(watched / duration * 100)) : 0,
                completed,
                passedAt: completed ? (sameVideo && prior.passedAt || Date.now()) : null,
            };

            save(item, progress);
        };

        video.addEventListener('timeupdate', () => update(false));
        video.addEventListener('ended', () => update(true));
        video.addEventListener('loadedmetadata', () => update(true));
        update(true);
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
                try {
                    if (frame.contentWindow) scan(item, frame.contentWindow);
                } catch {}
            }

            sync(item, false);
        }, 1500);
    }

    return { read, clear, recordSlide, sync, scan, watch };
}
