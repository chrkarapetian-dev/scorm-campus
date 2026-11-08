import { createStorylineTracker } from './storyline.js';

const { courses, sections } = window.usum;

const STORE = 'usum-topic8-progress-v1';
const FLASH_KEY = 'usum-flash';
const VIEW_LABELS = { home: 'Դասընթացներ', library: 'Գլխավոր', progress: 'Իմ առաջընթացը' };
const CRITERION_TITLES = {
    understanding: 'Թեմայի ըմբռնում',
    connections: 'Կայունության չափումների կապ',
    example_measure: 'Օրինակ և չափում',
    completeness: 'Ամբողջականություն և հստակություն',
};

const $ = id => document.getElementById(id);
const fromTemplate = id => $(id).content.cloneNode(true);
const elementFromTemplate = (id, text = '') => {
    const element = fromTemplate(id).firstElementChild;
    element.textContent = text;

    return element;
};
const minutes = seconds => `${Math.floor((seconds || 0) / 60)}ր`;
const postJson = async (url, body) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
            accept: 'application/json',
            'x-csrf-token': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });

    // The session ended: reloading leads to the login page.
    if (response.status === 401 || response.status === 419) {
        location.reload();
        throw new Error('Շարունակելու համար մուտք գործեք ձեր հաշիվ։');
    }

    const data = await response.json();

    if (!response.ok) throw Object.assign(new Error(data.error), { data });

    return data;
};

const blankState = () => ({ completed: [], active: 'video', seconds: {}, quiz: null, evaluation: null, history: [] });
let state;

try {
    state = { ...blankState(), ...JSON.parse(localStorage.getItem(STORE) || '{}') };
} catch {
    state = blankState();
}

const saveState = () => localStorage.setItem(STORE, JSON.stringify(state));

let activeCourse = courses[0] || null;
let loadedFeaturedId = null;
let selectedFile = null;
let toastTimer;

const tracker = createStorylineTracker({
    sections,
    frames: [$('featured-scorm-frame'), $('scorm-frame')],
    onChange: () => {
        if (!$('view-progress').hidden) renderAnalytics();
    },
});

function toast(message) {
    const element = $('toast');
    element.textContent = message;
    element.dataset.show = '';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => delete element.dataset.show, 2800);
}

// ---- SCORM -----------------------------------------------------------------

function setScormApi(item) {
    const prefix = `usum-scorm-${item.id}-`;
    const get = key => localStorage.getItem(prefix + key) || '';
    const set = (key, value) => {
        localStorage.setItem(prefix + key, String(value));
        tracker.sync(item);

        return 'true';
    };

    window.API = { LMSInitialize: () => 'true', LMSFinish: () => 'true', LMSCommit: () => 'true', LMSGetValue: get, LMSSetValue: set, LMSGetLastError: () => '0', LMSGetErrorString: () => 'No error', LMSGetDiagnostic: () => '' };
    window.API_1484_11 = { Initialize: () => 'true', Terminate: () => 'true', Commit: () => 'true', GetValue: get, SetValue: set, GetLastError: () => '0', GetErrorString: () => 'No error', GetDiagnostic: () => '' };
}

// Storyline asks whether to resume a saved session; answer yes on the learner's behalf.
function autoResume(frame) {
    let attempts = 0;

    const resume = () => {
        try {
            const button = frame.contentDocument?.querySelector('[data-dv_ref="resume"]');

            if (button) {
                button.click();

                return;
            }
        } catch {}

        if (++attempts < 30) setTimeout(resume, 350);
    };

    resume();
}

window.addEventListener('message', event => {
    const data = event.data;

    if (event.origin !== location.origin || data?.type !== 'usum-storyline-slide' || typeof data.slideId !== 'string') return;

    const item = activeCourse || courses[0];

    if (!item) return;

    const frames = [$('featured-scorm-frame'), $('scorm-frame')];

    if (frames.some(frame => frame.contentWindow === event.source)) tracker.recordSlide(item, data.slideId);
});

