// Read-only selection for the progress view, independent of the course player.
export class ProgressSelection {
    constructor(courses, load, onChange = () => {}) {
        this.courses = courses;
        this.load = load;
        this.onChange = onChange;
        this.request = 0;
        this.state = { course: courses[0] || null, progress: null, loading: false, error: null };
    }

    open() { return this.select(this.courses[0]?.id); }

    async select(id) {
        const course = this.courses.find(item => item.id === id) || null;
        const request = ++this.request;
        this.state = { course, progress: null, loading: Boolean(course), error: null };
        this.onChange();
        if (!course) return;
        try {
            const progress = await this.load(course);
            if (request !== this.request) return;
            const latest = this.state.progress;
            this.state = { course, progress: latest && latest.revision > progress.revision ? latest : progress, loading: false, error: null };
        } catch (error) {
            if (request !== this.request) return;
            this.state = { course, progress: null, loading: false, error };
        }
        this.onChange();
    }

    accept(id, progress) {
        if (!progress || this.state.course?.id !== id || this.state.error) return;
        if (progress.revision < (this.state.progress?.revision ?? 0)) return;
        this.state = { ...this.state, progress };
        this.onChange();
        return true;
    }
}
