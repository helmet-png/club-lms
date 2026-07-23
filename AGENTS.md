# AGENTS.md — Instructions for coding agents (Codex, Claude, etc.)

This repo deploys a Moodle-based club LMS. There are **two separate things** in here — don't confuse them:

1. **This git repo** — deployment infrastructure (Dockerfiles, entrypoints, a custom Moodle plugin). Editing files here and pushing triggers a Render redeploy.
2. **The live Moodle site's database** (courses, quizzes, questions, categories) — this is runtime content, **not stored in this repo**. You edit it by calling the site's web service REST API, not by editing files.

If your task is "add/edit course content" (weekly readings, quizzes, categories, enrolments), you want **#2 — use the API described below**. You do not need to touch or redeploy any code for that.

## Live site

- URL: `https://debate-club-system.onrender.com`
- REST endpoint: `https://debate-club-system.onrender.com/webservice/rest/server.php`
- Format param: always send `moodlewsrestformat=json`
- Auth: token via `wstoken=...` param. **You will be given your own token out-of-band (not in this repo, not in git). Never commit a token. Read it from an environment variable, e.g. `$MOODLE_TOKEN`.**

Minimal call shape (curl):
```bash
curl -s "https://debate-club-system.onrender.com/webservice/rest/server.php" \
  --data-urlencode "wstoken=$MOODLE_TOKEN" \
  --data-urlencode "wsfunction=<function_name>" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "param1=value1" ...
```

## Current course/category IDs (as of 2026-07-22)

| Category | id |
|---|---|
| 英文辯論 (English debate) | 2 |
| 中文辯論 (Chinese debate) | 3 |
| 社務 (Club affairs) | 4 |
| 行政 (Admin, hidden) | 5 |

| Course | id | category |
|---|---|---|
| 英辯資源 — PF-Reading-115 (10-week Public Forum reading + quiz course, already built) | 2 | 2 |
| 社務資訊 — info | 3 | 4 |
| 社內比賽 — intra-comp | 4 | 4 |
| 社員專區 — member | 5 | 4 |
| 英辯比賽準備 — en-prep | 6 | 2 |
| 中辯資源 — zh-course | 7 | 3 |
| 中辯比賽準備 — zh-prep | 8 | 3 |
| 行政與社課主持 — admin | 9 | 5 |

Fetch current state instead of trusting this table blindly: `core_course_get_categories`, `core_course_get_courses`.

## Standard Moodle functions enabled on this token's service

`core_course_get_categories`, `core_course_create_courses`, `core_course_get_courses`, `core_course_update_courses`, `core_user_get_users`, `enrol_manual_enrol_users`. (`core_course_create_categories` is NOT enabled — ask a site admin to add it if you need to create new categories.)

## Custom functions (this repo's plugin, `local_clubws`)

Standard Moodle web services **cannot create course activities** (this is a known gap in Moodle core). We wrote a small plugin — `render-experiment/plugins/local_clubws/` — to fill it. Its source is the ground truth; below is a summary.

### `local_clubws_create_urls`
Batch-creates URL resource activities with "mark complete on view" (check-in) and optional date-locking.

```
items[N][courseid]       int, required
items[N][section]        int, required — week/section number (auto-creates missing sections)
items[N][name]            string, required
items[N][url]              string, required — the external URL
items[N][intro]            string, optional — HTML description
items[N][availablefrom]    int, optional — unix timestamp; 0 or omitted = no lock
```

### `local_clubws_create_quizzes`
Batch-creates a quiz activity per item and imports multiple-choice questions from **GIFT format** text.

```
items[N][courseid]       int, required
items[N][section]        int, required
items[N][name]            string, required
items[N][gift]             string, required — GIFT-format question text (see gotcha below)
items[N][intro]            string, optional
items[N][availablefrom]    int, optional — unix timestamp
```

Example call creating one URL activity:
```bash
curl -s "$BASE" \
  --data-urlencode "wstoken=$MOODLE_TOKEN" \
  --data-urlencode "wsfunction=local_clubws_create_urls" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "items[0][courseid]=7" \
  --data-urlencode "items[0][section]=1" \
  --data-urlencode "items[0][name]=Some Article" \
  --data-urlencode "items[0][url]=https://example.com/article" \
  --data-urlencode "items[0][availablefrom]=1788883200"
```

## Known gotchas (learned the hard way — save yourself the debugging)

1. **Course format is `weeks`, not `weekly`.** Passing `weekly` fails silently with `invalid_parameter_exception`.
2. **`numsections` cannot be set via the standard course API** in this Moodle version — it returns success but does nothing. Don't rely on it; our plugin auto-creates missing sections when you create an activity in a section number that doesn't exist yet, which is the actual workaround.
3. **Chinese/UTF-8 text passed as an inline `--data-urlencode` shell argument can get mangled** depending on the shell/encoding. Safest fix: write the value to a UTF-8 file and pass it with `--data-urlencode "field@/path/to/file"` instead of inlining the text.
4. **GIFT format requires a blank line between each question block**, or the parser silently merges multiple questions into one. Always double-check the question count returned by `create_quizzes` matches what you sent.
5. If you get `dml_write_exception` from `create_quizzes`, it's almost certainly a required-field gap in a manually-built `mod_quiz` row (e.g. a NOT NULL column with no default). Ask the site admin to temporarily set `$CFG->debug = 32767; $CFG->debugdisplay = 1;` in Moodle config to get the real SQL error instead of a generic message — then revert once fixed.
6. Web service errors return a JSON object with `exception`/`errorcode`/`message` — always check for that key before assuming success.

## Deployment notes (only relevant if you're changing this repo's code, not course content)

- This is the **Render + Supabase experiment** deployment (`render-experiment/`), separate from the self-host version at repo root (`Dockerfile`, `docker-compose.yml` — that one is for Oracle Cloud/VPS, uses MariaDB, not Postgres). Don't mix them up.
- Render's **Root Directory must be set to `render-experiment`** or it builds the wrong Dockerfile.
- Supabase region must match the Render region (cross-ocean DB latency is brutal otherwise).
- `$CFG->sslproxy = true` is required behind Render's reverse proxy (otherwise infinite http→https redirect loop).
- The Traditional Chinese language pack is re-downloaded from `download.moodle.org` on every boot (see `docker-entrypoint.sh`) because Render's disk is ephemeral — don't try to "install" it through the UI, it won't survive a restart.
- Full narrative writeups: [`README.md`](README.md), [`部署與設定步驟.md`](部署與設定步驟.md), [`render-experiment/README-實驗步驟.md`](render-experiment/README-實驗步驟.md).

## Ground rules

- **Never commit a web service token, database password, or admin password to this repo** — it's public. Use environment variables and out-of-band sharing.
- Prefer the API over asking a human to click through the Moodle admin UI for anything repetitive (batch content creation). Reserve manual UI steps for one-time structural setup (categories, top nav menu) that a site admin already did.
- If a call fails, read the `message`/`debuginfo` in the JSON response before guessing — Moodle's web service errors are usually specific enough to act on directly.
