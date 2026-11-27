# Kit findings

Problems found in the zero-debt kit while building this starter, checked against kit commit b24b2b5 (kit 0.12.x; the starter is pinned to it in `.zero-debt-kit-version`). The kit was not changed by this repository. Each open finding has a reproduction that was run against b24b2b5. The numbers are the ones of earlier revisions of this file (the first run was against cfb4405), so a reference in a commit message still finds its entry.

## Open

P8-1. **A database service health check by socket passes during the first-run init of the image.** The skeleton's `pg_isready -U <user>` health command (and a compose healthcheck of the same form) answered "accepting connections" for about a second while the image's temporary init server, which listens on the unix socket only, was running, and then the server restarted. A job that starts at that moment fails every connection ("the database system is starting up" or "server closed the connection unexpectedly"). Repro: `docker run -d --name hc -e POSTGRES_USER=u -e POSTGRES_PASSWORD=p -e POSTGRES_DB=d postgres:17`, then once a second run `docker exec hc pg_isready -U u` next to `docker exec hc pg_isready -h 127.0.0.1 -U u`: the first printed "accepting connections" at second 28 while the second printed "no response" until second 35. The starter uses the TCP form `pg_isready -h 127.0.0.1 -U <user>`; the skeleton should too.
P8-2. **`.gitattributes` of the Laravel skeleton marks `/.github export-ignore`, which breaks a template.** A zip, `git archive` or `composer create-project` copy then lacks the workflows and the code owners file, which a template script needs. Repro: `git archive HEAD | tar -t | grep -c '^.github/'` prints 0 on a copy of the skeleton. The kit's docs on a template or starter should say to drop that line; the starter did.

## Resolved in kit b24b2b5

- P7-1. `reference-project/files-to-copy.md` names the ten guard classes and the `tests/Support/Ci/` skeleton files that exist in the starter, and no longer names `GithubTriggerRules`. Checked by reading the file against the starter tree.
- P7-2. `docs/starter-variants.md` says that the GitHub reader family is in the starter. Checked by reading the row.
- P7-3. `04-guards.md` C4 names the indexed secret forms for a reader of secret names. Checked by reading the paragraph on an auxiliary workflow that reads a secret.
- P7-4. `starter-canary.yml` has no step that puts task lines back. Checked by grepping the workflow.

## Resolved in kit e48979c

Re-verified against e48979c (the number of the earlier entry, then how it was checked).

- P6-1. The kit no longer ships a copy of `PhpSource`: `reference-project/` is a pointer to this starter, so the file that a project copies is the one here, which wraps the parser's result in `array_values` and passes level 10 (`composer test:types`: no errors).
- P6-2. The same: the GitHub guard family is the starter's (ten classes, level 10 with the strict rules, the disallowed calls and the dead-code detector, `composer test:types`: no errors). The kit holds no second copy that fails.
- P6-3. The starter's GitHub guard reads no Bitbucket guard and no kit class: the dependency closure of `GithubWorkflowGuard` is the ten classes of the README, and a test fails when one of them names another class of the test support (checked by adding a reference to `Narrow` to `ChecksParts`: the test failed with it, passed without). The constant `CHECKS` is `GithubWorkflowReader::CHECKS`. What a copier needs is in P7-1.
- P6-4. `kit-sync.yml` is read by the guard now: `auxiliaryWorkflows: ['kit-canary.yml', 'kit-sync.yml' => ['KIT_SYNC_TOKEN']]`, with the kit's `AuxiliarySecretRule` (copied as it is, without its prose). Failing examples: a `pull_request`, `push` or `workflow_run` trigger, an undeclared secret, the indexed form, a declared secret that is not read, an unpinned action (the job rules still apply); passing example: the sync workflow with its declared secret; the real directory. With the file named auxiliary and no declaration the generic rule still fails it (a test says so). The shape of the argument is the one `04-guards.md` spells.
- P6-5. The doctor prints one `[ratchet: <row>] ...` line per carried row. Checked on a scratch copy of this starter's scripts from before kit 119d889: `--apply` ended with the lines for `analyser-ignore`, `lint-config-off`, `frontend-test-skip` and `ci-skipped-steps`. The sync workflow takes those lines for the commit of the bump pull request instead of building its own. A pull request-style run of the checks with a changed workflow and no tag still fails the gate (`.github/workflows changed. FAILS without [ratchet: script]`), as it should.
- P6-6. The kit's own example files are three, 209, 237 and 146 lines, with no spaced `==` or `!=` (`grep -c` prints 0 for each); the starter's are `CiHostExamples.php` (197 lines), `CiRunnerExamples.php` (224) and `CiHostVariants.php` (130), all built from parts, with the rows `loose-comparisons` and `cap-test-files-over-limit` at 0.

## Resolved in kit 8fd4285

