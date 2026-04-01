#!/bin/bash
# Copyright (c) 2026 Michael D Henderson. All rights reserved.
# /opt/gamehub/deploy.sh

set -e

export PATH="/home/deploy/.bun/bin:$PATH"

cd /var/www/gamehub

# Pull latest changes
git pull origin main

# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Clear stale caches so Wayfinder sees new routes during build
php artisan optimize:clear

# Install JS dependencies and build
bun install
bun run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue worker
sudo systemctl restart gamehub-queue

# Reload PHP-FPM (zero-downtime)
sudo systemctl reload php8.4-fpm

echo "✅ Deployed successfully."
exit 0
