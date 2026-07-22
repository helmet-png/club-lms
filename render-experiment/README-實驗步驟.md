# [Experiment] Render free + Supabase free, no credit card anywhere

> Goal: prove whether "Render + Supabase" can run Moodle for free, with no credit card.
> **This is an experiment, not for members to rely on** — it cold-starts and uploaded files are not persistent (see the end).

All three accounts sign in with GitHub/Google and need **no credit card**: Supabase, GitHub, Render.

> 中文版請往下捲：**[繁體中文](#繁體中文)**

---

## Phase 1: Get it running + verify "accounts/courses persist"

### A. Supabase (the database)
1. supabase.com → sign in with GitHub → **New project**. **Pick a region close to your Render region** (e.g. if Render is in Oregon, pick West US (Oregon) — a far region means every page crawls across the ocean). Note the region and the **Database password** you set.
2. Project → **Connect** → choose **Session pooler** (**not Direct**! Direct is IPv6-only and Render can't reach it; Session pooler is IPv4). Copy:
   - Host: `aws-0-<region>.pooler.supabase.com`
   - Port: `5432`  User: `postgres.<project-ref>`  Database: `postgres`

### B. GitHub (the code)
3. Push this `render-experiment/` folder to a GitHub repo (private is fine).

### C. Render (the service)
4. render.com → sign in with GitHub → **New → Web Service** → pick your repo → Runtime **Docker**, Plan **Free**.
5. **Set Root Directory to `render-experiment`** (critical — otherwise Render builds the repo-root Dockerfile, which is the self-host version and has no Moodle inside → 403/404). Dockerfile Path is then just `Dockerfile`.
6. Name the service (e.g. `club-lms`); your URL becomes `https://club-lms.onrender.com`.
7. **Environment** — add these variables (from what you copied in A):

   | Variable | Value |
   |---|---|
   | `MOODLE_DB_HOST` | `aws-0-xxx.pooler.supabase.com` |
   | `MOODLE_DB_PORT` | `5432` |
   | `MOODLE_DB_NAME` | `postgres` |
   | `MOODLE_DB_USER` | `postgres.<project-ref>` |
   | `MOODLE_DB_PASS` | Supabase database password |
   | `MOODLE_WWWROOT` | `https://club-lms.onrender.com` |
   | `MOODLE_ADMIN_PASS` | your admin password |
   | `MOODLE_ADMIN_EMAIL` | your email |
   | `MOODLE_CRON_PASS` | any string you choose |

8. **Deploy**. Apache starts immediately and the DB install runs in the background (first time takes a few minutes; watch the logs for `✅`). The build log should show `Cloning into '/var/www/html'` — if it doesn't, Root Directory is wrong (step 5).
9. Open the URL → log in with `admin` + `MOODLE_ADMIN_PASS`.

> Handled automatically by the entrypoint, no action needed: the HTTPS reverse-proxy fix (`sslproxy`, otherwise Render's http→https causes an infinite redirect loop) and the Traditional Chinese language pack (re-downloaded on every boot since the disk is ephemeral).

### D. External cron (Render free sleeps; cron must be poked from outside)
10. On cron-job.org (no card), create a job that GETs every 5 minutes:
    `https://club-lms.onrender.com/admin/cron.php?password=<MOODLE_CRON_PASS>`
    > Bonus: this heartbeat also acts as a keep-alive, and incidentally stops Supabase from pausing after 7 idle days.

### E. Verify the experiment (the whole point!)
11. In the admin area create a test user + a course → hit **Restart** on Render → log in again.
    **The account and course are still there** ✅ = the "DB persists" half works (data lives in Supabase).
12. Upload a file to the course → **Restart** → the file is **gone** ❌ = `moodledata` is ephemeral, which is why Phase 2 exists.

---

## Phase 2 (only if Phase 1 is OK): make uploaded files persist too

Use the `tool_objectfs` + `local_aws` plugins to offload files to **Supabase Storage** (S3-compatible; you'll create a bucket and get an S3 access key in Supabase).

⚠️ **Honestly**: objectfs pushes to S3 **asynchronously via cron**, so a file uploaded between two cron runs that then hits a restart can still be lost. This is experiment-grade duct tape, **no guarantee against data loss**. If Phase 1 feels usable, ask me to add Phase 2 to the Dockerfile.

---

## Known limitations (the nature of the experiment)
- **Cold start**: after 15 idle minutes, the first visit waits ~1 minute.
- **Memory**: Render free is 512MB; Moodle may occasionally 500, usually fine on reload.
- **Supabase pause**: the whole project sleeps after 7 idle days and needs a manual wake in the dashboard (the cron keep-alive mitigates this).
- **Uploaded files not persistent**: unsolved in Phase 1; Phase 2 is only "best effort".

> This is exactly why real deployment should use `../` (self-host + Tailscale Funnel) or Oracle. An experiment is an experiment — have fun.

---

<a name="繁體中文"></a>

# 繁體中文

# 【實驗】Render 免費 + Supabase 免費，全程不綁信用卡

> 目的：驗證「Render + Supabase」能不能免費、免卡跑起 Moodle。
> **這是實驗，不是給社員正式用**——已知會冷啟動、上傳檔案不持久（見文末）。

三個帳號都用 GitHub/Google 登入，**都不需要信用卡**：Supabase、GitHub、Render。

---

## 階段一：跑起來 + 驗證「帳號/課程持久」

### A. Supabase（當資料庫）
1. supabase.com → 用 GitHub 登入 → **New project**。**region 要選跟 Render 同一區**（例如 Render 在 Oregon 就選 West US (Oregon)；選太遠的話每開一頁都要跨海，會很慢）。記下 region 和你設的 **Database password**。
2. 專案 → **Connect** → 選 **Session pooler**（**不要選 Direct**！Direct 是 IPv6-only，Render 連不到；Session pooler 走 IPv4）。抄下：
   - Host：`aws-0-<region>.pooler.supabase.com`
   - Port：`5432`　User：`postgres.<專案ref>`　Database：`postgres`

### B. GitHub（放程式碼）
3. 把本資料夾 `render-experiment/` 推上一個 GitHub repo（私有也行）。

### C. Render（跑服務）
4. render.com → 用 GitHub 登入 → **New → Web Service** → 選你的 repo → Runtime **Docker**、Plan **Free**。
5. **Root Directory 一定要設成 `render-experiment`**（關鍵——否則 Render 會去建 repo 根目錄的 Dockerfile，那是自架版、裡面沒有 Moodle → 會 403/404）。Dockerfile Path 這時填 `Dockerfile` 即可。
6. 服務取名（例如 `club-lms`），你的網址就會是 `https://club-lms.onrender.com`。
7. **Environment** 加這些變數（對照 A 抄的值）：

   | 變數 | 值 |
   |---|---|
   | `MOODLE_DB_HOST` | `aws-0-xxx.pooler.supabase.com` |
   | `MOODLE_DB_PORT` | `5432` |
   | `MOODLE_DB_NAME` | `postgres` |
   | `MOODLE_DB_USER` | `postgres.<專案ref>` |
   | `MOODLE_DB_PASS` | Supabase 資料庫密碼 |
   | `MOODLE_WWWROOT` | `https://club-lms.onrender.com` |
   | `MOODLE_ADMIN_PASS` | 自訂管理員密碼 |
   | `MOODLE_ADMIN_EMAIL` | 你的 email |
   | `MOODLE_CRON_PASS` | 自訂一組字串 |

8. **Deploy**。Apache 會立刻啟動，資料庫安裝在背景跑（第一次數分鐘，看 log 等 `✅`）。build log 應出現 `Cloning into '/var/www/html'`——若沒有，代表 Root Directory 設錯（第 5 步）。
9. 打開網址 → 用 `admin` + `MOODLE_ADMIN_PASS` 登入。

> 以下由 entrypoint 自動處理、你不用管：HTTPS 反向代理修正（`sslproxy`，否則 Render 的 http→https 會無限轉址）、正體中文語言包（磁碟短暫，每次開機自動重抓）。

### D. 外部 cron（Render 免費會休眠，cron 要外戳）
10. cron-job.org（免卡）建一個排程，每 5 分鐘 GET：
    `https://club-lms.onrender.com/admin/cron.php?password=<MOODLE_CRON_PASS>`
    > 附帶好處：這個定時打點也能當 keep-alive，順便避免 Supabase 閒置 7 天被暫停。

### E. 驗證實驗（重點！）
11. 後臺建一個測試使用者 + 一門課 → 到 Render 按 **Restart** → 重新登入。
    **帳號和課程還在** ✅ ＝「DB 持久」這半成立（資料在 Supabase）。
12. 上傳一個檔案到課程 → **Restart** → 檔案**不見** ❌ ＝ `moodledata` 短暫，這就是需要階段二的原因。

---

## 階段二（若階段一 OK 再做）：讓上傳檔案也持久

用 `tool_objectfs` + `local_aws` 外掛，把檔案外送到 **Supabase Storage**（S3 相容，需在 Supabase 建 bucket 並取得 S3 access key）。

⚠️ **老實說**：objectfs 靠 cron **非同步**上傳，「兩次 cron 之間上傳、又剛好遇到 restart」的檔案仍可能掉。這是實驗級拼裝，**不保證不掉檔**。若階段一你覺得堪用，再叫我把階段二加進 Dockerfile。

---

## 已知限制（實驗版本質）
- **冷啟動**：閒置 15 分鐘後首次訪問要等約 1 分鐘。
- **記憶體**：Render 免費機 512MB，Moodle 偶爾可能 500 錯誤，重載通常就好。
- **Supabase 暫停**：閒置 7 天整個專案睡著，要去 dashboard 手動喚醒（cron keep-alive 可緩解）。
- **上傳檔案不持久**：階段一不解；階段二也只是「盡量」。

> 這些正是為什麼正式上線建議走 `../`（自架 + Tailscale Funnel）或 Oracle。實驗歸實驗，玩得開心。
