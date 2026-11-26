#!/usr/bin/env bash

# The one command. CI runs it in the checks step; a developer runs the same file before a push.
# Copy to scripts/checks.sh, set the config block, and replace every <placeholder> with the project's command.
# Always run it through bash (bash scripts/checks.sh): the kit's scripts carry no executable bit.
# Every command checks the whole tree and writes no file. Nothing here commits or pushes.
#
#   bash scripts/checks.sh              a pull request, or a branch on a developer's machine: every check
#   bash scripts/checks.sh --main       a push to the main branch: no base to compare with, so the script guard,
#                                       the ratchets, the commit-message check and the secret scan are left out
#   bash scripts/checks.sh --scheduled  the scheduled job: only the dashboard, in its full run (no --offline)
#   bash scripts/checks.sh [--main] --part <name>
#                                       one part of the run above, one pipeline step each (a lone check on 4 CPUs is
#                                       2 to 3 times faster than 13 of them side by side); parts are in PARTS below
#   bash scripts/checks.sh --list-parts the names of the parts, one per line (scripts/prepush.sh runs them side by side)
#   bash scripts/checks.sh --coverage <clover file>
#                                       a line of a test step: fails when the line coverage of the report is below
#                                       the committed floor

# >>> config: the project sets every value in this block
# Empty from step 2 on. At the exit of step 10 it becomes "--final", and it stays that.
DASHBOARD_MODE=""
# The committed coverage floor: one line "threshold: <whole number>". The min ratchet row coverage-floor holds the number.
COVERAGE_FLOOR_FILE="scripts/coverage-floor.txt"
# The parts of the checks step, "<part>::<task names split by |>": one pipeline step per part (04-guards.md C4). The part gate
# holds the guard of the ratchet script, the ratchets, their self-test and the dashboard and names no task. Every task of TASKS below is in
# exactly one part and every name here is a task; the script stops when that is not so.
PARTS=(
    "gate::"
    "static::Format|Refactor|Analyser|Type coverage|Lint"
    "guards::Guard tests"
    "repo::Audit|Commit messages|Secret scan"
)
# The base branch, the remote and the host's variables are in kit-config.sh, next to this script.
# <<< config

set -euo pipefail

# A placeholder left in this file, in kit-config.sh or in the config block of ci-commit-messages.sh stops every run.
# That includes --list-parts, so scripts/prepush.sh fails before it starts. Only a line that is not a comment is read: a <word> in a command that
# is not a placeholder (grep -E 'a<b>c') is written without the angle brackets, or the pattern is built from two strings.
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLACEHOLDER_FILES=("${BASH_SOURCE[0]}")
for file in kit-config.sh ci-commit-messages.sh; do
    [ ! -r "$HERE/$file" ] || PLACEHOLDER_FILES+=("$HERE/$file")
done
LEFT=$(awk '
    FNR == 1 { block = (FILENAME ~ /ci-commit-messages\.sh$/); inside = !block }
    block && /^# >>> config/ { inside = 1; next }
    block && /^# <<< config/ { inside = 0 }
    inside && !/^[[:space:]]*#/ && /<[a-z][a-z0-9 ,.:()\/-]*>/ { parts = split(FILENAME, part, "/"); print part[parts] ":" FNR ":" $0 }
' "${PLACEHOLDER_FILES[@]}")
if [ -n "$LEFT" ]; then
    printf '%s\n' "$LEFT"
    echo "A check script still holds placeholders: replace each <...> with the project's value or command (checks.sh, kit-config.sh, the config block of ci-commit-messages.sh)." >&2
    exit 1
fi

SHARED_CONFIG="$HERE/kit-config.sh"
[ -r "$SHARED_CONFIG" ] || {
    echo "Checks: cannot read $SHARED_CONFIG: kit-config.sh belongs next to this script" >&2
    exit 64
}
. "$SHARED_CONFIG"

usage() {
    echo "Usage: bash scripts/checks.sh [--main | --scheduled | --coverage CLOVER_FILE] [--part NAME]   or   --list-parts" >&2
    echo "  --part goes with a pull-request run or with --main only; --list-parts, --scheduled and --coverage stand alone" >&2
    exit 64
}
part_names() {
    local part
    for part in "${PARTS[@]}"; do
        printf '%s ' "${part%%::*}"
    done
}

RUN=""
PART=""
PART_GIVEN=0
LIST=0
REPORT=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        --list-parts) LIST=1 ;;
        --main | --scheduled)
            [ -z "$RUN" ] || usage
            RUN="$1"
            ;;
        --coverage)
            [ -z "$RUN" ] || usage
            RUN="$1"
            REPORT="${2:-}"
            [ "$#" -lt 2 ] || shift
            ;;
        --part)
            [ "$PART_GIVEN" -eq 0 ] || usage
            PART_GIVEN=1
            PART="${2:-}"
            [ "$#" -lt 2 ] || shift
            ;;
        *) usage ;;
    esac
    shift
