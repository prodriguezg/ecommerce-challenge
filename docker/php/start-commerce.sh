#!/bin/sh
set -eu

php artisan migrate --force
php artisan db:seed --force
exec php artisan serve --host=0.0.0.0 --port=8000
