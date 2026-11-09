# Course assignments

The assignment endpoint uses the existing `OpenAiResponses` integration (`POST https://api.openai.com/v1/responses`, configured with `OPENAI_MODEL`), not a separate Codex endpoint. No new provider, API key, production dependency, or background worker is required.

## Source and evaluation

- Available, authenticated courses with a TXT source have an assignment. Questions for the existing seven TXT-backed courses live in `config/scorm.php`; course 8 retains its original question. Future sources use a course-title-specific question until an authored question is configured.
- The server accepts 200–6000 Unicode characters after trimming edge whitespace. Course ID is required; posted scores, questions and detection fields are ignored.
- `CourseChunkSearch::forAssignment()` selects up to six cached TXT chunks using the assignment question, independently of the submitted answer. This prevents an off-topic answer from steering the evidence. Q&A keeps its original search coverage settings.
- Detection runs first. Below the configured flagging threshold, a separate grading call evaluates accuracy /35, understanding /30, completeness /25, and clarity /10. The server validates each integer score and sums the four criteria, with a normal final score of at least 1. Missing or insufficient course evidence is an error, not a guessed grade.
- Feedback includes a summary, criterion reasons, correct points, missing information and improvements, in Armenian.

## Detection and limits

This is a **heuristic linguistic analysis, not a calibrated authorship detector**. There is no validated Armenian accuracy claim. OpenAI [withdrew its text classifier for low accuracy](https://openai.com/index/new-ai-classifier-for-indicating-ai-written-text/); its published limitations include short texts, non-English text and false positives. A dedicated classifier without representative labeled Armenian examples would not establish reliability either. Provenance or supervised review would be needed for stronger authorship evidence.

The existing model performs a separate structured analysis of formulaic structure, generic exposition, repetitive patterns and assistant boilerplate, with local character/sentence/repetition observations and explicit counter-indicators. Each reported indicator must quote the submitted answer exactly; categories cannot repeat. Estimates of 50 or higher require at least two independently quoted categories. These checks validate evidence structure, **not the truth of an authorship claim**. The prompt explicitly excludes grammar, vocabulary, organization and proficiency as sufficient evidence, and accounts for source quotations and assignment-required structures.

Classification is computed on the server:

| Estimate | Stored classification |
| --- | --- |
| 0–29 | `likely_human_written` |
| 30–49 | `suspicious` |
| 50–69 | `potentially_ai_generated` |
| 70–100 | `strong_ai_indicators` |

`ASSIGNMENT_AI_THRESHOLD` defaults to **50**. At or above the threshold, status becomes `flagged`, final score is **0**, the normal grade and feedback remain null, and the grading API call is skipped. The UI shows the estimate, policy threshold and false-positive warning. Classification bands remain fixed when the configurable flagging threshold changes. Each submission stores the threshold and model used.

This zero-score rule implements the requested business policy; it must not be interpreted as proof of misconduct. The interface warns about automated uncertainty before submission and beside every result.

## Persistence, retries and authorization

Migration: `2026_11_09_000000_create_assignment_submissions_table.php`. Existing `ai_questions` records concern chat Q&A and cannot represent assignment scoring or processing state.

`assignment_submissions` stores user/course foreign keys, question and answer snapshots, detection estimate/classification/reason/quoted indicators, rubric feedback, normal and final scores, model, token usage, status, timestamps, and processing ownership/expiry. API results never expose processing tokens or hashes. GET history and individual results always filter by the signed-in user's ID and final statuses. History uses bounded, 10-item cursor pages.

Reservations lock the user row in a short database transaction, preventing parallel AI evaluations for the same account without holding a transaction during network calls. A unique user/course/submission-hash index prevents duplicate records. The hash incorporates the answer, question, selected evidence, model, threshold and evaluator policy version. Identical successful retries return the saved result without API calls. Failed attempts remain unscored and can retry the same record; a timed lease allows recovery after an interrupted worker. Ownership tokens prevent stale workers from overwriting a reclaimed attempt.

API failures, refusal, incomplete responses, invalid JSON, invented indicator quotes, invalid rubric fields and insufficient evidence produce a retryable error. Partial detection is not saved as a final result. The legacy `CourseAssistant::evaluate()` / `LocalCourseTutor` fallback remains covered by service tests but is not used by this assignment endpoint.

## Limits and operations

- Assignment POST requests have a separate per-user limit of 5/minute, session authentication and CSRF protection. They do not consume the chat Q&A allowance.
- The browser disables repeated clicks, preserves drafts while switching courses, and times out after twice the configured API timeout plus 15 seconds. Each upstream call uses `OPENAI_TIMEOUT` (45 seconds by default). The database lease lasts twice that timeout plus 30 seconds.
- Both calls reuse strict [Responses API JSON schemas](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses), with additional server-side validation. Inputs are explicitly untrusted data. Keys remain server-side and upstream storage is disabled.
- Token usage is bounded by the 6000-character answer, six cached excerpts and existing output cap. Normal answers use two calls; flagged answers use one; cached successful retries use none. There are no automatic upstream retries. Usage totals are saved when supplied by the provider.
- Bump `AssignmentEvaluator::VERSION` when changing prompts/rubric semantics so old successful results are not reused under a new policy. TXT chunking itself is unchanged.
- Local and production Compose configurations pass `ASSIGNMENT_AI_THRESHOLD`. Run `make up` locally (or the existing production deployment procedure) to rebuild images and apply migrations. No course files move into `public/`.

## Verification

`php artisan test` mocks all model requests and covers validation, all classification boundaries, configurable threshold, score tampering, authentication/ownership, source isolation, failures/refusal/incomplete responses, retry deduplication, overlapping requests (including two independent PHP processes against MySQL), expired and stale reservations, pagination and Q&A regression. Existing local-grader fallback tests run against the service directly.

Verification also includes the full suite against an isolated MySQL 8.4 database, a Docker build/migration and compiled Blade views, `npm run build`, and browser checks with mocked evaluation responses for loading, counters, normal/flagged results, retry, history, mobile layout, course switching and iframe preservation. No live AI request is needed to run these checks. Heuristic detection quality still requires a separately reviewed, representative Armenian dataset before making any accuracy claim.


## Files added or modified for this change

Added:

- `app/Http/Controllers/Api/AssignmentController.php`
- `app/Models/AssignmentSubmission.php`
- `app/Services/AssignmentEvaluator.php`
- `database/migrations/2026_11_09_000000_create_assignment_submissions_table.php`
- `lang/hy/assignment.php`
- `tests/Feature/AssignmentTest.php`
- `tests/Support/assignment-worker.php`
- `docs/assignments.md`

Modified:

- `app/Http/Controllers/Api/AssistantController.php` — removes the obsolete assignment action; Q&A logic is preserved.
- `app/Http/Controllers/HomeController.php` — exposes course question and localized assignment settings.
- `app/Models/ScormCourse.php` — resolves the server-owned question.
- `app/Providers/AppServiceProvider.php` — separate assignment rate limiter.
- `app/Services/CourseChunkSearch.php` — bounded assignment evidence selection.
- `app/Services/OpenAiResponses.php` — rejects incomplete upstream responses.
- `config/scorm.php` — questions, evidence cap and configurable threshold.
- `routes/api.php` — submission and private history endpoints.
- `resources/js/app.js` — validation, loading, results, history and course-specific draft handling.
- `resources/views/partials/view-course.blade.php` — assignment interface.
- `resources/views/partials/templates.blade.php` — generated result/history markup.
- `tests/Feature/AssistantTest.php` — retains local fallback tests at service level and updates endpoint validation expectations.
- `compose.yaml`, `compose.prod.yaml` — forwards threshold configuration.
- `.env.example`, `.env.prod.example` — documents the threshold.
- `README.md` — current assignment behavior and operations.

Existing unrelated working-tree changes were preserved.
