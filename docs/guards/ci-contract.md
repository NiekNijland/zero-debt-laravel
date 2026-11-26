# Guard: the CI contract

The contract test reads the workflow files, the checks script, the task runner scripts, the code-owners file and the guides, and fails a pipeline that narrows a check. A guard class of `tests/Support/Architecture` holds each rule, and every rule has a failing example in its thin test.

| Rule id | The guide must say (the words the guard pins) | Instead |
| --- | --- | --- |
| github-workflow | five runs; has no path filter; one step per part; checks-result; has a manual trigger; full commit sha; checked against a checksum; integer `timeout-minutes`; no `continue-on-error`; from the lock file; the full dashboard; whole-tree mutation run; `fetch-depth: 0`; A suite runs once per trigger; `contents: read`; `pull_request_target`; `DATABASE_IMAGE` | the kit's `GithubWorkflowGuard` names the file, the job and the rule in its message: fix that line of the workflow (add the missing run, set the limit, pin the action to its sha with the release in a comment, remove the line that lets a check fail) |
| drivers | the production drivers | set the production drivers in the smoke job |
| mutation | mutation tests on the changed files | run the mutation script with --changed in the tests job |
| checks-script | four stages in this order; holds no `<placeholder>`; `DEFAULT_BASE_BRANCH`; production dependencies only | restore the order of the stages and the tasks of the checks script, set DEFAULT_BASE_BRANCH to the branch pull requests target, and run the audit of the deploying branch with --no-dev |
| guard-group | guard test file is in the guard group | add uses()->group('arch') to the test |
| analyser-paths | name every folder of the source and test paths | add the folder or the include to the analyser config |
| composer-scripts | The six scripts of `composer.json` keep their names | restore the script, or the line of the checks script it runs |
| blame-revisions | `.git-blame-ignore-revs` exists | restore the file and keep only commits of the history |
| code-owners | covered by a code-owners rule that names a person | add the path to .github/CODEOWNERS with an owner |
| documented-path | every backticked path | name the file or folder as it is now, or write a placeholder |
| guide-rules | loses the words of a rule | restore the sentence that the row pins |
| one-pattern-rubric | a row that is in one and not in the other | add the row to the rubric table, or remove the stale one |

Review checks, and the guard does not: whether a host setting is on, which is rubric item 2 and the list of `docs/ci.md`, "What the repository cannot hold".
