# Review rubric

What a reviewer, human or agent, checks in every pull request. The one-pattern table in the root `AGENTS.md` is the reference: when the PR does a task differently from that table, it is a finding, even when CI is green.

CI enforces most rows of that table (second section). A reviewer spends the time on what CI cannot see: the rows marked "review only", the findings below, and whether a guard that was supposed to keep a pattern is in the PR. The numbers of the findings are the kit's and are the same in every project.

## Findings

Blockers

1. The PR adds an entry to an allowlist, baseline, suppression, skip, ignore or reasoned-exception list: `phpstan.neon` ignores, a Rector skip, a `config.audit.ignore`, `scripts/audit-exceptions.json`, a skipped test. These lists only shrink, and at zero they take no entry.
2. The PR converges a pattern without the guard that keeps it, removes a guard, makes a check non-blocking, scopes it to changed files, or hides its failure (`|| true`, `continue-on-error`). Every diff to `scripts/ci-ratchets.sh` outside adding a row, to the other scripts in `scripts/`, to `.github/workflows` and to `tests/Support/Architecture` is read line by line, with the reason of its `[ratchet: script]` tag.
3. Object-level authorization: an id from the request reaches a record without route binding and a policy; a `can:` check that names a permission where the route holds a record; `DB::table`; a role name in a check.
4. A moved or renamed class whose name is stored in data (a queued job, an event or notification name, a morph alias). The starter stores none; the PR that introduces the first one adds its register, its alias and its guard.
5. A migration that builds an index or a foreign key on an existing table without the engine's non-blocking form (`CREATE INDEX CONCURRENTLY`, a foreign key `NOT VALID` first).
6. A commit with `[ratchet: <name>] <reason>` or `[ratchet: script] <reason>`: read the reason and the diff. The tag is for a rule that moved, not for a violation that was easier to keep. A row at 0 takes no override, except `comments` and `cap-dependencies-majors-behind`.
7. Production behaves differently after the PR and the description does not say so under "Behavior that changes on purpose".
8. A raised cap in `scripts/debt-caps.json` without its `[ratchet: cap-<key>]` tag, or with a reason that does not hold (item 6); a test that switches off middleware or exception handling; a new double of a class of the project where the real class or a fake works.

Warnings

9. New code does a task differently from the one-pattern table, in a part no guard decides.
10. Authorization by role name, or a check in the wrong layer: a permission check in a form request or controller that belongs on the route.
11. New behavior that no test names, or a test named after a class that does not exercise it or cannot fail. CI checks that every action, service, command, policy and middleware has a test file of its own; whether the test is worth its name is yours to judge.
12. The PR changes a counter (a ratchet row, a file over 400 lines, an outdated package) and the description does not say which and by how much. `bash scripts/debt-dashboard.sh --offline` prints them.
13. A new or changed guard without a failing example for each other spelling of its construct: through a parent class or a sub-interface, as a function and as a method, under an alias or a full name.
14. A sentence in a guide that says a guard fails on something the guard's failing examples do not show; a sentence that describes a transition; a finished plan that is not archived.
15. A commit type that does not fit the change. On a PR that only touches docs or `*.md`, read the commit titles yourself, ticket and type.
16. Copy: a string missing in one of the two languages, a key built from variables, wording the glossary in `AGENTS.md` rules out.

Judgements (review only: no guard can decide these)

17. Frontend design: a PR that adds a frontend adds its guide, its layer table and its lint rules first; the starter has none.
18. A test exercises the class it names, and the class it names is the one whose behavior it asserts.
19. A value that depends on runtime configuration fits: the numbers of the rate limiters in `config/rate-limits.php` suit the route, the timeout of a queued class stays under its worker's timeout.
20. A migration's `down()` really undoes its `up()`; a type change on a big table has its migration plan in the PR.
21. The empty value of a checked helper (a failed parse, an unexpected type) is handled by its caller; an overriding property carries the same intent as an overriding method.
22. The reason of a `[ratchet: comments]` override meets the comment rule: a future reader would act differently because of the comment.

Suggestions

23. A comment that restates the code; a new abstraction where deleting code would do.

## What CI already enforces, and what is review only

Every row of the one-pattern table in `AGENTS.md`, in the same order. A row without a guard is review only: check it by eye.

