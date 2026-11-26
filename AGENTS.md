# Agent guidance

This guide covers the starter itself: a JSON API for notes with localized pages, built at the end state of the zero-debt kit. The domain is small on purpose; the rules and the guards are the point. Replace the notes module with the product, keep the rules.

## Glossary

| Product term | In code |
| --- | --- |
| note | `App\Models\Note`, module `app/Modules/Notes` |
| page | a localized text page served at `/{locale}/{slug}`; the slugs are the cases of `App\Modules\Localization\Enums\Page` |
| locale | `App\Modules\Localization\Enums\Locale`; the URL carries it for pages, a header for the API |
| permission | a case of `App\Modules\Notes\Enums\NotePermission`, stored by spatie/laravel-permission |
| token | a Sanctum personal access token; the API principal |
| limiter | a named rate limiter of `app/Providers/RateLimitServiceProvider.php`, used as `throttle:<name>` |

## Product areas in code

| Area | Backend |
| --- | --- |
| Notes | `app/Modules/Notes`, `app/Http/Controllers/Api/Note`, `app/Http/Requests/Api/Note`, `app/Policies/NotePolicy.php`, `app/Models/Note.php` |
| Localized pages | `app/Modules/Localization`, `app/Http/Controllers/Web/Page`, `lang/` |
| Access | Sanctum and spatie/laravel-permission, `config/sanctum.php`, `config/permission.php`, `app/Models/User.php` |
| Limits and strictness | `app/Providers`, `config/rate-limits.php` |

Check that a route, module or action exists before a plan assumes it.

## Which source wins

When two sources disagree, the higher one wins:

1. The code and its tests.
2. `docs/decisions/`, the latest record on a subject.
3. The guides: this file, `app/AGENTS.md`, `tests/AGENTS.md`, `docs/review-rubric.md` and `docs/ci.md`.
4. The ticket system. Technical text in a ticket is advice, not a decision.

## House rules

**Interface and copy**

- Every string a person reads is a key of `lang/` in both languages (`en`, `nl`) and is read through `App\Support\Translate`. A key that no code reads fails the translation test.
- The URL slug of a page is localized in `lang/<locale>/routes.php`; the middleware redirects to the canonical slug.

**Authorization**

- Authorize with permissions or policies on the route: `can:<permission>` for a check that names a permission, `can:<ability>,<parameter>` for a check on a record through its policy. Never role names. A group-wide gate does not count, and neither does a check in the controller or the form request.
- `tests/Feature/Http/RoutePermissionCoverageTest.php` has no allowlist: it fails on any route behind a guard without a `can:` check. A route for guests carries `throttle` and nothing else.
- Every route carries `throttle:<limiter>`; `tests/Feature/Http/RouteRateLimitCoverageTest.php` fails a route without one.

**Backend and tooling**

