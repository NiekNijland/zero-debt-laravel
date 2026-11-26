#!/usr/bin/env bash

# >>> config: the project sets every value in this block
# The mutation tool of the stack: pest, infection or stryker (tools/README.md, "Mutation").
MUTATION_TOOL="<pest, infection or stryker>"
# The committed minimum score: one line "threshold: <whole number>". The min ratchet row mutation-floor holds the number.
MUTATION_FLOOR_FILE="scripts/mutation-floor.txt"
# The tracked source folders a pull request's changed files are taken from, separated by spaces.
MUTATION_SOURCE_FOLDERS="<source folders>"
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
    # shellcheck disable=SC2086
    CHANGED=$(git -c core.quotePath=false diff --no-renames --name-only --diff-filter=AM "$REMOTE/$BASE"...HEAD -- $MUTATION_SOURCE_FOLDERS | tr '\n' ',' | sed 's/,$//')
    if [ -z "$CHANGED" ]; then
        echo "Mutation: this pull request changes no source file; nothing to mutate."
        exit 0
    fi
fi

# Stryker has no command-line option for the break threshold: stryker.config.mjs reads the floor from the environment variable
# MUTATION_FLOOR that this script sets (thresholds: { break: Number(process.env.MUTATION_FLOOR) }), so the floor file stays the
# one place of the number. Stryker and Infection are not run for this kit: verify both lines in step 2.
case "$MUTATION_TOOL:$MODE" in
    pest:) vendor/bin/pest --mutate --everything --parallel --min="$FLOOR" ;;
    pest:--changed) vendor/bin/pest --mutate --parallel --path="$CHANGED" --min="$FLOOR" ;;
    infection:) vendor/bin/infection --threads=max --min-msi="$FLOOR" ;;
    infection:--changed) vendor/bin/infection --threads=max --min-msi="$FLOOR" --git-diff-filter=AMR --git-diff-base="$REMOTE/$BASE" ;;
    stryker:) MUTATION_FLOOR="$FLOOR" npx stryker run ;;
    stryker:--changed) MUTATION_FLOOR="$FLOOR" npx stryker run --mutate "$CHANGED" ;;
    *) fail "MUTATION_TOOL is \"$MUTATION_TOOL\": it is one of pest, infection, stryker" ;;
esac