| Row | Guard | Review only |
| --- | --- | --- |
| Action (business logic) | `ActionNamingArchitectureTest`, `FinalReadonlyArchitectureTest` | One change of state per action (9) |
| Banned call | PHPStan with the disallowed-calls rules in `phpstan.neon` | Whether a new `allowIn` has a reason (1) |
| Comments | ratchet row `comments` | Whether a comment let through with `[ratchet: comments]` earns its place (22) |
| Config | PHPStan bans `config()` and `noEnvCallsOutsideOfConfig` is on | That the default sits in `config/*.php` (9) |
| Container | `ArchContainerTest` | |
| Controller | `ArchHttpTest`, `ThinControllersArchitectureTest` | The check is on the route, not in the controller (10) |
| Current time | PHPStan bans `Carbon\Carbon`, `time()` and `Date::now()` | |
| Enum | `ArchClassShapeTest` | Whether a case value is readable where it is stored or sent (9) |
| File size | `FileSizeTest`, `MethodSizeArchitectureTest` | How a class is split (9) |
| JSON response | `ArchJsonEndpointsTest` | The shape of the DTO (9) |
| Layout | `ModuleLayoutArchitectureTest`, `OneModulePerDomainArchitectureTest` | |
| Locale | the middleware tests under `tests/Feature/Http/Middleware` | |
| Migration | `MigrationShapeTest`, `ForeignKeyIndexTest`, `ColumnTypeTest` | Whether `down()` really undoes `up()` (20) |
| Model | `ArchModelsTest`, `NoRawTableArchitectureTest`, `NoObserversArchitectureTest` | |
| Narrow a type | PHPStan at level 10; `NoInlineVarCastArchitectureTest` | The caller handles the empty value of a checked helper (21) |
| Rate limit | `RouteRateLimitCoverageTest`, `RateLimiterArchitectureTest` | The numbers in `config/rate-limits.php` (19) |
| Route | `ArchHttpTest` | |
| Route permission | `RoutePermissionCoverageTest` (no allowlist), `ArchAuthorizationTest` | The rule in the policy itself (3, 10) |
| Strictness switches | `AppServiceProviderTest`, `ConfigTest` | |
| Test | `BannedTestCallArchitectureTest`, `NamedRouteUrlArchitectureTest`, `TestTierArchitectureTest`, `TestHelperMirrorArchitectureTest`, `TestAssertionArchitectureTest`, `TestFolderMirrorsAppArchitectureTest`, `NoSharedTestFixturesArchitectureTest`; ratchets `test-doubles` and `event-fake-all` | The lowest honest tier; whether the assertion can fail (18) |
| Text | the translation key test | The wording in each language (16) |
| Validation | `FormRequestArchitectureTest`, `ValidatedInputArchitectureTest`, `ArchHttpTest` | An `authorize()` that really switches off a check of a parent request |

House rules without a row:

| Rule | Guard | Review only |
| --- | --- | --- |
| Commit message starts with the ticket and a type | `scripts/ci-commit-messages.sh` | Docs-only PRs skip it; whether the type fits the change (15) |
| Dependencies free of known advisories | `composer audit --locked --abandoned=report` through `scripts/audit-gate.sh` | An advisory with no fixed release is a dated line of `scripts/audit-exceptions.json` that expires in 7 days |
| Drift | `scripts/debt-dashboard.sh --offline` in the checks, `--final` in the scheduled run | The rows that need a registry run in the scheduled job |
| Formatting and manifest | `vendor/bin/pint --test`, Rector with `--dry-run`, `composer validate --strict` | |
| Path in a guide | `DocumentedPathArchitectureTest`: every backticked path in a guide exists | Whether the named file still shows the rule it is the example of |
| Rules a guide pins | `GuideRulesArchitectureTest`: a guide that loses the words of a rule fails | Whether the words say the rule (14) |
| The pipeline | `CiContractArchitectureTest`, `CodeOwnersArchitectureTest` | That the host setting of `docs/ci.md` is on (2) |
| Method length, traits, reflection in tests | `MethodSizeArchitectureTest`, `NoSingleUseTraitsArchitectureTest`, `NoReflectionInTestsArchitectureTest` | |
| Debug and exit | PHPStan `disallowedFunctionCalls`, `ExitArchitectureTest` | |

## PR checklist

The checklist lives in `.github/pull_request_template.md`. The host fills a new PR's description from that file when the PR is created in the web UI. PRs opened through the API may not get it, so agents copy the file into the description themselves.
