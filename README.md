# zero-debt-laravel
<!-- badges:start -->
[![Kit checks](https://github.com/NiekNijland/zero-debt-laravel/actions/workflows/kit-checks.yml/badge.svg?branch=main)](https://github.com/NiekNijland/zero-debt-laravel/actions/workflows/kit-checks.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
<!-- badges:end -->

A Laravel 13 API starter at the end state of the [zero-debt kit](https://github.com/NiekNijland/zero-debt-kit-laravel): one way per task, a guard in CI for every rule, and every counter at zero from the first commit.

## Why

Green CI does not mean a codebase has a shape. High coverage and PHPStan at a high level still leave three ways to authorize a route and a new pattern with every pull request. This starter ships the structure first, so the first feature is written inside it:

- **Architecture tests** decide the one allowed way per task: where a class lives, what it may depend on, how a controller or a test is shaped. Code that does it another way fails the build with a message that says what to do instead.
- **Ratchets** are counters for everything that can go wrong: suppressions, skips, comments, oversized files, baselines. Every counter starts at zero and may only stay there. Nothing can be added to make a build green.
- **A pipeline that cannot narrow**: a contract test reads the workflow files and fails a check that skips, a step with no time limit, an unpinned action or a missing code-owner rule.

Anyone, including an agent working unattended, can contribute, and when CI is green the code fits the patterns.

<!-- init:start -->
## Use it as a template

```sh
bash scripts/init.sh --name my-api --owner @your-github-user
```

The script asks for what you do not give it: the project name, the Composer vendor, the ticket prefix of your commit messages (`NOTICKET` when you have no ticket system), the database image, the base branch and the code owner. It rewrites `composer.json`, `.env.example`, this title, `scripts/kit-config.sh`, `scripts/ci-commit-messages.sh`, `.github/CODEOWNERS`, `renovate.json` and the workflows, refreshes the hash of `composer.lock`, and deletes itself with its test. `--dry-run` prints every change and writes nothing. Commit the result with `[ratchet: script] instantiate the template` in the message: the files it changes are guarded files.
<!-- init:end -->

## Quick start

```sh
cp .env.example .env
docker compose up -d --wait
composer install
php artisan key:generate
php artisan migrate --seed
composer test
```

`compose.yaml` starts PostgreSQL 17 with the test credentials of `.env.example` on `127.0.0.1:5432`, the port that `phpunit.xml`, `.env.example` and the CI services use. The seed creates the permissions and a demo user, `demo@example.test`, with a random password that is never printed; there is no login route, so sign in with a token:

```sh
php artisan serve
TOKEN="$(php artisan notes:issue-token demo@example.test)"
curl -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' http://localhost:8000/api/v1/notes
```

Port 5432 taken? Set `DB_PORT=5433` in `.env`: `docker compose` and the application read it. The tests pin their own database settings in `phpunit.xml` and never read `.env`, so run them as `DB_PORT=5433 composer test`.

Requirements: PHP 8.4 or later, Composer, Docker (or any PostgreSQL 17 server with the credentials of `compose.yaml`), `pcov` for the coverage floor, `jq`, and `gitleaks` for the secret scan of the checks.

| Command | What it does |
| --- | --- |
| `composer test` | the whole gate: `bash scripts/checks.sh` |
| `composer test:unit` | Pest in parallel with coverage, then the coverage floor |
| `composer test:types` | PHPStan at level 10 on source and tests |
| `composer test:type-coverage` | Pest type coverage at 100 |
| `composer test:lint` | Pint and Rector, check only |
| `composer lint` | Rector and Pint, rewriting the tree |
| `bash scripts/debt-dashboard.sh --final` | every counter against its goal |
| `bash scripts/ci-ratchets.sh --head` | every ratchet row as it stands |

## What is inside

**The application.** `app/Modules/Notes` (actions, DTOs, enums, a service) and `app/Modules/Localization` (locale and pages), with a flat framework layer: single-action controllers under `app/Http/Controllers/Api/Note`, form requests, a policy, middleware and two providers. The JSON API sits under `/api/v1` behind Sanctum tokens, with permissions from spatie/laravel-permission, DTOs from spatie/laravel-data, `can:` and `throttle:<limiter>` on every route, and localized pages at `/{locale}/{slug}`.

**The tools at their strictest.** PHPStan at level 10 with bleeding edge, Larastan, strict rules, disallowed calls, a dead-code detector and 100% type coverage; Rector with 19 sets and no skip; Pint with the 21 strictness rules; `roave/security-advisories`; Pest 5 with a hermetic base set-up, line coverage held at a committed floor and a mutation floor.

**The guards.** Architecture tests in `tests/Unit/*ArchitectureTest.php` with their detectors in `tests/Support/Architecture`, each with failing examples, passing examples and one case over the real tree:

| Kind | What fails |
| --- | --- |
| Layout and class shape | a class outside the folder table of `app/AGENTS.md`, a controller or action that is not `final readonly`, a wrong name |
| Controllers and requests | a thick controller, inline validation, a response that is no DTO |
| Authorization and limits | a route without `can:` or `throttle:`, a role name in a check |
| Models, migrations and data | a model without immutable casts, a foreign key without an index, a missing `down()` |
| Tests | a spelling other than `it()`, a test in the wrong folder, a shared fixture, a double of an own class |
| The pipeline | `CiContractArchitectureTest`: a workflow that narrows a check; `CodeOwnersArchitectureTest`: a guard file with no owner |
| The guides | `DocumentedPathArchitectureTest`: a path in a guide that is gone; `GuideRulesArchitectureTest`: a guide that lost the words of a rule; `OnePatternRubricArchitectureTest`: a row of the one-pattern table missing from the rubric |

**The ratchets, all at zero.** `scripts/ci-ratchets.sh` counts the rows of the kit's start set plus its caps: `analyser-baseline`, `analyser-ignore`, `analyser-level`, `refactor-skip`, `audit-ignore`, `audit-exceptions`, `test-skip`, `test-doubles`, `event-fake-all`, `lint-disable`, `lint-config-off`, `lint-config-ignores`, `lint-baseline`, `type-baseline`, `ts-suppressions`, `frontend-test-skip`, `comments`, `todo`, `ci-skipped-steps`, `strict-types-missing`, `loose-comparisons`, `mutable-dates`, `byte-string-functions`, `queue-bus-fake-all`, `guide-future-tense`, `unused-ignore` and one `cap-<key>` row per key of `scripts/debt-caps.json`. Their counterparts that may only go up are held at their goal: `refactor-sets` at 19, `formatter-rules` at 21, `advisory-block` at 1, `analyser-switches` at 7, `coverage-floor` and `mutation-floor` at their committed numbers. The dashboard (`scripts/debt-dashboard.sh --final`) adds files over 400 lines, open classes, classes with no test of their own, docs past their age limit and the package audit.

**The documents.** `AGENTS.md` (glossary, product areas, which source wins, house rules, the one-pattern table), `app/AGENTS.md`, `tests/AGENTS.md`, `docs/decisions/0001-one-backend-pattern.md`, `docs/review-rubric.md`, `docs/ci.md`, `docs/kit-sync.md` and `docs/guards/ci-contract.md`.

## Layout

```text
app/Modules/<Domain>/{Actions,Data,Enums,Services,...}   domain code, one module per domain
app/Http, app/Models, app/Policies, app/Providers        the flat framework layer
tests/Unit, tests/Feature                                tests that mirror the folders they cover
tests/Support/Architecture                               the detector classes of the guards
scripts/                                                 the kit's scripts with this project's config
docs/                                                    decisions, rubric, CI, kit sync
.github/workflows                                        kit-checks, kit-sync, kit-canary
```

## Staying in step with the kit

The scripts under `scripts/` are copies of the kit with this project's config block, ratchet rows and task commands. `.zero-debt-kit-version` holds one line, `<owner>/<repository>@<commit hash>`, the kit commit they derive from. A weekly workflow compares the kit's head with the pin and opens a bump pull request that brings the scripts to the kit's version and moves the pin; a nightly canary runs this project's ratchet self-test, rows and dashboard against the kit's newest scripts and reports the drift. A changed script is a guarded file: the commit carries `[ratchet: script] <reason>` and the code owner reviews it. Details and the manual steps: `docs/kit-sync.md`.

## Compared with other strict starter kits

One kit is compared, and only on what its public files say. Source: the repository `nunomaduro/laravel-starter-kit` and the README of `nunomaduro/essentials` on GitHub, read on 2026-11-26.

| | nunomaduro/laravel-starter-kit | this starter |
| --- | --- | --- |
| Static analysis | Larastan, `level: max`, bleeding edge (its README says level 9) | Larastan at level 10 with bleeding edge, strict rules, disallowed calls and a dead-code detector |
| Refactoring | Rector with six prepared sets (dead code, code quality, coding style, type declarations, privatization, early return) and ten Laravel sets, with three skips | the same six prepared sets and ten Laravel sets; no skip is allowed |
| Formatter | Pint with a `rules` list (`declare_strict_types`, `strict_comparison`, `final_class`, `date_time_immutable` among them) | Pint with the 21 strictness rules of the kit, counted by a ratchet row |
| Types and coverage | Pest type coverage at 100, line coverage required at exactly 100.0 | Pest type coverage at 100, line coverage held at a committed floor that only goes up, a mutation floor |
| Runtime defaults | `nunomaduro/essentials`: strict models, auto eager loading, immutable dates, forced HTTPS, a safe console, stray requests prevented, fake sleep | the same switches as calls in one provider, each asserted by a config test |
| Architecture | `tests/Unit/ArchTest.php` (Pest `arch()`) | `arch()` presets plus guard classes for layout, class shape, controllers, requests, models, migrations, routes and tests, each with failing examples |
| Counters | the repository root has no `scripts` folder | a ratchet script with its self-test, a dashboard with caps, and a gate that fails when a counter moves |
| CI | one workflow (`tests.yml`) on `ubuntu-latest`, actions pinned to version tags, running `composer test` | five runs, parts as a matrix, actions pinned by commit sha, time limits on every job and step, a PostgreSQL service, a smoke job, a scheduled run, and a contract test over the workflow files |
| Code owners | no `CODEOWNERS` file in the repository root or in `.github`, which holds `dependabot.yml`, `skills` and `workflows` | `.github/CODEOWNERS` over every guard file, read by a test |
| Dependency bot | Dependabot | Renovate with pinned action digests |
| Frontend | Blade, with sibling repositories for Inertia with React and with Vue | none (see below) |
| Agent files | `AGENTS.md` of Laravel Boost guidelines, and config folders for several agents | `AGENTS.md` with the one-pattern table, a review rubric, and a guard over the guides |

The two differ in what they start from. The other kit gives a strict skeleton and a runtime package; this starter is the skeleton plus the machinery that keeps it strict while many people change it.

## What is not in v1

- No frontend: no Blade views, no Inertia, no Livewire. The `FRONTEND_*` settings of the kit's scripts are empty and the frontend rows count nothing. The kit's frontend guards, layers and lint rules have no variant in this starter, and the other kit above has Blade, React and Vue repositories.
- No multi-tenancy, no queue jobs, events, mail or notifications, so the register of stored class names has no subject.
- No login route: a token is issued with `php artisan notes:issue-token <email>`, and the demo user exists outside production only.
- No container image for the CI jobs: the toolchain comes from `shivammathur/setup-php`, and the kit's skeleton pins an image by digest.
- One dashboard row has no subject in a new project: `model-strictness-violations` has no list of violations to count. `ci-duration-minutes` reads `UNKNOWN` online until the repository is hosted on GitHub and has a successful push run of `main`, and its cap is a decision row (`docs/decisions/0001-one-backend-pattern.md`).

## Proven and not proven

Proven on GitHub Actions, on `ubuntu-24.04` with PHP 8.4 and a PostgreSQL 17 service:

- a push to `main`: the four parts of the checks, the tests with the coverage floor, and the smoke job with its signed-in step;
- a pull request: the four parts with the gate against the base branch, the secret scan, the tests with the mutation run on the changed files, the smoke job and the aggregate job `checks-result`;
- the gate refusing a pull request that raises a cap that stands at 0, and accepting the same file pushed to `main` (`KIT-FINDINGS.md`, P4-1);
- the scheduled job: the full dashboard with its online rows and the mutation run over the whole tree, started from a throwaway branch;
- the kit sync (it finds the pin equal to the kit's head and stops) and the canary, started by hand.

Proven on a developer machine with PHP 8.5.12 and PostgreSQL 17:

- `bash scripts/checks.sh`, `composer test:unit` with the coverage floor and `bash scripts/debt-dashboard.sh --final --offline` on a clean clone;
- every failing example of the CI contract test, and the contract test against a real break of the workflow (a removed time limit, an unpinned action) that was reverted;
- the instantiation script on a scratch copy, followed by a green `bash scripts/checks.sh` on the instantiated copy.

Not proven:

- the canary fails until the kit's `main` has `tools/doctor.sh`, and the bump pull request of the kit sync has never been opened: both need a kit commit after the pinned one (`KIT-FINDINGS.md`, P3-2 and P4-4);
- the branch protection of `docs/ci.md` is not set on the host, so a merge is not yet blocked by the required checks or the code-owner review;
- the mutation floor was measured with four processes; a different count flaked on the first measurement (`KIT-FINDINGS.md`).

`KIT-FINDINGS.md` lists what building this starter found wrong or missing in the kit.
