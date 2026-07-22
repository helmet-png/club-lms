#!/usr/bin/env bash
# 在 Oracle VM (Ubuntu ARM) 上跑這支，一鍵下載 Moodle+外掛並啟動。
set -euo pipefail

MOODLE_BRANCH="${MOODLE_BRANCH:-MOODLE_405_STABLE}"   # 4.5 LTS；要更新版就改這行

# 1) Moodle 原始碼
if [ ! -d moodle ]; then
  echo ">> 下載 Moodle ($MOODLE_BRANCH)..."
  git clone --branch "$MOODLE_BRANCH" --depth 1 https://github.com/moodle/moodle.git
fi

# 2) Level Up! 排行榜/XP 外掛 (block_xp)
if [ ! -d moodle/blocks/xp ]; then
  echo ">> 下載 Level Up! 排行榜外掛..."
  git clone --depth 1 https://github.com/branchup/moodle-block_xp.git moodle/blocks/xp
fi

# 3) 建置並啟動
echo ">> 建置並啟動容器（第一次會比較久）..."
docker compose up -d --build

# 4) 權限：Apache 以 www-data(uid 33) 執行，需可寫 config.php 與 moodledata
echo ">> 設定權限..."
docker compose exec -T moodle chown -R www-data:www-data /var/www/html /var/www/moodledata

echo ""
echo "===== 完成 ====="
echo "瀏覽器打開  http://<你的VM公網IP>  跑安裝精靈"
echo "安裝精靈資料庫頁填：類型=MariaDB  主機=db  資料庫/帳號/密碼=見 .env"
echo "資料目錄(moodledata)填： /var/www/moodledata"