Re-verified against 8fd4285 (the number of the earlier entry, then how it was checked).

- 9. `bootstrap/app.php` and `bootstrap/providers.php` are the tool paths of the reference configs and the template; "never the folder" is in `templates/tool-configs.md`. Rector and PHPStan here take the two files.
- P3-5. `auxiliaryWorkflows` names the canary: the kit's guard accepts `kit-canary.yml` with `workflow_dispatch`, no pull request trigger, no aggregate job and no matrix, and still reads its jobs and hazards (a test over the real directory, and a failing example with an unpinned action in the canary).
- P3-6. The rename of the package and `composer update --lock` are in the "Manifest floor" row of `catalog/tests-database-ci-docs.md`.
- P3-8. Documented, not changed: `tools/README.md` names three ways out (build the text at run time, a data file no row reads, accept the count). The rows still cannot tell text from code (P6-6).
- P3-9. `DocumentedPathGuard` takes `foreignRoots`; the test of the kit is in this repository too (`tools` and `ci` named, a path under them is not checked).
- P3-10. `lang` is in the PHPStan block of `templates/tool-configs.md` with the rule for a project that has the folder.
- P3-11. `doctor.sh --apply` keeps the task lines a project added to `checks.sh`: on a copy of the previous main the four task lines are identical before and after, and `--list-parts` is unchanged. The sync and the canary of this repository no longer put them back.
- P3-12. `--head` prints "counts the commit HEAD, not the working tree" with the number of paths it did not count, and the usage line says so.
- P4-2. The first-push registration of a workflow with only `schedule` and `workflow_dispatch` triggers is in `04-guards.md` (observed on GitHub, not reproducible offline).
- P5-1. The doctor reports `ratchet rows the kit changed since the project took them` and `--apply` carries them: `frontend-test-skip` came in, the self-test prints `367 cases run, 0 wrong`, and the config values `GENERATED`, `NORMALIZE_tsc` and `NORMALIZE_knip` came with it. The tag of the carried row is P6-5.
- P5-2. `TypedReceivers` passes level 10 as the kit ships it (the file equals the one here, which read the stacks through `end()`, apart from two one-line `@var` tags). `PhpSource` does not (P6-1).
- P5-3. The kit's GitHub guard accepts the runner form, the owner exceptions (published port on 127.0.0.1, the application fetched on a local address, the coverage line in the Composer script) and the canary; this repository's three workflows pass it with `backendTestScript` and `auxiliaryWorkflows`.
- P5-4. `TypedReceivers`, `ReportNotLogGuard`, `RawTableGuard` and `GlobalImportGuard` hold no line the `comments` row counts (the expression of the row run over each file: 0), and the reference README says the prose of the older guards is for the reader of the kit.
- P5-5. Not reproduced: a Rector pass over the 31 merged guards dropped no variable, and the guard tests pass after it.
- P5-6. `checks.sh --part static` and `--part guards` run in CI without `CHECK_BASE` or `--main` (checked with `CI=true` and no base); `--part repo` still stops, as it holds the commit-message check and the secret scan, which read the base.
- P5-7. The hazard of a kit checkout inside the workspace is in `10-hazards.md`; the canary keeps moving the checkout out.

## Resolved in kit 119d889

One line each, with the number of the earlier entry.

