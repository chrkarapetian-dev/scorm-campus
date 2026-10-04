const CATALOG_KEY = 'meta/catalog.json';
const MVP_COURSE_ID = '2c4eb6499843c263';
const aiWindows = new Map();
const AI_SCHEMAS = {
  qa: { type:'json_schema', name:'armenian_course_answer', strict:true, schema:{ type:'object', additionalProperties:false, required:['answer_armenian','citations','suggested_followups_armenian'], properties:{ answer_armenian:{type:'string'}, citations:{type:'array',items:{type:'object',additionalProperties:false,required:['source_type','reference_label'],properties:{source_type:{type:'string',enum:['slide','video','literature']},reference_label:{type:'string'}}}}, suggested_followups_armenian:{type:'array',items:{type:'string'}} } } },
  evaluation: { type:'json_schema', name:'armenian_rubric_evaluation', strict:true, schema:{ type:'object', additionalProperties:false, required:['total_score_awarded','max_total_score','summary_feedback_armenian','criterion_breakdown','actionable_tips_armenian'], properties:{ total_score_awarded:{type:'number'}, max_total_score:{type:'number'}, summary_feedback_armenian:{type:'string'}, actionable_tips_armenian:{type:'string'}, criterion_breakdown:{type:'array',items:{type:'object',additionalProperties:false,required:['criterion_id','points_earned','max_points','reasoning_armenian'],properties:{criterion_id:{type:'string'},points_earned:{type:'number'},max_points:{type:'number'},reasoning_armenian:{type:'string'}}}} } } }
};
const COURSE_CONTEXT = `Դասընթացի թեման՝ Կայուն զարգացման ցուցանիշները և նպատակները։ Զարգացումը միայն տնտեսական աճը չէ. պետք է միասին դիտարկել սոցիալական բարեկեցությունը, տնտեսությունն ու շրջակա միջավայրը։ Ինդիկատորը չափելի արժեք է, որը նկարագրում է երևույթի վիճակը կամ փոփոխությունը. օրինակ՝ կյանքի սպասվող տևողություն, կրթության տևողություն, մեկ անձի հաշվով եկամուտ, CO₂ արտանետումներ։ Մարդկային զարգացման համաթիվը համադրում է առողջությունը (կյանքի սպասվող տևողությունը), կրթությունը և մեկ անձի հաշվով համախառն ազգային եկամուտը։ Կայուն զարգացման համաթիվը դիտարկում է նաև մեկ շնչի հաշվով էկոլոգիական ճնշումը՝ CO₂ արտանետումներն ու նյութական ոտնահետքը։ ՀՆԱ-ի աճը միայնակ բավարար չէ, քանի որ այն կարող է ուղեկցվել անհավասարությամբ կամ բնապահպանական վիճակի վատթարացմամբ։ ՄԱԿ-ի 17 ԿԶՆ-ները փոխկապակցված են և ներառում են աղքատություն, սով, առողջություն, կրթություն, հավասարություն, ջուր, էներգիա, արժանապատիվ աշխատանք, նորարարություն, անհավասարություն, քաղաքներ, սպառում և արտադրություն, կլիմա, ջրային ու ցամաքային էկոհամակարգեր, խաղաղություն և գործընկերություն։ Այս համառոտ դասանյութը չպարունակող կոնկրետ թվերը կամ աղբյուրային մեջբերումները մի հորինիր։`;
function aiAllowed(request) {
  const key = request.headers.get('cf-connecting-ip') || 'unknown', now = Date.now(), old = aiWindows.get(key);
  if (!old || now - old.start >= 60_000) aiWindows.set(key,{start:now,count:1});
  else if (old.count >= 8) return false;
  else old.count++;
  if (aiWindows.size > 1000) for (const [ip,item] of aiWindows) if (now - item.start > 120_000) aiWindows.delete(ip);
  return true;
}
async function openAiJson(env, schema, instructions, input) {
  const response = await fetch('https://api.openai.com/v1/responses',{method:'POST',headers:{authorization:`Bearer ${env.OPENAI_API_KEY}`,'content-type':'application/json'},body:JSON.stringify({model:env.OPENAI_MODEL||'gpt-4o',instructions,input,store:false,max_output_tokens:700,text:{format:schema}}),signal:AbortSignal.timeout(45_000)});
  const data = await response.json();
  if (!response.ok) { console.error('OpenAI API response:',response.status,data.error?.type || 'request_error'); const error=new Error('OpenAI request failed.'); error.code=data.error?.type||data.error?.code; error.status=response.status; throw error; }
  const outputText = (data.output||[]).flatMap(item=>item.content||[]).find(item=>item.type==='output_text')?.text;
  if (!outputText) throw new Error('OpenAI returned no text.');
  return JSON.parse(outputText);
}
const MIME = {
  '.html':'text/html; charset=utf-8','.htm':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8',
  '.css':'text/css; charset=utf-8','.json':'application/json','.xml':'application/xml','.svg':'image/svg+xml',
  '.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.gif':'image/gif','.webp':'image/webp',
  '.mp3':'audio/mpeg','.mp4':'video/mp4','.webm':'video/webm','.woff':'font/woff','.woff2':'font/woff2','.ttf':'font/ttf'
};
const json = (data, status=200) => Response.json(data, { status, headers: { 'cache-control':'no-store' } });
const safePath = p => typeof p === 'string' && p.length < 800 && !p.startsWith('/') && !p.includes('\\') && p.split('/').every(part => part && part !== '.' && part !== '..' && !part.includes('\0'));
async function catalog(env) {
  const obj = await env.COURSES.get(CATALOG_KEY);
  if (!obj) return [];
  try { return await obj.json(); } catch { return []; }
}
async function saveCatalog(env, rows) {
  await env.COURSES.put(CATALOG_KEY, JSON.stringify(rows), { httpMetadata: { contentType:'application/json; charset=utf-8' } });
}
const authorized = (request, env) => Boolean(env.UPLOAD_TOKEN) && request.headers.get('x-upload-token') === env.UPLOAD_TOKEN;
function page(course) {
  const title = escapeHtml(course.title);
  const api = `<script>(function(){const k='northstar-scorm-${course.id}-',get=x=>localStorage.getItem(k+x)||'',set=(x,v)=>(localStorage.setItem(k+x,String(v)),'true');window.API={LMSInitialize:()=> 'true',LMSFinish:()=> 'true',LMSCommit:()=> 'true',LMSGetValue:get,LMSSetValue:set,LMSGetLastError:()=> '0',LMSGetErrorString:()=> 'No error',LMSGetDiagnostic:()=> ''};window.API_1484_11={Initialize:()=> 'true',Terminate:()=> 'true',Commit:()=> 'true',GetValue:get,SetValue:set,GetLastError:()=> '0',GetErrorString:()=> 'No error',GetDiagnostic:()=> ''};})();</script>`;
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>${title}</title><style>*{box-sizing:border-box}body{margin:0;font:14px system-ui;color:#202521;background:#f7f8f6}.bar{height:58px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #e9ece8}.bar b{font-size:15px}.bar button{padding:9px 13px;border:1px solid #e0e5e0;border-radius:8px;background:#fff;cursor:pointer}iframe{width:100%;height:calc(100vh - 58px);border:0;background:#fff}</style>${api}</head><body><header class="bar"><b>${title}</b><button onclick="history.back()">← Back</button></header><iframe allow="fullscreen" src="/content/${course.id}/${encodeURI(course.launchPath).replaceAll('#','%23').replaceAll('?','%3F')}"></iframe></body></html>`;
}
function escapeHtml(value) { return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url), path = url.pathname;
    try {
      if (path === '/api/courses' && request.method === 'GET') {
        return json([{ id:MVP_COURSE_ID,title:'Կայուն զարգացում',desc:'SCORM դասընթաց · Articulate 360',category:'SCORM ԴԱՍԸՆԹԱՑ',duration:'Ինքնուրույն ուսուցում',done:0,color:'teal' }]);
      }
      if (path === '/api/scorm/qa-assistant' && request.method === 'POST') {
        if (!aiAllowed(request)) return json({error:'Հարցումների սահմանաչափը լրացել է։ Սպասեք մեկ րոպե և փորձեք կրկին։'},429);
        let body; try { const raw=await request.text(); if(raw.length>12_000) return json({error:'Հարցը չափազանց երկար է։'},413); body=JSON.parse(raw); } catch { return json({error:'Հարցման տվյալները սխալ են։'},400); }
        const question=String(body.student_question||'').trim().slice(0,1200);
        if (!question) return json({error:'Գրեք ձեր հարցը։'},400);
        if (!env.OPENAI_API_KEY) return json({error:'AI օգնականը դեռ միացված չէ։ Կարգավորեք OPENAI_API_KEY-ը Cloudflare-ում։'},503);
        const section=String(body.active_section||'slides').slice(0,40);
        try {
          const result=await openAiJson(env,AI_SCHEMAS.qa,'Դու հայերեն ուսումնական զրուցակից և մտածողության խթանող ես։ Պատասխանիր բնական, իմաստալից և բավարար խորությամբ. մի սահմանափակվիր նախապես գրված տարբերակներով կամ կարճ բառարանային սահմանումներով։ Հարցի էությունը պարզելուց հետո զարգացրու պատասխանը՝ անհրաժեշտության դեպքում կապելով սոցիալական, տնտեսական և բնապահպանական կողմերը, ներկայացնելով պատճառահետևանքային կապեր, տարբեր տեսակետներ, փոխզիջումներ կամ առօրյա/համայնքային օրինակներ։ Կարող ես քննարկել կայուն զարգացման թեմային առնչվող ավելի լայն գաղափարներ՝ դասանյութից դուրս ընդհանուր գիտելիքի հիման վրա. հստակ տարբերակիր դասանյութում նշվածը քո ընդհանուր բացատրությունից։ Օգտագործիր դասանյութը որպես ելակետ և մի պնդիր, թե այն ասում է բան, որը այնտեղ չկա։ Եթե հարցը թեմային առնչվող մտահղացում, քննադատություն կամ կիրառություն է, զարգացրու այն՝ ոչ թե մերժիր որպես դասընթացից դուրս։ Ճշգրիտ ընթացիկ վիճակագրություն կամ հղում մի հորինիր։ Վերջում կարող ես տալ առավելագույնը երկու իրական, բաց հարց՝ որպես կամընտիր շարունակություն, ոչ թե որպես պատասխանի փոխարինում։ Պատասխանիր հայերեն։ Վերադարձիր միայն պահանջվող JSON-ը։',JSON.stringify({course_material:COURSE_CONTEXT,active_section:section,student_question:question}));
          return json(result);
        } catch (error) { console.error('AI Q&A error:',error.code||error.message); if(error.code==='insufficient_quota') return json({error:'OpenAI API-ի օգտագործման հասանելի վարկը սպառվել է կամ API հաշվարկային կարգավորումը դեռ ակտիվ չէ։ Ստուգեք API billing-ը։',code:'insufficient_quota'},503); if(error.code==='invalid_api_key') return json({error:'OpenAI API բանալին անվավեր է։ Ստեղծեք նոր բանալի և պահեք այն որպես Cloudflare Secret։',code:'invalid_api_key'},503); return json({error:'AI ծառայությունը ժամանակավորապես անհասանելի է։ Փորձեք կրկին։'},502); }
      }
      if (path === '/api/scorm/evaluate-open-ended' && request.method === 'POST') {
        if (!aiAllowed(request)) return json({error:'Հարցումների սահմանաչափը լրացել է։ Սպասեք մեկ րոպե և փորձեք կրկին։'},429);
        let body; try { const raw=await request.text(); if(raw.length>20_000) return json({error:'Պատասխանը չափազանց երկար է։'},413); body=JSON.parse(raw); } catch { return json({error:'Պատասխանի տվյալները սխալ են։'},400); }
        const answer=String(body.student_response||'').trim().slice(0,6000);
        if (answer.length<40) return json({error:'Գրեք առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։'},400);
        if (!env.OPENAI_API_KEY) return json({error:'AI գնահատումը դեռ միացված չէ։ Կարգավորեք OPENAI_API_KEY-ը Cloudflare-ում։'},503);
        const rubric=[{criterion_id:'understanding',max_points:30,description:'Թեմայի ճիշտ ըմբռնումը և հիմնական հասկացությունների օգտագործումը։'},{criterion_id:'connections',max_points:25,description:'Սոցիալական, տնտեսական և բնապահպանական չափումների կապը։'},{criterion_id:'example_measure',max_points:25,description:'Հիմնավորված օրինակ և չափելի տվյալ կամ ինդիկատոր։'},{criterion_id:'completeness',max_points:20,description:'Հարցի բոլոր մասերին հստակ և ամբողջական պատասխան։'}];
        try {
          const result=await openAiJson(env,AI_SCHEMAS.evaluation,'Դու հայերեն ակադեմիական գնահատող ես։ Գնահատիր միայն սովորողի պատասխանում առկա ապացույցները՝ տրված դասանյութի և չափանիշների համեմատ։ Յուրաքանչյուր միավոր չի կարող գերազանցել չափանիշի առավելագույնը։ Ընդհանուր միավորը չափանիշների միավորների գումարն է՝ առավելագույնը 100։ Անավարտ, թեմայից շեղված կամ հիմնական պահանջվող տարրերից զուրկ պատասխանը պետք է ստանա 60-ից ցածր միավոր։ Անբավարար՝ 0–59, Զարգացող՝ 60–74, Իմացող / Լիարժեք՝ 75–89, Օրինակելի՝ 90–100։ Տուր կարճ հայերեն հիմնավորում յուրաքանչյուր չափանիշի համար։ Մի հորինիր սովորողի մտադրությունը։ Վերադարձիր միայն պահանջվող JSON-ը։',JSON.stringify({course_material:COURSE_CONTEXT,question_prompt:'Ինչպե՞ս կարող են կայուն զարգացման ինդիկատորներն ու համաթվերը օգնել հասկանալ՝ արդյոք երկրի կամ համայնքի զարգացումը բարելավում է մարդկանց կյանքը՝ պահպանելով տնտեսության ու շրջակա միջավայրի հավասարակշռությունը։ Բերեք մեկ օրինակ և նշեք՝ ինչ տվյալներով կչափեիք առաջընթացը։',rubric,student_response:answer}));
          result.max_total_score=100;
          for (const row of result.criterion_breakdown||[]) { const item=rubric.find(x=>x.criterion_id===row.criterion_id); if(item){row.max_points=item.max_points;row.points_earned=Math.max(0,Math.min(item.max_points,Number(row.points_earned)||0));} }
          result.total_score_awarded=(result.criterion_breakdown||[]).reduce((sum,row)=>sum+row.points_earned,0);
          return json(result);
        } catch (error) { console.error('AI evaluation error:',error.code||error.message); if(error.code==='insufficient_quota') return json({error:'OpenAI API-ի օգտագործման հասանելի վարկը սպառվել է կամ API հաշվարկային կարգավորումը դեռ ակտիվ չէ։ Ստուգեք API billing-ը։',code:'insufficient_quota'},503); if(error.code==='invalid_api_key') return json({error:'OpenAI API բանալին անվավեր է։ Ստեղծեք նոր բանալի և պահեք այն որպես Cloudflare Secret։',code:'invalid_api_key'},503); return json({error:'AI գնահատումը ժամանակավորապես անհասանելի է։ Փորձեք կրկին։'},502); }
      }
      if (path === '/api/courses' && request.method === 'POST') {
        if (!authorized(request, env)) return json({error:'Upload password is missing or incorrect.'},401);
        const body = await request.json();
        if (!body.title?.trim() || !safePath(body.launchPath)) return json({error:'Course title or launch path is invalid.'},400);
        const id = crypto.randomUUID().replaceAll('-','').slice(0,20), rows = await catalog(env);
        const course = {id,title:body.title.trim().slice(0,160),desc:'SCORM course package · Articulate 360',category:'SCORM COURSE',launchPath:body.launchPath,created:Date.now()};
        await env.COURSES.put(`meta/${id}.json`,JSON.stringify(course),{httpMetadata:{contentType:'application/json; charset=utf-8'}});
        rows.unshift(course); await saveCatalog(env,rows);
        return json({id,url:`/course/${id}`},201);
      }
      const uploadMatch = path.match(/^\/api\/courses\/([a-f0-9]+)\/files$/);
      if (uploadMatch && request.method === 'POST') {
        if (!authorized(request, env)) return json({error:'Upload password is missing or incorrect.'},401);
        const id = uploadMatch[1], courseObj = await env.COURSES.get(`meta/${id}.json`);
        if (!courseObj) return json({error:'Course not found.'},404);
        const paths = JSON.parse(decodeURIComponent(request.headers.get('x-file-paths') || '[]'));
        if (!Array.isArray(paths) || paths.length < 1 || paths.length > 30 || paths.some(p => !safePath(p))) return json({error:'Invalid package file list.'},400);
        const form = await request.formData(), files = [...form.values()].filter(v => v instanceof File);
        if (files.length !== paths.length) return json({error:'Package file list does not match upload.'},400);
        await Promise.all(files.map((f,i) => env.COURSES.put(`courses/${id}/${paths[i]}`,f.stream(),{httpMetadata:{contentType:f.type || mimeType(paths[i])}})));
        return json({uploaded:files.length});
      }
      const courseMatch = path.match(/^\/course\/([a-f0-9]+)$/);
      if (courseMatch && request.method === 'GET') {
        const obj = await env.COURSES.get(`meta/${courseMatch[1]}.json`);
        if (!obj) return new Response('Course not found',{status:404});
        return new Response(page(await obj.json()),{headers:{'content-type':'text/html; charset=utf-8','cache-control':'no-store','x-content-type-options':'nosniff'}});
      }
      const contentMatch = path.match(/^\/content\/([a-f0-9]+)\/(.+)$/);
      if (contentMatch && (request.method === 'GET' || request.method === 'HEAD')) {
        const [,id,asset] = contentMatch, decoded = decodeURIComponent(asset);
        if (!safePath(decoded)) return new Response('Not found',{status:404});
        const key = `courses/${id}/${decoded}`;
        // Storyline's HTML5 video player seeks by issuing byte-range requests.
        // Passing the request headers lets R2 return just the requested segment.
        const obj = request.method === 'HEAD'
          ? await env.COURSES.head(key)
          : await env.COURSES.get(key, { range: request.headers });
        if (!obj) return new Response('Not found',{status:404});
        const headers = new Headers({'content-type':obj.httpMetadata?.contentType || mimeType(decoded),'cache-control':'public, max-age=3600','x-content-type-options':'nosniff','accept-ranges':'bytes'});
        obj.writeHttpMetadata(headers);
        headers.set('accept-ranges','bytes');
        if (obj.range) {
          const {offset,length} = obj.range;
          headers.set('content-range',`bytes ${offset}-${offset + length - 1}/${obj.size}`);
          headers.set('content-length',String(length));
          return new Response(request.method === 'HEAD' ? null : obj.body,{status:request.method === 'HEAD' ? 200 : 206,headers});
        }
        headers.set('content-length',String(obj.size));
        return new Response(request.method === 'HEAD' ? null : obj.body,{headers});
      }
      return env.ASSETS.fetch(request);
    } catch (e) { return json({error:e.message || 'Request failed.'},500); }
  }
};
function mimeType(path) { const ext = path.slice(path.lastIndexOf('.')).toLowerCase(); return MIME[ext] || 'application/octet-stream'; }
