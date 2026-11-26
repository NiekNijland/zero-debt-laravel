# Kit findings

Problems found in the zero-debt kit while building this starter, checked against kit commit 119d889 (the starter is pinned to it in `.zero-debt-kit-version`). The kit was not changed by this repository. Each open finding has a reproduction that was run against 119d889. The numbers are the ones of earlier revisions of this file (the first run was against cfb4405), so a reference in a commit message still finds its entry.

## Open

9. **`bootstrap` as a tool path breaks on a normal install.** `bootstrap/cache/packages.php` and `services.php` are generated and untracked; Rector rewrites them (`rector --dry-run` exits 2) and PHPStan reports `classConstant.internalClass` on them. `templates/tool-configs.md` still says the Rector paths are "every folder the analyser reads ... bootstrap". Here both tools name `bootstrap/app.php` and `bootstrap/providers.php`. Repro: `composer install`, put `__DIR__ . '/bootstrap'` in `withPaths()` of `rector.php`, run `vendor/bin/rector process --dry-run`.
P3-5. **A workflow that runs `bash scripts/checks.sh` is a "kit file" of the reference guard and may have only the triggers `pull_request`, `push` and `schedule`.** A nightly canary or a sync workflow that calls the checks script (even one part of it) is read as the kit's own workflow and fails on `workflow_dispatch`, on the missing `pull_request` and `push` triggers and on the missing aggregate job. The kit does not say that a second workflow must avoid the script, and its skeleton has no manual trigger for the scheduled job, so the first run of a scheduled workflow waits for its cron (P4-3 of an earlier revision). Repro: copy the reference `GithubWorkflowGuard` with its `Github*Rules`, `ChecksParts`, `DatabaseEngine` and `CiContractGuard` and run `new GithubWorkflowGuard($root . '/.github/workflows', $root . '/scripts/checks.sh', 'kit-checks.yml', ['main'], 'composer test:unit', 'postgres:17', frontend: false)->violations()` over `.github/workflows` of this repository: `kit-canary.yml` fails with 13 messages. The starter's canary runs the ratchet script, the dashboard and the doctor, and the `static` and `guards` parts through `bash scripts/checks.sh --part`, and its own `GithubWorkflowGuard` reads only the kit's file.
P3-6. **Renaming the package invalidates `composer.lock`.** The content hash covers `name`, so `composer validate --strict` fails after the first edit of `composer.json`. `composer update --lock` repairs it; the kit's template instructions say nothing about the rename. Repro: change `name` in a copy of `composer.json` and run `composer validate --strict`.
P3-8. **The row `loose-comparisons` and the row `comments` count text inside strings.** A test fixture that holds a GitHub expression (`github.event_name == 'push'`) or a YAML example counts as a loose comparison, and a string that holds source with `// note` counts as a comment (the kit's own example `'a comment after the line' => ["<?php\n\nuse Exception; // for the test\n"]` made `comments` 1). The rows cannot tell code from text; the kit offers no exclusion short of a path. Here the operator and the slashes are built at run time from two string parts.
P3-9. **`DocumentedPathGuard` flags a guide that names a file of the kit.** `tools/doctor.sh` is a file of the kit, not of the project; the guard fails it ("no file ends in"). A guide that names the kit's tools writes the bare file name. Repro: a guide with the line ``Run `tools/doctor.sh` from the kit.`` over a project that has no `tools` folder.
P3-10. **Point 15 (analyser paths name every source folder) fails a project with `lang`.** The kit's default `SOURCE_PATHS` now holds `lang?`, but the PHPStan block of `templates/tool-configs.md` lists `app`, `bootstrap`, `config`, `database` and `routes`; a project with a `lang` folder fails the contract until it adds `lang` to `phpstan.neon` (green here: `No errors`).
P3-11. **`doctor.sh --apply` drops the task lines a project added to `checks.sh`, and the doctor then calls the script `body-differs`.** The tasks are a project's to add (`04-guards.md` C4), but the doctor keeps the commands of the tasks the kit's file has and removes the others, while the config block keeps `PARTS` naming them, so `checks.sh` stops with "names a task that is no task of this file". Repro: in a copy of this repository at 061caf7, `bash tools/doctor.sh --project <copy> --upgrade-plan --apply`, then `grep -c Manifest scripts/checks.sh` prints 1 (the `PARTS` line); before it printed 4. The sync and the canary of this repository put the lines back (`docs/kit-sync.md`). The `mutation.sh` half of the earlier entry is resolved: `MUTATION_PROCESSES`.
P3-12. **`ci-ratchets.sh --head` counts the committed tree, not the working tree.** A new file that is not committed is invisible to every row, so a counter looks 0 while the file breaks it. The usage says "head" and not "committed". Repro: add an untracked PHP file with `if ($a == $b) {}` and run `bash scripts/ci-ratchets.sh --head`: `loose-comparisons max 0`. It bit again in this revision: 33 comment lines of copied guards were invisible until they were committed.
P4-2. **A workflow that has only `schedule` and `workflow_dispatch` triggers is not registered when it arrives in the first push of a new repository.** `gh workflow list` listed `kit-checks.yml` only, and `gh workflow run kit-sync.yml` answered "HTTP 404: workflow kit-sync.yml not found on the default branch" although the file was on `main`; both appeared after a later push to `main` that changed each of them. A template repository has the same shape: a project made from the starter needs one more commit that touches the two files before they can run. Observed on GitHub on the first publication; not reproducible offline. The kit's skeleton notes do not say it.

## New in this revision (against 119d889)

P5-1. **The doctor does not report a ratchet row whose definition the kit changed, and the self-test then fails.** The upgrade keeps the project's table, and the report lists only rows that exist in the kit's table and not in the project's. The kit corrected the expressions of `analyser-ignore`, `lint-config-off`, `frontend-test-skip` and `ci-skipped-steps`; after `--apply` on a copy of this repository at 061caf7, `bash scripts/ci-ratchets.sh --self-test` prints `361 cases run, 20 wrong`, and the doctor says `Diverged: no`. A project has to diff the tables by hand (`docs/kit-sync.md`, step 4). The same gap hides the `unused-ignore` form.
P5-2. **The reference's `TypedReceivers` does not pass the kit's own analyser settings.** At PHPStan level 10 with the kit's `phpstan.neon` it has 11 errors: `$this->variables[array_key_last($this->variables)]` reads an offset that might not exist (`offsetAccess.notFound`, `offsetAccess.invalidOffset`), the stack assignment does not keep the list type, and `$parameter instanceof Param` is always true on `FunctionLike::getParams()`. Repro: copy `reference-project/tests/Support/Architecture/TypedReceivers.php` into a project with the kit's configs and run `vendor/bin/phpstan analyse --configuration=phpstan-tests.neon`. Here the stacks are read through `end()`. The reference is "illustrative, not runnable", but `04-guards.md` C1 says a hand-written guard passes the project's own checks before the first push.
P5-3. **The reference's GitHub guard rejects the form that the skeleton now documents as an owner exception.** `ci/github-actions.yml` says a project without an image of its own may use a public image or `shivammathur/setup-php`; the guard then fails this repository's `kit-checks.yml` in four ways (repro of P3-5): a service on the runner reached through a published port is "not the DB_HOST of its job: a container job reaches a service by its name" (5 jobs); the smoke job's `curl` of the application under test is read as a download with no checksum; the backend job runs the coverage through `composer test:unit`, which ends in `bash scripts/checks.sh --coverage`, and the guard wants that line in the job itself; and the file that holds the exception is not the file the guard names. The starter keeps its own `GithubWorkflowGuard`, which reads the form it uses.
P5-4. **The reference guards carry prose comments that the row `comments` counts.** `comments` counts the lines of every source and test path; the four guards of the kit's last hardening commit (`TypedReceivers`, `ReportNotLogGuard`, `RawTableGuard`, `GlobalImportGuard`) hold 33 lines of docblock prose, so copying them turns a row at 0 into 33, which no override can raise without `[ratchet: comments]`. They were removed here (the `@param`, `@return` and `@var` tags stay). The reference README should say that its prose is for the reader of the kit and is deleted in a project.
P5-5. **Not isolated: a Rector pass over a folder of the kit's raw guards dropped a variable.** Running `vendor/bin/rector process` over `tests/Support/Architecture` with the kit's `rector.php` and 31 copied guard files left `ReportNotLogGuard` with `TypedReceivers::in(...)` called and its result unused, and the callers passing one argument fewer than the method takes (`Too few arguments ... severeLogInCatchLines()`). A second pass over the same file alone did not reproduce it and kept the variable. The pass has to be followed by the guard's own tests, as `templates/tool-configs.md` says for any file the sets touch.
P5-6. **`checks.sh` in CI needs a base or `--main` even for the parts that read no base.** The canary's `bash scripts/checks.sh --part static` stopped with "no base branch" on a manual run (no `CHECK_BASE`, no pull request). The canary now runs `--main --part static`; the kit's usage text says nothing about a scheduled or manual run that is neither.
P5-7. **A kit checkout inside the workspace is formatted by the formatter.** `actions/checkout` with `path: kit-main` puts the kit's 250 reference files under the repository, and `vendor/bin/pint --test` then fails on them. The canary moves the checkout to `$RUNNER_TEMP` before the static part; nothing in the kit says a workflow that reads the kit's checkout next to a project must do so.

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

- The reference's GitHub guards (`GithubWorkflowGuard` and its rule classes): the starter's own guard reads the runner-service form (P5-3).
- The tenant, Inertia, frontend and broadcast guards: the starter has no such subject.
- The larger `LaravelMagicUsage` provider and `DatabaseRuleGuard`'s tenant argument: the starter's smaller provider covers its classes.
- The `bootingRoots` argument of `TestTierGuard`: the starter names its one base test case in the guard.
- The seven `Arch*Test` files of the starter that still hold several guards each: they work; the kit's one-test-per-guard form is not applied to them yet.
