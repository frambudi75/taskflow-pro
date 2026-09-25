#!/bin/sh
set -e

# Ensure storage directories exist with full permissions on live volume
mkdir -p /var/www/html/uploads/settings \
         /var/www/html/uploads/avatars \
         /var/www/html/uploads/chat \
         /var/www/html/cache \
         /var/www/html/logs

chown -R www-data:www-data /var/www/html/uploads /var/www/html/cache /var/www/html/logs 2>/dev/null || true
chmod -R 777 /var/www/html/uploads /var/www/html/cache /var/www/html/logs 2>/dev/null || true

exec apache2-foreground "$@"
