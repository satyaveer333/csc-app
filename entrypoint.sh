#!/bin/bash
set -e

# Ensure data and upload directories exist
mkdir -p /var/www/html/data /var/www/html/uploads/prints /var/www/html/uploads/forms

# Fix ownership and permissions for www-data (Apache user)
chown -R www-data:www-data /var/www/html/data /var/www/html/uploads 2>/dev/null || true
chmod -R 777 /var/www/html/data /var/www/html/uploads 2>/dev/null || true

# Execute CMD (apache2-foreground)
exec "$@"
