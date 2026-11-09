import { createStorylineTracker } from './storyline.js';
import { ProgressStore } from './progress.js';

async function main() {

const { courses, sections, assignmentMessages: assignmentText } = window.usum;
const progressStore = new ProgressStore(() => {
    if (!document.getElementById('view-progress').hidden) renderAnalytics();
    if (progressStore.error) toast(progressStore.error.message);
});
await progressStore.load(courses);

const STORE = 'usum-topic8-progress-v1';
const FLASH_KEY = 'usum-flash';
const VIEW_LABELS = { home: 'Գլխավոր', library: 'Գլխավոր', progress: 'Իմ առաջընթացը', certificates: 'Վկայականներ' };


const $ = id => document.getElementById(id);
const fromTemplate = id => $(id).content.cloneNode(true);
const elementFromTemplate = (id, text = '') => {
    const element = fromTemplate(id).firstElementChild;
    element.textContent = text;

    return element;
};
const minutes = seconds => `${Math.floor((seconds || 0) / 60)}ր`;
const postJson = async (url, body, signal) => {
    const response = await fetch(url, {
        method: 'POST',
        signal,
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

    if (!response.ok) throw Object.assign(new Error(data.error || data.message), { data });

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
let reflectionCourseId = null;
const reflections = new Map();
let selectedFile = null;
let toastTimer;

const tracker = createStorylineTracker({
    sections,
    store: progressStore,
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
    progressStore.install(item);
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

    if (frames.some(frame => frame.dataset.courseId === item.id && frame.contentWindow === event.source)) tracker.recordSlide(item, data.slideId);
});

function renderFeaturedCourse(item) {
    if (!item) return;

    setScormApi(item);
    selectAssistantCourse(item);
    if (reflectionCourseId !== item.id) {
        if (reflectionCourseId) reflections.set(reflectionCourseId, {
            answer: $('open-answer').value,
            result: [...$('evaluation-result').childNodes],
        });
        const reflection = reflections.get(item.id);
        $('open-answer').value = reflection?.answer ?? '';
        $('evaluation-result').replaceChildren(...(reflection?.result ?? []));
        reflectionCourseId = item.id;
    }
    $('open-question').hidden = !item.openQuestion;
    $('assignment-question').textContent = item.assignmentQuestion;
    syncAnswer();
    resetAssignmentHistory();
    $('featured-course-title').textContent = item.title;
    $('featured-course').hidden = false;

    if (loadedFeaturedId !== item.id) {
        const frame = $('featured-scorm-frame');
        frame.addEventListener('load', () => autoResume(frame), { once: true });
        frame.dataset.courseId = item.id;
        frame.src = item.url;
        loadedFeaturedId = item.id;
    }

    tracker.watch(item);
    tracker.sync(item);
    $('featured-fullscreen').onclick = () => openPlayer(item);
}

function openPlayer(item) {
    const frame = $('scorm-frame'), player = $('player');
    $('featured-scorm-frame').src = 'about:blank';
    loadedFeaturedId = null;

    setScormApi(item);
    $('player-title').textContent = ` ${item.title}`;
    frame.dataset.courseId = item.id;
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

function closePlayer(resume = true) {
    const player = $('player');
    const wasOpen = 'open' in player.dataset;

    $('scorm-frame').src = 'about:blank';
    delete player.dataset.open;
    player.setAttribute('aria-hidden', 'true');
    $('player-title').textContent = '';
    if (wasOpen && resume) renderFeaturedCourse(activeCourse);
}

// ---- Views -----------------------------------------------------------------

function showView(name) {
    $('view-library').hidden = name !== 'library' && name !== 'home';
    $('view-home').hidden = name !== 'home';
    $('view-progress').hidden = name !== 'progress';
    $('view-certificates').hidden = name !== 'certificates';

    document.querySelectorAll('[data-view]').forEach(link => {
        if (link.dataset.view === (name === 'home' ? 'library' : name)) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    });

    $('crumb-current').textContent = VIEW_LABELS[name];

    if (name === 'progress') { renderAnalytics(); progressStore.refresh(activeCourse).catch(error => toast(error.message)); }
    if (name === 'home') renderFeaturedCourse(activeCourse);

    if (name === 'home') $('view-home').scrollIntoView();
    else window.scrollTo(0, 0);
}

function sectionRow(section, progress) {
    const s = progress.sections[section.id] || {};
    const isQuiz = section.id === 'quiz', isVideo = section.id === 'video';
    const complete = Boolean(s.completed), visited = Boolean(s.visited || complete), active = progress.activeSection === section.id;
    const score = isQuiz ? (progress.quiz?.score ?? progress.quizScore?.score ?? null) : null;

    const percent = isVideo ? Number(s.percent) || 0 : isQuiz ? (score ?? 0) : (complete ? 100 : 0);

    let label = 'Չի սկսվել';
    if (complete) label = 'Ավարտված';
    else if (isQuiz && progress.quiz) label = 'Չի անցել';
    else if (isQuiz && progress.quizScore) label = 'Ընթացքի մեջ';
    else if (active) label = 'Ընթացքի մեջ';
    else if (visited) label = 'Բացվել է';
    else if (isVideo && s.seconds) label = 'Ընթացքի մեջ';

    let detail = 'Բաժինը կհայտնվի այստեղ, երբ բացեք այն Storyline դասընթացում';
    if (isQuiz && progress.quiz) detail = `Փորձի արդյունք՝ ${progress.quiz.score}% · անցողիկ՝ 70%`;
    else if (isQuiz && progress.quizScore) detail = `Ընթացիկ արդյունք՝ ${progress.quizScore.score}% · ինքնաստուգումը դեռ չի ավարտվել`;
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
    const done = progress.completedSections;
    const score = progress.quiz?.score ?? progress.quizScore?.score;
    const status = progress.status;

    $('progress-course-access').hidden = !item;

    if (item) {
        $('progress-course-title').textContent = item.title;
        $('progress-open-course').onclick = () => showView('home');
    }

    let courseStatus = status && status !== 'not_started' ? 'Ընթացքի մեջ' : 'Չի սկսվել';
    if (status === 'passed') courseStatus = 'Հանձնված է';
    else if (status === 'failed') courseStatus = 'Չի հանձնվել';
    else if (status === 'completed') courseStatus = 'Ավարտված՝ ըստ Storyline';

    const totalTime = progress.scorm['cmi.core.total_time'];

    $('stat-sections').textContent = `${done}/${progress.totalSections}`;
    $('stat-sections-note').textContent = item?.title || 'SCORM դասընթաց';
    $('stat-quiz').textContent = score === undefined || score === null ? '—' : `${score}%`;
    $('stat-quiz-note').textContent = progress.quiz
        ? (progress.quiz.passed ? '70% անցողիկ միավորը հաղթահարված է' : '70% անցողիկ միավորը դեռ չի հաղթահարվել')
        : progress.quizScore ? 'Ընթացիկ արդյունք · ինքնաստուգումը դեռ չի ավարտվել'
        : 'Արդյունքը կերևա Storyline-ի ինքնաստուգումից հետո';
    $('stat-status').textContent = courseStatus;
    $('stat-status-note').textContent = totalTime ? `Ուսումնառության ժամանակ՝ ${totalTime}` : 'Առաջընթացը պահպանվում է ձեր հաշվում';

    $('progress-save-status').textContent = progressStore.error?.message || '';
    const answerList = $('progress-answers');
    answerList.replaceChildren();
    const quizProgress = progress.quizProgress;
    $('progress-answer-count').textContent = quizProgress?.total !== null && quizProgress?.total !== undefined
        ? `Պատասխանված է ${quizProgress.answered}/${quizProgress.total}`
        : '';
    for (const answer of progress.answers) {
        const row = fromTemplate('tpl-progress-answer');
        row.querySelector('[data-number]').textContent = `Հարց ${answer.number}`;
        const question = row.querySelector('[data-question]');
        question.textContent = answer.description || '';
        question.hidden = !answer.description;
        row.querySelector('[data-selected]').textContent = answer.selectionUnavailable
            ? 'Պատասխանը պահպանված է, սակայն դրա տեքստը հնարավոր չէ վերականգնել։'
            : answer.selected ?? 'Պատասխան չի տրվել';
        const result = row.querySelector('[data-result]');
        result.textContent = answer.result === 'correct' ? 'Ճիշտ պատասխան'
            : answer.result === 'incorrect' ? 'Սխալ պատասխան'
            : answer.answered ? 'Պատասխանը պահպանված է' : 'Չի պատասխանվել';
        result.dataset.state = answer.result || '';
        row.querySelector('[data-correct-row]').hidden = !answer.answered;
        row.querySelector('[data-correct]').textContent = answer.correct?.join(' · ') || 'Դասընթացի տվյալներում նշված չէ։';
        answerList.append(row);
    }
    $('progress-answers-empty').hidden = progress.answers.length > 0;

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

let assignmentBusy = false;
let pendingAssignmentCourseId = null;
let historyRequest = 0;
let historyBefore = null;
const assignmentAnswer = () => $('open-answer').value.replace(/^[\s\u200B]+|[\s\u200B]+$/gu, '');
const assignmentMessage = (key, values = {}) => Object.entries(values).reduce((text, [name, value]) => text.replaceAll(`:${name}`, value), assignmentText[key]);

function syncAnswer() {
    const count = Array.from(assignmentAnswer()).length;
    const valid = count >= 200 && count <= 6000;
    $('answer-count').textContent = count;
    $('open-answer-validation').textContent = count > 6000 ? assignmentText.maximum : valid ? assignmentText.ready : assignmentMessage('remaining', { count: 200 - count });
    $('evaluate-answer').disabled = assignmentBusy || !valid;
    $('evaluate-answer').textContent = assignmentBusy ? assignmentText.loading : assignmentText.submit;
    $('open-answer').readOnly = assignmentBusy && pendingAssignmentCourseId === reflectionCourseId;
    $('open-question').setAttribute('aria-busy', String(assignmentBusy && pendingAssignmentCourseId === reflectionCourseId));
}

function evaluationMessageElement(message, isError = false) {
    const box = elementFromTemplate('tpl-evaluation-box', message);
    box.toggleAttribute('data-flagged', isError);
    box.setAttribute('role', isError ? 'alert' : 'status');
    return box;
}

function showEvaluationMessage(message, isError = false) {
    $('evaluation-result').replaceChildren(evaluationMessageElement(message, isError));
}

function evaluationElement(data) {
    const box = elementFromTemplate('tpl-evaluation-box');
    const details = fromTemplate('tpl-evaluation-details');
    const slot = name => details.querySelector(`[data-slot="${name}"]`);
    const score = data.final_score;
    const flagged = data.status === 'flagged';
    box.toggleAttribute('data-flagged', flagged);
    slot('score').textContent = assignmentMessage('score', { score });
    slot('bar').setAttribute('aria-valuenow', score);
    slot('bar').setAttribute('aria-label', assignmentMessage('score', { score }));
    slot('fill').style.width = `${score}%`;
    slot('flagged').hidden = !flagged;
    slot('flagged').textContent = assignmentText.flagged;
    slot('rule').hidden = !flagged;
    slot('rule').textContent = assignmentMessage('flagged_rule', { threshold: data.ai_threshold });
    slot('detection').textContent = assignmentMessage('detection', { percent: data.ai_probability });
    slot('classification').textContent = assignmentText.classifications[data.classification];
    slot('reason').textContent = data.reason;
    slot('summary').textContent = data.feedback?.summary ?? '';

    for (const [id, criterion] of Object.entries(data.feedback?.criteria ?? {})) {
        const item = fromTemplate('tpl-evaluation-criterion');
        const line = item.firstElementChild;
        line.firstElementChild.textContent = assignmentText.criteria[id];
        line.append(`՝ ${criterion.points}/${{ accuracy: 35, understanding: 30, completeness: 25, clarity: 10 }[id]}․ ${criterion.reason}`);
        slot('criteria').append(item);
    }
    for (const field of ['correct_points', 'missing_points', 'improvements']) {
        const items = data.feedback?.[field] ?? [];
        details.querySelector(`[data-feedback-section="${field}"]`).hidden = items.length === 0;
        for (const text of items) slot(field).append(elementFromTemplate('tpl-assignment-feedback', text));
    }
    box.append(details);
    return box;
}

$('open-answer').addEventListener('input', syncAnswer);
$('evaluate-answer').addEventListener('click', async () => {
    if (assignmentBusy) return;
    const answer = assignmentAnswer();
    const courseId = reflectionCourseId;
    if (Array.from(answer).length < 200 || Array.from(answer).length > 6000) {
        syncAnswer();
        return;
    }
    assignmentBusy = true;
    pendingAssignmentCourseId = courseId;
    syncAnswer();
    showEvaluationMessage(assignmentText.processing);
    try {
        const data = await postJson('/api/scorm/evaluate-open-ended', { student_response: answer, course_id: courseId }, AbortSignal.timeout(window.usum.assignmentTimeoutMs));
        const box = evaluationElement(data);
        if (courseId === reflectionCourseId) {
            $('evaluation-result').replaceChildren(box);
            if ($('assignment-history').open) loadAssignmentHistory();
        } else {
            const reflection = reflections.get(courseId);
            if (reflection) reflection.result = [box];
        }
        if (courses.find(course => course.id === courseId)?.number === 8) {
            state.evaluation = { score: data.final_score, max: 100, at: Date.now() };
            if (!state.completed.includes('reflection')) state.completed.push('reflection');
            saveState();
        }
    } catch (error) {
        const message = error.data?.error || assignmentText.failed;
        if (courseId === reflectionCourseId) showEvaluationMessage(message, true);
        else if (reflections.has(courseId)) reflections.get(courseId).result = [evaluationMessageElement(message, true)];
    } finally {
        assignmentBusy = false;
        pendingAssignmentCourseId = null;
        syncAnswer();
    }
});

function resetAssignmentHistory() {
    historyRequest++;
    historyBefore = null;
    $('assignment-history-list').replaceChildren();
    $('assignment-history-status').textContent = '';
    $('assignment-history-more').hidden = true;
    if ($('assignment-history').open && activeCourse?.openQuestion) loadAssignmentHistory();
}

async function loadAssignmentHistory(append = false) {
    const courseId = reflectionCourseId;
    const requestId = ++historyRequest;
    $('assignment-history-status').textContent = assignmentText.history_loading;
    $('assignment-history-more').disabled = true;
    $('assignment-history-retry').disabled = true;
    try {
        const query = new URLSearchParams({ course_id: courseId });
        if (append && historyBefore) query.set('before', historyBefore);
        const response = await fetch(`/api/scorm/assignment-submissions?${query}`, { headers: { accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
        if (response.status === 401 || response.status === 419) { location.reload(); return; }
        if (!response.ok) throw new Error();
        const data = await response.json();
        if (requestId !== historyRequest || courseId !== reflectionCourseId) return;
        if (!append) $('assignment-history-list').replaceChildren();
        for (const submission of data.submissions) {
            const item = fromTemplate('tpl-assignment-history');
            const slot = name => item.querySelector(`[data-slot="${name}"]`);
            slot('title').textContent = `${new Date(submission.created_at).toLocaleString('hy-AM')} · ${submission.final_score}/100`;
            slot('question').textContent = submission.assignment_question;
            slot('answer').textContent = submission.answer;
            slot('result').append(evaluationElement(submission));
            $('assignment-history-list').append(item);
        }
        historyBefore = data.next_before;
        $('assignment-history-more').hidden = !historyBefore;
        $('assignment-history-status').textContent = $('assignment-history-list').children.length ? '' : assignmentText.history_empty;
    } catch {
        if (requestId === historyRequest) $('assignment-history-status').textContent = assignmentText.history_failed;
    } finally {
        if (requestId === historyRequest) {
            $('assignment-history-more').disabled = false;
            $('assignment-history-retry').disabled = false;
        }
    }
}

$('assignment-history').addEventListener('toggle', () => {
    if ($('assignment-history').open) loadAssignmentHistory();
});
$('assignment-history-retry').addEventListener('click', () => loadAssignmentHistory());
$('assignment-history-more').addEventListener('click', () => loadAssignmentHistory(true));

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

const courseCards = [...document.querySelectorAll('[data-course-title]')];
function filterCourses() {
    const query = $('course-search').value.trim().normalize('NFC').toLocaleLowerCase('hy');
    let matches = 0;

    courseCards.forEach(card => {
        card.hidden = !card.dataset.courseTitle.normalize('NFC').toLocaleLowerCase('hy').includes(query);
        if (!card.hidden) matches++;
    });

    $('course-search-empty').hidden = !query || matches > 0;
    if ($('courses-coming-soon')) $('courses-coming-soon').hidden = query.length > 0;
}

$('course-search').addEventListener('input', filterCourses);
$('course-search-form').addEventListener('submit', event => {
    event.preventDefault();
    filterCourses();
});

document.querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', () => showView(link.dataset.view)));

document.querySelectorAll('[data-open-course]').forEach(button => button.addEventListener('click', () => {
    closePlayer(false);
    activeCourse = courses.find(course => course.id === button.dataset.openCourse) || courses[0];
    showView('home');
}));

$('close-player').addEventListener('click', closePlayer);

$('reset-progress').addEventListener('click', async () => {
    if (!confirm('Վերակայե՞լ այս դասընթացի պահպանված առաջընթացը։')) return;

    if (!activeCourse) return;

    closePlayer(false);
    $('featured-scorm-frame').src = 'about:blank';
    loadedFeaturedId = null;
    try { await tracker.clear(activeCourse); } catch (error) { toast(error.message); return; }

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
        for (const key of ['cmi.suspend_data', 'cmi.core.lesson_location', 'cmi.location']) progressStore.set(course, key, '');
    }
    await progressStore.flush();

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

}
main().catch(error => {
    const status = document.getElementById('progress-save-status');
    status.textContent = error.message || 'Առաջընթացը բեռնել չհաջողվեց։ Վերբեռնեք էջը։';
    const toast = document.getElementById('toast');
    toast.textContent = status.textContent;
    toast.dataset.show = '';
});
