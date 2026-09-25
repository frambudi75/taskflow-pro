FROM php:8.2-apache

# Install system dependencies & graphic libraries for GD and file processing
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd exif pdo_mysql zip

# Configure PHP settings (uploads, memory, timezone)
RUN echo "upload_max_filesize = 50M\n\
post_max_size = 55M\n\
memory_limit = 256M\n\
max_execution_time = 300\n\
date.timezone = Asia/Jakarta" > /usr/local/etc/php/conf.d/uploads.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create necessary upload & cache folders and set proper permissions
RUN mkdir -p /var/www/html/uploads/settings \
    /var/www/html/uploads/avatars \
    /var/www/html/uploads/chat \
    /var/www/html/cache \
    /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/cache /var/www/html/logs \
    && chmod -R 775 /var/www/html/uploads /var/www/html/cache /var/www/html/logs

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]


