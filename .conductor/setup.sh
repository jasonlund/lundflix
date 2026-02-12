#!/bin/bash
set -e

# =============================================================================
# Conductor Workspace Setup Script
# =============================================================================

FOLDER=$(basename "$PWD")

# -----------------------------------------------------------------------------
# Environment Configuration
# -----------------------------------------------------------------------------
cp ~/conductor/repos/lundflix/.env .env
sed -i '' "s|^APP_URL=.*|APP_URL=https://${FOLDER}.test|" .env
sed -i '' "s|^DB_DATABASE=.*|DB_DATABASE=${FOLDER}|" .env

# -----------------------------------------------------------------------------
# Database Setup
# -----------------------------------------------------------------------------
mysql -uroot -e "DROP DATABASE IF EXISTS \`${FOLDER}\`; CREATE DATABASE \`${FOLDER}\`"

# -----------------------------------------------------------------------------
# Herd Configuration
# -----------------------------------------------------------------------------
herd link --secure

# Fix Herd bug: herd link generates nginx configs with empty server.php paths
NGINX_CONF="$HOME/Library/Application Support/Herd/config/valet/Nginx/${FOLDER}.test"
SERVER_PHP="/Applications/Herd.app/Contents/Resources/valet/server.php"
if [ -f "$NGINX_CONF" ]; then
    sed -i '' "s|rewrite ^ \"\" last;|rewrite ^ \"$SERVER_PHP\" last;|g" "$NGINX_CONF"
    sed -i '' "s|error_page 404 \"\";|error_page 404 \"$SERVER_PHP\";|g" "$NGINX_CONF"
    sed -i '' "s|fastcgi_index \"\";|fastcgi_index \"$SERVER_PHP\";|g" "$NGINX_CONF"
    sed -i '' "s|fastcgi_param SCRIPT_FILENAME \"\";|fastcgi_param SCRIPT_FILENAME \"$SERVER_PHP\";|g" "$NGINX_CONF"
fi

# -----------------------------------------------------------------------------
# Composer Setup
# -----------------------------------------------------------------------------
ln -sf ~/Herd/lundflix/auth.json auth.json
composer install --no-interaction

# -----------------------------------------------------------------------------
# Laravel Setup
# -----------------------------------------------------------------------------
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
php artisan storage:link --no-interaction

# -----------------------------------------------------------------------------
# Frontend Build
# -----------------------------------------------------------------------------
npm install
npm run build

# -----------------------------------------------------------------------------
# Search Index Setup
# -----------------------------------------------------------------------------
php artisan scout:sync-index-settings --no-interaction
php artisan scout:import 'App\Models\Movie' --no-interaction
php artisan scout:import 'App\Models\Show' --no-interaction
