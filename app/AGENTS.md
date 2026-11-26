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
| `Actions` | one change of state: `final readonly`, dependencies in the constructor, one public `handle()` | verb and noun, never an `Action` suffix: `CreateNote` |
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
