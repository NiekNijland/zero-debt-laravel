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
