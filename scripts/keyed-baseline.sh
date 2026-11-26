#!/usr/bin/env bash

# >>> config: one group of values per tool, chosen on the command line with --format <name>; FORMATS lists the names
FORMATS="tsc knip"
# FINDING_<name>: an extended regular expression: the lines of the command's output that are findings.
# NORMALIZE_<name>: a sed -E expression that removes what moves when code above a finding changes: line and column numbers.
# SECTION_<name>: empty, or an extended regular expression for a heading line. A finding is then written as
# "<heading without its count>: <finding>", so the same line under two headings is two findings.
# tsc: the TypeScript compiler. A finding has a position (plain "file(1,2): error TS1234" and pretty "file:1:2 - error TS1234"); a
# start-up or config error ("error TS5058: ...") has none and is no finding: the command then fails without a finding.
FINDING_tsc='\([0-9]+,[0-9]+\): error TS[0-9]+|:[0-9]+:[0-9]+ - error TS[0-9]+'
NORMALIZE_tsc='s/\(([0-9]+),([0-9]+)\)//; s/:[0-9]+:[0-9]+ - error/: error/'
SECTION_tsc=''
# SKIP_<name>: empty, or an extended regular expression for a heading whose lines are not findings (the tool's own hints).
# knip: the unused-code tool, in its default reporter: a heading per kind ("Unused exports (2)"), then one line per finding, its
# columns padded with spaces (a padded last column leaves a trailing space after "file:1:2", so the trailing space goes first).
# A clean run prints nothing and exits 0; "Configuration hints" follows the findings and is skipped. Read from knip 6.48.
FINDING_knip='^[^[:space:]]'
NORMALIZE_knip='s/[[:space:]]+$//; s/:[0-9]+:[0-9]+$//; s/[[:space:]]+/ /g'
SECTION_knip='^[A-Z][A-Za-z ]+ \([0-9]+\)$'
SKIP_knip='^Configuration hints'
# <<< config

set -uo pipefail

usage() {
    echo "Usage: bash keyed-baseline.sh <baseline file> [--write] --format <name> -- <command that prints one finding per line>" >&2
    echo "  Compares the findings of the command with the baseline file: one line per finding, no line numbers, sorted." >&2
    echo "  Fails on a finding that is not in the file and on a line of the file that is no longer found." >&2
    echo "  --format <name>  the tool whose output the command prints; one of: $FORMATS" >&2
    echo "  --write          rewrites the file from the findings and drops the fixed lines; it refuses a finding the file lacks" >&2
    echo "  --init           the first write of a file (or a deliberate rewrite): --write without that refusal; the ratchet row type-baseline stops growth later" >&2
    exit 64
}

[ "$#" -ge 5 ] || usage
BASELINE="$1"
shift
WRITE=0
INIT=0
FORMAT=""
while [ "$#" -gt 0 ] && [ "$1" != "--" ]; do
    case "$1" in
        --write) WRITE=1 ;;
        --init) WRITE=1 INIT=1 ;;
        --format)
            shift
            FORMAT="${1:-}"
            ;;
        *) usage ;;
    esac
    shift
done
[ "${1:-}" = "--" ] || usage
shift
[ "$#" -ge 1 ] || usage

case " $FORMATS " in
    *" $FORMAT "*) [ -n "$FORMAT" ] && [[ "$FORMAT" =~ ^[A-Za-z0-9_]+$ ]] || usage ;;
    *)
        echo "Keyed baseline: no format \"$FORMAT\". The config block has: $FORMATS. A tool with another output gets its own FINDING_, NORMALIZE_ and SECTION_ values there, and its name in FORMATS." >&2
        exit 64
        ;;
esac
eval "FINDING=\${FINDING_$FORMAT-} NORMALIZE=\${NORMALIZE_$FORMAT-} SECTION=\${SECTION_$FORMAT-} SKIP=\${SKIP_$FORMAT-}"
[ -n "$FINDING" ] || {
    echo "Keyed baseline: the format $FORMAT has no FINDING_$FORMAT in the config block." >&2
    exit 64
}

export LC_ALL=C
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

STATUS=0
"$@" > "$WORK/output" 2>&1 || STATUS=$?
# A tool that colours its output (tsc --pretty colours even into a pipe) must not hide a finding behind escape codes.
ESC=$(printf '\033')
sed -E "s/${ESC}\[[0-9;]*[A-Za-z]//g" "$WORK/output" > "$WORK/plain"
FINDING="$FINDING" SECTION="$SECTION" SKIP="$SKIP" awk '
    ENVIRON["SECTION"] != "" && $0 ~ ENVIRON["SECTION"] { heading = $0; sub(/[[:space:]]*\([0-9]+\)$/, "", heading); skipping = (ENVIRON["SKIP"] != "" && heading ~ ENVIRON["SKIP"]); next }
    skipping { next }
    ENVIRON["SECTION"] != "" && heading == "" { next }
    $0 ~ ENVIRON["FINDING"] { print (heading == "" ? "" : heading ": ") $0 }' "$WORK/plain" | sed -E "${NORMALIZE:-s/^//}" | sort > "$WORK/now"

if [ "$STATUS" -ne 0 ] && [ ! -s "$WORK/now" ]; then
    echo "Keyed baseline: the command failed (exit $STATUS) without a finding in the format $FORMAT, so nothing can be compared. Its output:" >&2
    cat "$WORK/output" >&2
    exit 1
fi

if [ "$WRITE" -eq 1 ]; then
    # --write only drops fixed lines. A finding that is not in the file is growth: it needs --init, which is for the first write
    # of a file (step 2), so a command that prints something new cannot be written into the baseline by accident.
    # A missing or empty file is the empty set: with --write alone, every finding is growth (a baseline deleted "at zero" is not
    # written back by accident).
    if [ "$INIT" -eq 0 ]; then
        if [ -f "$BASELINE" ]; then sort "$BASELINE" > "$WORK/then"; else : > "$WORK/then"; fi
        GROWTH=$(comm -23 "$WORK/now" "$WORK/then")
        if [ -n "$GROWTH" ]; then
            echo "Keyed baseline: --write would add findings that are not in $BASELINE. FAILS: fix them; a line is never added to the baseline (--init writes the first file)"
            printf '%s\n' "$GROWTH" | sed 's/^/  /'
            exit 1
        fi
    fi
    mkdir -p "$(dirname "$BASELINE")"
    cat "$WORK/now" > "$BASELINE"
    echo "Keyed baseline: $BASELINE rewritten, $(wc -l < "$WORK/now" | tr -d ' ') lines."
    exit 0
fi

if [ -f "$BASELINE" ]; then
    sort "$BASELINE" > "$WORK/then"
else
    : > "$WORK/then"
fi

NEW=$(comm -23 "$WORK/now" "$WORK/then")
STALE=$(comm -13 "$WORK/now" "$WORK/then")
FAILED=0
if [ -n "$NEW" ]; then
    echo "Keyed baseline: findings that are not in $BASELINE. FAILS: fix them; a line is never added to the baseline"
    printf '%s\n' "$NEW" | sed 's/^/  /'
    FAILED=1
fi
if [ -n "$STALE" ]; then
    echo "Keyed baseline: lines of $BASELINE that are no longer found. FAILS: they are fixed, so drop them by running this command with --write"
    printf '%s\n' "$STALE" | sed 's/^/  /'
    FAILED=1
fi
if [ "$FAILED" -eq 0 ]; then
    echo "Keyed baseline: $(wc -l < "$WORK/now" | tr -d ' ') findings, the same as $BASELINE."
fi
exit "$FAILED"
