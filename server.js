const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const crypto = require('node:crypto');
const learningCourse = require('./course-data.js');

const ROOT = __dirname;
const DATA = path.resolve(process.env.DATA_DIR || path.join(ROOT, 'data'));
const COURSES = path.join(DATA, 'courses');
const META = path.join(DATA, 'courses.json');
const PORT = Number(process.env.PORT || 3000);
const MAX_UPLOAD = 400 * 1024 * 1024;
const MIME = { '.html':'text/html; charset=utf-8','.htm':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.json':'application/json; charset=utf-8','.xml':'application/xml; charset=utf-8','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.gif':'image/gif','.webp':'image/webp','.mp3':'audio/mpeg','.mp4':'video/mp4','.webm':'video/webm','.woff':'font/woff','.woff2':'font/woff2','.ttf':'font/ttf' };
const AI_SCHEMAS = {
  qa: { type:'json_schema', name:'armenian_course_answer', strict:true, schema:{ type:'object', additionalProperties:false, required:['answer_armenian','citations','suggested_followups_armenian'], properties:{ answer_armenian:{type:'string'}, citations:{type:'array',items:{type:'object',additionalProperties:false,required:['source_type','reference_label'],properties:{source_type:{type:'string',enum:['slide','video','literature']},reference_label:{type:'string'}}}}, suggested_followups_armenian:{type:'array',items:{type:'string'}} } } },
  evaluation: { type:'json_schema', name:'armenian_rubric_evaluation', strict:true, schema:{ type:'object', additionalProperties:false, required:['total_score_awarded','max_total_score','summary_feedback_armenian','criterion_breakdown','actionable_tips_armenian'], properties:{ total_score_awarded:{type:'number'}, max_total_score:{type:'number'}, summary_feedback_armenian:{type:'string'}, actionable_tips_armenian:{type:'string'}, criterion_breakdown:{type:'array',items:{type:'object',additionalProperties:false,required:['criterion_id','points_earned','max_points','reasoning_armenian'],properties:{criterion_id:{type:'string'},points_earned:{type:'number'},max_points:{type:'number'},reasoning_armenian:{type:'string'}}}} } } }
};
fs.mkdirSync(COURSES, { recursive: true });

// Optional local settings. The API key stays on the server and is never served to the browser.
const envFile = path.join(ROOT, '.env.local');
if (fs.existsSync(envFile)) for (const line of fs.readFileSync(envFile, 'utf8').split(/\r?\n/)) {
  const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
  if (match && !process.env[match[1]]) process.env[match[1]] = match[2].replace(/^(["'])(.*)\1$/, '$2');
}

const readCourses = () => { try { return JSON.parse(fs.readFileSync(META, 'utf8')); } catch { return []; } };
const writeCourses = rows => fs.writeFileSync(META, JSON.stringify(rows, null, 2));
const json = (res, data, status = 200) => { res.writeHead(status, { 'content-type':'application/json; charset=utf-8', 'cache-control':'no-store' }); res.end(JSON.stringify(data)); };
const html = (res, body, status = 200) => { res.writeHead(status, { 'content-type':'text/html; charset=utf-8', 'x-content-type-options':'nosniff', 'cache-control':'no-store' }); res.end(body); };
const notFound = res => { res.writeHead(404, { 'content-type':'text/plain; charset=utf-8' }); res.end('Չի գտնվել։'); };
const escapeHtml = value => String(value).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
const safeZipPath = value => typeof value === 'string' && value.length < 800 && !value.startsWith('/') && !value.startsWith('\\') && !value.includes('\\') && !value.includes('\0') && !/^[a-z]:/i.test(value) && value.split('/').every(part => part && part !== '.' && part !== '..');
const safeCourseId = value => /^[a-f0-9]{16}$/.test(value);

function serveFile(req, res, file) {
  let stat;
  try { stat = fs.statSync(file); } catch { return notFound(res); }
  if (!stat.isFile()) return notFound(res);
  const headers = {
    'content-type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream',
    'x-content-type-options': 'nosniff',
    'accept-ranges': 'bytes',
    'cache-control': 'no-store'
  };
  let start = 0, end = stat.size - 1, status = 200;
  const range = req.headers.range;
  if (range) {
    const match = /^bytes=(\d*)-(\d*)$/.exec(range.trim());
    if (!match || (!match[1] && !match[2]) || stat.size === 0) {
      res.writeHead(416, { ...headers, 'content-range': `bytes */${stat.size}`, 'content-length': '0' });
      return res.end();
    }
    if (!match[1]) {
      const suffixLength = Number(match[2]);
      if (!suffixLength) { res.writeHead(416, { ...headers, 'content-range': `bytes */${stat.size}`, 'content-length': '0' }); return res.end(); }
      start = Math.max(0, stat.size - suffixLength);
    } else {
      start = Number(match[1]);
      if (match[2]) end = Math.min(Number(match[2]), stat.size - 1);
    }
    if (start >= stat.size || end < start) {
      res.writeHead(416, { ...headers, 'content-range': `bytes */${stat.size}`, 'content-length': '0' });
      return res.end();
    }
    status = 206;
    headers['content-range'] = `bytes ${start}-${end}/${stat.size}`;
  }
  headers['content-length'] = String(end - start + 1);
  res.writeHead(status, headers);
  if (req.method === 'HEAD' || stat.size === 0) return res.end();
  fs.createReadStream(file, { start, end }).pipe(res);
}

async function readBody(req, limit) {
  const chunks = []; let size = 0;
  for await (const chunk of req) { size += chunk.length; if (size > limit) throw Object.assign(new Error('Փոխանցվող ֆայլը գերազանցում է թույլատրելի չափը։'), { status:413 }); chunks.push(chunk); }
  return Buffer.concat(chunks);
}

function parseMultipart(buffer, boundary) {
  const delimiter = Buffer.from(`--${boundary}`), separator = Buffer.from('\r\n\r\n'), nextPart = Buffer.from(`\r\n--${boundary}`);
  const fields = {}, files = []; let cursor = buffer.indexOf(delimiter);
  if (cursor < 0) throw new Error('Վերբեռնման ձևաչափը սխալ է։');
  cursor += delimiter.length;
  while (cursor < buffer.length) {
    if (buffer[cursor] === 45 && buffer[cursor + 1] === 45) break;
    if (buffer[cursor] === 13 && buffer[cursor + 1] === 10) cursor += 2;
    const headerEnd = buffer.indexOf(separator, cursor); if (headerEnd < 0) break;
    const headers = buffer.toString('latin1', cursor, headerEnd);
    const dataStart = headerEnd + separator.length, dataEnd = buffer.indexOf(nextPart, dataStart); if (dataEnd < 0) break;
    const disposition = headers.match(/content-disposition:\s*form-data;([^\r\n]+)/i)?.[1] || '';
    const name = disposition.match(/\bname="([^"]*)"/i)?.[1];
    const filename = disposition.match(/\bfilename="([^"]*)"/i)?.[1];
    const value = buffer.subarray(dataStart, dataEnd);
    if (name && filename !== undefined) files.push({ name, filename, data: value });
    else if (name) fields[name] = value.toString('utf8');
    cursor = dataEnd + 2 + delimiter.length;
  }
  return { fields, files };
}

function readZipEntries(zip) {
  const signature = Buffer.from([0x50,0x4b,0x05,0x06]);
  const min = Math.max(0, zip.length - 65_557), eocd = zip.lastIndexOf(signature);
  if (eocd < min || eocd < 0 || eocd + 22 > zip.length) throw new Error('ZIP փաթեթի կառուցվածքը սխալ է։');
  const count = zip.readUInt16LE(eocd + 10), directorySize = zip.readUInt32LE(eocd + 12), directoryOffset = zip.readUInt32LE(eocd + 16);
  if (directoryOffset + directorySize > zip.length || count > 50_000) throw new Error('ZIP փաթեթի կառուցվածքը սխալ է։');
  const entries = []; let offset = directoryOffset, expandedBytes = 0;
  for (let i = 0; i < count; i++) {
    if (zip.readUInt32LE(offset) !== 0x02014b50) throw new Error('ZIP փաթեթի կենտրոնական աղյուսակը թերի է։');
    const flags = zip.readUInt16LE(offset + 8), method = zip.readUInt16LE(offset + 10), compressedSize = zip.readUInt32LE(offset + 20), size = zip.readUInt32LE(offset + 24);
    const nameLength = zip.readUInt16LE(offset + 28), extraLength = zip.readUInt16LE(offset + 30), commentLength = zip.readUInt16LE(offset + 32), localOffset = zip.readUInt32LE(offset + 42);
    const name = zip.toString((flags & 0x0800) ? 'utf8' : 'utf8', offset + 46, offset + 46 + nameLength).replaceAll('\\', '/');
    offset += 46 + nameLength + extraLength + commentLength;
    if (flags & 1) throw new Error('Գաղտնաբառով պաշտպանված ZIP ֆայլը չի աջակցվում։');
    if (name.endsWith('/')) { entries.push({ name, directory:true }); continue; }
    if (!safeZipPath(name)) throw new Error('ZIP փաթեթը պարունակում է անվավեր ֆայլի ուղի։');
    expandedBytes += size; if (expandedBytes > 512 * 1024 * 1024) throw new Error('ZIP փաթեթի բացված չափը չափազանց մեծ է։');
    if (zip.readUInt32LE(localOffset) !== 0x04034b50) throw new Error('ZIP փաթեթի ֆայլային վերնագիրը սխալ է։');
    const localNameLength = zip.readUInt16LE(localOffset + 26), localExtraLength = zip.readUInt16LE(localOffset + 28), dataOffset = localOffset + 30 + localNameLength + localExtraLength;
    const compressed = zip.subarray(dataOffset, dataOffset + compressedSize);
    let data;
    if (method === 0) data = Buffer.from(compressed);
    else if (method === 8) data = zlib.inflateRawSync(compressed, { maxOutputLength: 512 * 1024 * 1024 });
    else throw new Error('Այս ZIP սեղմման եղանակը չի աջակցվում։');
    if (data.length !== size) throw new Error('ZIP ֆայլի բացված չափը չի համընկնում։');
    entries.push({ name, data });
  }
  return entries;
}

function launchFromManifest(entries) {
  const manifest = entries.find(entry => !entry.directory && /(^|\/)imsmanifest\.xml$/i.test(entry.name));
  if (!manifest) throw new Error('Չգտնվեց imsmanifest.xml ֆայլը։ Արտահանեք SCORM 1.2 կամ SCORM 2004 փաթեթ Articulate-ից։');
  const xml = manifest.data.toString('utf8'), manifestDir = path.posix.dirname(manifest.name);
  const itemId = xml.match(/<item\b[^>]*\bidentifierref=["']([^"']+)["'][^>]*>/i)?.[1];
  const resources = [...xml.matchAll(/<resource\b([^>]*)>/gi)].map(match => match[1]);
  const resource = resources.find(attrs => itemId && attrs.match(/\bidentifier=["']([^"']+)["']/i)?.[1] === itemId) || resources.find(attrs => /scormtype=["']sco["']/i.test(attrs)) || resources[0];
  let launch = resource?.match(/\bhref=["']([^"']+)["']/i)?.[1] || entries.find(entry => /(^|\/)(story\.html|index_lms\.html)$/i.test(entry.name))?.name;
  launch = launch ? path.posix.normalize(path.posix.join(manifestDir === '.' ? '' : manifestDir, launch)) : '';
  // Articulate's launcher.html opens index_lms.html in a popup. Popups launched
  // automatically from the embedded course frame are blocked by browsers, so
  // launch the LMS player directly when the package includes it.
  if (/(^|\/)launcher\.html$/i.test(launch)) {
    const directPlayer = path.posix.join(path.posix.dirname(launch), 'index_lms.html');
    if (entries.some(entry => entry.name === directPlayer)) launch = directPlayer;
  }
  if (!launch || !safeZipPath(launch) || !entries.some(entry => entry.name === launch)) throw new Error('imsmanifest.xml-ում նշված դասընթացի գործարկման էջը չի գտնվել։');
  return launch;
}

function saveZipCourse(file, title) {
  const entries = readZipEntries(file.data), launch = launchFromManifest(entries), id = crypto.randomBytes(8).toString('hex'), dest = path.join(COURSES, id);
  fs.mkdirSync(dest, { recursive:true });
  try {
    for (const entry of entries) {
      if (!safeZipPath(entry.name)) { if (entry.directory) continue; throw new Error('ZIP փաթեթը պարունակում է անվավեր ֆայլի ուղի։'); }
      const target = path.resolve(dest, ...entry.name.split('/'));
      if (!target.startsWith(dest + path.sep) && target !== dest) throw new Error('ZIP փաթեթը պարունակում է անվավեր ֆայլի ուղի։');
      if (entry.directory) fs.mkdirSync(target, { recursive:true });
      else { fs.mkdirSync(path.dirname(target), { recursive:true }); fs.writeFileSync(target, entry.data); }
    }
  } catch (error) { fs.rmSync(dest, { recursive:true, force:true }); throw error; }
  const baseName = path.basename(file.filename || 'Դասընթաց', path.extname(file.filename || ''));
  const course = { id, title: String(title || '').trim().slice(0,160) || baseName, description:'SCORM դասընթաց · Articulate 360', category:'SCORM ԴԱՍԸՆԹԱՑ', launch, created:Date.now() };
  const courses = readCourses(); courses.unshift(course); writeCourses(courses); return course;
}

const aiWindows = new Map();
function rateAllowed(req) {
  const key = req.socket.remoteAddress || 'local', now = Date.now(), old = aiWindows.get(key);
  if (!old || now - old.start >= 60_000) aiWindows.set(key, { start:now, count:1 });
  else if (old.count >= 20) return false;
  else old.count++;
  if (aiWindows.size > 1000) for (const [ip,item] of aiWindows) if (now - item.start > 120_000) aiWindows.delete(ip);
  return true;
}

async function aiResponse(payload) {
  const response = await fetch('https://api.openai.com/v1/responses', { method:'POST', headers:{ authorization:`Bearer ${process.env.OPENAI_API_KEY}`, 'content-type':'application/json' }, body:JSON.stringify({ ...payload, store:false, max_output_tokens:700 }), signal:AbortSignal.timeout(45_000) });
  const data = await response.json(); if (!response.ok) throw new Error(data.error?.message || `API ${response.status}`);
  const outputText = (data.output || []).flatMap(item => item.content || []).find(item => item.type === 'output_text')?.text;
  if (!outputText) throw new Error('The AI service returned no text.');
  return JSON.parse(outputText);
}
const stripHtml = value => String(value || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g,' ').replace(/&amp;/g,'&').replace(/\s+/g,' ').trim();

function localAnswer(question, active) {
  const q = question.toLocaleLowerCase('hy');
  const inScope = /կայուն|զարգաց|ինդիկատ|ցուցանիշ|համաթիվ|ինդեքս|մզհ|կզհ|կրթ|եկամուտ|հնա|հաե|աղքատ|սով|գենդեր|կլիմա|ջուր|էներգիա|քաղաք|գործընկեր|նպատակ|բնապահպան|սոցիալ|տնտես|էկոլոգ|co2|hdi|sdg|index|indicator|sustainable development|poverty|climate|education|income|gender|water|energy|goal|why|how|explain/i.test(q);
  const decline = { answer_armenian:'Ես նախագծված եմ օգնելու միայն այս դասընթացի շրջանակներում։ Խնդրում եմ հարցեր ուղղել կայուն զարգացման դասի նյութի վերաբերյալ։', citations:[], suggested_followups_armenian:['Ի՞նչ է կայուն զարգացման ինդիկատորը։','Ի՞նչ է չափում մարդկային զարգացման համաթիվը։'] };
  if (!inScope) return decline;
  let answer, citations;
  if (/ինդիկատոր|ցուցանիշ|indicator|չափելի|չափել/.test(q)) { answer='Ըստ դասընթացի՝ կայուն զարգացման ինդիկատորը չափելի արժեք է, որը նկարագրում է տնտեսական, սոցիալական կամ էկոլոգիական ոլորտի վիճակը կամ փոփոխությունը։ Այն բարդ երևույթը դարձնում է համեմատելի և օգնում է հիմնավորել որոշումները։'; citations=[{source_type:'slide',reference_label:'Սլայդներ 3–4'},{source_type:'literature',reference_label:'Թեմա 8 ընթերցանության նյութ'}]; }
  else if (/համաթիվ|ինդեքս|մզհ|կզհ|hdi|հնա|հաե|եկամուտ|index/.test(q)) { answer='Ըստ դասընթացի՝ Մարդկային զարգացման համաթիվը համադրում է կյանքի սպասվող տևողության, կրթության և մեկ անձի հաշվով համախառն ազգային եկամտի չափումները։ Կայուն զարգացման համաթիվը լրացնում է պատկերը էկոլոգիական չափումներով, այդ թվում՝ մեկ շնչի հաշվով CO₂ արտանետումներով և նյութական ոտնահետքով։'; citations=[{source_type:'slide',reference_label:'Սլայդներ 5–7 և 10–11'},{source_type:'literature',reference_label:'Թեմա 8 ընթերցանության նյութ'}]; }
  else if (/նպատակ|goal|աղքատ|սով|գենդեր|կլիմա|ջուր|էներգիա|քաղաք|գործընկեր|poverty|climate|water|energy/.test(q)) {
    const numeric=q.match(/(?:նպատակ|goal|sdg|թիվ)\D{0,8}(1[0-7]|[1-9])|(?:^|\s)(1[0-7]|[1-9])(?:\s|$)/), n=numeric?.slice(1).find(Boolean);
    const goal=learningCourse.goals.find(g=>(n&&g.n===Number(n))||q.includes(g.title.toLocaleLowerCase('hy').split(' ')[0]));
    answer=goal?`Ըստ դասընթացի՝ ${goal.n}-րդ նպատակը՝ «${goal.title}», ուղղված է հետևյալ խնդրին․ ${goal.short} Նպատակները փոխկապակցված են, ուստի դրանց առաջընթացը դիտարկվում է հարակից պայմանների հետ։`:'Ըստ դասընթացի՝ ՄԱԿ-ի 17 նպատակները ներառում են աղքատության նվազեցում, առողջություն, կրթություն, հավասարություն, շրջակա միջավայր և համագործակցություն։ Դրանք փոխկապակցված են։'; citations=[{source_type:'slide',reference_label:'Սլայդներ 13–32'},{source_type:'literature',reference_label:'Թեմա 8 ընթերցանության նյութ'}];
  } else { answer=`Ըստ այս բաժնի՝ ${active.title.toLocaleLowerCase('hy')} թեման կապում է կայուն զարգացման չափումները հասարակության և շրջակա միջավայրի խնդիրների հետ։ ${active.lead}`; citations=[{source_type:active.id==='video'?'video':'literature',reference_label:active.label}]; }
  return { answer_armenian:answer, citations, suggested_followups_armenian:['Ինչո՞վ են տարբերվում ինդիկատորն ու համաթիվը։','Ինչպե՞ս կարող է համայնքը ընտրել օգտակար ինդիկատոր։'] };
}

function localEvaluation(answer) {
  const s=answer.toLocaleLowerCase('hy');
  const relevant=/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|համաթվ|նպատակ|տնտես|շրջակա միջավայր/.test(s);
  const dimensions=[/սոցիալ|հասարակ|առողջ|կրթ|հավասար/.test(s),/տնտես|եկամուտ|աշխատ|աղքատ/.test(s),/բնապահպան|էկոլոգ|շրջակա միջավայր|ռեսուրս|կլիմա/.test(s)].filter(Boolean).length;
  const example=/օրինակ|համայնք|քաղաք|գյուղ|ջուր|էներգիա|տրանսպորտ|աղտոտ|թափոն|գործազրկ/.test(s), measurable=/ինդիկատոր|ցուցանիշ|համաթիվ|տոկոս|քանակ|չափել|հետևել|տվյալ|մակարդակ|բաժին/.test(s);
  const criteria=[
    {id:'understanding',title:'Թեմայի ըմբռնում',max:30,points:relevant?Math.min(30,18+Math.min(12,(s.match(/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|նպատակ/g)||[]).length*3)):0,full:'Թեմայի հիմնական գաղափարները ճիշտ են կիրառված։',partial:'Թեմային առնչվող միտք կա, բայց հիմնական հասկացությունները կարելի է ավելի հստակ բացատրել։',missing:'Բացակայում է կայուն զարգացման թեմայի հստակ բացատրությունը։'},
    {id:'connections',title:'Կայունության չափումների կապ',max:25,points:dimensions===3?25:dimensions===2?19:dimensions===1?10:0,full:'Սոցիալական, տնտեսական և բնապահպանական կողմերի կապը հստակ է։',partial:'Նշված է չափումներից մի քանիսը, սակայն դրանց կապը լիարժեք բացատրված չէ։',missing:'Բացատրեք, թե ինչպես են սոցիալական, տնտեսական կամ բնապահպանական կողմերը կապվում թեմային։'},
    {id:'example_measure',title:'Օրինակ և չափում',max:25,points:example&&measurable?25:example||measurable?13:0,full:'Բերված է համապատասխան օրինակ և դրա առաջընթացը չափելու եղանակ։',partial:'Օրինակը կամ չափման եղանակը կա, բայց երկուսն էլ միասին հստակ չեն։',missing:'Բերեք կոնկրետ օրինակ և առաջարկեք չափելի տվյալ կամ ինդիկատոր։'},
    {id:'completeness',title:'Ամբողջականություն և հստակություն',max:20,points:Math.min(20,(s.length>=450?12:s.length>=250?9:s.length>=120?6:s.length>=40?3:0)+(/քանի որ|հետևաբար|այսպիսով|որովհետև|կապված|ազդում/.test(s)?8:0)),full:'Պատասխանը ամբողջական է, հստակ և հիմնավորում է գաղափարների կապը։',partial:'Պատասխանը հասկանալի է, բայց հիմնավորման կամ ամբողջականության մի մասը պակասում է։',missing:'Պատասխանը թերի է կամ դժվար է հասկանալ։'}
  ];
  const criterion_breakdown=criteria.map(c=>({criterion_id:c.id,title_armenian:c.title,points_earned:c.points,max_points:c.max,reasoning_armenian:c.points/c.max>=.8?c.full:c.points/c.max>=.4?c.partial:c.missing})),total_score_awarded=criterion_breakdown.reduce((n,x)=>n+x.points_earned,0),grade=total_score_awarded>=90?'Օրինակելի':total_score_awarded>=75?'Իմացող / Լիարժեք':total_score_awarded>=60?'Զարգացող':'Անբավարար';
  return {total_score_awarded,max_total_score:100,grade_level:grade,summary_feedback_armenian:total_score_awarded<60?'Սպասելիքներից ցածր է․ պատասխանը թերի է, շեղված թեմայից կամ անճիշտ, կամ բացակայում են պահանջվող հիմնական տարրերը։':total_score_awarded<75?'Պատասխանը մասամբ է բացահայտում թեման։ Ավելացրեք գաղափարների կապը, օրինակն ու չափելի հիմնավորումը։':total_score_awarded<90?'Պատասխանը լավ է ներկայացնում հիմնական գաղափարները, սակայն որոշ կապեր կամ հիմնավորումներ կարելի է լրացնել։':'Պատասխանը ամբողջական ու հիմնավորված է և հստակ կապում է կայուն զարգացումը չափելի առաջընթացի հետ։',criterion_breakdown,actionable_tips_armenian:total_score_awarded<60?'Վերանայեք թեմայի հիմնական գաղափարները, պատասխանեք հարցի բոլոր մասերին և բերեք համապատասխան օրինակ ու չափելի տվյալ։':'Ուժեղացրեք հիմնավորումը՝ բացատրելով, թե առաջարկված տվյալը ինչպես է ցույց տալիս կայուն զարգացման առաջընթացը։'};
}

async function handle(req, res) {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`), route = url.pathname;
  if (req.method === 'GET' && route === '/api/learning-course') return json(res,{id:learningCourse.id,title:learningCourse.title,lessons:learningCourse.lessons.map(({id,kind,title,label,lead,duration})=>({id,kind,title,label,lead,duration})),goals:learningCourse.goals});
  if (req.method === 'GET' && route === '/api/courses') return json(res,readCourses().map(({id,title,description,category,created})=>({id,title,desc:description,category,duration:'Ինքնուրույն ուսուցում',done:0,color:'teal',author:new Date(created).toLocaleDateString('hy-AM'),created})));
  if (req.method === 'POST' && route === '/api/recommendations') {
    let progress={}; try { progress=JSON.parse((await readBody(req,100_000)).toString('utf8')).progress || {}; } catch {}
    const completed=Array.isArray(progress.completed)?progress.completed:[], seconds=progress.seconds||{}, total=Object.values(seconds).reduce((sum,value)=>sum+(Number(value)||0),0);
    const item={course_id:learningCourse.id,title:learningCourse.title,ui_synopsis_armenian:'Ինդիկատորների, համաթվերի և ՄԱԿ-ի 17 նպատակների ներածական դասընթաց։',manifest_path:'/',badge_text_armenian:completed.length?'Շարունակել':'Նոր դասընթաց',progress_percentage:completed.length===learningCourse.lessons.length?100:Math.min(99,Math.round(total/2400*100)),journey_summary_armenian:{total_time_formatted:total?`${Math.floor(total/60)}ր`:'Նոր դասընթաց',video_time_seconds:Number(seconds.video)||0,slides_time_seconds:Number(seconds.slides)||0,literature_time_seconds:Number(seconds.literature)||0,quiz_accuracy_percentage:progress.quiz?.total?Math.round(progress.quiz.correct/progress.quiz.total*100):0}};
    const review=Boolean(progress.quiz&&progress.quiz.correct<progress.quiz.total)||!completed.includes('reflection');
    return json(res,{curated_rows:[{row_id:'continue',category_title_armenian:'Շարունակել ուսումնառությունը',items:completed.length<learningCourse.lessons.length?[item]:[]},{row_id:'review',category_title_armenian:'Կրկնության և պրակտիկայի կարիք ունեցող',items:review?[{...item,badge_text_armenian:'Կրկնել և ամրապնդել'}]:[]},{row_id:'all',category_title_armenian:'Բոլոր դասընթացները',items:[{...item,badge_text_armenian:'Հայերեն · 5 բաժին'}]}]});
  }
  if (req.method === 'POST' && (route === '/api/scorm/qa-assistant'||route === '/api/scorm/evaluate-open-ended')) {
    if (!rateAllowed(req)) return json(res,{error:'Հարցումների սահմանաչափը լրացել է։ Սպասեք մեկ րոպե և փորձեք կրկին։'},429);
    let body; try { body=JSON.parse((await readBody(req,100_000)).toString('utf8')); } catch { return json(res,{error:'Հարցման տվյալները սխալ են։'},400); }
    if (route.endsWith('qa-assistant')) {
      const question=String(body.student_question||'').trim().slice(0,1200); if(!question)return json(res,{error:'Գրեք ձեր հարցը։'},400);
      const active=learningCourse.lessons.find(x=>x.id===body.active_section)||learningCourse.lessons[1];
      if (!process.env.OPENAI_API_KEY) return json(res,localAnswer(question,active));
      const context=learningCourse.lessons.map(x=>`${x.label} — ${x.title}\n${x.lead}\n${stripHtml(x.html)}`).join('\n\n');
      try {
        const result = await aiResponse({ model:process.env.OPENAI_MODEL||'gpt-4o', instructions:'Դու հայերեն ուսումնական օգնական ես։ Պատասխանիր հստակ և խրախուսող տոնով։ Հիմնվիր տրամադրված դասանյութի վրա։ Դասընթացից դուրս հարցին քաղաքավարի մերժիր։ Մի հորինիր մեջբերումներ և մի ցուցադրիր թաքնված պատճառաբանություն։ Վերադարձիր միայն JSON։', input:JSON.stringify({active_section:active.label,course_material:context,student_question:question}), text:{format:AI_SCHEMAS.qa} });
        return json(res,result);
      }
      catch(error){console.error('AI Q&A error:',error.message);return json(res,{error:'AI ծառայությունը ժամանակավորապես անհասանելի է։ Ստուգեք API կարգավորումները։'},502);}
    }
    const answer=String(body.student_response||'').trim().slice(0,6000); if(answer.length<40)return json(res,{error:'Գրեք առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։'},400);
    if(!process.env.OPENAI_API_KEY)return json(res,localEvaluation(answer));
    const rubric=[{criterion_id:'understanding',title_armenian:'Թեմայի ըմբռնում',max_points:30,description:'Accurate understanding of sustainable development, indicators, indices, and balanced progress.'},{criterion_id:'connections',title_armenian:'Կայունության չափումների կապ',max_points:25,description:'Explains connections among social, economic, and environmental dimensions.'},{criterion_id:'example_measure',title_armenian:'Օրինակ և չափում',max_points:25,description:'Provides a relevant example and a measurable indicator or data to track progress.'},{criterion_id:'completeness',title_armenian:'Ամբողջականություն և հստակություն',max_points:20,description:'Answers every part of the prompt clearly, accurately, and with a reasoned explanation.'}];
    try {
      const result = await aiResponse({ model:process.env.OPENAI_MODEL||'gpt-4o', instructions:'Դու հայերեն ակադեմիական գնահատող ես։ Գնահատիր պատասխանը միայն տրված չափանիշներով և դրանում առկա ապացույցներով։ Յուրաքանչյուր չափանիշի միավորները չպետք է գերազանցեն դրա առավելագույնը։ Ընդհանուր միավորը չափանիշների միավորների գումարն է՝ 100-ից։ Անավարտ, թեմայից շեղված կամ հիմնական տարրերից զուրկ պատասխանը պետք է ստանա 60-ից ցածր միավոր։ Յուրաքանչյուր չափանիշի համար տուր կարճ, հասկանալի հիմնավորում։ Մի հորինիր սովորողի մտադրությունը։ Վերադարձիր միայն JSON։', input:JSON.stringify({question_prompt:'Ինչպե՞ս կարող են կայուն զարգացման ինդիկատորներն ու համաթվերը օգնել հասկանալ՝ արդյոք երկրի կամ համայնքի զարգացումը բարելավում է մարդկանց կյանքը՝ պահպանելով տնտեսության ու շրջակա միջավայրի հավասարակշռությունը։ Բերեք մեկ օրինակ և նշեք՝ ինչ տվյալներով կչափեիք առաջընթացը։',max_total_score:100,score_bands:[{name:'Օրինակելի',min:90,max:100},{name:'Իմացող / Լիարժեք',min:75,max:89},{name:'Զարգացող',min:60,max:74},{name:'Անբավարար',min:0,max:59}],rubric_criteria:rubric,student_response:answer}), text:{format:AI_SCHEMAS.evaluation} });
      return json(res,result);
    }
    catch(error){console.error('AI evaluation error:',error.message);return json(res,localEvaluation(answer));}
  }
  if (req.method === 'POST' && route === '/api/courses') {
    if(process.env.UPLOAD_TOKEN && req.headers['x-upload-token']!==process.env.UPLOAD_TOKEN)return json(res,{error:'Ներբեռնման գաղտնաբառը բացակայում է կամ սխալ է։'},401);
    const type=req.headers['content-type']||'', boundary=type.match(/boundary=(?:"([^"]+)"|([^;]+))/i); if(!boundary)return json(res,{error:'Ընտրեք SCORM ZIP փաթեթը։'},400);
    try { const body=await readBody(req,MAX_UPLOAD+1024*1024), parsed=parseMultipart(body,boundary[1]||boundary[2]), file=parsed.files.find(x=>x.name==='package'); if(!file)return json(res,{error:'Ընտրեք SCORM ZIP փաթեթը։'},400); if(!file.filename.toLowerCase().endsWith('.zip'))return json(res,{error:'Ընտրեք ZIP ձևաչափով SCORM փաթեթ։'},400); const item=saveZipCourse(file,parsed.fields.title); return json(res,{...item,url:`/course/${item.id}`},201); }
    catch(error){return json(res,{error:error.message||'SCORM փաթեթը հնարավոր չէ կարդալ։'},error.status||400);}
  }
  const courseMatch=route.match(/^\/course\/([a-f0-9]{16})$/);
  if(req.method==='GET'&&courseMatch){const item=readCourses().find(x=>x.id===courseMatch[1]);if(!item)return html(res,'Դասընթացը չի գտնվել։',404);const api=`<script>(function(){const prefix='usum-scorm-${item.id}-',get=k=>localStorage.getItem(prefix+k)||'',set=(k,v)=>(localStorage.setItem(prefix+k,String(v)),'true');window.API={LMSInitialize:()=> 'true',LMSFinish:()=> 'true',LMSCommit:()=> 'true',LMSGetValue:get,LMSSetValue:set,LMSGetLastError:()=> '0',LMSGetErrorString:()=> 'No error',LMSGetDiagnostic:()=> ''};window.API_1484_11={Initialize:()=> 'true',Terminate:()=> 'true',Commit:()=> 'true',GetValue:get,SetValue:set,GetLastError:()=> '0',GetErrorString:()=> 'No error',GetDiagnostic:()=> ''};})();<\/script>`;const launch=item.launch.split('/').map(encodeURIComponent).join('/');return html(res,`<!doctype html><html lang="hy"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>${escapeHtml(item.title)}</title><style>*{box-sizing:border-box}body{margin:0;font:14px system-ui,'Noto Sans Armenian',sans-serif;color:#f2f4ef;background:#101412}.bar{height:58px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:#151a17;border-bottom:1px solid #2a332d}.bar b{font-size:14px}.bar button{padding:9px 13px;border:1px solid #354037;border-radius:7px;background:#1b211d;color:#d6ded5;cursor:pointer}iframe{width:100%;height:calc(100vh - 58px);border:0;background:white}</style>${api}</head><body><header class="bar"><b>${escapeHtml(item.title)}</b><button onclick="history.back()">← Վերադառնալ</button></header><iframe allow="fullscreen" src="/content/${item.id}/${launch}"></iframe></body></html>`);}
  const contentMatch=route.match(/^\/content\/([a-f0-9]{16})\/(.+)$/);
  if((req.method==='GET'||req.method==='HEAD')&&contentMatch){let relative;try{relative=decodeURIComponent(contentMatch[2]);}catch{return notFound(res);}if(!safeZipPath(relative)||!readCourses().some(x=>x.id===contentMatch[1]))return notFound(res);const root=path.join(COURSES,contentMatch[1]),file=path.resolve(root,...relative.split('/'));if(!file.startsWith(root+path.sep))return notFound(res);return serveFile(req,res,file);}
  if(req.method==='GET'||req.method==='HEAD'){
    let relative;try{relative=decodeURIComponent(route==='/'?'/index.html':route).replace(/^\//,'');}catch{return notFound(res);}if(!relative)relative='index.html';if(!safeZipPath(relative))return notFound(res);const file=path.resolve(ROOT,...relative.split('/'));if(!file.startsWith(ROOT+path.sep))return notFound(res);return serveFile(req,res,file);
  }
  json(res,{error:'Այս հասցեն հասանելի չէ։'},404);
}

const server=http.createServer((req,res)=>{Promise.resolve(handle(req,res)).catch(error=>{console.error('Request failed:',error.message);if(!res.headersSent)json(res,{error:'Հարցումը հնարավոր չեղավ մշակել։'},500);else res.destroy();});});
server.listen(PORT,process.env.HOST || '127.0.0.1',()=>console.log(`Ուսում MVP-ն աշխատում է՝ http://${process.env.HOST || 'localhost'}:${PORT}`));
