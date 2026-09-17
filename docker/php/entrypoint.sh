#!/bin/sh
set -e

# storage/ va bootstrap/cache/ Windows bind-mount orqali keladi, shuning
# uchun image qurish vaqtidagi chown/chmod runtime'da ko'rinmaydi - bu yerda
# har bir konteyner start bo'lganda qayta bajariladi.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# setgid bit: shu papkalar ichida keyinchalik (masalan "docker compose exec
# app composer ..." root sifatida) yaratiladigan yangi fayl/papkalar ham
# avtomatik www-data guruhiga tegishli bo'lib qoladi, aks holda ular
# root:root bo'lib qolib, php-fpm (www-data) ularga yoza olmay qoladi.
find storage bootstrap/cache -type d -exec chmod g+s {} +

exec docker-php-entrypoint "$@"
