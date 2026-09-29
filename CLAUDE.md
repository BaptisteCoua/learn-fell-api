# CINQ — API

Laravel 13 backend of CINQ, consumed by the `web` repo (Nuxt PWA). Part of the
`learn-fell-workspace` spec-kit workspace: open sessions at the workspace root.

## Stack

- Laravel 13, PHP 8.5, PostgreSQL, Redis, Mailpit — all run through Laravel Sail (Docker). PHP is not installed on the host.
- Architecture: `xefi/laravel-osdd`. There is no `app/`, `database/` or root `config/`: code lives in layers under `functional/<layer>/` (business domains: `users`, `catalog`, `moderation`, `learning`, `reminders`) and `technical/<layer>/` (cross-cutting: `osdd`). Each layer is a Composer package with its own `src/`, `database/`, `routes/api.php`, `config/`, `lang/` and `tests/`.
- Generate code with the `osdd:*` mirrors of `make:*`, always with `--layer=functional/<layer>` (for example `osdd:model`, `osdd:migration`, `osdd:test`). Create a layer with `osdd:layer functional/<name> --target-path=/var/www/html/functional --generators=service-provider --generators=test --generators=routes`, then `osdd:phpunit`.
- Layer config overrides go in the layer provider's `register()`, so packages read them before they boot.
- CRUD endpoints via `lomkit/laravel-rest-api`, authorization via `lomkit/laravel-access-control`, roles and permissions stored with `spatie/laravel-permission` (checks by permission only), factories with `xefi/faker-php-laravel`.
- Authentication: Sanctum SPA sessions with Fortify in headless mode.

## Run

```bash
./vendor/bin/sail up -d           # app on http://localhost:8090, Mailpit on http://localhost:8035
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan osdd:seed
./vendor/bin/sail artisan queue:work    # new questions reach their subject's learners, and reminders go out, through queued jobs
./vendor/bin/sail artisan schedule:work # reminders:dispatch every minute, model:prune every day
```

Review reminders (`functional/reminders`) need VAPID keys for web push: `./vendor/bin/sail artisan webpush:vapid` writes them into `.env`, and the web app needs the same public key as `NUXT_PUBLIC_VAPID_PUBLIC_KEY`. Keep the keys once devices have subscribed: new keys lose every device. To send the reminders of a given minute without waiting, `./vendor/bin/sail artisan reminders:dispatch --now="2026-09-26 17:00"` (UTC) sends them right away, outside the queue.

Every `php`, `composer`, `artisan` and `npm` command goes through `./vendor/bin/sail`.

## Test

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail bin pint --dirty
```

## Commits

Imperative English subject line, no AI attribution trailer.

## Boost guidelines

@AGENTS.md
