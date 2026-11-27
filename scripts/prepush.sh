#!/usr/bin/env bash

# Before every push: the parts of the checks step, side by side, in this one place, so CI only confirms what already ran here.
# Copy to scripts/prepush.sh. Run it where the pipeline's tools are installed (the pipeline's own image: baselines and
# analyser results depend on the extensions of the machine). It writes no file and commits nothing.
#   bash scripts/prepush.sh [--main]
# The base is fetched once, here, before the parts start: four parts that each move origin/<base> at the same moment fail
# with "cannot lock ref" for no reason, and that is the normal state of a clone after anyone merged.

set -euo pipefail

case "${1:-}" in
    "" | --main) ;;
    *)
        echo "Usage: bash scripts/prepush.sh [--main]" >&2
        exit 64
        ;;
esac
MAIN="${1:-}"

if [ -z "$MAIN" ]; then
    . "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/kit-config.sh"
    BASE="${CHECK_BASE:-$DEFAULT_BASE_BRANCH}"
    BASE="${BASE#"$REMOTE"/}"
    kit_local_base "$BASE" || {
        echo "Prepush: $KIT_BRANCH_ERROR. FAILS: nothing was checked" >&2
        exit 1
    }
    [ "$KIT_LOCAL_BASE" -eq 1 ] || git fetch -q "$REMOTE" "+refs/heads/$BASE:refs/remotes/$REMOTE/$BASE" || {
        echo "Prepush: cannot fetch $REMOTE/$BASE. FAILS: nothing was checked" >&2
        exit 1
    }
    export CHECK_BASE="$BASE"
fi

ARGS=()
for part in $(bash scripts/checks.sh --list-parts); do
    ARGS+=("$part::bash scripts/checks.sh $MAIN --part $part")
done

bash scripts/ci-parallel.sh "${ARGS[@]}"
