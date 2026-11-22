
## Lunar

The shop runs on Lunar 1.x with its own admin panel (Filament 4) at `/admin`.

- First setup, once the stack is up: `docker compose exec laravel php artisan migrate`, then `docker compose exec laravel php artisan lunar:install` (default language, currency, channel, tax class and the first admin).
- Lunar's configuration is not published; `docker compose exec laravel php artisan vendor:publish --tag=lunar` publishes it when it needs changing.
- There is no Lunar guideline layer yet (`package/lunar`): the rules for Lunar code are written from the first Lunar project. Until then Laravel's rules apply, and a Lunar-specific decision is recorded in an ADR.
