# laravel/base: design notes

Notes for the people who maintain the layer. This file is not copied into projects (the builder copies `files/`, `merge/` and `append/` only); the application's own README is `files/README.md`.

## Money

`App\Domain\ValueObjects\{Money, Currency, CurrencyMismatchException, MoneyFormatter}` (framework-free, tested in `tests/Unit/Domain/ValueObjects`) and `App\Infrastructure\Casts\MoneyCast` with `config/money.php` (tested in `tests/Feature/Casts`) make the money rules of the guidelines hold out of the box (`framework/laravel/architecture.md#money`, `general/domain.md#money`).

**Why an own class and not a library.** `general/dependencies.md` says to use a maintained library when one fits the framework. `moneyphp/money` does fit, but the rules are small and strict: integer minor units, arithmetic only within one currency, one rounding mode applied in one place, no float anywhere (not even for display). A general-purpose library has float and numeric-string entry points those rules forbid, so each use would be wrapped in the same checks. The own class is about 200 lines with exact `BcMath\Number` arithmetic, `allocate()` and tests. A project that prefers `moneyphp/money` keeps `Money`'s API and wraps the library behind it; a project that needs another rounding mode (half-even for a tax rule) changes `Money::ROUNDING` and records it in its money ADR (the guideline asks every project that stores money for one).

Design points:

- `Money` has no float anywhere: `fromDecimalString()` is the one place that turns major into minor units and refuses surplus fraction digits; `toDecimalString()` is the one place back. `multiply()` takes an int, a decimal string or a `BcMath\Number` and is the only operation that rounds, once, with `Money::ROUNDING`. Integer overflow throws `ArithmeticError` instead of becoming a float.
- `Currency` is a backed enum of the currencies the company works in with their exponent and symbol; a project adds a case.
- `MoneyCast` reads a `<attribute>_currency` sibling column and falls back to `config('money.default_currency')` (`MONEY_DEFAULT_CURRENCY`); `MoneyCast::class . ':config'` is for tables without a currency column.
- `MoneyFormatter` builds the string from integer and decimal-string parts with a small locale table (`cs`, `sk`, `de`, `en`); it never calls ICU with a float.

## Trusted proxies

`TRUSTED_PROXIES` is read by `config/proxy.php` and applied by `App\Infrastructure\Http\Middleware\TrustProxies`, which replaces the framework's middleware in `bootstrap/app.php`. Empty trusts nobody (the host NGINX passes the scheme and the client address as FastCGI parameters); `*` is refused. The framework's own middleware reads `config('trustedproxy.proxies')`, which the project does not define. `bootstrap/app.php` exists twice (here and in `laravel/inertia-vue`, which adds the Inertia middleware and the preload cap): keep the `TrustProxies` replacement in both.

## Production healthcheck

`docker/production/healthcheck` combines two signals: the Supervisor programs and a FastCGI request for `/up` through `cgi-fcgi` (apk package `fcgi`, installed in the `base` stage next to Supervisor; present in the Alpine of `php-fpm-laravel:8.5`). php-fpm sends no `Status:` header for a 200, so the script reads the missing header together with a `Content-type:` header as a 200.

## pnpm hardening

`pnpm-workspace.yaml` has `minimumReleaseAge: 1440`, an empty `allowBuilds` (a clean install of every variant reports no ignored build script) and an empty `auditConfig.ignoreGhsas` (`pnpm audit` is clean; an entry needs a reason and a review date). `.npmrc` stays because the production Dockerfile copies it, but carries no settings: pnpm 12 reads only auth, registry, TLS, proxy and `local-address` from it.
