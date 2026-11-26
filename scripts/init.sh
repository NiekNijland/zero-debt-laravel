#!/usr/bin/env bash

# Turns this template into your project, once. It sets the project name, the ticket prefix, the database image, the base
# branch and the code owner in every file that holds them (the owner replaces the one of every rule of .github/CODEOWNERS),
# drops the badges of the template from the README, refreshes the lock file hash, and deletes itself with its test.
#
#   bash scripts/init.sh [--dry-run] [--name <kebab-case name>] [--vendor <composer vendor>] [--ticket <PREFIX|NOTICKET>]
#                        [--database-image <postgres:NN>] [--base-branch <branch>] [--owner <@user|@org/team>]
#
# A value that is not given as an argument is asked for, with the default in brackets. --dry-run prints every change and
# writes nothing. Every change sets a line by its pattern and not by its old value, so a run that stopped half way is
# run again with the same arguments. INIT_COMPOSER=<binary> names another composer. The files it changes are guarded
# files: commit the result with the line [ratchet: script] instantiate the template in the message, and have the code
# owner review it.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DRY=0
NAME=""
VENDOR=""
TICKET_PREFIX=""
IMAGE=""
BASE=""
OWNER=""

usage() {
    sed -n '3,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//' >&2
    exit 64
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --dry-run) DRY=1; shift ;;
        --name) [ "$#" -ge 2 ] || usage; NAME="$2"; shift 2 ;;
        --vendor) [ "$#" -ge 2 ] || usage; VENDOR="$2"; shift 2 ;;
        --ticket) [ "$#" -ge 2 ] || usage; TICKET_PREFIX="$2"; shift 2 ;;
        --database-image) [ "$#" -ge 2 ] || usage; IMAGE="$2"; shift 2 ;;
        --base-branch) [ "$#" -ge 2 ] || usage; BASE="$2"; shift 2 ;;
        --owner) [ "$#" -ge 2 ] || usage; OWNER="$2"; shift 2 ;;
        -h | --help) usage ;;
        *) echo "Init: unknown argument $1" >&2; usage ;;
    esac
done

ask() {
    local variable="$1" prompt="$2" default="$3" required="${4:-}" answer=""
    if [ -n "${!variable}" ]; then
        return 0
    fi
    if [ -t 0 ]; then
        read -r -p "$prompt [$default]: " answer || answer=""
    elif [ -n "$required" ]; then
        echo "Init: $required is required when no one can be asked (stdin is not a terminal)" >&2
        exit 1
    fi
    printf -v "$variable" '%s' "${answer:-$default}"
}

fail() {
    echo "Init: $1" >&2
    exit 1
}

ask NAME "Project name (kebab-case)" "my-project" "--name"
ask TICKET_PREFIX "Ticket prefix of your commit messages (for example PROJ), or NOTICKET" "NOTICKET"
ask IMAGE "Database image of the test and smoke runs, the production major version" "postgres:17"
ask BASE "Base branch that pull requests target" "main"
ask OWNER "Code owner of the guard files (@user or @org/team, a person or a team, never a bot)" "@OWNER" "--owner"

