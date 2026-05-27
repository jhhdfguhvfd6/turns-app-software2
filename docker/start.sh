#!/bin/sh
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
