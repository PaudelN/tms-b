#!/bin/sh
set -e

echo "🚀 Deploying Laravel App..."

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies & build assets
npm install
npm run build

# Set permissions
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
