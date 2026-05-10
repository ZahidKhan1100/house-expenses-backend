FROM php:8.4-cli
WORKDIR /var/www/html

# Composer warns when running as root during image build — normal for Docker builds.
ENV COMPOSER_ALLOW_SUPERUSER=1

# System deps — libicu-dev required to compile ext-intl (filament/support and friends).
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    libonig-dev libxml2-dev libzip-dev libicu-dev \
 && rm -rf /var/lib/apt/lists/*

# Compile intl explicitly first so ICU is linked cleanly, then remaining extensions.
RUN docker-php-ext-install -j "$(nproc)" intl \
 && docker-php-ext-install -j "$(nproc)" pdo pdo_mysql mbstring xml zip bcmath \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && php -r "extension_loaded('intl') || exit(1);"

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy all files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-interaction

# Set permissions for Laravel
RUN mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Start Laravel server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
