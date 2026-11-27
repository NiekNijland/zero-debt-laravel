# CI

The pipeline is one workflow file of its own, `.github/workflows/kit-checks.yml`. A developer runs the same checks before a push with `bash scripts/checks.sh`, so the result in CI is the result on the machine. `tests/Unit/CiContractArchitectureTest.php` reads the workflow files, the checks script and the task runner scripts on every run, and the rules it pins are in `docs/guards/ci-contract.md`.

## The five runs

| Run | When | What |
| --- | --- | --- |
| checks | every pull request | `bash scripts/checks.sh --part <name>`, one step per part of the checks script (`gate`, `static`, `guards`, `repo`) as a matrix; the aggregate job `checks-result` fails unless every part passed |
| checks on the main branch | every push to `main` | the same parts with `--main`, which has no base to compare with |
| tests | every pull request and every push to `main` | `composer test:unit`: Pest in parallel with pcov coverage held at the committed floor; on a pull request also the mutation tests on the changed files |
| smoke | every pull request and every push to `main` | a stack booted with seeded data and the production drivers, then five checks, the last one signed in |
| scheduled | once a day | `bash scripts/checks.sh --scheduled`, the full dashboard with its online rows, and the whole-tree mutation run |

There are five runs, and the checks run has no path filter: a pull request that only edits a guide runs every check. No job has a manual trigger, and nothing is allowed to fail: no `continue-on-error`, no `|| true`, no job switched off.

## Rules of the workflow files

- **One step per part.** The matrix of the checks job equals `bash scripts/checks.sh --list-parts`, and the step runs exactly `bash scripts/checks.sh --part ${{ matrix.part }}`, with no condition. A new part of the checks script is a new matrix entry.
- **Required status checks.** The branch rule of `main` requires `checks-result`, `backend-tests` and `smoke`, never the four `checks (<part>)` names.
- **Pinning.** Every `uses:` ends in a full commit sha with its release in a comment after it; the dependency bot keeps both current. The runner label is `ubuntu-24.04`, never a `latest` label. The one downloaded tool, gitleaks, is checked against a checksum. The toolchain comes from `shivammathur/setup-php`, so no job runs in a container image; a job that does names the image by digest.
- **Time limits.** Every job has an integer `timeout-minutes`, and so does every step.
- **Full history.** Every checkout sets `fetch-depth: 0` and none sets `persist-credentials: false`, because the checks script fetches the base with the credentials the checkout leaves.
- **Read only.** The workflow has `contents: read` at the top, a job that needs more says so on the job, and no check job writes: nothing commits, adds or pushes. `pull_request_target` is never used. The concurrency group cancels the older run of a pull request and never a push to `main`.
- **Lock file.** Every install is `composer install` from the lock file; `composer update` and `composer require` never run in a check.
- **The database.** The service image is `postgres:17`, the value of `DATABASE_IMAGE` in `scripts/kit-config.sh`, with a password, a health check and the published port 5432, the port of `compose.yaml` and `phpunit.xml`. The smoke job sets the production drivers: database cache, database queue and the `pgsql` connection.
- **Mutation.** The tests job runs `bash scripts/mutation.sh --changed` on a pull request; the scheduled job runs `bash scripts/mutation.sh` over the whole tree.
- **One suite per trigger.** A suite runs once per trigger: no two jobs or workflows run `composer test:unit` on the same event.

## The checks script

`scripts/checks.sh` has four stages in this order: the base copy of the ratchet script with `--guard`, the ratchets, the dashboard with `--offline --details`, and the parallel checks. It holds no `<placeholder>`. Every task checks the whole tree: no command takes a list of changed files, the formatter runs with `--test`, the refactoring tool with `--dry-run` and type coverage with `--min=100`. The audit of a deploying branch covers production dependencies only (`--no-dev`), the audit of a pull request covers everything. `DEFAULT_BASE_BRANCH` in `scripts/kit-config.sh` is the branch pull requests target.

## The task runner and the analyser

- The six scripts of `composer.json` keep their names: `test`, `test:lint`, `test:types`, `test:type-coverage`, `test:unit` and `lint`. `test` is `bash scripts/checks.sh`, every command of the three `test:*` check scripts is a line of the checks script, and `lint` rewrites files, so no workflow calls it.
- The analyser paths name every folder of the source and test paths of `scripts/ci-ratchets.sh` and `public`, and the configs include the bleeding-edge file and each extension.
- Every guard test file is in the guard group, `uses()->group('arch')`, and the checks script runs `--group=arch`. A guard test that the owner of the pipeline keeps outside the group is named as an exception in the thin test; none is.
- `.git-blame-ignore-revs` exists and every hash in it is a commit of the history.

## Code owners

The files that carry the guards are covered by a code-owners rule that names a person: `scripts/`, `.github/workflows/`, the guard folder `tests/Support/Architecture`, the thin tests `tests/Unit/*ArchitectureTest.php`, `renovate.json`, `.zero-debt-kit-version` and `.github/CODEOWNERS` itself. `tests/Unit/CodeOwnersArchitectureTest.php` fails a guard file that no rule covers. The override tag sits in a commit message that the author writes, so only the review of the code owner stops an author from writing it to get past a guard.

## What the repository cannot hold

These settings live on the host and are the owner's to set and to read back:

- Branch protection on `main`: require a pull request, require review from code owners, require the status checks `checks-result`, `backend-tests` and `smoke`, and require branches to be up to date before merging. The repository of this template has it set, with repository admins allowed to bypass so that the single owner can still push; a project with a team sets no bypass.
- The setting "Allow GitHub Actions to create and approve pull requests" (Settings, Actions, General): the bump pull request of `docs/kit-sync.md` is opened with the default token. A pull request opened with the default token starts no checks, so the secret `KIT_SYNC_TOKEN`, a token that may open pull requests, is optional: when it is set, the workflow uses it and the bump pull request starts the checks.
- No merge queue: the workflow has no `merge_group` trigger on purpose.

## Smoke

The smoke job migrates and seeds a PostgreSQL 17 database, starts the built-in PHP server with the production drivers in its environment and checks five things: the application reports the cache, queue, session and database drivers of production; a page answers with its body; the API refuses a request without a token with the translated message; the rate limit header is present; the demo user signs in with a token, writes a note and reads it back. The production seed creates the permissions and no user, because `DemoUserSeeder` refuses to run in production; the job runs that one seeder with `APP_ENV=local` and issues the token with `php artisan notes:issue-token`, so no password or token is stored in the repository.

## Not applicable here

The starter has no frontend, so two points of the kit's contract have no subject: the type-aware lint cache and a browser in the smoke run. The `frontend-tests` job of the kit's skeleton is left out.

