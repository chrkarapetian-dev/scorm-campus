# Agent notes

Laravel 13, Blade, Tailwind 4, MySQL 8.4, Docker. Armenian SCORM course platform.

## Commands
- `make up` builds and starts the stack and runs migrations (http://localhost:8080). `make down` stops it; data stays in volumes.
- Container code is baked into the image, so changes only appear after `make up`.
- Faster loop: `docker compose up -d db`, then `composer dev` (host PHP and Vite against the container DB).
- Tests: `php artisan test [--filter=Name]`. They use in-memory SQLite and need no Docker.
- Assets: `npm run build`.
- `make rebuild-cache` re-chunks the course texts in the running stack.

## Saving tokens
- Use graft (below) for PHP, JS and Blade. It does not index `resources/css/app.css`, `compose.yaml`, `Dockerfile`, `docker/` or `Makefile`; read those directly.
- Don't read lockfiles, `vendor/`, `public/build/` or logs. `database/data/learning-course.json` is long Armenian course content: search it, don't read it whole.

## Rules that aren't obvious from the code
- **The design is a pixel-exact port. Don't "clean up" arbitrary values** like `text-[11px]` or `p-[17px]`: scale classes such as `text-sm` also change line-height. Use `rounded-[50%]`, not `rounded-full`, because it draws a pill on non-square boxes. Tailwind's reset removes browser defaults, so button and paragraph spacing is set explicitly. `max-compact:` means width <= 760px. Tokens live in `resources/css/app.css`.
- **The UI stays one page with tabs** (`home.blade.php`). Separate routes would reload the course iframe on every tab switch.
- Markup the JS creates belongs in `partials/templates.blade.php`, not in JS strings, so Tailwind classes stay in the views.
- **Learner progress is account-scoped in `course_progress` and `course_answers`.** Both players hydrate SCORM state from the authenticated progress API before launch. Preserve revision conflict checks and byte-exact suspend data. Legacy `usum-*` browser keys are left untouched and must not be automatically assigned to an account. Storyline slide ids are in `config/scorm.php`.
- **Package HTML runs on our origin.** Keep `ScormPackageImporter::isSafePath` checks, the production upload-token requirement in `CourseController`, and Nginx running only `index.php`. Don't add `internal`-less Nginx locations for course files.
- **Accounts:** session auth in `app/Http/Controllers/Auth`. Everything needs login, including every course file, except `POST /api/courses`, which has its upload token. Use `User::reserveAiQuestion()` for the per-user limit (`AI_QUESTION_LIMIT`, default 10). Messages come from `lang/hy`.
- **Course files stay out of `public/`:** Articulate courses are in `storage/courses/course{N}` (not in git or images, bind-mounted read-only in Docker) and source texts in `storage/course_texts`. Active course numbers are 3, 4, 5, 6, 8, 9 and 10; preserve their IDs. `CourseContentController` serves them; in Docker Nginx sends the file via `X-Accel-Redirect`.
- **Course text** is `storage/course_texts/course{N}.txt`, one paragraph per line. It is baked into the image, so edits need `make up`. It is chunked once and kept in the cache (`CourseTextRepository`), and rebuilds when a file's size or mtime changes, or with `make rebuild-cache`. Bump `VERSION` there when chunking changes.
- User-facing text and API `{error}` messages are Armenian.
- The course assistant answers only from excerpts of the selected course, chosen by `CourseChunkSearch`. If OpenAI fails it returns an error and gives the question back. `LocalCourseTutor` remains only as the grader's fallback, with a `notice` saying so. Keep that note honest.
- Local `.env` has debug on and Docker runs production. Error-handling tests must set `config(['app.debug' => false])`, or they miss production-only bugs.

<!-- graft:start -->
## Graft — repo context graph

This repo is indexed in `graft/`: small linked markdown nodes that explain each
system and carry exact file:line spans, kept in sync with the code through git.

For ANY task here — understanding how something works, finding where code lives,
or scoping a change — get context from the graph before grepping or opening
source files. Re-ask freely (it's cheap) and reuse literal identifiers you
already have (symbol, error string, file name) as the query. New to this repo?
Run `graft map` first — a token-budgeted orientation (dir clusters, hubs,
hotspots), no LLM, no key.

- Run `graft ask "<your question>" --source` → ranked nodes with the relevant
  code spans inlined (each hit's ≤8-line crux by default; `--full` for whole
  definitions when the crux isn't enough). Match the tool to the task shape:
  for understanding or editing, the top node IS the answer — cite its
  `covers:` file:line spans and edit straight from `--source`. For
  exhaustive tasks ("every occurrence / every caller of this pattern"), ranked
  results are top-N, not complete — run `graft grep "<literal>"` instead
  (exhaustive over indexed files, grouped by enclosing symbol), falling back
  to raw `grep -rn` only for unindexed files.
- `graft skeleton <file>` → every definition's signature + span, ~10× cheaper
  than reading the file; use it to skim an API surface.
- `graft callers <symbol>` gives precomputed, exact edges — who calls this.
  Add `--direction out` for what it calls, or `--depth N` to walk
  transitively for the full blast radius. For structural questions, skip
  ranking and use this directly.
- Or browse: `graft/INDEX.md` lists every node; follow the links.
- Monorepos and folders of multiple repos rank fairly across sub-projects —
  hits carry `[scope/]` labels naming which one they're from. Narrow with
  `graft ask "<task>" --in <scope>/` once you know where you're working.

If a returned span is truncated ("+N more lines"), open the file at that exact
range before finalizing. Only open source files when a node genuinely lacks a
needed detail, and then at the exact file:line the node points to — never
re-read whole files.

After big code changes, refresh the graph with `graft build` (deterministic,
no API key, $0).
<!-- graft:end -->
