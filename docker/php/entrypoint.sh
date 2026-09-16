#!/bin/sh
set -e

chmod -R 777 storage bootstrap/cache

exec docker-php-entrypoint "$@"