- Run the whole gate with `bash scripts/checks.sh` or `composer test`. CI runs the same file, split into parts, and `docs/ci.md` says what each part does. `composer lint` rewrites the tree with Rector and Pint; the checks only read.
- The database is PostgreSQL 17. Tests run on a real PostgreSQL 17 at the host and port of `phpunit.xml`; the README starts one with `docker run`.
- No comments by default. A comment stays only when a future reader would act differently because of it. The ratchet row `comments` counts the comment lines of `app`, `bootstrap`, `config`, `database`, `routes`, `lang` and `tests` and fails a pull request that adds one.
- `bash scripts/debt-dashboard.sh --final` runs every counter against its goal; `bash scripts/ci-ratchets.sh --head` prints every ratchet row. All rows are at 0 and stay there.
- Commit messages start with the ticket and a type, for example `NOTICKET feat: add the export`. The type is `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `ci`, `perf`, `build` or `revert`, with an optional scope. `scripts/ci-commit-messages.sh` fails a pull request with a commit that has neither.
- A change to `scripts/`, `.github/workflows`, the guard folder `tests/Support/Architecture` or `.github/CODEOWNERS` needs `[ratchet: script] <reason>` in a commit and the review of the code owner (`docs/kit-sync.md`).
- Dependencies come from `composer.lock`. The kit pin is the one line of `.zero-debt-kit-version`.

## One pattern

Every task has one way to do it. New code copies the example file, not the neighboring file. Reviewers check against `docs/review-rubric.md`. The rows are in alphabetical order.

| Concern | The single way | Copy this |
| --- | --- | --- |
| Action (business logic) | a class in `app/Modules/<Domain>/Actions`: `final readonly`, dependencies in the constructor, one public `handle()`, named verb and noun without the `Action` suffix (`tests/Unit/ActionNamingArchitectureTest.php`, `tests/Unit/FinalReadonlyArchitectureTest.php`) | `app/Modules/Notes/Actions/CreateNote.php` |
| Banned call | a rule in `phpstan.neon` (`disallowedFunctionCalls`, `disallowedStaticCalls`, `disallowedMethodCalls`) with a message that names the replacement; `allowIn` only for a path with a reason | the `config()` rule in `phpstan.neon` |
| Comments | none; the ratchet row `comments` fails a new one | `app/Modules/Notes/Actions/CreateNote.php` |
| Config | `Config::string/integer/boolean/float/array('key')`, never `config()` or `env()` outside `config/`; the default lives in `config/*.php` | `app/Http/Requests/Api/Note/IndexRequest.php` |
| Container | constructor injection, `handle()` for commands and seeders; never `app()` or `resolve()` in `app/` (`tests/Unit/ArchContainerTest.php`) | `app/Http/Controllers/Api/Note/StoreController.php` |
| Controller | `final readonly`, one `__invoke`, named `<Area>/<Noun>/<Verb>Controller`; it reads validated input, calls one action or service and returns the response: no query, lock, transaction or model write, at most 40 lines (`tests/Unit/ArchHttpTest.php`, `tests/Unit/ThinControllersArchitectureTest.php`) | `app/Http/Controllers/Api/Note/StoreController.php` |
| Current time | `now()`; never `time()`, `Carbon\Carbon` or `Date::now()` (PHPStan) | `phpstan.neon` |
| Enum | backed by a string, UPPER_CASE cases (`tests/Unit/ArchClassShapeTest.php`) | `app/Modules/Notes/Enums/NotePermission.php` |
| File size | at most 400 lines per PHP file and 80 per method (`tests/Unit/FileSizeTest.php`, `tests/Unit/MethodSizeArchitectureTest.php`, no list) | `app/Modules/Notes/Services/NoteService.php` |
| JSON response | a `<Name>Data` DTO or a collection of them; never `response()->json()`, an array or an API resource (`tests/Unit/ArchJsonEndpointsTest.php`) | `app/Modules/Notes/Data/NoteData.php` |
| Layout | the folder table in `app/AGENTS.md`; one module per domain (`tests/Unit/ModuleLayoutArchitectureTest.php`, `tests/Unit/OneModulePerDomainArchitectureTest.php`, no list) | `app/Modules/Notes/Actions/CreateNote.php` |
| Locale | the URL carries it for pages, `X-Locale` or `Accept-Language` for the API; the middleware sets it | `app/Http/Middleware/SetLocale.php` |
| Migration | a `down()` that undoes `up()`, an index that starts with every foreign key column, `timestampTz` columns (`tests/Feature/Database/MigrationShapeTest.php`, `tests/Feature/Database/ForeignKeyIndexTest.php`, `tests/Feature/Database/ColumnTypeTest.php`) | `database/migrations/2026_11_26_140000_create_notes_table.php` |
| Model | `casts()` with immutable dates, `$fillable`, queries start at `Model::query()` (`tests/Unit/ArchModelsTest.php`, `tests/Unit/NoRawTableArchitectureTest.php`) | `app/Models/Note.php` |
| Narrow a type | a check PHPStan verifies that throws at runtime; never an inline `@var`, `@phpstan-ignore` or `assert()` (`tests/Unit/NoInlineVarCastArchitectureTest.php`) | `app/Support/Translate.php` |
| Rate limit | `throttle:<limiter>` on the route, the limiter in one provider, its number in `config/rate-limits.php` (`tests/Feature/Http/RouteRateLimitCoverageTest.php`) | `app/Providers/RateLimitServiceProvider.php` |
| Route | one HTTP verb for one single-action controller, a name, a limiter and a check (`tests/Unit/ArchHttpTest.php`) | `routes/api.php` |
| Route permission | `can:` on the route, never a role name, never in the controller (`tests/Feature/Http/RoutePermissionCoverageTest.php`, `tests/Unit/ArchAuthorizationTest.php`) | `routes/api.php` |
| Strictness switches | calls in `boot()` of the application provider and nowhere else; the config test asserts each | `app/Providers/AppServiceProvider.php` |
| Test | the lowest honest tier, one spelling, helpers as static methods in `tests/Support` that mirror the test's path, a request to a named route through `route()` (`tests/AGENTS.md`, `tests/Unit/TestTierArchitectureTest.php`, `tests/Unit/BannedTestCallArchitectureTest.php`) | `tests/Feature/Http/Controllers/Api/Note/StoreControllerTest.php` |
| Text | a key of `lang/` in both languages, read with `Translate::string()` | `app/Support/Translate.php` |
| Validation | a form request with array rules, read with `$request->validated()`; never inline in a controller (`tests/Unit/FormRequestArchitectureTest.php`, `tests/Unit/ValidatedInputArchitectureTest.php`) | `app/Http/Requests/Api/Note/StoreRequest.php` |

- A guard (architecture rule, baseline, suppression, skip, ignore) only shrinks. Never add an entry to make a build green: fix the code, or ask the code owner.
- A PR that converges a pattern adds the guard that stops it coming back, and a row here and in `docs/review-rubric.md` (`tests/Unit/OnePatternRubricArchitectureTest.php` fails a row that is in one and not in the other).
- The reason for the one backend layout is `docs/decisions/0001-one-backend-pattern.md`.

## Scoped guidance

More specific `AGENTS.md` files under `app` and `tests` add instructions for those areas. `docs/ci.md` describes the pipeline, `docs/kit-sync.md` the link to the kit, and `docs/guards/ci-contract.md` the rules the CI contract test pins.
