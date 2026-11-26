# zero-debt-laravel

A Laravel 13 API starter at the end state of the zero-debt kit: strict types, a single pattern per task, every check, ratchet and counter at zero from the first commit.

## Stack

PHP 8.4, Laravel 13, PostgreSQL 17, Sanctum tokens, spatie/laravel-data DTOs, spatie/laravel-permission, Pest 5, Larastan at level 10, Rector, Pint.

## Run

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Check

```sh
bash scripts/checks.sh
```

The scripts, the composer scripts and the guides (`app/AGENTS.md`, `tests/AGENTS.md`) hold the rules.
