# BACKEND

Every task has one way to do it, written down here. Code that does it differently is waiting to be moved: copy this file's examples, not the neighboring code. The layout below is the one the starter ships; `ModuleLayoutArchitectureTest` fails on any class that leaves it.

## LAYOUT

The framework layer stays flat, like the stock framework:

| Folder | Holds |
| --- | --- |
| `app/Http/Controllers/<Area>/<Noun>/<Verb>Controller.php` | single-action controllers: the area is the route group (`Api`, `Web`), the folder is what the controller acts on, the class is one verb |
| `app/Http/Requests` | form requests: validation, and authorization that depends on the input |
| `app/Http/Middleware` | middleware |
| `app/Models` | Eloquent models |
| `app/Policies` | one policy per model, called from the route |
| `app/Providers` | the application provider with the strictness calls, and the rate-limit provider |
| `app/Support` | code that knows no domain: framework glue and static helpers |

Domain code lives in one module per domain, `app/Modules/<Domain>`, using only these folders:

| Folder | Holds | Name |
| --- | --- | --- |
| `Actions` | one change of state: `final readonly`, dependencies in the constructor, one public `handle()` | verb and noun, never an `Action` suffix: `CreateOrder` |
| `Services` | reads that several callers share, and clients of an external system | `<X>Service`, `<X>Client` |
| `Data` | DTOs: request and response shapes of the API | `<X>Data` |
| `ValueObjects` | every other domain value, `final readonly` | noun |
| `Support` | domain helpers that are neither an Action nor a Service | noun |
| `Enums` | string-backed enums with UPPER_CASE cases | noun |
| `Events`, `Listeners` | events and the listeners that handle them | `<X>Event`; a listener says what it does |
| `Jobs` | queued work | `<X>Job` |
| `Commands` | console commands | `<X>Command` |
| `Mail`, `Notifications` | mailables and notifications | `<X>Mail`, `<X>Notification` |
| `Exceptions` | exceptions | `<X>Exception` |
| `Contracts` | interfaces | noun |
| `Facades` | facades over a service of the module | noun |

The controller verbs, one list, each used by at least one controller (`ControllerNameArchitectureTest` fails a verb no controller carries):

| Verb | Used for |
| --- | --- |
| `Destroy` | delete one record |
| `Index` | list records |
| `Show` | read one record or one page |
| `Store` | create a record |
| `Update` | change a record |

Limits: a method at most 80 lines, a controller action at most 40. A PHP file at most 400 lines, `FileSizeTest` and `MethodSizeArchitectureTest` fail above them. A class is `final`; a parent is abstract and a concrete class is never extended (`ClosedClassArchitectureTest`).

Nothing sits in a module root. Controllers, requests, middleware, models, policies and providers never sit in a module. Enums, exceptions, value objects and the support folder never touch models or the database; a DTO names a model only in its static `from*` factory.

## ONE WAY PER TASK

