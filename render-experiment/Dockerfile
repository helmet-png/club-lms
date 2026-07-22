# 【實驗版】Moodle on Render 免費 Web Service + Supabase Postgres
# 跟正式版(../Dockerfile)不同處：改用 PostgreSQL 擴充，且 Moodle 原始碼「烤進映像」
# （Render 免費版沒有持久磁碟，不能用掛載，程式碼必須包在 image 裡）
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpng-dev libjpeg-dev libfreetype6-dev \
      libicu-dev libzip-dev libxml2-dev libxslt-dev \
      libonig-dev libsodium-dev libpq-dev \
      git unzip curl \
 && rm -rf /var/lib/apt/lists/*

# Moodle 需要的 PHP 擴充（注意：pgsql 而非 mysqli）
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
      gd intl zip pgsql soap opcache exif mbstring xsl sodium

COPY php/moodle.ini /usr/local/etc/php/conf.d/moodle.ini
RUN a2enmod rewrite

# Moodle 原始碼烤進映像
ARG MOODLE_BRANCH=MOODLE_405_STABLE
RUN git clone --branch ${MOODLE_BRANCH} --depth 1 \
      https://github.com/moodle/moodle.git /var/www/html

# 自訂外掛：開一個能建 URL 活動(含打卡/日期解鎖)的 web service
COPY plugins/local_clubws /var/www/html/local/clubws

COPY check-installed.php /usr/local/bin/check-installed.php
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