done

if [ "$LIST" -eq 1 ]; then
    [ -z "$RUN" ] && [ "$PART_GIVEN" -eq 0 ] || usage
    for part in "${PARTS[@]}"; do
        echo "${part%%::*}"
    done
    exit 0
fi
if [ "$PART_GIVEN" -eq 1 ]; then
    [ -z "$RUN" ] || [ "$RUN" = "--main" ] || usage
    [ -n "$PART" ] || {
        echo "Checks: --part needs a name: $(part_names)" >&2
        exit 64
    }
    KNOWN=0
    for part in "${PARTS[@]}"; do
        [ "${part%%::*}" != "$PART" ] || KNOWN=1
    done
    [ "$KNOWN" -eq 1 ] || {
        echo "Checks: no part \"$PART\". The parts are: $(part_names)" >&2
        exit 64
    }
fi

if [ "$RUN" = "--coverage" ]; then
    [ -r "$REPORT" ] || {
        echo "Coverage: cannot read the clover report \"$REPORT\". FAILS: the test command writes it, and this line names it" >&2
        exit 1
    }
    FLOOR=""
    [ ! -r "$COVERAGE_FLOOR_FILE" ] || FLOOR=$(sed -n -E 's/^threshold: ([0-9]+)$/\1/p' "$COVERAGE_FLOOR_FILE" | awk 'NR == 1')
    [ -n "$FLOOR" ] || {
        echo "Coverage: $COVERAGE_FLOOR_FILE holds no line \"threshold: N\" with N a whole number. FAILS" >&2
        exit 1
    }
    METRICS=$({ grep -o -E '<metrics [^>]*>' "$REPORT" || [ $? -eq 1 ]; } | awk 'END { print }')
    TOTAL=$(sed -n -E 's/.*[[:space:]]statements="([0-9]+)".*/\1/p' <<< "$METRICS")
    COVERED=$(sed -n -E 's/.*[[:space:]]coveredstatements="([0-9]+)".*/\1/p' <<< "$METRICS")
    [ -n "$TOTAL" ] && [ -n "$COVERED" ] && [ "$TOTAL" -gt 0 ] || {
        echo "Coverage: $REPORT holds no project metrics with statements. FAILS: nothing was measured" >&2
        exit 1
    }
    PERCENT=$((COVERED * 100 / TOTAL))
    if [ "$PERCENT" -lt "$FLOOR" ]; then
        echo "Coverage: $PERCENT% of the lines ($COVERED of $TOTAL), below the floor of $FLOOR% in $COVERAGE_FLOOR_FILE. FAILS: add the missing tests; the floor never goes down" >&2
        exit 1
    fi
    echo "Coverage: $PERCENT% of the lines ($COVERED of $TOTAL), floor $FLOOR%."
    [ "$PERCENT" -eq "$FLOOR" ] || echo "Coverage: at the end of the step, raise the floor in $COVERAGE_FLOOR_FILE to $PERCENT."
    exit 0
fi

if [ "$RUN" = "--scheduled" ]; then
    bash scripts/debt-dashboard.sh $DASHBOARD_MODE --details
    exit $?
fi

if [ -z "$RUN" ]; then
    BASE="${CHECK_BASE:-}"
    for variable in $BASE_BRANCH_VARS; do
        [ -n "$BASE" ] || BASE=$(eval "printf '%s' \"\${$variable:-}\"")
    done
    if [ -z "$BASE" ]; then
        if [ -n "${CI:-}" ]; then
            echo "Checks: no base branch. This is a CI run and none of CHECK_BASE, ${BASE_BRANCH_VARS// /, } is set. A pull request sets one; a push to the main branch runs: bash scripts/checks.sh --main" >&2
            exit 1
        fi
        BASE="$DEFAULT_BASE_BRANCH"
    fi
    export CHECK_BASE="$BASE"

    # Only the parts that read the base fetch it: the gate (guard and ratchets) and a part that holds the commit-message check or
    # the secret scan. A transient fetch failure then cannot fail static or guards, which never look at the base.
    NEEDS_BASE=1
    if [ -n "$PART" ] && [ "$PART" != "gate" ]; then
        NEEDS_BASE=0
        for part in "${PARTS[@]}"; do
            [ "${part%%::*}" = "$PART" ] || continue
            case "|${part#*::}|" in
                *"|Commit messages|"* | *"|Secret scan|"*) NEEDS_BASE=1 ;;
            esac
        done
    fi
    if [ "$NEEDS_BASE" -eq 1 ] && ! git fetch -q "$REMOTE" "+refs/heads/$BASE:refs/remotes/$REMOTE/$BASE"; then
        echo "Checks: cannot fetch $REMOTE/$BASE, so the script guard cannot run. FAILS: nothing was checked" >&2
        exit 1
    fi
    if [ -n "$PART" ] && [ "$PART" != "gate" ]; then
        :
    elif git cat-file -e "$REMOTE/$BASE:scripts/ci-ratchets.sh" 2> /dev/null; then
        GUARD=$(mktemp -d)
        git show "$REMOTE/$BASE:scripts/ci-ratchets.sh" > "$GUARD/ci-ratchets.sh"
        git show "$REMOTE/$BASE:scripts/kit-config.sh" > "$GUARD/kit-config.sh"
        GUARDED=0
        bash "$GUARD/ci-ratchets.sh" --guard scripts/ci-ratchets.sh || GUARDED=$?
        rm -rf "$GUARD"
        [ "$GUARDED" -eq 0 ] || exit "$GUARDED"
    else
        echo "Checks: scripts/ci-ratchets.sh is not on $REMOTE/$BASE yet, so there is no copy of the base to guard with. Only the pull request that installs the tools may print this line."
    fi

    if [ -z "$PART" ] || [ "$PART" = "gate" ]; then
        bash scripts/ci-ratchets.sh
    fi
