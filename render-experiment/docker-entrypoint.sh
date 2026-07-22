#!/usr/bin/env bash
# 每次開機：重建 config.php（檔案系統短暫）、必要時建表、啟動 Apache
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
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';
\$CFG->directorypermissions = 02777;
\$CFG->dbsessions = true;                 // session 存進 DB → 重啟不掉登入
\$CFG->cronremotepassword = getenv('MOODLE_CRON_PASS');
require_once(__DIR__.'/lib/setup.php');
PHP

mkdir -p /var/www/moodledata
chown -R www-data:www-data /var/www/moodledata /var/www/html

# 首次部署自動建表；已安裝則略過（install_database 會自己拒絕）
php /var/www/html/admin/cli/install_database.php \
    --lang=zh_tw --adminuser=admin \
    --adminpass="$(printenv MOODLE_ADMIN_PASS)" \
    --adminemail="$(printenv MOODLE_ADMIN_EMAIL)" \
    --fullname="社團學習平臺(實驗)" --shortname="ClubLMS" \
    --agree-license 2>/dev/null || echo ">> DB 已安裝或略過，繼續啟動"

exec apache2-foreground
