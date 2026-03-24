#!/bin/sh
set -e

echo "🚀 Starting Laravel..."

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan migrate:fresh --force
php artisan db:seed --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan serve --host=0.0.0.0 --port=10000
