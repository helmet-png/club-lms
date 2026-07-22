# 【實驗】Render 免費 + Supabase 免費，全程不綁信用卡

> 目的：驗證「Render + Supabase」能不能免費、免卡跑起 Moodle。
> **這是實驗，不是給社員正式用**——已知會冷啟動、上傳檔案不持久（見文末）。

三個帳號都用 GitHub/Google 登入，**都不需要信用卡**：Supabase、GitHub、Render。

---

## 階段一：跑起來 + 驗證「帳號/課程持久」

### A. Supabase（當資料庫）
1. supabase.com → 用 GitHub 登入 → **New project**。記下 region 和你設的 **Database password**。
2. 專案 → **Connect** → 選 **Session pooler**（**不要選 Direct**！Direct 是 IPv6-only，Render 連不到；Session pooler 走 IPv4）。抄下：
   - Host：`aws-0-<region>.pooler.supabase.com`
   - Port：`5432`　User：`postgres.<專案ref>`　Database：`postgres`

### B. GitHub（放程式碼）
3. 把本資料夾 `render-experiment/` 推上一個 GitHub repo（私有也行）。

### C. Render（跑服務）
4. render.com → 用 GitHub 登入 → **New → Web Service** → 選你的 repo → Runtime **Docker**、Plan **Free**。
5. 服務取名（例如 `club-lms`），你的網址就會是 `https://club-lms.onrender.com`。
6. **Environment** 加這些變數（對照 A 抄的值）：

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

7. **Deploy**。第一次會自動建表（30–60 秒）；若健康檢查逾時，按 **Manual Deploy** 再跑一次即可。
8. 打開網址 → 用 `admin` + `MOODLE_ADMIN_PASS` 登入。

### D. 外部 cron（Render 免費會休眠，cron 要外戳）
9. cron-job.org（免卡）建一個排程，每 5 分鐘 GET：
   `https://club-lms.onrender.com/admin/cron.php?password=<MOODLE_CRON_PASS>`
   > 附帶好處：這個定時打點也能當 keep-alive，順便避免 Supabase 閒置 7 天被暫停。

### E. 驗證實驗（重點！）
10. 後臺建一個測試使用者 + 一門課 → 到 Render 按 **Restart** → 重新登入。
    **帳號和課程還在** ✅ ＝「DB 持久」這半成立（資料在 Supabase）。
11. 上傳一個檔案到課程 → **Restart** → 檔案**不見** ❌ ＝ `moodledata` 短暫，這就是需要階段二的原因。

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

> 這些正是為什麼正式上線建議走 `../`（自架 + Tailscale Funnel）或 Oracle。實驗歸實驗，玩得開心 🙂
