# TESTS

## TIERS

| Tier | Set-up it may use | Lives in |
| --- | --- | --- |
| Pure unit | no application, plain PHPUnit case | `tests/Unit`, mirroring the path of the class it covers |
| Feature | the booted application, the real database, the hermetic base set-up of `tests/Pest.php` | `tests/Feature`, mirroring the path of the class or the route it covers |

## ONE WAY PER TASK

- **Where a test lives** a unit test mirrors the path of its class; a feature test mirrors the domain and the route or command it enters through.
- **Helpers, fixtures, fakes** `public static` methods of a `final class` under `tests/Support`, at the mirrored path of the test that uses them; a test builds its state in local variables, never in shared properties.
- **Config, clock, container** `config([...])`, `travelTo()` from the moment the base set-up froze, `resolve()` and `app()->instance()`.
- **Spelling** Pest, `it('does x', function (): void { ... })` and nothing else; `expect()` with the exact matcher; named status assertions; a dataset for each case list.
- **Hermetic base set-up** every feature test starts from `Http::preventStrayRequests()`, `Process::preventStrayProcesses()`, `Sleep::fake()`, `$this->freezeTime()` and the two normal-generator calls; `tests/Feature/HermeticBaseTest.php` pins them.
- **The real path** a request goes through the middleware and the exception handler; sign in as a user who holds the permission the route asks for, with `Users::signedIn()`.
- **Stand-ins** the real class and the framework's fakes; no mocking-library double.
- **Typing** Pest's closures are typed by the extensions of `tests/Support/PHPStan`; a value the framework returns as a union is narrowed in a helper of `tests/Support`.
- **Coverage** the floor is the number in `scripts/coverage-floor.txt`, on its way to the kit's goal of 95 or at it; the `min` ratchet row `coverage-floor` holds it. The mutation score is the number in `scripts/mutation-floor.txt`; the row `mutation-floor` holds it.
- **Declare a test** `it('does x', function (): void { ... })`; never `test(...)`, `it('it ...')` or `->only()`; every closure declares `: void`; one assertion at least per file. `BannedTestCallGuard` (`BannedTestCallArchitectureTest`), `TestAssertionGuard`. Copy `tests/Feature/HermeticBaseTest.php`.
- **Banned calls** no `sleep()` or `usleep()`, `allowStray...`, `preventStray...(false)`, `ignoring()`, `withoutMiddleware()`, `withoutExceptionHandling()`, `assertStatus(n)` where a named assertion exists, `toBeTruthy()`, `toBeFalsy()`, `expect(true)` or `->not->toThrow()`.
- **Class or function in a test file** none: a helper is a `public static` method of a `final class` under `tests/Support`, at the mirrored path of the test as `<Test>Helpers`; a class several tests share sits in the root of `tests/Support` (`TestClassGuard`, `TestHelperFunctionGuard`, `TestHelperMirrorGuard`). A detector is not a helper: it is a `<Subject>Guard` class in `tests/Support/Architecture`, found by the guard class a test names.
- **Fixtures** a base test case declares no property and no `$this->x` is assigned (`NoSharedTestFixturesArchitectureTest`); no global `use` in a test file (`NoGlobalImportInTestFilesArchitectureTest`); no reflection on private state (`NoReflectionInTestsArchitectureTest`).
- **Tier** `tests/Unit` never boots the application or names a booting base, and the pure layers never name a model or `DB` (`TestTierArchitectureTest`).
- **Request to a named route** the URL comes from `route('name', ...)`, never the text of a URL a named route has (`NamedRouteUrlArchitectureTest`, which asks the booted application for its named routes).
- **Where a test lives** the folder of its class in `app`; a test of code outside `app` sits in a home folder of `tests/Feature` named after what it covers (Config, Database, Lang, Routing, Bootstrap) (`TestFolderMirrorsAppArchitectureTest`).

## GUARDS

- **Shape** a guard is a `<Subject>Guard` class in `tests/Support/Architecture` and a thin test in `tests/Unit` that names it by import, in the group `arch`, with three parts: failing examples that carry the words of the message, passing examples with the near misses, and one case over the real tree (`dirname(__DIR__, 2)`). A detector holds no literal that names a path of the repository: it takes the root and every folder as constructor arguments, reads a table of folders from `app/AGENTS.md` where it needs one, and the thin test passes the paths. A guard that scans a tree has a case for a checkout under a dot directory, and a folder that does not exist is an error, not an empty pass. `GuardShapeArchitectureTest` fails a detector with such a literal, a guard no test names, and a guard test without one of the three parts; the readers (`PhpFileInspector`, `ClassGraph` and the others of the folder) have a test of their own and are no guard.
- **Run** `php -d memory_limit=-1 vendor/bin/pest --group=arch` is the guard part of `scripts/checks.sh`; a rule written with Pest's `arch()` has no example form, so its failing case is proved by breaking the real tree. A feature test that needs the booted application (`NamedRouteUrlArchitectureTest`, the route coverage tests) is not in the group and runs in the test step.
- **Built and proved on examples, not exercised by the real tree** `HttpRefusalGuard`, `RedirectGuard`, `DatabaseRuleGuard`, `LikeSearchGuard` (no refusal outside HTTP, redirect, existence rule or search exists), `UniqueJobGuard`, `QueuedClassLimitsGuard`, `QueueNameGuard` (no job, queue enum or worker config of its own), `EventDispatchGuard`, `ReportNotLogGuard`, `LogMessageGuard` (no event or log call), `ConcurrentIndexGuard` (every migration creates its table), the controller verb list and the listener and command discovery of `BootstrapRegistrationGuard`.
- **Not ported** the tenant guards (the application has one tenant), the Inertia, TypeScript and frontend-naming guards, `BroadcastChannelGuard`, `FlashMessageGuard` (no page flashes), `CopyWordingGuard` (no banned-word list is decided), `StoredNameRegisterGuard` and `StoredClassNameGuard` (the application stores no class name: no queued class, event, mail or notification), the role-assignment and user-role-table guards (no role is assigned), `RegistryGuard` (no registry of implementers), the morph map and soft-delete pruning tests (no polymorphic relation or soft delete).
