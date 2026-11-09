import { ProgressStore } from './progress.js';
import { createStorylineTracker } from './storyline.js';

async function start() {
    const item = window.usumCourse;
    const status = document.getElementById('progress-save-status');
    const store = new ProgressStore(() => { status.textContent = store.error?.message || ''; });
    await store.load([item]);
    store.install(item);
    const frame = document.getElementById('course-frame');
    const tracker = createStorylineTracker({ frames: [frame], store });
    frame.dataset.courseId = item.id;
    window.addEventListener('message', event => {
        if (event.origin === location.origin && event.source === frame.contentWindow && event.data?.type === 'usum-storyline-slide' && typeof event.data.slideId === 'string') tracker.recordSlide(item, event.data.slideId);
    });
    frame.src = item.url;
    tracker.watch(item);
}
start().catch(error => { document.getElementById('progress-save-status').textContent = error.message; });
