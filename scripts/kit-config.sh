#!/usr/bin/env bash

# The values (and, below the block, the branch-name helpers) more than one script reads. ci-ratchets.sh, debt-dashboard.sh, ci-commit-messages.sh and checks.sh source this file
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
DATABASE_IMAGE="postgres:17"
# <<< config

# Shared by ci-ratchets.sh and ci-commit-messages.sh, which read the branch names from the CI environment. A branch name is data
# from the pull request's author: one that starts with a dash would be read by the version-control tool as an option (an upload-pack
# option runs a command).

# kit_check_branches: BASE_BRANCH and HEAD_BRANCH are set. Returns 1 with the reason in KIT_BRANCH_ERROR when the base is not a
# usable branch name; a head branch that is not one is dropped (HEAD_BRANCH="").
kit_check_branches() {
    case "$BASE_BRANCH" in
        -*)
            KIT_BRANCH_ERROR="the base branch \"$BASE_BRANCH\" starts with a dash"
            return 1
            ;;
    esac
    if ! git check-ref-format "refs/heads/$BASE_BRANCH" 2> /dev/null; then
        KIT_BRANCH_ERROR="the base branch \"$BASE_BRANCH\" is not a branch name"
        return 1
    fi
    case "$HEAD_BRANCH" in
        -*) HEAD_BRANCH="" ;;
    esac
    if [ -n "$HEAD_BRANCH" ] && ! git check-ref-format "refs/heads/$HEAD_BRANCH" 2> /dev/null; then
        HEAD_BRANCH=""
    fi
    return 0
}

# kit_local_base <base branch>: a developer's machine in a repository that has no remote (a first local commit series) has no
# REMOTE/<base branch> to fetch. The local branch of that name stands in for it: its tip is written to refs/remotes/REMOTE/<base branch>
# (KIT_LOCAL_BASE=1) and every script compares against it as against a fetched base. On the base branch itself nothing differs from the
# head, so nothing is compared: work on a branch to have a diff. Never in CI: a CI run without a remote has no base and fails where it
# fetches. Returns 1 (reason in KIT_BRANCH_ERROR) when there is no remote and no local branch of that name.
KIT_LOCAL_BASE=0
kit_local_base() {
    KIT_LOCAL_BASE=0
    [ -z "${CI:-}" ] || return 0
    ! git remote get-url "$REMOTE" > /dev/null 2>&1 || return 0
    if ! git rev-parse -q --verify "refs/heads/$1" > /dev/null 2>&1; then
        KIT_BRANCH_ERROR="there is no remote \"$REMOTE\" and no local branch \"$1\": no base, so the script guard cannot run (commit on $1 first, then work on a branch; or set DEFAULT_BASE_BRANCH to the branch you have)"
        return 1
    fi
    git update-ref "refs/remotes/$REMOTE/$1" "refs/heads/$1" || return 1
    KIT_LOCAL_BASE=1
    return 0
}

# kit_deepen <number of commits|all>: fetch more history of the base branch (a failure returns 1) and, as a courtesy, of the head
# branch (a pull request from a fork has none on this remote: HEAD_BRANCH is dropped, no failure). "--" ends the options.
kit_deepen() {
    local option="--deepen=$1"
    [ "$1" != "all" ] || option="--unshallow"
    git fetch -q "$option" -- "$REMOTE" "$BASE_BRANCH" 2> /dev/null || return 1
    if [ -n "$HEAD_BRANCH" ] && ! git fetch -q "$option" -- "$REMOTE" "$HEAD_BRANCH" 2> /dev/null; then
        HEAD_BRANCH=""
    fi
    return 0
}
