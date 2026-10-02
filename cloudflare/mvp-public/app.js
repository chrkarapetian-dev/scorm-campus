(() => {
  const course = window.SUSTAINABLE_COURSE;
  const STORE = 'usum-topic8-progress-v1';
  const STORYLINE_STORE = 'usum-storyline-progress-v1';
  const directCourseLink = new URLSearchParams(location.search).get('course') === '1';
  if (new URLSearchParams(location.search).get('recover') === '1') {
    const resumeKey = 'usum-scorm-2c4eb6499843c263-';
    ['cmi.suspend_data','cmi.core.lesson_location'].forEach(key => localStorage.removeItem(resumeKey + key));
    const recoveredUrl = new URL(location.href);
    recoveredUrl.searchParams.delete('recover');
    history.replaceState(null, '', recoveredUrl);
  }
  const STORYLINE_SECTIONS = [
    { id:'video', title:'Տեսանյութ', slide:'5WdltCBo0mn', video:true },
    { id:'materials', title:'Ուսումնական նյութեր', slide:'6m4h0Gy3mUh' },
    { id:'literature', title:'Գրականություն', slide:'6eZpibFOcqO' },
    { id:'quiz', title:'Ինքնաստուգիչ հարցեր', slide:'6aymNm6ZUBv', resultsSlide:'6SfrhhbiYCV' }
  ];
  const emptyStorylineProgress = () => ({ sections:{}, scorm:{}, quiz:null, updatedAt:null });
  const blank = () => ({ completed: [], active: 'video', seconds: {}, quiz: null, evaluation: null, history: [] });
  let state;
  try { state = { ...blank(), ...JSON.parse(localStorage.getItem(STORE) || '{}') }; } catch { state = blank(); }
  let activeLesson = 'video', toastTimer, elapsedTimer, selectedFile = null, activeFeaturedCourse = null, loadedFeaturedId = null, storylineWatchTimer = null;
  const $ = id => document.getElementById(id);
  const save = () => localStorage.setItem(STORE, JSON.stringify(state));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const lessonIndex = id => course.lessons.findIndex(x => x.id === id);
  const pct = () => Math.round(state.completed.length / course.lessons.length * 100);
  const lesson = id => course.lessons.find(x => x.id === id) || course.lessons[0];
  const mm = sec => `${Math.floor((sec || 0) / 60)}ր`;
  function readStorylineProgress(item) {
    let all={}; try { all=JSON.parse(localStorage.getItem(STORYLINE_STORE)||'{}'); } catch {}
    return { ...emptyStorylineProgress(), ...(all[item?.id]||{}), sections:{...(all[item?.id]?.sections||{})}, scorm:{...(all[item?.id]?.scorm||{})} };
  }
  function saveStorylineProgress(item, progress, refresh=true) {
    if(!item)return;
    let all={}; try { all=JSON.parse(localStorage.getItem(STORYLINE_STORE)||'{}'); } catch {}
    progress.updatedAt=Date.now(); all[item.id]=progress; localStorage.setItem(STORYLINE_STORE,JSON.stringify(all));
    if(refresh&&!$('view-progress')?.hidden) renderAnalytics();
  }
  function sectionForLocation(location) { return STORYLINE_SECTIONS.find(x=>location?.includes(x.slide)) || null; }
  function sectionForSlide(slide) { return STORYLINE_SECTIONS.find(x=>slide===x.slide || slide===x.resultsSlide) || null; }
  function scorePercent(score,min,max) { const value=Number(score), low=Number(min||0), high=Number(max||100); return Number.isFinite(value)&&high>low?Math.max(0,Math.min(100,Math.round((value-low)/(high-low)*100))):null; }
  function interactionScore(item) {
    const prefix=`usum-scorm-${item.id}-`, answers=[];
    for(let i=0;i<localStorage.length;i++) { const key=localStorage.key(i); if(!key?.startsWith(prefix)||!/^cmi\.interactions\.\d+\.result$/.test(key.slice(prefix.length)))continue; const result=String(localStorage.getItem(key)||'').toLowerCase(); if(result==='correct')answers.push(1); else if(result==='incorrect')answers.push(0); }
    return answers.length?Math.round(answers.reduce((a,b)=>a+b,0)/answers.length*100):null;
  }
  function recordStorylineSlide(item, slide) {
    if(!slide)return;
    const progress=readStorylineProgress(item), next=sectionForSlide(String(slide)), prior=STORYLINE_SECTIONS.find(x=>x.id===progress.activeSection);
    if(progress.lastSlide===String(slide)&&progress.activeSection===next?.id&&(!next||progress.sections[next.id]?.visited))return;
    if(prior&&['materials','literature'].includes(prior.id)&&prior.id!==next?.id) progress.sections[prior.id]={...(progress.sections[prior.id]||{}),visited:true,completed:true,passedAt:progress.sections[prior.id]?.passedAt||Date.now()};
    progress.lastSlide=String(slide); progress.activeSection=next?.id||null;
    if(next) {
      const previous=progress.sections[next.id]||{};
      progress.sections[next.id]={...previous,visited:true,openedAt:previous.openedAt||Date.now()};
    }
    if(next?.id==='quiz'&&String(slide)===next.resultsSlide) {
      const raw=progress.scorm['cmi.core.score.raw'], min=progress.scorm['cmi.core.score.min'], max=progress.scorm['cmi.core.score.max'];
      const score=scorePercent(raw,min,max)??interactionScore(item), status=String(progress.scorm['cmi.core.lesson_status']||'').toLowerCase(), passed=status==='passed'||(score!==null&&score>=70);
      if(score!==null)progress.quiz={score,passed,at:Date.now(),source:raw?'storyline':'interactions'};
      progress.sections.quiz={completed:passed,passedAt:passed?Date.now():null,score};
    }
    saveStorylineProgress(item,progress);
  }
  window.addEventListener('message',event=>{
    const data=event.data;
    if(event.origin!==location.origin||data?.type!=='usum-storyline-slide'||typeof data.slideId!=='string')return;
    const item=activeFeaturedCourse||window.uploadedCourses?.[0]; if(!item)return;
    const frames=['featured-scorm-frame','scorm-frame'].map(id=>$(id)).filter(Boolean);
    if(frames.some(frame=>frame.contentWindow===event.source))recordStorylineSlide(item,data.slideId);
  });
  function syncStorylineProgress(item, refresh=true) {
    if(!item)return;
    const progress=readStorylineProgress(item), prefix=`usum-scorm-${item.id}-`, get=key=>localStorage.getItem(prefix+key)||'';
    for(const key of ['cmi.core.lesson_status','cmi.core.score.raw','cmi.core.score.min','cmi.core.score.max','cmi.core.lesson_location','cmi.core.total_time']) { const v=get(key); if(v)progress.scorm[key]=v; }
    const location=progress.scorm['cmi.core.lesson_location']||'', section=sectionForLocation(location), oldSection=sectionForLocation(progress.lastLocation);
    if(oldSection&&['materials','literature'].includes(oldSection.id)&&section?.id!==oldSection.id) {
      const prior=progress.sections[oldSection.id]||{}; progress.sections[oldSection.id]={...prior,completed:true,passedAt:prior.passedAt||Date.now()};
    }
    if(section&&!progress.lastSlide){progress.activeSection=section.id;progress.sections[section.id]={...(progress.sections[section.id]||{}),visited:true,openedAt:progress.sections[section.id]?.openedAt||Date.now()};}
    progress.lastLocation=location;
    const raw=progress.scorm['cmi.core.score.raw'], min=progress.scorm['cmi.core.score.min'], max=progress.scorm['cmi.core.score.max'];
    const pct=scorePercent(raw,min,max)??interactionScore(item), status=String(progress.scorm['cmi.core.lesson_status']||'').toLowerCase();
    if(pct!==null&&(!progress.quiz||['passed','failed','completed'].includes(status))) {
      const passed=status==='passed'||pct>=70;
      progress.quiz={score:pct,passed,at:Date.now(),source:raw?'storyline':'interactions'};
      if(location.includes('6SfrhhbiYCV')||progress.lastSlide==='6SfrhhbiYCV'||['passed','failed','completed'].includes(status)) progress.sections.quiz={completed:passed,passedAt:passed?Date.now():null,score:pct};
    }
    if(status==='passed'&&progress.quiz&&!progress.quiz.passed) { progress.quiz.passed=true; progress.sections.quiz={...(progress.sections.quiz||{}),completed:true,passedAt:Date.now(),score:progress.quiz.score}; }
    saveStorylineProgress(item,progress,refresh);
  }
  function trackVideoPlayback(item, video) {
    if(!video||video.dataset.usumProgressHook)return;
    const source=video.currentSrc||video.src||'';
    if(!source.includes('/WebObjects/5qvRxGnnI1u/video.mp4'))return;
    video.dataset.usumProgressHook='1'; let lastSaved=0;
    const update=force=>{
      if(!force&&Date.now()-lastSaved<4000)return; lastSaved=Date.now();
      const progress=readStorylineProgress(item), duration=Number.isFinite(video.duration)?video.duration:0;
      let watched=0; try { for(let i=0;i<video.played.length;i++)watched+=video.played.end(i)-video.played.start(i); } catch {}
      const prior=progress.sections.video||{}, sameVideo=prior.source==='storyline-main-video', seconds=Math.max(sameVideo?(Number(prior.seconds)||0):0,Math.floor(watched));
      const completed=(sameVideo&&Boolean(prior.completed))||(duration>0&&watched>=duration*.9);
      progress.sections.video={...prior,source:'storyline-main-video',seconds,duration:Math.round(duration),percent:duration?Math.min(100,Math.round(watched/duration*100)):0,completed,passedAt:completed?(sameVideo&&prior.passedAt||Date.now()):null};
      saveStorylineProgress(item,progress);
    };
    video.addEventListener('timeupdate',()=>update(false)); video.addEventListener('ended',()=>update(true)); video.addEventListener('loadedmetadata',()=>update(true)); update(true);
  }
  function scanStorylineVideos(item, win, visited=new Set()) {
    if(!win||visited.has(win))return; visited.add(win);
    try {
      win.document.querySelectorAll('video').forEach(video=>trackVideoPlayback(item,video));
      const slideText=String(win.document.body?.innerText||'');
      if(slideText.includes('Այս բաժնում տեղադրված են դասին առնչվող ուսումնական նյութերը'))recordStorylineSlide(item,'6m4h0Gy3mUh');
      else if(slideText.includes('Голубев Г.Н. Геоэкология. Изд.')&&slideText.includes('Գրականություն'))recordStorylineSlide(item,'6eZpibFOcqO');
      win.document.querySelectorAll('iframe').forEach(frame=>{try{scanStorylineVideos(item,frame.contentWindow,visited);}catch{}});
    } catch {}
  }
  function watchStorylineVideos(item) {
    clearInterval(storylineWatchTimer);
    storylineWatchTimer=setInterval(()=>{ for(const id of ['featured-scorm-frame','scorm-frame']){try{const frame=$(id);if(frame?.contentWindow)scanStorylineVideos(item,frame.contentWindow);}catch{}} syncStorylineProgress(item,false); },1500);
  }
  function toast(message) { const el = $('toast'); el.textContent = message; el.classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => el.classList.remove('show'), 2800); }
  function updateProgress() {
    const p = pct();
    if ($('progress-fill')) $('progress-fill').style.width = `${p}%`;
    if ($('progress-number')) $('progress-number').textContent = `${p}%`;
    if ($('lesson-progress-fill')) $('lesson-progress-fill').style.width = `${p}%`;
    if ($('lesson-progress-text')) $('lesson-progress-text').textContent = `${p}% ավարտված`;
    if ($('progress-caption')) $('progress-caption').textContent = p ? `${state.completed.length} բաժին՝ ${course.lessons.length}-ից ավարտված` : 'Սկսեք առաջին բաժնից';
    if ($('chapter-grid')) renderChapters(); if ($('goal-grid')) renderGoals(); if ($('library-list')) renderLibrary();
  }
  function renderChapters() {
    if (!$('chapter-grid')) return;
    $('chapter-grid').innerHTML = course.lessons.map((item, i) => {
      const done = state.completed.includes(item.id), current = item.id === state.active;
      const seen = Math.min(100, Math.round(((state.seconds[item.id] || 0) / 90) * 100));
      const n = String(i + 1).padStart(2, '0');
      return `<article class="chapter-card" tabindex="0" role="button" data-open-lesson="${item.id}"><div class="chapter-visual"><span class="chapter-no">${n}</span><span class="chapter-visual-label">${esc(item.kind.toUpperCase())}</span></div><div class="chapter-body"><span class="card-overline">${done ? 'ԱՎԱՐՏՎԱԾ' : current && seen ? 'ԸՆԹԱՑՔԻ ՄԵՋ' : item.label}</span><strong>${esc(item.title)}</strong><small>${esc(item.duration)}${state.seconds[item.id] ? ` · ${mm(state.seconds[item.id])} ուսումնառություն` : ''}</small></div><div class="chapter-progress"><i style="width:${done ? 100 : seen}%"></i></div></article>`;
    }).join('');
    document.querySelectorAll('[data-open-lesson]').forEach(el => {
      const open = () => openPlayer(el.dataset.openLesson);
      el.addEventListener('click', open); el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    });
  }
  function renderGoals() {
    if (!$('goal-grid')) return;
    $('goal-grid').innerHTML = course.goals.map(g => `<button class="goal-chip" data-goal="${g.n}" title="${esc(g.title)}"><b>${String(g.n).padStart(2, '0')}</b><span>${esc(g.title)}</span></button>`).join('');
    document.querySelectorAll('[data-goal]').forEach(el => el.addEventListener('click', () => { openPlayer('literature'); setTimeout(() => document.getElementById(`goal-${el.dataset.goal}`)?.scrollIntoView({ behavior:'smooth', block:'center' }), 180); }));
    const lessonGoalList=$('lesson-goal-list');
    if(lessonGoalList) lessonGoalList.innerHTML = course.goals.map(g => `<div class="goal-detail" id="goal-${g.n}"><b>${String(g.n).padStart(2, '0')}</b><div><strong>${esc(g.title)}</strong><p>${esc(g.short)}</p></div></div>`).join('');
  }
  function renderLibrary(query = '') {
    if (!$('library-list')) return;
    const q = query.trim().toLocaleLowerCase('hy');
    const items = course.lessons.filter(x => !q || `${x.title} ${x.kind}`.toLocaleLowerCase('hy').includes(q));
    $('library-list').innerHTML = items.map((x,i) => `<article class="library-item" data-open-lesson="${x.id}"><div class="library-number">${String(lessonIndex(x.id)+1).padStart(2,'0')}</div><div class="library-copy"><small>${esc(x.kind.toUpperCase())} · ${state.completed.includes(x.id) ? 'ԱՎԱՐՏՎԱԾ' : 'ԴԱՍԸՆԹԱՑԻ ԲԱԺԻՆ'}</small><strong>${esc(x.title)}</strong><p>${esc(x.lead)}</p></div><span class="library-arrow">→</span></article>`).join('') || '<div class="empty-state">Համապատասխան դասընթաց չի գտնվել։</div>';
    document.querySelectorAll('#library-list [data-open-lesson]').forEach(el => el.addEventListener('click', () => openPlayer(el.dataset.openLesson)));
  }
  function renderUploaded() {
    const rows = window.uploadedCourses || [];
    if (!activeFeaturedCourse || !rows.some(c => c.id === activeFeaturedCourse.id)) activeFeaturedCourse = rows[0] || null;
    $('uploaded-list').innerHTML = rows.length ? rows.map(c => `<article class="course-catalog-card"><div class="course-card-art"><img src="sustainable-development-poster.svg" alt="Կայուն զարգացման թեմայով դասընթացի պաստառ"><span>ARTICULATE · STORYLINE</span><i>ԻՆՏԵՐԱԿՏԻՎ ԴԱՍԸՆԹԱՑ</i></div><div class="course-card-copy"><div class="eyebrow">ՀԱՅԵՐԵՆ · SCORM ԴԱՍԸՆԹԱՑ</div><h2>${esc(c.title)}</h2><p>Կայուն զարգացումը՝ չափելի փոփոխությունների, մարդկանց բարեկեցության և շրջակա միջավայրի պահպանության միջոցով։</p><button class="button button-primary" data-open-course="${esc(c.id)}">Բացել Articulate դասը <span>→</span></button></div></article>`).join('') + `<article class="course-coming-soon"><div class="coming-soon-mark">＋</div><div class="eyebrow">ՇՈՒՏՈՎ</div><h2>Ավելի շատ դասընթացներ</h2><p>Նոր դասընթացներ կավելացվեն այստեղ։</p></article>` : '<div class="empty-state">Դասընթացներ դեռ չկան։ Դասընթացը հասանելի կլինի այստեղ՝ փաթեթը վերբեռնելուց հետո։</div>';
    document.querySelectorAll('[data-open-course]').forEach(el => el.addEventListener('click', () => { activeFeaturedCourse=rows.find(c=>c.id===el.dataset.openCourse)||rows[0]; showView('home'); }));
    if (!$('view-home').hidden) renderFeaturedCourse(activeFeaturedCourse);
  }
  function scormUrl(item) { const path=(item.launch||'index_lms.html').split('/').map(encodeURIComponent).join('/'); return `/content/${encodeURIComponent(item.id)}/${path}`; }
  function autoResumeScorm(frame) {
    let attempts=0;
    const resume=()=>{
      try { const button=frame.contentDocument?.querySelector('[data-dv_ref="resume"]'); if(button){button.click();return;} } catch {}
      if(++attempts<30)setTimeout(resume,350);
    };
    resume();
  }
  function setScormApi(item) {
    const prefix=`usum-scorm-${item.id}-`,get=key=>localStorage.getItem(prefix+key)||'',set=(key,value)=>{localStorage.setItem(prefix+key,String(value));syncStorylineProgress(item);return 'true';};
    window.API={LMSInitialize:()=> 'true',LMSFinish:()=> 'true',LMSCommit:()=> 'true',LMSGetValue:get,LMSSetValue:set,LMSGetLastError:()=> '0',LMSGetErrorString:()=> 'No error',LMSGetDiagnostic:()=> ''};
    window.API_1484_11={Initialize:()=> 'true',Terminate:()=> 'true',Commit:()=> 'true',GetValue:get,SetValue:set,GetLastError:()=> '0',GetErrorString:()=> 'No error',GetDiagnostic:()=> ''};
  }
  function renderFeaturedCourse(item) {
    if(!item)return;
    setScormApi(item);
    $('featured-course-title').textContent=item.title;
    $('featured-course').hidden=false;
    if(loadedFeaturedId!==item.id){const frame=$('featured-scorm-frame');frame.addEventListener('load',()=>autoResumeScorm(frame),{once:true});frame.src=scormUrl(item);loadedFeaturedId=item.id;}
    watchStorylineVideos(item); syncStorylineProgress(item);
    $('featured-fullscreen').onclick=()=>openScorm(item);
  }
  function openScorm(item) {
    if (!item) return;
    setScormApi(item);
    $('player-layout').hidden = true;
    $('scorm-stage').hidden = false;
    $('player').classList.add('scorm-mode');
    $('player-brand').innerHTML = `<span class="brand-mark">Ու</span> ${esc(item.title)}`;
    $('scorm-frame').src = scormUrl(item);
    watchStorylineVideos(item);
    const frame=$('scorm-frame'); frame.onload=()=>{autoResumeScorm(frame);const poll=()=>{try{scanStorylineVideos(item,frame.contentWindow);}catch{}};poll();frame.contentWindow?.addEventListener?.('load',poll,true);};
    $('player').classList.add('open'); $('player').setAttribute('aria-hidden','false');
  }
  function renderAnalytics() {
    const item=activeFeaturedCourse, p=readStorylineProgress(item), done=STORYLINE_SECTIONS.filter(x=>p.sections[x.id]?.completed).length, pct=p.quiz?.score, status=String(p.scorm['cmi.core.lesson_status']||'').toLowerCase();
    $('progress-course-access').hidden=!item;
    if(item){$('progress-course-title').textContent=item.title;$('progress-open-course').onclick=()=>showView('home');}
    const courseStatus=p.quiz?.passed||status==='passed'?'Հանձնված է':status==='failed'?'Չի հանձնվել':status==='completed'?'Ավարտված՝ ըստ Storyline':'Ընթացքի մեջ';
    $('analytics-summary').innerHTML = `<div class="analytics-card"><span>Articulate բաժիններ</span><strong>${done}/${STORYLINE_SECTIONS.length}</strong><small>${esc(item?.title||'SCORM դասընթաց')}</small></div><div class="analytics-card"><span>Ինքնաստուգման արդյունք</span><strong>${pct===undefined||pct===null?'—':`${pct}%`}</strong><small>${p.quiz?(p.quiz.passed?'70% անցողիկ միավորը հաղթահարված է':'70% անցողիկ միավորը դեռ չի հաղթահարվել'):'Արդյունքը կերևա Storyline-ի ինքնաստուգումից հետո'}</small></div><div class="analytics-card"><span>Storyline կարգավիճակ</span><strong class="analytics-status">${esc(courseStatus)}</strong><small>${p.scorm['cmi.core.total_time']?`Ուսումնառության ժամանակ՝ ${esc(p.scorm['cmi.core.total_time'])}`:'Առաջընթացը պահվում է այս դիտարկչում'}</small></div>`;
    $('progress-details').innerHTML = `<div class="storyline-progress-heading"><strong>Storyline դասընթացի բաժիններ</strong><span>Կարգավիճակը թարմացվում է դասընթացի ներսում</span></div>`+STORYLINE_SECTIONS.map(x=>{const s=p.sections[x.id]||{}, complete=Boolean(s.completed), visited=Boolean(s.visited||complete), score=x.id==='quiz'&&p.quiz?p.quiz.score:null, percent=x.id==='video'?Number(s.percent)||0:x.id==='quiz'?(score??0):(complete?100:visited?25:0), label=complete?'Ավարտված':x.id==='quiz'&&p.quiz?'Չի անցել':p.activeSection===x.id?'Ընթացքի մեջ':visited?'Բացվել է':x.id==='video'&&s.seconds?'Ընթացքի մեջ':'Չի սկսվել', detail=x.id==='quiz'&&p.quiz?`Փորձի արդյունք՝ ${p.quiz.score}% · անցողիկ՝ 70%`:x.id==='video'&&s.seconds?`Դիտվել է ${s.seconds<60?`${s.seconds} վայրկյան`:mm(s.seconds)}${s.duration>=60?`՝ ${Math.round(s.seconds/60)} / ${Math.round(s.duration/60)} րոպե`:''}`:complete?'Բաժինն ուսումնասիրվել է Articulate Storyline-ում':p.activeSection===x.id?'Դուք բացել եք այս բաժինը Articulate Storyline-ում':visited?'Այս բաժինը բացվել է Articulate Storyline-ում':'Բաժինը կհայտնվի այստեղ, երբ բացեք այն Storyline դասընթացում';return `<div class="progress-row storyline-progress-row"><div class="storyline-row-copy"><strong>${esc(x.title)}</strong><small>${esc(detail)}</small></div><div class="progress-track"><i style="width:${percent}%"></i></div><small class="storyline-row-status ${complete?'is-complete':p.quiz&&!p.quiz.passed&&x.id==='quiz'?'is-failed':''}">${esc(label)}</small></div>`}).join('');
  }
  function showView(name) {
    ['home','library','progress'].forEach(v => { if($(`view-${v}`)) $(`view-${v}`).hidden = v !== name; });
    document.querySelectorAll('.nav-link').forEach(el => el.classList.toggle('active', el.dataset.view === name));
    $('crumb-current').textContent = ({home:'Դասընթացներ',library:'Գլխավոր',progress:'Իմ առաջընթացը'})[name] || 'Գլխավոր';
    if (name === 'progress') renderAnalytics();
    if (name === 'library') renderUploaded();
    if (name === 'home') renderFeaturedCourse(activeFeaturedCourse);
    window.scrollTo(0,0);
  }
  function renderLesson(id) {
    activeLesson = id; state.active = id; save();
    const item = lesson(id), completed = state.completed.includes(id);
    $('lesson-content').innerHTML = `<div class="lesson-kicker">${esc(item.label)}</div><h1>${esc(item.title)}</h1><p class="lesson-lead">${esc(item.lead)}</p><div class="lesson-body">${item.html}</div>`;
    $('lesson-nav').innerHTML = course.lessons.map((x,i) => `<button class="${x.id === id ? 'active' : ''} ${state.completed.includes(x.id) ? 'done' : ''}" data-lesson="${x.id}"><span>${state.completed.includes(x.id) ? '✓' : String(i+1).padStart(2,'0')}</span><span>${esc(x.title)}</span></button>`).join('');
    document.querySelectorAll('[data-lesson]').forEach(el => el.addEventListener('click', () => renderLesson(el.dataset.lesson)));
    const i = lessonIndex(id), next = course.lessons[i + 1];
    $('lesson-bottom').innerHTML = `<small>${state.seconds[id] ? `${mm(state.seconds[id])} ուսումնառություն · ` : ''}${completed ? 'Բաժինն ավարտված է' : 'Առաջընթացը պահվում է ավտոմատ'}</small><button class="button ${completed ? 'button-outline' : 'button-primary'}" id="lesson-next">${next ? (completed ? 'Հաջորդ բաժինը' : 'Ավարտել և շարունակել') : (completed ? 'Բաժինն ավարտված է' : 'Ավարտել դասընթացը')} ${next ? '→' : '✓'}</button>`;
    $('lesson-next').addEventListener('click', () => {
      if (!state.completed.includes(id)) { state.completed.push(id); save(); updateProgress(); }
      if (next) renderLesson(next.id); else { toast('Դուք ավարտել եք այս դասընթացի բաժինները։'); renderLesson(id); }
    });
    if (id === 'quiz') { renderQuizMarkup(); bindQuiz(); }
    if (id === 'reflection') bindEvaluation();
    updateProgress();
  }
  function openPlayer(id = state.active || 'video') {
    renderLesson(id); $('player').classList.add('open'); $('player').setAttribute('aria-hidden','false');
    clearInterval(elapsedTimer); elapsedTimer = setInterval(() => { state.seconds[activeLesson] = (state.seconds[activeLesson] || 0) + 1; save(); if (state.seconds[activeLesson] % 15 === 0) updateProgress(); }, 1000);
  }
  function closePlayer() { clearInterval(elapsedTimer); $('scorm-frame').src = 'about:blank'; $('scorm-stage').hidden = true; $('player-layout').hidden = false; $('player').classList.remove('scorm-mode','open'); $('player').setAttribute('aria-hidden','true'); $('player-brand').innerHTML = '<span class="brand-mark">Ու</span> ուսում'; save(); updateProgress(); }
  function bindQuiz() {
    $('submit-quiz').addEventListener('click', () => {
      let correct = 0, answered = 0;
      course.questions.forEach((q,i) => {
        const selected = document.querySelector(`input[name="q${i}"]:checked`);
        if (selected) { answered++; if (Number(selected.value) === q.a) correct++; }
      });
      if (answered < course.questions.length) { $('quiz-results').innerHTML = `<div class="quiz-result wrong">Պատասխանեք բոլոր ${course.questions.length} հարցերին՝ արդյունքը տեսնելու համար։</div>`; return; }
      state.quiz = { correct, total: course.questions.length, at: Date.now() };
      if (!state.completed.includes('quiz')) state.completed.push('quiz');
      save(); updateProgress();
      const lines = course.questions.map((q,i) => { const selected = document.querySelector(`input[name="q${i}"]:checked`); const ok = Number(selected.value) === q.a; return `${i+1}. ${ok ? 'Ճիշտ է' : 'Կրկնեք այս գաղափարը'} — ${q.why}`; });
      $('quiz-results').innerHTML = `<div class="quiz-result ${correct < 3 ? 'wrong' : ''}"><strong>Արդյունք՝ ${correct}/${course.questions.length}</strong><br>${lines.map(esc).join('<br>')}</div>`;
      $('submit-quiz').textContent = 'Կրկին ստուգել պատասխանները';
    });
  }
  function renderQuizMarkup() {
    $('quiz-questions').innerHTML = course.questions.map((q,i) => `<div class="question-card"><strong>${i+1}. ${esc(q.q)}</strong>${q.opts.map((o,j) => `<label class="option"><input type="radio" name="q${i}" value="${j}"><span>${esc(o)}</span></label>`).join('')}</div>`).join('');
  }
  function bindEvaluation() {
    $('evaluate-answer').addEventListener('click', async () => {
      const response = $('open-answer').value.trim();
      if (response.length < 40) { $('evaluation-result').innerHTML = '<div class="eval-result">Խնդրում ենք գրել առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։</div>'; return; }
      $('evaluate-answer').disabled = true; $('evaluate-answer').textContent = 'Գնահատում…';
      try {
        if (location.protocol === 'file:') {
          const data=offlineEvaluation(response); state.evaluation={score:data.total_score_awarded,max:data.max_total_score,at:Date.now()};
          if(!state.completed.includes('reflection'))state.completed.push('reflection'); save(); updateProgress();
          $('evaluation-result').innerHTML=renderEvaluation(data);
          return;
        }
        const res = await fetch('/api/scorm/evaluate-open-ended', { method:'POST', headers:{'content-type':'application/json'}, body:JSON.stringify({student_response:response}) });
        const data = await res.json(); if (!res.ok) throw new Error(data.error || 'Չհաջողվեց գնահատել պատասխանը։');
        state.evaluation = { score:data.total_score_awarded, max:data.max_total_score, at:Date.now() };
        if (!state.completed.includes('reflection')) state.completed.push('reflection'); save(); updateProgress();
        $('evaluation-result').innerHTML = renderEvaluation(data);
      } catch(e) { $('evaluation-result').innerHTML = `<div class="eval-result">${esc(e.message)}</div>`; }
      finally { $('evaluate-answer').disabled = false; $('evaluate-answer').textContent = 'Կրկին ստանալ հետադարձ կապ'; }
    });
  }
  function evaluationBand(score) { return score>=90?'Օրինակելի':score>=75?'Իմացող / Լիարժեք':score>=60?'Զարգացող':'Անբավարար'; }
  function renderEvaluation(data) {
    const score=Math.max(0,Math.min(100,Number(data.total_score_awarded)||0)), rows=data.criterion_breakdown||[];
    const titles={understanding:'Թեմայի ըմբռնում',connections:'Կայունության չափումների կապ',example_measure:'Օրինակ և չափում',completeness:'Ամբողջականություն և հստակություն'};
    return `<div class="eval-result"><div class="eval-score">${score}/100 միավոր</div><div class="eval-level">${evaluationBand(score)}</div><p>${esc(data.summary_feedback_armenian||'Գնահատումն ավարտված է։')}</p><ul>${rows.map(x=>`<li><b>${esc(x.title_armenian||titles[x.criterion_id]||x.criterion_id)}</b>՝ ${esc(x.points_earned)}/${esc(x.max_points)}․ ${esc(x.reasoning_armenian)}</li>`).join('')}</ul><p>${esc(data.actionable_tips_armenian||'')}</p></div>`;
  }
  function toggleAssistant(show = true) { $('assistant-panel').classList.toggle('show', show); if (show) setTimeout(() => $('chat-input').focus(), 200); }
  function offlineAnswer(question) {
    const q=question.toLocaleLowerCase('hy');
    if(!/կայուն|զարգաց|ինդիկատ|ցուցանիշ|համաթիվ|ինդեքս|մզհ|կզհ|կրթ|եկամուտ|աղքատ|սով|գենդեր|կլիմա|ջուր|էներգիա|քաղաք|գործընկեր|նպատակ|հնա|հաե|hdi|sdg|indicator|sustainable development|poverty|climate|education|income|gender|water|energy|goal|index|why|how|explain/i.test(q)) return {answer_armenian:'Ես նախագծված եմ օգնելու միայն այս դասընթացի շրջանակներում։ Խնդրում եմ հարցեր ուղղել կայուն զարգացման դասի նյութի վերաբերյալ։',citations:[],suggested_followups_armenian:['Ի՞նչ է կայուն զարգացման ինդիկատորը։','Ի՞նչ է չափում մարդկային զարգացման համաթիվը։']};
    if(/ինդիկատ|ցուցանիշ|indicator/.test(q)) return {answer_armenian:'Ըստ դասընթացի՝ կայուն զարգացման ինդիկատորը չափելի արժեք է, որը նկարագրում է տնտեսական, սոցիալական կամ էկոլոգիական ոլորտի վիճակը կամ փոփոխությունը։ Այն օգնում է համեմատել երևույթներն ու հիմնավորել որոշումները։',citations:[{reference_label:'Սլայդներ 3–4'},{reference_label:'Թեմա 8 ընթերցանության նյութ'}],suggested_followups_armenian:['Ինչո՞վ է ինդիկատորը տարբերվում համաթվից։','Ինչպե՞ս ընտրել համայնքի համար ինդիկատոր։']};
    if(/համաթիվ|ինդեքս|մզհ|կզհ|hdi|index|եկամուտ/.test(q)) return {answer_armenian:'Մարդկային զարգացման համաթիվը համադրում է առողջության, կրթության և եկամտի չափումները։ Կայուն զարգացման համաթիվը դրանց հետ միասին դիտարկում է նաև բնապահպանական ճնշումը, այդ թվում՝ CO₂ արտանետումներն ու նյութական ոտնահետքը։',citations:[{reference_label:'Սլայդներ 5–7 և 10–11'}],suggested_followups_armenian:['Ինչո՞ւ ՀՆԱ-ն միայնակ բավարար չէ։','Որո՞նք են կայուն զարգացման ինդիկատորները։']};
    if(/նպատակ|goal|աղքատ|սով|կլիմա|ջուր|էներգիա/.test(q)) { const m=q.match(/(?:նպատակ|goal|sdg|թիվ)\D{0,8}(1[0-7]|[1-9])|(?:^|\s)(1[0-7]|[1-9])(?:\s|$)/),n=m?.slice(1).find(Boolean),g=course.goals.find(x=>(n&&x.n===Number(n))||q.includes(x.title.toLocaleLowerCase('hy').split(' ')[0])); return {answer_armenian:g?`Ըստ դասընթացի՝ ${g.n}-րդ նպատակը՝ «${g.title}», ուղղված է հետևյալ խնդրին․ ${g.short}`:'ՄԱԿ-ի կայուն զարգացման 17 նպատակները միավորում են սոցիալական, տնտեսական և բնապահպանական խնդիրները։ Դրանք փոխկապակցված են։',citations:[{reference_label:'Սլայդներ 13–32'},{reference_label:'Թեմա 8 ընթերցանության նյութ'}],suggested_followups_armenian:['Ինչպե՞ս են նպատակները կապված միմյանց։','Ո՞ր ինդիկատորով կարելի է չափել առաջընթացը։']}; }
    return {answer_armenian:`Ըստ ընթացիկ բաժնի՝ ${lesson(activeLesson).title.toLocaleLowerCase('hy')} թեման կապում է կայուն զարգացման չափումները հասարակության և շրջակա միջավայրի խնդիրների հետ։ ${lesson(activeLesson).lead}`,citations:[{reference_label:lesson(activeLesson).label}],suggested_followups_armenian:['Ինչո՞ւ են տարբեր ոլորտների տվյալները կարևոր։','Ինչպե՞ս կիրառել այս գաղափարը համայնքում։']};
  }
  function offlineEvaluation(answer) {
    const s=answer.toLocaleLowerCase('hy');
    const hits=pattern=>(s.match(pattern)||[]).length;
    const hasTopic=/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|համաթվ|նպատակ|տնտես|շրջակա միջավայր/.test(s);
    const dimensions=[/սոցիալ|հասարակ|առողջ|կրթ|հավասար/.test(s),/տնտես|եկամուտ|աշխատ|աղքատ/.test(s),/բնապահպան|էկոլոգ|շրջակա միջավայր|ռեսուրս|կլիմա/.test(s)].filter(Boolean).length;
    const example=/օրինակ|օր.`|օր՝|համայնք|քաղաք|գյուղ|ջուր|էներգիա|տրանսպորտ|աղտոտ|թափոն|գործազրկ/.test(s);
    const measurable=/ինդիկատոր|ցուցանիշ|համաթիվ|տոկոս|քանակ|չափել|հետևել|տվյալ|մակարդակ|բաժին/.test(s);
    const reasons=(score,full,partial,missing)=>score>=.8?full:score>=.4?partial:missing;
    const criteria=[
      {criterion_id:'understanding',title_armenian:'Թեմայի ըմբռնում',max_points:30,points_earned:hasTopic?Math.min(30,18+Math.min(12,hits(/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|նպատակ/g)*3)):0,full:'Թեմայի հիմնական գաղափարները ճիշտ են կիրառված։',partial:'Թեմային առնչվող միտք կա, բայց հիմնական հասկացությունները կարելի է ավելի հստակ բացատրել։',missing:'Բացակայում է կայուն զարգացման թեմայի հստակ բացատրությունը։'},
      {criterion_id:'connections',title_armenian:'Կայունության չափումների կապ',max_points:25,points_earned:dimensions===3?25:dimensions===2?19:dimensions===1?10:0,full:'Սոցիալական, տնտեսական և բնապահպանական կողմերի կապը հստակ է։',partial:'Նշված է չափումներից մի քանիսը, սակայն դրանց կապը լիարժեք բացատրված չէ։',missing:'Բացատրեք, թե ինչպես են սոցիալական, տնտեսական կամ բնապահպանական կողմերը կապվում թեմային։'},
      {criterion_id:'example_measure',title_armenian:'Օրինակ և չափում',max_points:25,points_earned:example&&measurable?25:example||measurable?13:0,full:'Բերված է համապատասխան օրինակ և դրա առաջընթացը չափելու եղանակ։',partial:'Օրինակը կամ չափման եղանակը կա, բայց երկուսն էլ միասին հստակ չեն։',missing:'Բերեք կոնկրետ օրինակ և առաջարկեք չափելի տվյալ կամ ինդիկատոր։'},
      {criterion_id:'completeness',title_armenian:'Ամբողջականություն և հստակություն',max_points:20,points_earned:Math.min(20,(s.length>=450?12:s.length>=250?9:s.length>=120?6:3)+(/քանի որ|հետևաբար|այսպիսով|որովհետև|կապված|ազդում/.test(s)?8:0)),full:'Պատասխանը ամբողջական է, հստակ և հիմնավորում է գաղափարների կապը։',partial:'Պատասխանը հասկանալի է, բայց հիմնավորման կամ ամբողջականության մի մասը պակասում է։',missing:'Պատասխանը թերի է կամ դժվար է հասկանալ։'}
    ];
    const criterion_breakdown=criteria.map(c=>({criterion_id:c.criterion_id,title_armenian:c.title_armenian,points_earned:c.points_earned,max_points:c.max_points,reasoning_armenian:reasons(c.points_earned/c.max_points,c.full,c.partial,c.missing)})),total_score_awarded=criterion_breakdown.reduce((n,x)=>n+x.points_earned,0),grade=evaluationBand(total_score_awarded);
    const summary_feedback_armenian=total_score_awarded<60?'Սպասելիքներից ցածր է․ պատասխանը թերի է, շեղված թեմայից կամ անճիշտ, կամ բացակայում են պահանջվող հիմնական տարրերը։':total_score_awarded<75?'Պատասխանը մասամբ է բացահայտում թեման։ Ավելացրեք գաղափարների կապը, օրինակն ու չափելի հիմնավորումը։':total_score_awarded<90?'Պատասխանը լավ է ներկայացնում հիմնական գաղափարները, սակայն որոշ կապեր կամ հիմնավորումներ կարելի է լրացնել։':'Պատասխանը ամբողջական ու հիմնավորված է և հստակ կապում է կայուն զարգացումը չափելի առաջընթացի հետ։';
    return {total_score_awarded,max_total_score:100,grade_level:grade,summary_feedback_armenian,criterion_breakdown,actionable_tips_armenian:total_score_awarded<60?'Վերանայեք թեմայի հիմնական գաղափարները, պատասխանեք հարցի բոլոր մասերին և բերեք համապատասխան օրինակ ու չափելի տվյալ։':'Ուժեղացրեք հիմնավորումը՝ բացատրելով, թե առաջարկված տվյալը ինչպես է ցույց տալիս կայուն զարգացման առաջընթացը։'};
  }
  async function askAssistant(question) {
    const msg = document.createElement('div'); msg.className = 'chat-message user-message'; msg.textContent = question; $('chat-messages').append(msg);
    const pending = document.createElement('div'); pending.className = 'chat-message assistant-message'; pending.textContent = 'Մտածում եմ…'; $('chat-messages').append(pending); $('chat-messages').scrollTop = $('chat-messages').scrollHeight;
    try {
      if (location.protocol === 'file:') {
        const data=offlineAnswer(question); pending.textContent=data.answer_armenian;
        if(data.citations?.length){const cites=document.createElement('div');cites.className='citation';cites.textContent=data.citations.map(c=>c.reference_label).join(' · ');pending.append(cites);}
        if(data.suggested_followups_armenian?.length){const follow=document.createElement('div');follow.className='suggested-followups';data.suggested_followups_armenian.forEach(text=>{const btn=document.createElement('button');btn.className='followup';btn.textContent=text;btn.addEventListener('click',()=>askAssistant(text));follow.append(btn);});pending.append(follow);}
        $('chat-messages').scrollTop=$('chat-messages').scrollHeight;return;
      }
      const res = await fetch('/api/scorm/qa-assistant', { method:'POST', headers:{'content-type':'application/json'}, body:JSON.stringify({student_question:question, active_section:activeLesson}) });
      const data = await res.json(); if (!res.ok) throw new Error(data.error || 'Հարցին պատասխանել չհաջողվեց։');
      pending.textContent = data.answer_armenian;
      if (data.citations?.length) { const cites = document.createElement('div'); cites.className = 'citation'; cites.textContent = data.citations.map(c => c.reference_label).join(' · '); pending.append(cites); }
      if (data.suggested_followups_armenian?.length) { const follow = document.createElement('div'); follow.className = 'suggested-followups'; data.suggested_followups_armenian.slice(0,2).forEach(text => { const btn = document.createElement('button'); btn.className='followup'; btn.textContent=text; btn.addEventListener('click',()=>askAssistant(text)); follow.append(btn); }); pending.append(follow); }
    } catch(e) {
      const data=offlineAnswer(question);
      pending.textContent=data.answer_armenian;
      if(data.citations?.length){const cites=document.createElement('div');cites.className='citation';cites.textContent=data.citations.map(c=>c.reference_label).join(' · ');pending.append(cites);}
      if(data.suggested_followups_armenian?.length){const follow=document.createElement('div');follow.className='suggested-followups';data.suggested_followups_armenian.slice(0,2).forEach(text=>{const btn=document.createElement('button');btn.className='followup';btn.textContent=text;btn.addEventListener('click',()=>askAssistant(text));follow.append(btn);});pending.append(follow);}
    }
    $('chat-messages').scrollTop = $('chat-messages').scrollHeight;
  }
  $('chat-form').addEventListener('submit', e => { e.preventDefault(); const q = $('chat-input').value.trim(); if (!q) return; $('chat-input').value = ''; askAssistant(q); });
  function openUpload() { $('upload-modal').classList.add('show'); }
  async function loadUploaded() { try { const res=await fetch('/api/courses'); if(res.ok) { window.uploadedCourses=await res.json(); renderUploaded(); if(!$('view-progress').hidden)renderAnalytics(); if(directCourseLink&&window.uploadedCourses.length){activeFeaturedCourse=window.uploadedCourses[0];showView('home');} } } catch { window.uploadedCourses=[]; } }
  async function submitUpload() {
    const status=$('upload-status'),btn=$('upload-submit');
    if (!selectedFile) { status.textContent='Նախ ընտրեք SCORM ZIP փաթեթը։'; return; }
    btn.disabled=true; btn.textContent='Վերբեռնվում է…'; status.textContent='Ստուգվում է imsmanifest.xml ֆայլը։';
    try {
      const body=new FormData(); body.append('package',selectedFile); body.append('title',$('upload-title').value.trim());
      const headers={}; if($('upload-token').value.trim()) headers['x-upload-token']=$('upload-token').value.trim();
      const res=await fetch('/api/courses',{method:'POST',body,headers}); const data=await res.json(); if(!res.ok) throw new Error(data.error || 'Փաթեթը չհաջողվեց վերբեռնել։');
      await loadUploaded(); $('upload-modal').classList.remove('show'); showView('library'); toast('SCORM դասընթացն ավելացվել է։'); selectedFile=null; $('upload-file').value='';
    } catch(e) { status.textContent=e.message === 'Failed to fetch' ? 'Սերվերը հասանելի չէ։ Գործարկեք npm start հրամանը։' : e.message; }
    finally { btn.disabled=false; btn.textContent='Վերբեռնել'; }
  }
  document.querySelectorAll('.nav-link').forEach(el => el.addEventListener('click',()=>showView(el.dataset.view)));
  $('close-player').addEventListener('click',closePlayer);
  $('upload-submit').addEventListener('click',submitUpload);
  bindEvaluation();
  document.querySelectorAll('[data-close-modal]').forEach(el=>el.addEventListener('click',()=>$(el.dataset.closeModal).classList.remove('show')));
  $('upload-file').addEventListener('change',e=>{ selectedFile=e.target.files?.[0] || null; if(selectedFile) { $('upload-title').value=$('upload-title').value || selectedFile.name.replace(/\.zip$/i,'').replace(/[-_]/g,' '); $('upload-status').textContent=`${selectedFile.name} · ${(selectedFile.size/1048576).toFixed(1)} ՄԲ`; } });
  $('upload-drop').addEventListener('dragover',e=>{e.preventDefault();$('upload-drop').classList.add('dragover');}); $('upload-drop').addEventListener('dragleave',()=> $('upload-drop').classList.remove('dragover')); $('upload-drop').addEventListener('drop',e=>{e.preventDefault();$('upload-drop').classList.remove('dragover');selectedFile=e.dataTransfer.files[0]||null;if(selectedFile){$('upload-title').value=$('upload-title').value||selectedFile.name.replace(/\.zip$/i,'').replace(/[-_]/g,' ');$('upload-status').textContent=`${selectedFile.name} · ${(selectedFile.size/1048576).toFixed(1)} ՄԲ`;}});
  $('reset-progress').addEventListener('click',()=>{ if(confirm('Վերակայե՞լ այս դասընթացի պահպանված առաջընթացը։')) { state=blank(); save(); if(activeFeaturedCourse){let all={};try{all=JSON.parse(localStorage.getItem(STORYLINE_STORE)||'{}')}catch{}delete all[activeFeaturedCourse.id];localStorage.setItem(STORYLINE_STORE,JSON.stringify(all));const key=`usum-scorm-${activeFeaturedCourse.id}-`;for(let i=localStorage.length-1;i>=0;i--){const k=localStorage.key(i);if(k?.startsWith(key))localStorage.removeItem(k);}} updateProgress(); renderAnalytics(); toast('Առաջընթացը վերակայվել է։'); } });
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){if($('player').classList.contains('open'))closePlayer();document.querySelectorAll('.modal-back.show').forEach(x=>x.classList.remove('show'));}});
  updateProgress(); renderUploaded(); loadUploaded();
})();