function renderFeaturedCourse(item) {
    if (!item) return;

    setScormApi(item);
    selectAssistantCourse(item);
    $('open-question').hidden = !item.openQuestion;
    $('featured-course-title').textContent = item.title;
    $('featured-course').hidden = false;

    if (loadedFeaturedId !== item.id) {
        const frame = $('featured-scorm-frame');
        frame.addEventListener('load', () => autoResume(frame), { once: true });
        frame.src = item.url;
        loadedFeaturedId = item.id;
    }

    tracker.watch(item);
    tracker.sync(item);
    $('featured-fullscreen').onclick = () => openPlayer(item);
}

function openPlayer(item) {
    const frame = $('scorm-frame'), player = $('player');

    setScormApi(item);
    $('player-title').textContent = ` ${item.title}`;
    frame.src = item.url;
    tracker.watch(item);
    frame.onload = () => {
        autoResume(frame);

        const poll = () => {
            try {
                tracker.scan(item, frame.contentWindow);
            } catch {}
        };

        poll();
        frame.contentWindow?.addEventListener?.('load', poll, true);
    };
    player.dataset.open = '';
    player.setAttribute('aria-hidden', 'false');
}

function closePlayer() {
    const player = $('player');

    $('scorm-frame').src = 'about:blank';
    delete player.dataset.open;
    player.setAttribute('aria-hidden', 'true');
    $('player-title').textContent = '';
}

// ---- Views -----------------------------------------------------------------

function showView(name) {
    for (const view of ['home', 'library', 'progress']) $(`view-${view}`).hidden = view !== name;

    document.querySelectorAll('[data-view]').forEach(link => {
        if (link.dataset.view === name) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });

    $('crumb-current').textContent = VIEW_LABELS[name];

    if (name === 'progress') renderAnalytics();
    if (name === 'home') renderFeaturedCourse(activeCourse);

    window.scrollTo(0, 0);
}

function sectionRow(section, progress) {
    const s = progress.sections[section.id] || {};
    const isQuiz = section.id === 'quiz', isVideo = section.id === 'video';
    const complete = Boolean(s.completed), visited = Boolean(s.visited || complete), active = progress.activeSection === section.id;
    const score = isQuiz && progress.quiz ? progress.quiz.score : null;

    const percent = isVideo ? Number(s.percent) || 0 : isQuiz ? (score ?? 0) : (complete ? 100 : 0);

    let label = 'Չի սկսվել';
    if (complete) label = 'Ավարտված';
    else if (isQuiz && progress.quiz) label = 'Չի անցել';
    else if (active) label = 'Ընթացքի մեջ';
    else if (visited) label = 'Բացվել է';
    else if (isVideo && s.seconds) label = 'Ընթացքի մեջ';

    let detail = 'Բաժինը կհայտնվի այստեղ, երբ բացեք այն Storyline դասընթացում';
    if (isQuiz && progress.quiz) detail = `Փորձի արդյունք՝ ${progress.quiz.score}% · անցողիկ՝ 70%`;
    else if (isVideo && s.seconds) {
        const time = seconds => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
        detail = `Դիտվել է ${time(s.seconds)}${s.duration ? ` / ${time(s.duration)}` : ''}`;
    }
    else if (complete) detail = isVideo ? 'Դիտվել է տեսանյութի առնվազն 90%-ը' : 'Բաժինը բացվել է, ապա՝ փակվել';
    else if (active) detail = 'Դուք բացել եք այս բաժինը Articulate Storyline-ում';
    else if (visited) detail = 'Այս բաժինը բացվել է Articulate Storyline-ում';

    const failed = !complete && Boolean(progress.quiz && !progress.quiz.passed && isQuiz);

    return { percent, label, detail, state: complete ? 'complete' : failed ? 'failed' : '' };
}

