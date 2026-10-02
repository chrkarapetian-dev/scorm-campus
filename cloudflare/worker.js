const CATALOG_KEY = 'meta/catalog.json';
const MVP_COURSE_ID = '2c4eb6499843c263';
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