OWNER_DEFAULT_VENDOR="${OWNER#@}"
OWNER_DEFAULT_VENDOR="$(printf '%s' "${OWNER_DEFAULT_VENDOR%%/*}" | tr '[:upper:]' '[:lower:]')"
ask VENDOR "Composer vendor name" "$OWNER_DEFAULT_VENDOR"

[[ "$NAME" =~ ^[a-z][a-z0-9-]*[a-z0-9]$ ]] || fail "the name \"$NAME\" is not kebab-case (lower-case letters, digits and hyphens, starting with a letter)"
[[ "$VENDOR" =~ ^[a-z0-9][a-z0-9-]*$ ]] || fail "the composer vendor \"$VENDOR\" is not lower-case letters, digits and hyphens"
[[ "$TICKET_PREFIX" =~ ^(NOTICKET|[A-Z][A-Z0-9]+)$ ]] || fail "the ticket prefix \"$TICKET_PREFIX\" is NOTICKET or upper-case letters and digits, such as PROJ"
[[ "$IMAGE" =~ ^postgres:[0-9]+(\.[0-9]+)?$ ]] || fail "the database image \"$IMAGE\" is not postgres:<major>: the starter's drivers, migrations and tests are PostgreSQL"
[[ "$BASE" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || fail "the base branch \"$BASE\" is not a simple branch name"
[[ "$OWNER" =~ ^@[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)?$ ]] || fail "the owner \"$OWNER\" is not @user or @org/team"
[ "$OWNER" != "@OWNER" ] || fail "the owner \"$OWNER\" is the placeholder of the template: name a person or a team"
case "$OWNER" in
    *[Bb]ot | *\[bot\]*) fail "the owner \"$OWNER\" looks like a bot: a person or a team owns the guard files" ;;
esac

if [ "$TICKET_PREFIX" = "NOTICKET" ]; then
    TICKET_PATTERN="NOTICKET"
    EXAMPLE="NOTICKET feat: add the export"
    ZERO="NOTICKET"
else
    TICKET_PATTERN="$TICKET_PREFIX-[0-9]+"
    EXAMPLE="$TICKET_PREFIX-123 feat: add the export"
    ZERO="$TICKET_PREFIX-0"
fi

FILES=(composer.json .env.example compose.yaml README.md scripts/kit-config.sh scripts/ci-commit-messages.sh .github/CODEOWNERS renovate.json .github/workflows/kit-checks.yml .github/workflows/kit-sync.yml)
for file in "${FILES[@]}"; do
    [ -f "$file" ] || fail "$file is missing: this is not the template (run the script from a clean copy of it)"
done
grep -q 'init:start' README.md || fail "README.md has no init block: the template was changed, or init already ran"
COMPOSER_BIN="${INIT_COMPOSER:-composer}"
command -v "$COMPOSER_BIN" > /dev/null 2>&1 || [ "$DRY" -eq 1 ] || fail "$COMPOSER_BIN is not on the PATH: it refreshes the hash of composer.lock (INIT_COMPOSER names another binary)"

changed=0
edit() {
    local file="$1" expression="$2" what="$3" tmp
    tmp="$(mktemp)"
    sed -E "$expression" "$file" > "$tmp"
    if cmp -s "$file" "$tmp"; then
        rm -f "$tmp"
        echo "  unchanged  $file: $what"
        return 0
    fi
    changed=$((changed + 1))
    if [ "$DRY" -eq 1 ]; then
        rm -f "$tmp"
        echo "  would set  $file: $what"
        return 0
    fi
    cat "$tmp" > "$file"
    rm -f "$tmp"
    echo "  set        $file: $what"
}

echo "Init: $([ "$DRY" -eq 1 ] && echo 'dry run, nothing is written' || echo 'writing')"
edit composer.json "s|^(    \"name\": \")[^\"]*(\",)\$|\\1$VENDOR/$NAME\\2|; s|^(    \"description\": \")[^\"]*(\",)\$|\\1$NAME: a Laravel API with the zero-debt guards.\\2|" "name $VENDOR/$NAME"
edit .env.example "s|^APP_NAME=.*\$|APP_NAME=$NAME|" "APP_NAME=$NAME"
edit compose.yaml "s|^(    image: )postgres:[0-9.]+\$|\\1$IMAGE|" "service image $IMAGE"
edit README.md "1s|^# .*\$|# $NAME|" "title $NAME"
edit scripts/kit-config.sh "s|^DEFAULT_BASE_BRANCH=\"[^\"]*\"|DEFAULT_BASE_BRANCH=\"$BASE\"|; s|^DATABASE_IMAGE=\"[^\"]*\"|DATABASE_IMAGE=\"$IMAGE\"|" "DEFAULT_BASE_BRANCH=$BASE, DATABASE_IMAGE=$IMAGE"
edit scripts/ci-commit-messages.sh "s|^TICKET='[^']*'|TICKET='$TICKET_PATTERN'|; s|^EXAMPLE='[^']*'|EXAMPLE='$EXAMPLE'|" "TICKET=$TICKET_PATTERN"
edit .github/CODEOWNERS "s|[[:space:]]+@[A-Za-z0-9._/-]+\$| $OWNER|" "owner $OWNER"
edit renovate.json "s|\"commitMessagePrefix\": \"[^\"]*chore\\(deps\\):\"|\"commitMessagePrefix\": \"$ZERO chore(deps):\"|" "commitMessagePrefix $ZERO"
edit .github/workflows/kit-checks.yml "s|^(        image: )postgres:[0-9.]+\$|\\1$IMAGE|; s|^(    branches: \\[)'[^']*'(\\])\$|\\1'$BASE'\\2|" "service image $IMAGE, push branch $BASE"
edit .github/workflows/kit-sync.yml "s/[A-Z][A-Z0-9]*(-0)? chore\\(kit\\):/$ZERO chore(kit):/g" "commit prefix $ZERO"

if [ "$DRY" -eq 1 ]; then
    echo "Init: dry run done, $changed file change(s) planned; it would also remove the init block of README.md, refresh composer.lock, and delete scripts/init.sh and its test."
    exit 0
fi

"$COMPOSER_BIN" update --lock --no-interaction --quiet
"$COMPOSER_BIN" validate --strict --no-interaction > /dev/null
echo "  set        composer.lock: content hash refreshed, composer validate --strict is green"

tmp="$(mktemp)"
awk '/<!-- init:start -->/ { skip = 1; next } /<!-- init:end -->/ { skip = 0; next } !skip { print }' README.md > "$tmp"
cat "$tmp" > README.md
rm -f "$tmp"
awk '/<!-- badges:start -->/ { skip = 1; next } /<!-- badges:end -->/ { skip = 0; next } !skip { print }' README.md > "$tmp"
cat "$tmp" > README.md
rm -f "$tmp"
echo "  set        README.md: init block and badges removed"

LEFT="$(grep -rIn --exclude-dir=vendor --exclude-dir=.git --exclude-dir=node_modules -e 'zero-debt/laravel' -e '@OWNER' -e 'NiekNijland/zero-debt-laravel' . | grep -v -e '^\./scripts/init\.sh' -e '^\./tests/Unit/InitScriptTest\.php' -e '^\./KIT-FINDINGS\.md' -e '^\./docs/' || [ "$?" -eq 1 ])"
if [ -n "$LEFT" ]; then
    printf '%s\n' "$LEFT" >&2
    fail "a placeholder is left in the files above"
fi

rm -f tests/Unit/InitScriptTest.php
rm -f "$ROOT/scripts/init.sh"
echo "Init: done. Next: copy .env.example to .env, then commit with this line in the message:"
echo "  [ratchet: script] instantiate the template"
echo "The files changed here are guarded: $OWNER reviews that commit. Then run: bash scripts/checks.sh"
