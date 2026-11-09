# Account-scoped course progress

This change is local source work; it has not been deployed to production.

## Investigation and changes

Previously, both SCORM players stored values in browser-wide localStorage. There were no progress or self-assessment database tables, no user isolation, and no backend progress API. The progress screen calculated statistics in JavaScript. Its interaction fallback divided correct answers by answered questions, potentially making a partially answered quiz appear perfect. Video tracking kept the maximum watched duration of a single visit rather than the union of watched intervals across visits.

`course_progress` now has a unique `(user_id, course_id)` pair, cascading foreign keys, a revision, raw SCORM runtime state, and observed activity. `course_answers` belongs to that record and uniquely identifies each question by its SCORM ID. It stores the latest submitted response and the question metadata reported by the package. Reset clears runtime, activity and answers while increasing the revision, preventing an older tab from silently restoring old records.

Authenticated GET/POST `/api/scorm/progress/{course}` endpoints derive the learner from the session. Writes use a transaction and lock the user row, including first creation. Stale revisions return 409. Answer updates use stable question IDs rather than interaction positions. Only interactions in the incoming patch are processed. Both SCORM 1.2 and 2004 response fields are supported. Suspend data bypasses trimming and empty-string conversion so the package can restore its exact state.

`ProgressSummary` derives statistics from saved records. Reading completion follows a transition out of a reading section. Video completion requires 90% of the actual duration, using merged, non-overlapping played ranges. Quiz results require a submitted results slide or a completed SCORM status and a valid reported score range. An answered subset is never used as a full-quiz denominator. All seven supplied course definitions specify a 70% quiz passing threshold. The existing section-based overall completion denominator is preserved.

Both player entry points use `resources/js/progress.js`. The synchronous SCORM adapter is hydrated before iframe launch, then sends ordered asynchronous patches. The progress screen renders acknowledged backend summaries rather than optimistic values. It displays Armenian questions and choices recovered from the published Storyline metadata, in quiz order. Selected and correct responses are separate, and an ambiguous legacy choice is labelled unavailable instead of guessed. The answer review is collapsed below the existing progress rows; unanswered questions have no correct-answer reveal. Reported scores from unfinished attempts are explicitly provisional and do not complete the quiz. Failed writes remain queued for retry, errors are visible, and navigating away with unsaved writes raises the browser's unsaved-change prompt. Conflicting tabs must reload. Only one embedded/fullscreen package instance runs at a time.

## Files

- Migration `2026_11_12_000000_create_course_progress_tables.php` and models `CourseProgress`, `CourseAnswer`; relationships on `User` and `ScormCourse`.
- `ProgressController`, `ProgressSummary`, `StorylineQuestionCatalog`, `routes/api.php`, and the narrowly scoped input-normalization exceptions in `bootstrap/app.php`.
- `resources/js/progress.js`, `storyline.js`, `app.js`, `course-player.js`; standalone course view, progress partial and answer template; Vite entry configuration.
- Feature and frontend regression tests and `npm run test:progress`.

## Verification

Feature coverage includes fresh and partial progress, answer updates, exact suspend-state round trips, both SCORM versions, results slides reporting an incomplete/failed attempt, users and courses isolated from one another, stale writes/resets, interval merging, invalid inputs, authentication, and cascading deletion. Frontend tests cover hydration and resume, batching, ordering during in-flight writes, retry without losing newer values, conflict handling, and per-course reset isolation.

An isolated SQLite application also passed real HTTP login, CSRF-protected save, subsequent retrieval, standalone page rendering, and delivery of a real bundled LMS launch page. Regression fixtures reproduce the underscore-only SCORM 1.2 codes in the reported screenshot. All 70 question definitions across the seven bundled courses were checked. Headless Chrome passed fresh and saved progress, natural question order, Armenian text, provisional score display, collapsed/expanded review, refresh, and a 390px viewport without horizontal overflow or JavaScript errors. These checks did not change production or existing learner progress records.

## Remaining verification and limitations

- The progress page has now been verified in Chrome at desktop and mobile widths. Clicking through every real Articulate question, resume dialog and video remains a separate acceptance check; the browser regression used isolated records in the real package format.
- Course 10 is a web export without `index_lms.html`. Its quiz/resume reporting cannot be assumed to work with an LMS API; republish it as SCORM and verify its reports. The observer can still record supported slide and video activity.
- The SCORM 1.2 driver replaces Armenian characters with underscores and omits descriptions. `StorylineQuestionCatalog` safely parses and caches the package metadata without executing its JavaScript. It resolves uniquely matching codes; some old choices are indistinguishable after encoding and cannot be recovered with certainty. Their stored result remains visible, with an explicit unavailable-text message. Raw runtime and answer records remain intact. If metadata is missing, the review uses a safe fallback without showing encoded strings.
- Course 8, question 8 (`Scene5_Slide9_MultiChoice_0_0`), defines every option as incorrect. Its correct-answer field is explicitly unavailable; the published content has not been altered. This requires a course-author correction and republish.
- SCORM scores and responses originate in the browser/package. Backend storage, validation and calculations provide persistence and consistency, not tamper-proof examination grading.
- Legacy anonymous browser data is preserved but not imported: its owner cannot be reliably established. Existing users start account-scoped tracking on deployment.
- Saves are asynchronous. Offline changes remain in memory with an explicit warning/retry path; forced browser termination before acknowledgement can lose unsaved changes. A durable, account-scoped offline outbox would require further design.

After review, deploy the source, run the migration and build the Vite assets using the usual deployment process. The server was not changed in this task.
