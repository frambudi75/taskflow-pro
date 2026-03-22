FROM php:7.4-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache mod_rewrite (if needed for clean URLs)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for uploads folder
RUN chown -R www-data:www-data /var/www/html/uploads

# Expose port 80
EXPOSE 80