function renderAnalytics() {
    const item = activeCourse, progress = tracker.read(item);
    const done = sections.filter(section => progress.sections[section.id]?.completed).length;
    const score = progress.quiz?.score;
    const status = String(progress.scorm['cmi.core.lesson_status'] || '').toLowerCase();

    $('progress-course-access').hidden = !item;

    if (item) {
        $('progress-course-title').textContent = item.title;
        $('progress-open-course').onclick = () => showView('home');
    }

    let courseStatus = status ? 'Ընթացքի մեջ' : 'Չի սկսվել';
    if (status === 'passed') courseStatus = 'Հանձնված է';
    else if (status === 'failed') courseStatus = 'Չի հանձնվել';
    else if (status === 'completed') courseStatus = 'Ավարտված՝ ըստ Storyline';

    const totalTime = progress.scorm['cmi.core.total_time'];

    $('stat-sections').textContent = `${done}/${sections.length}`;
    $('stat-sections-note').textContent = item?.title || 'SCORM դասընթաց';
    $('stat-quiz').textContent = score === undefined || score === null ? '—' : `${score}%`;
    $('stat-quiz-note').textContent = progress.quiz
        ? (progress.quiz.passed ? '70% անցողիկ միավորը հաղթահարված է' : '70% անցողիկ միավորը դեռ չի հաղթահարվել')
        : 'Արդյունքը կերևա Storyline-ի ինքնաստուգումից հետո';
    $('stat-status').textContent = courseStatus;
    $('stat-status-note').textContent = totalTime ? `Ուսումնառության ժամանակ՝ ${totalTime}` : 'Առաջընթացը պահվում է այս դիտարկչում';

    for (const section of sections) {
        const row = document.querySelector(`[data-progress-row="${section.id}"]`);
        const { percent, label, detail, state: rowState } = sectionRow(section, progress);

        row.querySelector('[data-detail]').textContent = detail;
        row.querySelector('[data-bar]').style.width = `${percent}%`;

        const statusEl = row.querySelector('[data-status]');
        statusEl.textContent = label;
        statusEl.dataset.state = rowState;
    }
}

// ---- Assistant ---------------------------------------------------------------

let assistantCourseId = activeCourse?.id ?? null;
let questionsRemaining = Number($('questions-remaining').textContent);
let assistantBusy = false;
const assistantChats = new Map();

function syncAssistant() {
    const available = Boolean(activeCourse?.assistant);

    $('questions-remaining').textContent = questionsRemaining;
    $('assistant-panel').toggleAttribute('data-unavailable', !available);
    $('assistant-unavailable').hidden = available;
    $('questions-exhausted').hidden = !available || questionsRemaining > 0;
    $('chat-form').hidden = !available || questionsRemaining === 0;
}

// Each course keeps its own conversation, so an answer that arrives after switching course isn't lost.
function selectAssistantCourse(item) {
    if (assistantCourseId !== item.id) {
        const messages = $('chat-messages');

        assistantChats.set(assistantCourseId, [...messages.children]);
        messages.replaceChildren(...(assistantChats.get(item.id) ?? [elementFromTemplate('tpl-assistant-message', 'Բարև։ Հարցրեք այս դասընթացի նյութի մասին։')]));
        assistantCourseId = item.id;
    }

    $('assistant-source').textContent = `Հիմք՝ ${item.title} նյութեր`;
    syncAssistant();
}

function setChatInput(value) {
    $('chat-input').value = value;
    $('chat-count').textContent = value.length;
}

