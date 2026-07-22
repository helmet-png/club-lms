#!/usr/bin/env bash
# 每次開機：重建 config.php、Apache 立刻開 port，DB 安裝丟背景跑（首次數分鐘）
set -e

export PGSSLMODE=require                 # Supabase 強制 SSL
PORT="${PORT:-80}"                       # Render 會指定 $PORT

# Apache 監聽 Render 指定的埠
sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/"              /etc/apache2/sites-available/000-default.conf

# 用環境變數重建 config.php（DB 在 Supabase 是持久的，config 每次生成即可）
cat > /var/www/html/config.php <<PHP
<?php
unset(\$CFG); global \$CFG; \$CFG = new stdClass();
\$CFG->dbtype    = 'pgsql';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = getenv('MOODLE_DB_HOST');
\$CFG->dbname    = getenv('MOODLE_DB_NAME');
\$CFG->dbuser    = getenv('MOODLE_DB_USER');
\$CFG->dbpass    = getenv('MOODLE_DB_PASS');
\$CFG->dbport    = getenv('MOODLE_DB_PORT') ?: '5432';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array('dbpersist'=>0, 'dbsocket'=>0);
\$CFG->wwwroot   = getenv('MOODLE_WWWROOT');
\$CFG->sslproxy  = true;                  // Render 在前面處理 HTTPS，轉進來是 http → 否則會無限轉址
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';
\$CFG->directorypermissions = 02777;
\$CFG->dbsessions = true;                 // session 存進 DB → 重啟不掉登入
\$CFG->cronremotepassword = getenv('MOODLE_CRON_PASS');
require_once(__DIR__.'/lib/setup.php');
PHP

mkdir -p /var/www/moodledata
chown -R www-data:www-data /var/www/moodledata /var/www/html

# 背景初始化：需要就安裝 DB；每次開機補中文語言包（moodledata 短暫，重啟會掉回英文）
# 全部丟背景，讓 Apache 立刻開 port，避免 Render 逾時砍容器
(
  if [ "$(php /usr/local/bin/check-installed.php 2>/dev/null)" != "yes" ]; then
    echo ">> 背景開始安裝 Moodle（首次數分鐘，請耐心）..."
    php /var/www/html/admin/cli/install_database.php \
        --lang=zh_tw --adminuser=admin \
        --adminpass="$(printenv MOODLE_ADMIN_PASS)" \
        --adminemail="$(printenv MOODLE_ADMIN_EMAIL)" \
        --fullname="社團學習平臺(實驗)" --shortname="ClubLMS" \
        --agree-license \
      || echo ">> ⚠️ 安裝程序結束（可能已安裝或發生錯誤）"
  else
    echo ">> 資料庫已安裝，跳過安裝"
  fi

  echo ">> 補中文語言包 (zh_tw)..."
  php /var/www/html/admin/cli/install_langpack.php --lang=zh_tw 2>/dev/null \
    || php /var/www/html/admin/cli/install_langpack.php zh_tw 2>/dev/null \
    || echo ">> ⚠️ 中文語言包安裝略過"
  php /var/www/html/admin/cli/purge_caches.php 2>/dev/null || true
  echo ">> ✅ 背景初始化完成，重新整理網頁即可"
) &

exec apache2-foreground
