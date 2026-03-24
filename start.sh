#!/bin/sh
set -e

echo "🚀 Starting Laravel..."

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run fresh migrations and seeders
php artisan migrate:fresh --force
php artisan db:seed --force

# Cache for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start server
php artisan serve --host=0.0.0.0 --port=10000
