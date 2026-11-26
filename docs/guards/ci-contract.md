# Guard: the CI contract

The contract test reads the workflow files, the checks script, the task runner scripts, the code-owners file and the guides, and fails a pipeline that narrows a check. A guard class of `tests/Support/Architecture` holds each rule, and every rule has a failing example in its thin test.

| Rule id | The guide must say (the words the guard pins) | Instead |
| --- | --- | --- |
| five-runs | five runs; has no path filter | add the missing run or remove the filter in `kit-checks.yml` |
| checks-parts | one step per part; checks-result | give each part of the checks script a matrix entry and one step with no condition |
| no-manual-trigger | has a manual trigger | remove the trigger: a check nobody has to run is a report |
| pinning | full commit sha; checked against a checksum | pin the action to its sha with the release in a comment, and check a download |
| time-limits | integer `timeout-minutes` | set an integer timeout-minutes on the job and on every step |
| nothing-fails | no `continue-on-error` | remove the line that lets a check fail |
| database-and-drivers | `DATABASE_IMAGE`; the production drivers | use the image of DATABASE_IMAGE and set the production drivers in the smoke job |
| lock-installs | from the lock file | install with composer install |
| scheduled-run | the full dashboard; whole-tree mutation run | run the scheduled checks and the whole-tree mutation run in the job on the schedule event |
| mutation | mutation tests on the changed files | run the mutation script with --changed in the tests job |
| full-history | `fetch-depth: 0` | set fetch-depth: 0 and keep the credentials of the checkout |
| one-suite-per-trigger | A suite runs once per trigger | remove the second job or workflow that runs the suite on the event |
| read-only | `contents: read`; `pull_request_target` | set contents: read, remove the write permission and the trigger |
| checks-script | four stages in this order; holds no `<placeholder>` | restore the order of the stages and the tasks of the checks script |
| base-branch | `DEFAULT_BASE_BRANCH` | set DEFAULT_BASE_BRANCH to the branch pull requests target |
| audit-production-only | production dependencies only | run the audit of the deploying branch with --no-dev and keep the full audit for pull requests |
| guard-group | guard test file is in the guard group | add uses()->group('arch') to the test |
| analyser-paths | name every folder of the source and test paths | add the folder or the include to the analyser config |
| composer-scripts | The six scripts of `composer.json` keep their names | restore the script, or the line of the checks script it runs |
| blame-revisions | `.git-blame-ignore-revs` exists | restore the file and keep only commits of the history |
| code-owners | covered by a code-owners rule that names a person | add the path to .github/CODEOWNERS with an owner |
| documented-path | every backticked path | name the file or folder as it is now, or write a placeholder |
| guide-rules | loses the words of a rule | restore the sentence that the row pins |
| one-pattern-rubric | a row that is in one and not in the other | add the row to the rubric table, or remove the stale one |

Review checks, and the guard does not: whether a host setting is on, which is rubric item 2 and the list of `docs/ci.md`, "What the repository cannot hold".
