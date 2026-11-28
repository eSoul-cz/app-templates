# Laravel Pulse

[Laravel Pulse](https://laravel.com/docs/pulse) is the monitoring dashboard of this application: servers (CPU, memory, disk), queues, cache, slow requests, jobs,
queries and outgoing requests, and exceptions. It was added by the `pulse` addon of the eSoul app templates (`.esoul/template.json` lists it).

## Open the dashboard

The dashboard is at `/pulse` (`PULSE_PATH` changes the path). In the Filament admin it has a navigation item, **Pulse**, which links to it. Not everyone may open it: the
`viewPulse` gate of `App\Domain\Providers\PulseServiceProvider` allows

- the administrators (users whose `is_admin` attribute is true; only variants with the Filament admin have that column), and
- the users whose email is in `PULSE_VIEWERS` (comma separated, case does not matter).

Everyone else, guests included, gets `403`. To change who may look, edit the gate in the provider; the rest of Pulse stays as it is.

## Settings

| Key | Meaning |
|---|---|
| `PULSE_ENABLED` | Master switch of the recorders. `false` stops recording (the dashboard still opens). `phpunit.xml` sets it to `false` |
| `PULSE_VIEWERS` | Emails that may open `/pulse` besides the administrators. Read by `config/pulse.php` (`viewers`) |
| `PULSE_PATH`, `PULSE_DOMAIN` | Where the dashboard lives; see below for the CMS path layout |
| `PULSE_STORAGE_KEEP`, `PULSE_INGEST_KEEP` | How long Pulse keeps its data (default 7 days) |

`config/pulse.php` is the package's published configuration, plus the `viewers` key. The rest of the keys (recorders, sample rates, thresholds) are Pulse's own;
see the documentation linked above.

## The worker

Pulse needs one more process, `php artisan pulse:check`, which samples the server (CPU, memory, disk). The production image runs it as the Supervisor program
`laravel-pulse` (`docker/production/supervisor-pulse.conf`, copied by `docker/production/Dockerfile`), next to php-fpm, the queue worker and the scheduler. The container's
healthcheck treats it like them: when it is not `RUNNING` the container is unhealthy. Every container of a stack runs its own `pulse:check`, which Pulse shows as a separate
server. Locally the dashboard works without the worker; run `php artisan pulse:check` in a second terminal to see the server cards.

## Deploy

- The migration `database/migrations/2026_11_28_000001_create_pulse_tables.php` creates the `pulse_*` tables (SQLite, MariaDB, MySQL and PostgreSQL are supported). The production
  container runs migrations at start where `RUN_MIGRATIONS=true`, so nothing is done by hand.
- **Rebuild the image**: the Supervisor program is part of it (`COPY ... pulse.conf`), and a Composer requirement (`laravel/pulse`) changes the dependencies.
- Set `PULSE_VIEWERS` (and `PULSE_ENABLED` when it is to be switched off) in the stack's environment and name them in the pull request's deploy notes.
- NGINX has to pass `/pulse` to php-fpm. A host NGINX that routes only some paths to the backend (the CMS site of the `astro-cms` monorepo: `docker/nginx.example`) needs
  the `/pulse` location, which the addon adds to that repository (`astro-cms` takes the `pulse` addon too). Livewire's script and update endpoint (`/livewire-<hash>/...`) are
  passed already.
- **Backend under `/admin` of the site's domain** (`CMS_ADMIN_PATH_MODE=true`, `SESSION_PATH=/admin`): the session cookie is only sent under `/admin`, so `/pulse` would
  refuse everyone. Set `PULSE_PATH=admin/pulse` (and keep the admin path in nginx as it is).
- After a deploy with a changed Pulse configuration the worker restarts with the container; outside a container run `php artisan pulse:restart`.
- Pulse keeps its data in the application database and trims it itself. A busy application can move it to its own connection (`PULSE_DB_CONNECTION`) or to Redis
  ingest (`PULSE_INGEST_DRIVER=redis`); that is a project decision.

## Tests

`tests/Feature/Addons/PulseDashboardTest.php` covers the gate: a guest and a user who is not a viewer get `403`, a listed viewer gets `200`. The Filament variants also have
`PulseNavigationTest` for the navigation item.
