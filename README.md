# Oyna-Rom CRM

Oyna va rom (deraza romi) sotuvchi tashkilot uchun buyurtmalarni raqamlashtirish tizimi.
Ishchilar Telegram bot orqali buyurtma kiritadi, admin esa web panel (Filament) orqali
narxni belgilaydi va buyurtmalarni kuzatadi.

## Texnologik stek

- Laravel 11 (PHP 8.3)
- Filament 3 — admin panel
- defstudio/telegraph — Telegram bot
- MySQL 8

## Lokal ishga tushirish (Docker)

Loyiha Docker Compose orqali ishlaydi (PHP-FPM + Nginx + MySQL):

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
```

Admin panel: http://localhost:8080/admin

Artisan buyruqlarini shu tarzda ishlatish mumkin:

```bash
docker compose exec app php artisan <buyruq>
```
