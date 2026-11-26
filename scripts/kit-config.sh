#!/usr/bin/env bash

# The values more than one script reads. ci-ratchets.sh, debt-dashboard.sh, ci-commit-messages.sh and checks.sh source this file
# from the folder they are in. It is a guarded file: a change needs [ratchet: script] <reason>.

# >>> config: the project sets every value in this block
# The base branch on a developer's machine, and the remote. In CI the base comes from CHECK_BASE or the host's variable.
DEFAULT_BASE_BRANCH="main"
REMOTE="origin"
BASE_BRANCH_VARS="BITBUCKET_PR_DESTINATION_BRANCH GITHUB_BASE_REF CI_MERGE_REQUEST_TARGET_BRANCH_NAME"
HEAD_BRANCH_VARS="BITBUCKET_BRANCH GITHUB_HEAD_REF CI_MERGE_REQUEST_SOURCE_BRANCH_NAME"
CAPS_FILE="scripts/debt-caps.json"
# The database service image of the test and smoke runs: the production engine in the production major version,
# for example postgres:17 or mysql:8.4; empty when the project has no database. checks.sh refuses to run while the
# placeholder is left. No script reads the value: the contract test over the CI files compares the service image
# of the pipeline with it.
DATABASE_IMAGE="<engine:major of production>"
# <<< config
