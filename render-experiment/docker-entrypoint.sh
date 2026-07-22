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

# 若尚未安裝，於「背景」安裝，避免擋住 Apache 開 port（Render 會逾時砍容器）
if [ "$(php /usr/local/bin/check-installed.php 2>/dev/null)" != "yes" ]; then
  echo ">> 資料庫尚未安裝，背景開始安裝 Moodle（首次數分鐘，請耐心）..."
  (
    php /var/www/html/admin/cli/install_database.php \
        --lang=zh_tw --adminuser=admin \
        --adminpass="$(printenv MOODLE_ADMIN_PASS)" \
        --adminemail="$(printenv MOODLE_ADMIN_EMAIL)" \
        --fullname="社團學習平臺(實驗)" --shortname="ClubLMS" \
        --agree-license \
      && echo ">> ✅ Moodle 安裝完成，重新整理網頁即可登入" \
      || echo ">> ⚠️ 安裝程序結束（可能已安裝或發生錯誤，看上方訊息）"
  ) &
else
  echo ">> 資料庫已安裝，直接啟動"
fi

exec apache2-foreground
