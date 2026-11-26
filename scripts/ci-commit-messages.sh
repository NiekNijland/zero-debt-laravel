#!/usr/bin/env bash

# >>> config: the project sets every value in this block
# The ticket as an extended regular expression, for example TICKET='PROJ-[0-9]+'. With no ticket system: the fixed
# prefix, TICKET='NOTICKET'. EXAMPLE is what the failure message prints, for example 'PROJ-123 feat: add the export'.
# The script refuses to run while a placeholder is left in either.
TICKET='<ticket pattern>'
TYPES='feat|fix|refactor|test|docs|chore|style|ci|perf|build|revert'
EXAMPLE='<example subject>'
# Titles a tool or the host writes, accepted without the form (an extended regular expression, matched over the whole subject).
# A commit with more than one parent is never checked, so "Merge branch ..." and "Merge pull request ..." are not listed: on a
# single-parent commit they would hide work behind a generated-looking title. A project that keeps a tool-made title adds its
# exact text here, for as long as open branches hold it.
GENERATED='^(Merge [0-9a-f]{7,40} into [0-9a-f]{7,40}|Merged in .+ \(pull request #[0-9]+\)|Revert ".+"|Reapply ".+")$'
# The base branch, the remote and the host's variables are in kit-config.sh, next to this script.
# <<< config

SHARED_CONFIG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/kit-config.sh"
[ -r "$SHARED_CONFIG" ] || {
    echo "Commit messages: cannot read $SHARED_CONFIG: kit-config.sh belongs next to this script" >&2
    exit 64
}
. "$SHARED_CONFIG"

set -euo pipefail

fail() {
    echo "Commit messages: $1" >&2
    exit 1
}

case "$TICKET$EXAMPLE" in
    *"<"*">"*) fail "TICKET or EXAMPLE in the config block of this script still holds a placeholder: set the project's ticket pattern (with no ticket system: TICKET='NOTICKET') and an example subject" ;;
esac

BASE_BRANCH="${CHECK_BASE:-}"
for variable in $BASE_BRANCH_VARS; do
    [ -n "$BASE_BRANCH" ] || BASE_BRANCH=$(eval "printf '%s' \"\${$variable:-}\"")
done
if [ -z "$BASE_BRANCH" ]; then
    [ -z "${CI:-}" ] || fail "no base branch. This is a CI run and none of CHECK_BASE, ${BASE_BRANCH_VARS// /, } is set: run the check on pull requests, or set CHECK_BASE"
    BASE_BRANCH="$DEFAULT_BASE_BRANCH"
fi
HEAD_BRANCH=""
for variable in $HEAD_BRANCH_VARS; do
    [ -n "$HEAD_BRANCH" ] || HEAD_BRANCH=$(eval "printf '%s' \"\${$variable:-}\"")
done

BASE_REF="refs/remotes/$REMOTE/$BASE_BRANCH"
if ! git fetch -q "$REMOTE" "+refs/heads/$BASE_BRANCH:$BASE_REF" 2> /dev/null; then
    git rev-parse -q --verify "$BASE_REF" > /dev/null || fail "cannot fetch $REMOTE/$BASE_BRANCH"
    [ -z "${CI:-}" ] || fail "cannot fetch $REMOTE/$BASE_BRANCH in a CI run, so the copy already here may be stale"
    echo "Commit messages: cannot fetch $REMOTE/$BASE_BRANCH; comparing against the copy already here (a developer's machine only)" >&2
fi
BASE_TIP=$(git rev-parse "$BASE_REF")
HEAD_SHA=$(git rev-parse "${CHECK_HEAD:-HEAD}^{commit}") || fail "cannot read the head ${CHECK_HEAD:-HEAD}"

DEPTH="Clone with full depth (Bitbucket: clone depth full; GitHub: fetch-depth 0; GitLab: GIT_DEPTH 0)"
for deepen in 200 2000 all; do
    ! git merge-base "$HEAD_SHA" "$BASE_TIP" > /dev/null 2>&1 || break
    [ "$(git rev-parse --is-shallow-repository)" = "true" ] || break
    if [ "$deepen" = "all" ]; then
        git fetch -q --unshallow "$REMOTE" "$BASE_BRANCH" ${HEAD_BRANCH:+"$HEAD_BRANCH"} 2> /dev/null || fail "cannot fetch the full history. $DEPTH"
    else
        git fetch -q --deepen="$deepen" "$REMOTE" "$BASE_BRANCH" ${HEAD_BRANCH:+"$HEAD_BRANCH"} 2> /dev/null || fail "cannot deepen the shallow clone. $DEPTH"
    fi
done
git merge-base "$HEAD_SHA" "$BASE_TIP" > /dev/null 2>&1 || fail "no merge base between $HEAD_SHA and $REMOTE/$BASE_BRANCH, so the commits of this pull request cannot be listed. $DEPTH"

FORM="^(${TICKET}) (${TYPES})(\([^)]+\))?!?: [^[:space:]]"

CHECKED=0
INVALID=""
while read -r commit parents; do
    [ -n "$commit" ] || continue
    [ -z "${parents#* }" ] || [ "$parents" = "${parents#* }" ] || continue
    subject=$(git log -1 --format=%s "$commit")
    CHECKED=$((CHECKED + 1))
    if ! grep -q -E -e "$FORM" <<< "$subject" && ! grep -q -E -e "$GENERATED" <<< "$subject"; then
        INVALID="$INVALID${commit:0:9} $subject"$'\n'
    fi
done <<< "$(git log --format='%H %P' "$BASE_TIP..$HEAD_SHA")"

if [ -n "$INVALID" ]; then
    echo "Every commit starts with its ticket and a type: <TICKET> <type>[(scope)][!]: <subject>, for example \"$EXAMPLE\"."
    echo "Ticket: $TICKET. Types: ${TYPES//|/, }. These commits do not have that form:"
    printf '%s' "$INVALID"
    exit 1
fi

echo "Commit messages: $CHECKED commits between $REMOTE/$BASE_BRANCH and ${HEAD_SHA:0:9} start with a ticket and a type."