- **Controller** single action, `<Area>/<Noun>/<Verb>Controller`, `final readonly`, read the validated input, call one action or service, return a DTO. Copy `app/Http/Controllers/Api/Note/StoreController.php`.
- **Change of state** an action with one public `handle()`. Copy `app/Modules/Notes/Actions/CreateNote.php`.
- **Validation** a form request with array rules and `$request->validated()`; never inline. Copy `app/Http/Requests/Api/Note/StoreRequest.php`.
- **Authorization** on the route: `can:` with a case of the permission enum for a check on a permission, `can:<ability>,<parameter>` for a check on a record through its policy. Never role names, `abort(403)` or `Gate::` in a controller. Copy `routes/api.php` and `app/Policies/NotePolicy.php`.
- **Rate limit** every route carries `throttle:<name>` with a limiter registered in `app/Providers/RateLimitServiceProvider.php`; the numbers are in `config/rate-limits.php`.
- **JSON response** a DTO or a DTO collection; never `response()->json()` or an API Resource. Copy `app/Modules/Notes/Data/NoteData.php`.
- **Config** read through the typed accessors of the config facade (`Config::string()`, `Config::integer()`); `env()` only in `config/` and always cast.
- **Strictness** the model, URL, date, password and destructive-command switches are calls in `boot()` of `app/Providers/AppServiceProvider.php` and nowhere else; the config test asserts each.
- **Text** every string a person reads is a key of `lang/` in both languages, read with `Translate::string()` (`app/Support/Translate.php`), which wraps `__()` and narrows its result to a string.
- **Locale** the URL carries it for pages (`/{locale}/...`), the `X-Locale` header or `Accept-Language` for the API; the middleware sets it. Copy `app/Http/Middleware/SetLocale.php`.
- **Dates** `now()` and the immutable date class; never `time()` or the mutable classes.
- **Comments** none: a name or a type says it. A comment stays only when a future reader would act differently because of it.
- **Route file** import the controller folder and write `Folder\Noun\VerbController::class`, one HTTP verb per single-action controller; never a controller by its own import, a resource, `any` or `match` route or a closure. A route parameter is camelCase (`RouteParameterGuard`; that it carries the name of the controller argument is review). Copy `routes/api.php`; the routes live in `routes/` and `bootstrap/app.php` only.
- **Form request** rules of a field are an array, never one string, and no `authorize()` that only returns `true` (`FormRequestGuard`).
- **Request input** a controller reads `$request->validated()` or an accessor of its form request, never `input()`, `query()`, `safe()` or a typed reader (`ValidatedInputGuard`); validation is never inline (`RequestValidationGuard`).
- **Refusal** the route refuses (`ControllerAuthorizationGuard`: no `authorize()`, `abort(401|403)` or hand-built refusal in a controller); outside the HTTP layer a refusal is a domain exception that extends the framework's authorization exception (`HttpRefusalGuard`).
- **Redirect** `to_route()` for a named route, `back()` for the previous page (`RedirectGuard`).
- **JSON write** a controller of a write wraps its DTO with the status: `new JsonResponse($data, Response::HTTP_CREATED)` (`JsonEndpointGuard`, `BareDataReturnGuard`). Copy `app/Http/Controllers/Api/Note/StoreController.php`.
- **Role names** never checked: a permission or a policy is (`RoleNameGuard`). Copy `app/Policies/NotePolicy.php`.
- **Existence and uniqueness rules** `Rule::exists(Model::class, 'column')` and `Rule::unique(Model::class, 'column')`, never a table name or a string rule (`DatabaseRuleGuard`).
- **Search** case-insensitive search only through one helper that escapes wildcards (`LikeSearchGuard`): the starter has no search endpoint, so the helper and its first use arrive together.
- **Class shape** controllers and actions are `final readonly`, enums string-backed with UPPER_CASE cases, a DTO ends in `Data`, a model keeps its casts in `casts()`, its dates immutable and its queries at `Model::query()`, and no observers, single-use traits or custom query builders (`ArchClassShapeTest`, `FinalReadonlyArchitectureTest`, `ActionNamingArchitectureTest`, `StringBackedEnumArchitectureTest`, `EnumCaseArchitectureTest`, `ModelConventionArchitectureTest`, `DateCastArchitectureTest`, `ModelMagicStaticCallArchitectureTest`, `NoObserversArchitectureTest`, `NoSingleUseTraitsArchitectureTest`).
- **Container** dependencies come through the constructor or `handle()`, never `app()` or `resolve()`; a class declares its own binding; no class loads a route file (`ArchContainerTest`, `BootstrapRegistrationArchitectureTest`, `ConfigAccessArchitectureTest`).
- **Database** no raw table access outside migrations (`NoRawTableArchitectureTest`); a transaction is `DB::transaction(fn () => ...)`, never a manual commit (`ManualTransactionArchitectureTest`).
- **Narrowing** a check that throws at runtime, never an inline `@var` cast (`NoInlineVarCastArchitectureTest`).
- **Errors and logs** a broad catch reports, rescues or rethrows and is never empty (`EmptyCatchArchitectureTest`); `report($e)` for an error, a log line is fixed text with `['exception' => $e]` and never the exception message (`ReportNotLogArchitectureTest`, `LogMessageArchitectureTest`).
- **Events** `Event::dispatch(new XEvent(...))`, the event built at the call; an event class has no `Dispatchable` trait and no `dispatch()` of its own, because the refactoring set turns `XEvent::dispatch()` into the `event()` helper that the analyser bans (`EventDispatchArchitectureTest`); no `exit` or `die` (`ExitArchitectureTest`).
- **Queued work** a unique job has `uniqueId()`, every queued class declares `$timeout` and `$tries` or `retryUntil()`, a queue name is a case of the queue enum, and a scheduled task is a command with `onOneServer()` and an explicit `withoutOverlapping(<minutes>)` (`UniqueJobArchitectureTest`, `QueuedClassLimitsArchitectureTest`, `QueueNameArchitectureTest`, `ScheduledTaskArchitectureTest`, `ScheduledOneServerArchitectureTest`). The starter has one scheduled task and no job: those guards are proved on examples.
- **Migrations** no application class, a `down()` that undoes `up()`, a foreign key with its on-delete action, a boolean with a default, an index on an existing table built concurrently (`MigrationClassArchitectureTest`, `MigrationShapeArchitectureTest`, `ConcurrentIndexMigrationTest`; `tests/Feature/Database` holds the schema tests).
- **Coverage tests** every route of the application carries a permission or a policy outside the public area (`RoutePermissionCoverageTest`), one registered limiter, the write limiter for a write (`RouteRateLimitCoverageTest`), and refuses someone else's record (`ObjectLevelAccessTest`: one test over every route with a record id, and a model with no factory in its map fails the test).
