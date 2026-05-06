FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache mod_rewrite (if needed for clean URLs)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for required folders
RUN chown -R www-data:www-data /var/www/html/uploads /var/www/html/cache /var/www/html/logs

# Expose port 80
EXPOSE 80
