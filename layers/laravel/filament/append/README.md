
## Admin panel (Filament)

The Filament panel is served at `/admin` and themed from `resources/css/filament/admin/theme.css`, a Vite entry (`pnpm build` builds it, `pnpm dev` serves it).

- Create a user with `docker compose exec laravel php artisan make:filament-user`, then give it access with `docker compose exec laravel php artisan admin:promote <email>`. Only users with `users.is_admin` set can open the panel.
- Regenerate the theme with `php artisan make:filament-theme admin --pm=pnpm`; without `--pm` Filament uses npm and creates an npm lockfile.
