# Learn Fell — API

Laravel 13 backend of Learn Fell, consumed by the `web` repo (Nuxt PWA). Part of the
`learn-fell-workspace` spec-kit workspace: open sessions at the workspace root.

## Stack

- Laravel 13, PHP 8.5, PostgreSQL, Redis — all run through Laravel Sail (Docker). PHP is not installed on the host.
- Architecture: `xefi/laravel-osdd` layers under `layers/`, one per domain.
- CRUD endpoints via `lomkit/laravel-rest-api`, authorization via `lomkit/laravel-access-control`, roles and permissions stored with `spatie/laravel-permission`, factories with `xefi/faker-php-laravel`.

## Run

```bash
./vendor/bin/sail up -d           # app on http://localhost:8090
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev     # Vite on port 5180
```

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
