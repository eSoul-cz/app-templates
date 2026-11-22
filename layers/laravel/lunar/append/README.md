
## Lunar

The shop runs on Lunar 1.x with its own admin panel (Filament 4) at `/admin`, registered by `App\Domain\Providers\Filament\LunarPanelProvider`.

- First setup, once the stack is up: `docker compose exec laravel php artisan migrate`, then `docker compose exec laravel php artisan lunar:install` (default language, currency, channel, tax class and the first admin).
- Lunar's configuration is not published, except `config/lunar/cart.php`, which swaps Lunar's `CalculateLines` cart step for the project's integer-arithmetic one (`App\Infrastructure\Lunar\Cart\CalculateLines`: minor units, rounded half away from zero). `docker compose exec laravel php artisan vendor:publish --tag=lunar` publishes the rest when it needs changing and leaves that file alone.
- A currency the shop sells in must be a case of `App\Domain\ValueObjects\Currency`.
