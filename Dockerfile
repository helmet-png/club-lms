# Moodle 執行環境 — 用官方多架構基底 php:8.3-apache，arm64 原生跑得動
# 不依賴 Bitnami（其免費映像目錄 2025 起有變動、arm64 支援也不穩）
FROM php:8.3-apache

# --- PHP 擴充需要的系統函式庫 ---
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpng-dev libjpeg-dev libfreetype6-dev \
      libicu-dev libzip-dev libxml2-dev libxslt-dev \
      libonig-dev libsodium-dev \
      git unzip \
 && rm -rf /var/lib/apt/lists/*

# --- Moodle 需要的 PHP 擴充 ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
      gd intl zip mysqli soap opcache exif mbstring xsl sodium

# --- Moodle 建議的 PHP 設定 ---
COPY php/moodle.ini /usr/local/etc/php/conf.d/moodle.ini

# --- Apache 開 rewrite（Moodle 乾淨網址需要）---
RUN a2enmod rewrite

WORKDIR /var/www/html
