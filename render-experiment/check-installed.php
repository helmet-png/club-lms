<?php
// 快速判斷 Moodle 是否已安裝（只查一張表，不 bootstrap Moodle）
$c = @pg_connect(sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    getenv('MOODLE_DB_HOST'), getenv('MOODLE_DB_PORT') ?: '5432',
    getenv('MOODLE_DB_NAME'), getenv('MOODLE_DB_USER'), getenv('MOODLE_DB_PASS')
));
if (!$c) { echo 'no'; exit; }
$r = @pg_query($c, "SELECT to_regclass('public.mdl_config')");
echo ($r && pg_fetch_result($r, 0, 0)) ? 'yes' : 'no';