1. Advisory block on a PHP patch version: the advice says `--ignore-platform-req=php`, never `config.platform`.
2. `earlyReturn` is dropped: 18 refactoring sets, in the catalog, the template and the ratchet script.
3. `#[Fillable]` and `#[Hidden]` are the one spelling of the Model row, with the note on the uncovered mutants of the attribute lines.
4. The Rector idioms (`$app['env']`, `unlink`, `$_SERVER`, a class name in a string) are a table in `templates/tool-configs.md`.
5. `featureToggles: internalTag: false` is in the `phpstan-tests.neon` block, with the count of errors it removes.
6. The Pest 5 extensions (the closure `$this`, `arch()`, `pest()->...->beforeEach()`) are named in "Top level in tests".
7. The reference dead-code provider knows the framework's by-name calls (controllers, middleware, requests, policies, seeders, factories, listeners, trait hooks).
8. The `Config::get()` ban names the facade form too.
10. The optimized autoloader and a new analyser extension: the trap is in the catalog.
11. The organisation-hosted formatter config: "nineteen decision rows to start" is in the template.
12. `autoload-conformance` runs `composer dump-autoload --dry-run` and writes nothing (checked: the real `vendor` is untouched after a run from a copy that links it).
13. `checks.sh`, `prepush.sh`, `ci-commit-messages.sh` and `mutation.sh` use the local base branch when the repository has no remote (checked in a fresh `git init`: the gate runs and says so).
14. The failure of the script guard names the tag for the first configuration of copied scripts.
15. The self-test has fixtures for `queue-bus-fake-all` and `unused-ignore` (`extra_rows`); a row the kit names without a case fails it.
16. `unused-ignore` for a PHP config is a `grep` row on `(NamedFilter|PatternFilter)::fromString`.
17. `MUTATION_PROCESSES` (default 4) is a config key of `mutation.sh`.
18. A row with no subject and a row that needs a host are described ("A row with no subject, and a row that needs a host").
19. The abandoned development package has its form: a decision row with an end date, the cap at the measured number, `cap-dependencies-abandoned` in `SHRINK_ONLY` and in `OVERRIDE_AT_ZERO`.
20. The defaults of `SOURCE_FILES` and `SOURCE_PATHS` follow each other and hold `lang`.
21. `Translate::string()` is a row of the catalog.
22. The public route is a third category of the "Authorization" row, and the coverage tests take the public area as an argument.
23. `--parallel` with the coverage run is in the catalog.
24. The macOS `pcov` build (`pcre2`) is in the catalog.
25. The unit tier's `unlink()` rewrite: the idiom table and the one support class that owns temporary files.
26. The reference guards take their first migration file, queue enum name and permission enums as arguments.
27. `@param` instead of an inline `@var` on a promoted property.
28. The route files stay out of the Rector paths, as the Rector section of the template says.
29. `RateLimiterGuard` reads a list of folders and a `throttle:60,1` in a route file fails.
30. A fully qualified class name in an example string: the idiom table says to write the backslash in front of a constant.
31. `DatabaseRuleGuard`, `JsonEndpointGuard` and `ImportSpellingGuard` take their facts as arguments.
32. The catalog says a write wraps its DTO with its status, and says why.
33. `BannedTestCallGuard` bans `test()`.
34. The banned test calls (`sleep`, `usleep`, stray switches, `ignoring()`) are listed.
35. A guard that names a booting base class: the idiom table gives the two-part spelling.
36. A missing folder throws in the reference guards (and here: the migration, schedule, route-file, guide and source-file guards of this repository).
37. The seeder test has a home in the tier rule of the catalog.
38. The "Listener" row says `withCommands` takes real folders, never a glob.
39. A Pest `arch()` rule over an absent namespace has a form that works (`ArchNamespaces`).
40. One thin test per guard replaces the `Arch*Test` files of the reference.
41. An empty schedule passes `ScheduledOneServerGuard`.
42. `PestArchCall` has `expect()`.
43. The guard over the guards exists (`GuardShapeGuard`, `PestExampleReader`).
44. The coverage tests read the application's own routes and take the public area as an argument; `ObjectLevelAccessTest` has a worked example.
45. A route built by hand needs `Class@__invoke`: the catalog says so.
46. The CI examples moved out of the guard folder (`tests/Support/Ci`).
P3-1. The reference `GithubWorkflowGuard` reads `if: false` as a boolean (the starter's own guard did already).
P3-2. `tools/doctor.sh` is on the kit's `main` (the starter's pin is its head).
P3-3. The two self-test cases are in the kit (see 15); the remaining gap is P5-1.
P3-4. The skeleton documents the image, what it must hold and the owner exception for a project without one; the guard does not accept the exception (P5-3).
P3-7. `$_SERVER['PATH']` is a row of the idiom table.
P4-1. A cap that a host measures has the first-measurement form: its name in `OVERRIDE_AT_ZERO` and `[ratchet: cap-<key>] first measurement <n>`.
P4-4. The canary finds `tools/doctor.sh` on the kit's `main` (checked on GitHub with a manual run).

## Not ported from the kit's reference, on purpose

- The tenant, Inertia, frontend, broadcast and GitLab guards and the Bitbucket pipeline guard: the starter has no such subject.
- `CountedList`: the starter has no counted list, every counter is at zero.
- The larger `LaravelMagicUsage` provider and `DatabaseRuleGuard`'s tenant argument: the starter's smaller provider covers its classes.
- The `bootingRoots` argument of `TestTierGuard`: the starter names its one base test case in the guard.
- The seven `Arch*Test` files of the starter that still hold several guards each: they work; the kit's one-test-per-guard form is not applied to them yet.
- The kit's `CiSafetyGuard` replaced the local one, but two checks the kit lacks stay local: `TaskFlagsGuard` (`--test`, `--min=100`, `--group arch` on their tasks) and `WorkflowDriversGuard` (the production drivers of the smoke job, the mutation run of a pull request).
- `AuxiliarySecretRule` is the kit's, wired into the GitHub guard through `auxiliaryWorkflows`; its kit test is part of `GithubWorkflowArchitectureTest` here, since a guard class needs a guard test with a real-tree case.
- `CiHostExamples`, `CiHostVariants` and the GitHub test are the kit's, with the operator built at run time and the runner examples in a class of their own; the GitLab and code-owner examples are not copied (the starter's `CodeOwnersArchitectureTest` keeps its own).