async function askAssistant(question) {
    const messages = $('chat-messages');
    const pending = elementFromTemplate('tpl-assistant-message', 'Մտածում եմ…');
    const courseId = assistantCourseId;

    assistantBusy = true;
    $('chat-submit').disabled = true;
    messages.append(elementFromTemplate('tpl-user-message', question), pending);
    messages.scrollTop = messages.scrollHeight;

    try {
        const data = await postJson('/api/scorm/qa-assistant', { student_question: question, course_id: courseId });

        pending.textContent = data.answer;
        questionsRemaining = data.questions_remaining;
    } catch (error) {
        pending.textContent = error.message && error.message !== 'Failed to fetch' ? error.message : 'Հարցին պատասխանել չհաջողվեց։';
        questionsRemaining = error.data?.questions_remaining ?? questionsRemaining;

        // The question wasn't answered, so it's still there to retry.
        if (assistantCourseId === courseId && !$('chat-input').value) setChatInput(question);
    }

    assistantBusy = false;
    $('chat-submit').disabled = false;
    syncAssistant();
    messages.scrollTop = messages.scrollHeight;
}

$('chat-form').addEventListener('submit', event => {
    event.preventDefault();

    const question = $('chat-input').value.trim();

    if (!question || assistantBusy) return;

    setChatInput('');
    askAssistant(question);
});

$('chat-input').addEventListener('keydown', event => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

    event.preventDefault();
    $('chat-form').requestSubmit();
});

$('chat-input').addEventListener('input', event => {
    $('chat-count').textContent = event.target.value.length;
});

syncAssistant();

// ---- Open question -------------------------------------------------------------

const evaluationBand = score => (score >= 90 ? 'Օրինակելի' : score >= 75 ? 'Իմացող / Լիարժեք' : score >= 60 ? 'Զարգացող' : 'Անբավարար');

function showEvaluationMessage(message) {
    $('evaluation-result').replaceChildren(elementFromTemplate('tpl-evaluation-box', message));
}

function renderEvaluation(data) {
    const score = Math.max(0, Math.min(100, Number(data.total_score_awarded) || 0));
    const box = elementFromTemplate('tpl-evaluation-box');
    const details = fromTemplate('tpl-evaluation-details');
    const slot = name => details.querySelector(`[data-slot="${name}"]`);

    slot('score').textContent = `${score}/100 միավոր`;
    slot('level').textContent = evaluationBand(score);
    slot('summary').textContent = data.summary_feedback_armenian || 'Գնահատումն ավարտված է։';
    slot('tips').textContent = data.actionable_tips_armenian || '';

    for (const row of data.criterion_breakdown || []) {
        const item = fromTemplate('tpl-evaluation-criterion');
        const line = item.firstElementChild;
        line.firstElementChild.textContent = row.title_armenian || CRITERION_TITLES[row.criterion_id] || row.criterion_id;
        line.append(`՝ ${row.points_earned}/${row.max_points}․ ${row.reasoning_armenian}`);
        slot('criteria').append(item);
    }

    if (data.notice) box.append(elementFromTemplate('tpl-evaluation-notice', data.notice));

    box.append(details);
    $('evaluation-result').replaceChildren(box);
}

$('evaluate-answer').addEventListener('click', async () => {
    const answer = $('open-answer').value.trim(), button = $('evaluate-answer');

    if (answer.length < 40) {
        showEvaluationMessage('Խնդրում ենք գրել առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։');

        return;
    }

    button.disabled = true;
    button.textContent = 'Պատասխանը գնահատվում է…';
    showEvaluationMessage('Պատասխանը ստացվել է։ Պատրաստում ենք հետադարձ կապը…');
    $('evaluation-result').firstElementChild.setAttribute('role', 'status');

    try {
        const data = await postJson('/api/scorm/evaluate-open-ended', { student_response: answer });

        state.evaluation = { score: data.total_score_awarded, max: data.max_total_score, at: Date.now() };

        if (!state.completed.includes('reflection')) state.completed.push('reflection');

        saveState();
        renderEvaluation(data);
    } catch (error) {
        showEvaluationMessage(error.message && error.message !== 'Failed to fetch' ? error.message : 'Չհաջողվեց գնահատել պատասխանը։');
    } finally {
        button.disabled = false;
        button.textContent = 'Պատասխանել';
    }
});

// ---- Upload --------------------------------------------------------------------

