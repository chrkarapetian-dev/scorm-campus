// SCORM calls are synchronous; hydrate before launching a package and persist ordered batches.
export class ProgressStore {
    constructor(onChange = () => {}) {
        this.records = new Map();
        this.pending = new Map();
        this.onChange = onChange;
        this.error = null;
        window.addEventListener('online', () => this.flush());
        this.queue = Promise.resolve();
        this.timer = null;
        window.addEventListener('beforeunload', event => {
            if (this.pending.size || this.saving) { event.preventDefault(); event.returnValue = ''; }
        });
        window.addEventListener('pagehide', () => this.flush(true));
        document.addEventListener('visibilitychange', () => { if (document.hidden) this.flush(true); });
    }

    async request(id, body, keepalive = false) {
        const response = await fetch(`/api/scorm/progress/${encodeURIComponent(id)}`, {
            method: body ? 'POST' : 'GET',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: body ? JSON.stringify(body) : undefined,
            cache: 'no-store', keepalive,
        });
        const data = await response.json();
        if (!response.ok) {
            const error = new Error(data.error || 'Առաջընթացը չի պահպանվել։ Ստուգեք կապը և կրկին փորձեք։');
            error.conflict = response.status === 409;
            throw error;
        }
        return data;
    }

    async load(courses) {
        await Promise.all(courses.map(async course => {
            const data = await this.request(course.id);
            this.records.set(course.id, { saved: data, runtime: { ...data.scorm } });
        }));
    }

    read(item) { return this.records.get(item?.id)?.saved || { sections: {}, scorm: {}, quiz: null, answers: [], completedSections: 0, totalSections: 0 }; }
    get(item, key) {
        const runtime = this.records.get(item.id).runtime;
        if (key === 'cmi.core.entry' || key === 'cmi.entry') return runtime['cmi.suspend_data'] ? 'resume' : 'ab-initio';
        if (key.endsWith('._count')) {
            const prefix = key.slice(0, -6);
            const indices = Object.keys(runtime).filter(k => k.startsWith(prefix)).map(k => k.slice(prefix.length).split('.')[0]).filter(k => /^\d+$/.test(k)).map(Number);
            return String(indices.length ? Math.max(...indices) + 1 : 0);
        }
        const defaults = { 'cmi.core.lesson_status': 'not attempted', 'cmi.completion_status': 'unknown', 'cmi.success_status': 'unknown', 'cmi.core.lesson_mode': 'normal', 'cmi.mode': 'normal', 'cmi.core.credit': 'credit', 'cmi.credit': 'credit', 'cmi.core.total_time': '0000:00:00', 'cmi.total_time': 'PT0S' };
        return runtime[key] ?? defaults[key] ?? '';
    }
    set(item, key, value) {
        if (this.error?.conflict) return 'false';
        const record = this.records.get(item.id);
        if (record.runtime[key] === String(value)) return 'true';
        record.runtime[key] = String(value);
        this.update(item, { runtime: { [key]: String(value) } });
        return 'true';
    }
    update(item, patch) {
        const old = this.pending.get(item.id) || {};
        this.pending.set(item.id, { ...old, ...patch, runtime: { ...(old.runtime || {}), ...(patch.runtime || {}) } });
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.flush(), 400);
    }
    flush(keepalive = false) {
        this.queue = this.queue.then(async () => {
            if (this.error?.conflict) return;
            for (const [id, patch] of [...this.pending]) {
                this.pending.delete(id);
                const record = this.records.get(id);
                this.saving = true;
                try {
                    record.saved = await this.request(id, { ...patch, revision: record.saved.revision }, keepalive);
                    this.error = null;
                } catch (error) {
                    const newer = this.pending.get(id) || {};
                    this.pending.set(id, { ...patch, ...newer, runtime: { ...patch.runtime, ...newer.runtime } });
                    this.saving = false;
                    this.error = error;
                    this.onChange();
                    return;
                }
                this.saving = false;
                this.onChange(id, record.saved);
            }
        });
        return this.queue;
    }
    async refresh(item) {
        await this.flush();
        if (this.error || !item) return;
        const record = this.records.get(item.id);
        const revision = record.saved.revision;
        const saved = await this.request(item.id);
        if (!this.pending.has(item.id) && record.saved.revision === revision) {
            record.saved = saved;
            record.runtime = { ...saved.scorm };
        }
        this.onChange(item.id, record.saved);
    }
    async clear(item) {
        await this.flush();
        if (this.error) throw this.error;
        const record = this.records.get(item.id);
        record.saved = await this.request(item.id, { revision: record.saved.revision, reset: true });
        record.runtime = {};
        this.onChange(item.id, record.saved);
    }
    install(item) {
        const get = key => this.get(item, key);
        const set = (key, value) => this.set(item, key, value);
        const commit = () => { this.flush(); return this.error ? 'false' : 'true'; };
        const common = { GetLastError: () => this.error ? '101' : '0', GetErrorString: () => this.error?.message || '', GetDiagnostic: () => this.error?.message || '' };
        window.API = { LMSInitialize: () => 'true', LMSFinish: commit, LMSCommit: commit, LMSGetValue: get, LMSSetValue: set, LMSGetLastError: common.GetLastError, LMSGetErrorString: common.GetErrorString, LMSGetDiagnostic: common.GetDiagnostic };
        window.API_1484_11 = { Initialize: () => 'true', Terminate: commit, Commit: commit, GetValue: get, SetValue: set, ...common };
    }
}
