#!/usr/bin/env bash

# >>> config: the project sets every value in this block
# The mutation tool of the stack: pest, infection or stryker (tools/README.md, "Mutation").
MUTATION_TOOL="pest"
# The committed minimum score: one line "threshold: <whole number>". The min ratchet row mutation-floor holds the number.
MUTATION_FLOOR_FILE="scripts/mutation-floor.txt"
# The tracked source folders a pull request's changed files are taken from, separated by spaces.
MUTATION_SOURCE_FOLDERS="app"
# Of the changed files only those are mutated that match the first extended regular expression and not the second. Stryker stops
# with an error on a file it cannot parse (a .css or .json in the source folder) and mutates a test file it is named (the score
# of a test file is no score of the code), so the defaults keep source files of PHP and TypeScript/JavaScript and drop test files.
MUTATION_FILE_PATTERN='\.(php|ts|tsx|js|jsx|mjs|cjs|mts|cts)$'
MUTATION_EXCLUDE_PATTERN='(\.(test|spec)\.[a-z]+$|/__tests__/|Test\.php$)'
# The worker count of Pest (--processes) and Infection (--threads): a whole number from 1. Pest's own default is one worker per
# core, and on a machine with many cores the workers starve each other: the same code scored 75% (29 untested, 10 timeouts) with the
# default and 94.59% with 4. The default is 4; raise it on a runner with the cores to spare, and keep the score at the floor.
MUTATION_PROCESSES="4"
# <<< config

SHARED_CONFIG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/kit-config.sh"
[ -r "$SHARED_CONFIG" ] || {
    echo "Mutation: cannot read $SHARED_CONFIG: kit-config.sh belongs next to this script" >&2
    exit 64
}
. "$SHARED_CONFIG"

set -euo pipefail

fail() {
    echo "Mutation: $1" >&2
    exit 1
}

case "$MUTATION_TOOL$MUTATION_SOURCE_FOLDERS" in
    *"<"*">"*) fail "the config block of this script still holds a placeholder" ;;
esac

case "$MUTATION_PROCESSES" in
    "" | *[!0-9]* | 0*) fail "MUTATION_PROCESSES is \"$MUTATION_PROCESSES\": a whole number from 1" ;;
esac

MODE="${1:-}"
case "$MODE" in
    "" | --changed) ;;
    *)
        echo "Usage: bash scripts/mutation.sh [--changed]" >&2
        echo "  (no flag)  the whole tree: the scheduled job" >&2
        echo "  --changed  the source files this pull request changes against CHECK_BASE: the test step of a pull request, from step 9" >&2
        exit 64
        ;;
esac

[ -r "$MUTATION_FLOOR_FILE" ] || fail "cannot read $MUTATION_FLOOR_FILE"
FLOOR=$(sed -n -E 's/^threshold: ([0-9]+)$/\1/p' "$MUTATION_FLOOR_FILE" | awk 'NR == 1')
[ -n "$FLOOR" ] || fail "$MUTATION_FLOOR_FILE holds no line \"threshold: N\" with N a whole number"

CHANGED=""
if [ "$MODE" = "--changed" ]; then
    # The base of the pull request: CHECK_BASE, else the host's variable (BASE_BRANCH_VARS), else the default branch.
    BASE="${CHECK_BASE:-}"
    for variable in $BASE_BRANCH_VARS; do
        [ -n "$BASE" ] || BASE=$(eval "printf '%s' \"\${$variable:-}\"")
    done
    BASE="${BASE:-$DEFAULT_BASE_BRANCH}"
    kit_local_base "$BASE" || fail "$KIT_BRANCH_ERROR"
    # shellcheck disable=SC2086
    CHANGED_FILES=$(git -c core.quotePath=false diff --no-renames --name-only --diff-filter=AM "$REMOTE/$BASE"...HEAD -- $MUTATION_SOURCE_FOLDERS)
    # An explicit loop, not a grep whose failure is forced to success: such a tail reads as a skipped step to the ci-skipped-steps ratchet.
    while IFS= read -r file; do
        [ -n "$file" ] || continue
        grep -q -E -e "$MUTATION_FILE_PATTERN" <<< "$file" || continue
        if grep -q -E -e "$MUTATION_EXCLUDE_PATTERN" <<< "$file"; then continue; fi
        CHANGED="${CHANGED:+$CHANGED,}$file"
    done <<< "$CHANGED_FILES"
    if [ -z "$CHANGED" ]; then
        echo "Mutation: this pull request changes no source file; nothing to mutate."
        exit 0
    fi
fi

# Stryker (10.1.1) has no command-line option for the break threshold ("unknown option '--thresholds.break'"): stryker.config.mjs
# reads the floor from the environment variable MUTATION_FLOOR that this script sets (thresholds: { break:
# Number(process.env.MUTATION_FLOOR) }), so the floor file stays the one place of the number. A config that ignores the variable
# would hold no floor and exit 0 below it, so the run's output must name the floor: "break threshold <N>" (passed) or "breaking
# threshold <N>" (failed). Stryker reads --mutate as globs: a path with [ ] ( ) { } * ! (a Next.js route) matches nothing, even
# escaped, so those characters become the single-character wildcard ?.
run_stryker() {
    local output status
    output=$(mktemp)
    # pipefail is on: the pipeline's status is stryker's, and the if keeps a failure from stopping the script before the check below.
    if MUTATION_FLOOR="$FLOOR" npx stryker run "$@" 2>&1 | tee "$output"; then status=0; else status=$?; fi
    if ! grep -q -E "(breaking|break) threshold $FLOOR( |,|$)" "$output"; then
        rm -f "$output"
        [ "$status" -eq 0 ] || exit "$status"
        fail "the Stryker run does not name the floor $FLOOR: stryker.config.mjs must hold thresholds: { break: Number(process.env.MUTATION_FLOOR) }, and the run must have mutated something"
    fi
    rm -f "$output"
    exit "$status"
}
case "$MUTATION_TOOL:$MODE" in
    pest:) vendor/bin/pest --mutate --everything --parallel --processes="$MUTATION_PROCESSES" --min="$FLOOR" ;;
    pest:--changed) vendor/bin/pest --mutate --parallel --processes="$MUTATION_PROCESSES" --path="$CHANGED" --min="$FLOOR" ;;
    infection:) vendor/bin/infection --threads="$MUTATION_PROCESSES" --min-msi="$FLOOR" ;;
    infection:--changed) vendor/bin/infection --threads="$MUTATION_PROCESSES" --min-msi="$FLOOR" --git-diff-filter=AMR --git-diff-base="$REMOTE/$BASE" ;;
    stryker:) run_stryker ;;
    stryker:--changed) run_stryker --mutate "$(printf '%s' "$CHANGED" | sed 's/[][(){}*!]/?/g')" ;;
    *) fail "MUTATION_TOOL is \"$MUTATION_TOOL\": it is one of pest, infection, stryker" ;;
esac
