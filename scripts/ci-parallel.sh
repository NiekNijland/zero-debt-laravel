#!/usr/bin/env bash

# >>> config: the project sets every value in this block
# Seconds one command may run. The environment variable CI_CHECK_TIMEOUT overrides it for one run.
DEFAULT_TIMEOUT=600
# Seconds between "stop" and "kill" for a command that ran out of time.
GRACE=10
# <<< config

set -uo pipefail

if [ "$#" -eq 0 ]; then
    echo "Usage: bash ci-parallel.sh \"Name::command\" [\"Name::command\" ...]" >&2
    exit 64
fi

CHECK_TIMEOUT="${CI_CHECK_TIMEOUT:-$DEFAULT_TIMEOUT}"
case "$CHECK_TIMEOUT$GRACE" in
    "" | *[!0-9]*)
        echo "ci-parallel: the time limit ($CHECK_TIMEOUT, from CI_CHECK_TIMEOUT or DEFAULT_TIMEOUT) and GRACE ($GRACE) are whole numbers of seconds" >&2
        exit 64
        ;;
esac
# A task is "Name::command" with a command that is not blank: a command that expanded to nothing (an unset variable in the
# caller) must not be a green check.
for task in "$@"; do
    case "$task" in
        *"::"*) ;;
        *)
            echo "ci-parallel: \"$task\" is not Name::command" >&2
            exit 64
            ;;
    esac
    command_text="${task#*::}"
    if [ -z "${command_text//[[:space:]]/}" ] || [ -z "${task%%::*}" ]; then
        echo "ci-parallel: \"$task\" has no name or no command" >&2
        exit 64
    fi
done
LOG_DIR="$(mktemp -d)"
[ -d "$LOG_DIR" ] || exit 1
NAMES=()
RUNNERS=()

stop_all() {
    local runner file
    for file in "$LOG_DIR"/*.pid "$LOG_DIR"/*.wd; do
        [ -f "$file" ] && kill -TERM "-$(cat "$file")" 2> /dev/null
    done
    for runner in ${RUNNERS[@]+"${RUNNERS[@]}"}; do
        kill -TERM "-$runner" 2> /dev/null
    done
    rm -rf "$LOG_DIR"
    exit 130
}
trap stop_all INT TERM

run() {
    local index="$1" command="$2" start status seconds pid watchdog
    start=$(date +%s)
    set -m
    bash -c "$command" > "$LOG_DIR/$index.log" 2>&1 < /dev/null &
    pid=$!
    echo "$pid" > "$LOG_DIR/$index.pid"
    (
        set +m
        sleep "$CHECK_TIMEOUT" &
        wait $!
        touch "$LOG_DIR/$index.timeout"
        kill -TERM "-$pid" 2> /dev/null
        sleep "$GRACE" &
        wait $!
        kill -KILL "-$pid" 2> /dev/null
    ) > /dev/null 2>&1 < /dev/null &
    watchdog=$!
    echo "$watchdog" > "$LOG_DIR/$index.wd"
    wait "$pid" 2> /dev/null
    status=$?
    kill -KILL "-$watchdog" 2> /dev/null
    wait "$watchdog" 2> /dev/null
    seconds=$(($(date +%s) - start))
    if [ -f "$LOG_DIR/$index.timeout" ]; then
        status=124
    fi
    echo "$status $seconds" > "$LOG_DIR/$index.tmp"
    mv "$LOG_DIR/$index.tmp" "$LOG_DIR/$index.status"
}

INDEX=0
for task in "$@"; do
    NAMES+=("${task%%::*}")
    set -m
    run "$INDEX" "${task#*::}" &
    RUNNERS+=("$!")
    set +m
    INDEX=$((INDEX + 1))
done

FAILED=0
PENDING=${#NAMES[@]}

while [ "$PENDING" -gt 0 ]; do
    INDEX=0
    for name in "${NAMES[@]}"; do
        if [ -f "$LOG_DIR/$INDEX.status" ] && [ ! -f "$LOG_DIR/$INDEX.printed" ]; then
            touch "$LOG_DIR/$INDEX.printed"
            PENDING=$((PENDING - 1))
            read -r status seconds < "$LOG_DIR/$INDEX.status"
            if [ "$status" -eq 124 ]; then
                echo "=== $name timed out after ${CHECK_TIMEOUT}s, output so far:"
            elif [ "$status" -eq 137 ]; then
                echo "=== $name killed after ${seconds}s (exit 137, likely out of memory), output so far:"
            else
                echo "=== $name (${seconds}s, exit $status)"
            fi
            cat "$LOG_DIR/$INDEX.log"
            [ "$status" -eq 0 ] || FAILED=1
        fi
        INDEX=$((INDEX + 1))
    done
    [ "$PENDING" -eq 0 ] || sleep 1
done
wait

echo
INDEX=0
for name in "${NAMES[@]}"; do
    read -r status seconds < "$LOG_DIR/$INDEX.status"
    if [ "$status" -eq 0 ]; then
        echo "PASS     ${seconds}s  $name"
    elif [ "$status" -eq 124 ]; then
        echo "TIMEOUT  ${seconds}s  $name"
    else
        echo "FAIL     ${seconds}s  $name"
    fi
    INDEX=$((INDEX + 1))
done

rm -rf "$LOG_DIR"
exit "$FAILED"
