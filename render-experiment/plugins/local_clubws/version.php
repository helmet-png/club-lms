<?php
// Club LMS 自訂 web service：建立 URL 活動（含完成度打卡、日期解鎖、自動補區塊）
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_clubws';
$plugin->version   = 2026072203;
$plugin->requires  = 2024041600;   // Moodle 4.4+（含 core_external 命名空間），4.5 適用
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
