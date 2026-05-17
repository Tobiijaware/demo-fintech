FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl unzip zip libzip-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application
COPY . .

# Normalize Windows line endings in scripts (CRLF -> LF)
RUN sed -i 's/\r$//' /var/www/scripts/*.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Set file permissions
RUN chown -R www-data:www-data /var/www \
    && chmod +x /var/www/scripts/*.sh

# Expose Laravel dev port
EXPOSE 8000

# Start Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

#CMD ["/bin/sh", "/var/www/scripts/start-web-and-queue.sh"]