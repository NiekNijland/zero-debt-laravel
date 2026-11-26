# The link to the kit

The scripts under `scripts/` are copies of the zero-debt kit (`NiekNijland/zero-debt-kit-laravel`) with this project's configuration. The one line of `.zero-debt-kit-version` records which kit commit they derive from.

## The pin

Format: `<owner>/<repository>@<40 character commit hash>`, one line, nothing else. The workflows read it with one regular expression and fail when it does not match. A commit hash and not a tag, because the kit's `main` moves between releases and a hash cannot be moved.

## What belongs to whom

| Part of a script | Owner | A sync |
| --- | --- | --- |
| The body of a script | the kit | replaced by the kit's body |
| The config block (`# >>> config` to `# <<< config`) | this project | kept |
| The rows of the ratchet table of `scripts/ci-ratchets.sh` | this project | kept; the rows the kit added are listed, never copied; a row the kit changed since this project took it is listed and carried |
| The values of the config blocks | this project | kept, except a value that equals an older kit default: the kit changed it and this project did not decide it, so the kit's comes in |
| The task commands of `scripts/checks.sh` | this project | kept, with the task lines this project added (`Manifest`, `Manifest floor`, `Unused packages`) |

## The workflows

- **`.github/workflows/kit-sync.yml`**, weekly and by hand. It checks out the kit's default branch next to the repository and compares its head with the pin. When the kit moved, it runs the kit's `doctor.sh` against this repository, applies the upgrade of the scripts with this project's config put back, writes the new pin, proves `bash scripts/ci-ratchets.sh --self-test` still passes and opens one pull request with the doctor's report as its description. It commits and pushes through the pull request action only, and it opens the pull request with the default token, or with the secret `KIT_SYNC_TOKEN` when one is set, which is what makes the checks run on the bump pull request.
- **`.github/workflows/kit-canary.yml`**, nightly and by hand. It checks out the kit's default branch, writes the doctor's drift report to the run summary, puts the kit's scripts into the workspace of the run (never into a commit) and runs the ratchet self-test, the ratchet rows, the dashboard and the `static` and `guards` parts of the checks against them. The gate part is left out: it compares the scripts with the base branch and fails on any changed script by design. A red canary means the kit's next version breaks this project's checks, found before the bump pull request, not in it. It writes nothing to the repository.

Both read the kit's `doctor.sh` and fail with a message when a kit checkout has no such file. Both run `--apply`, which carries the changed rows, the changed config values and keeps the task lines of `scripts/checks.sh` that this project added. The canary runs the `static` and `guards` parts as they are (no base is needed for a part that reads none) and moves the kit checkout out of the workspace first, because the formatter reads every folder below the repository root.

The contract test (`tests/Unit/GithubWorkflowArchitectureTest.php`) reads `kit-checks.yml` as the kit's file and `kit-canary.yml` as an auxiliary workflow: it runs the checks script on purpose, so its jobs and hazards are read, but it needs no pull request trigger, no aggregate job and no matrix, and it may have `workflow_dispatch`. `kit-sync.yml` runs no checks and is not read: it holds a secret by design (`KIT_SYNC_TOKEN`), which the guard refuses in every workflow it reads.

A workflow that has only `schedule` and `workflow_dispatch` triggers is registered by GitHub only after a push to the default branch that touches it (`KIT-FINDINGS.md`, P4-2): a repository made from this template needs one such commit before the sync and the canary can be started.

## When a kit script changes

1. The bump pull request holds the changed scripts. Every changed script is a guarded file, so the commit carries `[ratchet: script] <reason>`; the sync writes it, a person may reword it.
2. The code owner reviews the pull request and reads the diff of every script line by line (rubric item 2). The author of a bump does not approve it.
3. The checks of the pull request run on the new scripts, and a row that moved the wrong way fails there. The owner decides per new row: a new ratchet row is a pull request of its own with its cap.
4. A script that this project edited in its body is reported by the doctor as `body-differs`. Read the diff summary before overwriting it, and put the change into the kit, or into the config block, if it was a need. The ratchet table is the project's too: the doctor lists the rows the kit added, and carries the rows the kit changed since this project took them (the project's row equals an older kit row). A row that differs from the kit's and equals no kit commit's is this project's own edit and stays; compare it with the kit's row when `bash scripts/ci-ratchets.sh --self-test` fails for it.
5. After the merge, `bash scripts/ci-ratchets.sh --self-test` and `bash scripts/checks.sh --part gate` are green and the doctor reports that the scripts match the kit commit of the pin.

## Run by hand

```sh
git clone https://github.com/NiekNijland/zero-debt-kit-laravel ../zero-debt-kit-laravel
bash ../zero-debt-kit-laravel/tools/doctor.sh --project .
bash ../zero-debt-kit-laravel/tools/doctor.sh --project . --upgrade-plan
```
