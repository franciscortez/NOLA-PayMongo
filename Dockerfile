# Stage 1: Build assets (Node/NPM)
FROM node:20-slim AS asset-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Install PHP dependencies (Composer) - match PHP runtime
FROM php:8.4-cli AS composer-builder
WORKDIR /app

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install git and unzip
RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*

# Copy composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --no-progress \
  --no-scripts \
  --no-autoloader

# Copy the rest of the app
COPY . .

# Re-optimize autoloader (optional but common)
RUN composer dump-autoload --optimize --classmap-authoritative


# Stage 3: Final Production Image
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Opcache
RUN docker-php-ext-enable opcache
COPY ./docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Apache
RUN a2enmod rewrite headers

WORKDIR /var/www/html

# Copy app + vendor from composer stage
COPY --from=composer-builder /app /var/www/html

# Copy built assets
COPY --from=asset-builder /app/public/build /var/www/html/public/build

# Laravel permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
 && chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache

# Apache DocumentRoot for Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Cloud Run: listen on 8080 (don’t try to use ${PORT} in apache config)
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Logs to stdout/stderr
RUN ln -sf /dev/stderr /var/log/apache2/error.log \
 && ln -sf /dev/stdout /var/log/apache2/access.log

ENV PORT=8080
ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 8080

# Optional hardening: run as www-data (works since we’re on 8080)
USER www-data

CMD ["apache2-foreground"]