# Club LMS

A self-hostable learning platform for a school club / debate society, built on **Moodle**. This repo is a deployment package — it gives you a running Moodle with sensible defaults, without rebuilding an LMS from scratch.

Requirements it covers out of the box: check-in via activity completion, three-tier roles (member / officer / president), course approval, a leaderboard (Level Up!), weekly assignments, and quizzes.

> 中文版請往下捲：**[繁體中文說明](#繁體中文)**

## Two deployment paths

| Path | Where it runs | Cost | Best for |
|------|---------------|------|----------|
| **Self-host** (root of repo) | Any Linux VM (Oracle Cloud Always Free, a cheap VPS, or your own always-on machine) | Free–cheap | Real, persistent, production use |
| **Render + Supabase** (`render-experiment/`) | Render free web service + Supabase free Postgres | Free, no credit card | An experiment / demo. Not for production |

Both run the **same Moodle**; they differ only in where the app and database live.

## Stack

- **App:** Moodle 4.5 LTS on PHP 8.3 + Apache (official multi-arch base image, ARM64-native)
- **Database:** MariaDB 11 (self-host) or PostgreSQL / Supabase (experiment)
- **Container:** Docker + Docker Compose

## Quick start

- **Self-host:** follow [`部署與設定步驟.md`](部署與設定步驟.md) — install Docker on the VM, transfer this folder, `cp .env.example .env`, then `./setup.sh`, then run the web installer.
- **Render experiment:** follow [`render-experiment/README-實驗步驟.md`](render-experiment/README-實驗步驟.md) — set up Supabase, point Render at the `render-experiment` subdirectory, set the env vars, deploy.

## Repo layout

```
Dockerfile, docker-compose.yml, setup.sh   # self-host (Oracle / VPS) — MariaDB, bind-mounted Moodle
php/moodle.ini                              # PHP tuning for Moodle
部署與設定步驟.md                            # self-host guide (roles, check-in, leaderboard, approval, cron)
render-experiment/                          # Render + Supabase experiment — Postgres, baked-in Moodle
```

## Status

The self-host path is the recommended one for anything real. The Render + Supabase path is a working experiment, but Render's free tier spins down when idle, has no persistent disk (uploaded files are ephemeral), and limited CPU/RAM — fine for trying it out, not for members to rely on.

---

<a name="繁體中文"></a>

# 繁體中文

以 **Moodle** 為底的社團／辯論社學習平臺,自架用。這個 repo 是一份**部署包**——讓你快速跑起一套設定好的 Moodle,不用從零重寫一個 LMS。

開箱即涵蓋的需求:點擊即打卡(完成度追蹤)、三級權限(社員／幹部／社長)、課程審核、學習排行榜(Level Up!)、每週作業派發、測驗。

## 兩種部署路線

| 路線 | 跑在哪 | 費用 | 適合 |
|------|--------|------|------|
| **自架**(repo 根目錄) | 任一 Linux VM(Oracle Cloud 永久免費、便宜 VPS,或你自己的常開電腦) | 免費～便宜 | 正式、資料持久的長期使用 |
| **Render + Supabase**(`render-experiment/`) | Render 免費 web service + Supabase 免費 Postgres | 免費、免信用卡 | 實驗／展示,**不適合正式上線** |

兩者跑的是**同一套 Moodle**,差別只在 App 和資料庫放在哪。

## 技術棧

- **App:** Moodle 4.5 LTS,PHP 8.3 + Apache(官方多架構映像,ARM64 原生)
- **資料庫:** MariaDB 11(自架)或 PostgreSQL / Supabase(實驗版)
- **容器:** Docker + Docker Compose

## 快速開始

- **自架:** 照 [`部署與設定步驟.md`](部署與設定步驟.md)——在 VM 裝 Docker、傳這個資料夾、`cp .env.example .env`、跑 `./setup.sh`,再跑安裝精靈。
- **Render 實驗:** 照 [`render-experiment/README-實驗步驟.md`](render-experiment/README-實驗步驟.md)——設定 Supabase、把 Render 的 Root Directory 指到 `render-experiment`、填環境變數、部署。

## 目錄結構

```
Dockerfile, docker-compose.yml, setup.sh   # 自架(Oracle / VPS)—— MariaDB、掛載式 Moodle
php/moodle.ini                              # Moodle 用的 PHP 調校
部署與設定步驟.md                            # 自架完整指南(角色、打卡、排行榜、審核、cron)
render-experiment/                          # Render + Supabase 實驗版 —— Postgres、Moodle 烤進映像
```

## 現況

正式用途請走**自架**路線。Render + Supabase 路線是可運作的實驗,但 Render 免費機閒置會休眠、沒有持久磁碟(上傳檔案會消失)、CPU/RAM 也有限——拿來試玩可以,不適合讓社員長期依賴。
