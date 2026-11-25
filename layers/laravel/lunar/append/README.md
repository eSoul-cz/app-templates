
## Lunar

The shop runs on Lunar 1.x with its own admin panel (Filament 4) at `/admin`, registered by `App\Domain\Providers\Filament\LunarPanelProvider`.

- First setup, once the stack is up: `docker compose exec laravel php artisan migrate`, then `docker compose exec laravel php artisan lunar:install` (default language, currency, channel, tax class and the first admin).
- Lunar's configuration is not published, except `config/lunar/cart.php`, which swaps Lunar's `CalculateLines` cart step for the project's integer-arithmetic one (`App\Infrastructure\Lunar\Cart\CalculateLines`: minor units, rounded half away from zero). `docker compose exec laravel php artisan vendor:publish --tag=lunar` publishes the rest when it needs changing and leaves that file alone.
- A currency the shop sells in must be a case of `App\Domain\ValueObjects\Currency`.
- The panel's stylesheets, scripts and fonts are files under `public/`, published by `php artisan filament:upgrade`, which Composer runs after every install and autoload dump (`post-autoload-dump`), in the release image too. They are generated and ignored by git; a panel without styles means they are missing: run `composer dump-autoload`.
