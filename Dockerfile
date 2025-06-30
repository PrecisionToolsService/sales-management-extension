FROM php:8.2-cli

# 基本ツールと Node.js, npm のインストール
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install zip

# Composer（任意：EspoCRMには必須ではないが拡張開発に便利）
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Grunt CLI グローバルインストール（Espo拡張テーマで使用）
RUN npm install -g grunt-cli

WORKDIR /app