fi

if [ -z "$PART" ] || [ "$PART" = "gate" ]; then
    # The expressions of the ratchet rows against their fixture: a row whose regex stopped matching fails here, not silently.
    # It reads no base, so it runs on a push to the main branch too.
    bash scripts/ci-ratchets.sh --self-test
    bash scripts/debt-dashboard.sh $DASHBOARD_MODE --offline --details
fi
if [ "$PART" = "gate" ]; then
    exit 0
fi

TASKS=(
    "Format::<formatter in check mode over the whole tree>"
    "Refactor::<refactoring tool in dry-run mode over the whole tree>"
    "Analyser::<static analyser and type checker over the whole tree>"
    "Type coverage::<type coverage of the source, minimum 100>"
    "Lint::<linter over the whole tree, zero warnings allowed>"
    "Guard tests::<test runner command that runs the guard group and nothing else>"
)
if [ "$RUN" = "--main" ]; then
    # A deploying branch audits production dependencies only: an advisory in a development tool with no fix must not stop a hotfix.
    TASKS+=(
        "Audit::<the audit of each package manager as json, production dependencies only (npm: --omit=dev), piped into: bash scripts/audit-gate.sh>"
    )
else
    TASKS+=(
        "Audit::<the audit of each package manager as json, piped into: bash scripts/audit-gate.sh>"
    )
fi
if [ -z "$RUN" ]; then
    TASKS+=(
        "Commit messages::bash scripts/ci-commit-messages.sh"
        "Secret scan::gitleaks git --redact --no-banner --log-opts=$REMOTE/$BASE..${CHECK_HEAD:-HEAD}"
    )
fi

# Every name in PARTS is a task of this file, so a typo in a part cannot leave a task out of it unseen.
TASK_NAMES="|"
for task in "${TASKS[@]}" "Commit messages::" "Secret scan::"; do
    TASK_NAMES="$TASK_NAMES${task%%::*}|"
done
for part in "${PARTS[@]}"; do
    [ -n "${part#*::}" ] || continue
    IFS='|' read -r -a names <<< "${part#*::}"
    for name in "${names[@]}"; do
        case "$TASK_NAMES" in
            *"|$name|"*) ;;
            *)
                echo "Checks: the part \"${part%%::*}\" of PARTS names \"$name\", which is no task of this file. FAILS" >&2
                exit 1
                ;;
        esac
    done
done

# Every task is in exactly one part, so no check can fall out of a split pipeline.
for task in "${TASKS[@]}" "Commit messages::" "Secret scan::"; do
    name="${task%%::*}"
    IN=0
    for part in "${PARTS[@]}"; do
        case "|${part#*::}|" in
            *"|$name|"*) IN=$((IN + 1)) ;;
        esac
    done
    [ "$IN" -eq 1 ] || {
        echo "Checks: the task \"$name\" is in $IN parts of PARTS; it belongs in exactly one. FAILS" >&2
        exit 1
    }
done

SELECTED=()
for task in "${TASKS[@]}"; do
    name="${task%%::*}"
    if [ -z "$PART" ]; then
        SELECTED+=("$task")
        continue
    fi
    for part in "${PARTS[@]}"; do
        if [ "${part%%::*}" = "$PART" ]; then
            case "|${part#*::}|" in
                *"|$name|"*) SELECTED+=("$task") ;;
            esac
        fi
    done
done
[ "${#SELECTED[@]}" -gt 0 ] || {
    echo "Checks: the part \"$PART\" holds no task of this run"
    exit 0
}

bash scripts/ci-parallel.sh "${SELECTED[@]}"