function selectFile(file) {
    selectedFile = file || null;

    if (!selectedFile) return;

    $('upload-title').value = $('upload-title').value || selectedFile.name.replace(/\.zip$/i, '').replace(/[-_]/g, ' ');
    $('upload-status').textContent = `${selectedFile.name} · ${(selectedFile.size / 1048576).toFixed(1)} ՄԲ`;
}

async function submitUpload() {
    const status = $('upload-status'), button = $('upload-submit');

    if (!selectedFile) {
        status.textContent = 'Նախ ընտրեք SCORM ZIP փաթեթը։';

        return;
    }

    button.disabled = true;
    button.textContent = 'Վերբեռնվում է…';
    status.textContent = 'Ստուգվում է imsmanifest.xml ֆայլը։';

    try {
        const body = new FormData();
        body.append('package', selectedFile);
        body.append('title', $('upload-title').value.trim());

        const headers = { accept: 'application/json' };
        const token = $('upload-token').value.trim();

        if (token) headers['x-upload-token'] = token;

        const response = await fetch('/api/courses', { method: 'POST', body, headers });
        const data = await response.json();

        if (!response.ok) throw new Error(data.error || 'Փաթեթը չհաջողվեց վերբեռնել։');

        // The catalog is server-rendered, so reload to show the new course.
        sessionStorage.setItem(FLASH_KEY, 'SCORM դասընթացն ավելացվել է։');
        location.assign('/');
    } catch (error) {
        status.textContent = error.message === 'Failed to fetch' ? 'Սերվերը հասանելի չէ։ Փորձեք կրկին։' : error.message;
        button.disabled = false;
        button.textContent = 'Վերբեռնել';
    }
}

const closeModals = () => delete $('upload-modal').dataset.open;

$('upload-submit').addEventListener('click', submitUpload);
$('upload-file').addEventListener('change', event => selectFile(event.target.files?.[0]));
document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeModals));

const drop = $('upload-drop');
drop.addEventListener('dragover', event => {
    event.preventDefault();
    drop.dataset.dragover = '';
});
drop.addEventListener('dragleave', () => delete drop.dataset.dragover);
drop.addEventListener('drop', event => {
    event.preventDefault();
    delete drop.dataset.dragover;
    selectFile(event.dataTransfer.files[0]);
});

// ---- Page wiring -----------------------------------------------------------------

document.querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', () => showView(link.dataset.view)));

document.querySelectorAll('[data-open-course]').forEach(button => button.addEventListener('click', () => {
    activeCourse = courses.find(course => course.id === button.dataset.openCourse) || courses[0];
    showView('home');
}));

$('close-player').addEventListener('click', closePlayer);

$('reset-progress').addEventListener('click', () => {
    if (!confirm('Վերակայե՞լ այս դասընթացի պահպանված առաջընթացը։')) return;

    if (!activeCourse) return;

    closePlayer();
    $('featured-scorm-frame').src = 'about:blank';
    loadedFeaturedId = null;
    tracker.clear(activeCourse);

    renderAnalytics();
    toast('Առաջընթացը վերակայվել է։');
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    if ('open' in $('player').dataset) closePlayer();

    closeModals();
});

const params = new URLSearchParams(location.search);

// ?recover=1 clears saved Storyline positions when a package fails to resume.
if (params.get('recover') === '1') {
    for (const course of courses) {
        for (const key of ['cmi.suspend_data', 'cmi.core.lesson_location']) localStorage.removeItem(`usum-scorm-${course.id}-${key}`);
    }

    const url = new URL(location.href);
    url.searchParams.delete('recover');
    history.replaceState(null, '', url);
}

// ?course=1 is the shareable link straight to the embedded course; ?upload=1 opens the upload form.
if (params.get('course') === '1' && courses.length) showView('home');
if (params.get('upload') === '1') $('upload-modal').dataset.open = '';

const flash = sessionStorage.getItem(FLASH_KEY);

if (flash) {
    sessionStorage.removeItem(FLASH_KEY);
    toast(flash);
}
