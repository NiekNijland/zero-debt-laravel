#!/usr/bin/env bash

# The dependency audit with time-boxed exceptions. Copy to scripts/audit-gate.sh.
#
#   composer audit --locked --format=json --no-interaction | bash scripts/audit-gate.sh
#
# Reads the audit's JSON on standard input and the exceptions file. Prints one whole number on standard output: the advisories
# that no active exception covers, plus the problems in the exceptions file. Exits 1 when that number is above 0, so the same
# line is the audit step of the checks and the command of the dashboard row dependency-advisories.
#
# The exceptions file exists for one case: an advisory is published today and no fixed release exists yet, and the pipeline
# of every pull request would stay red meanwhile. An exception always ends: at most MAX_DAYS after it was added. It is never
# the audit's own ignore list, which stays empty (ratchet row audit-ignore).
#
# The file holds one entry per line, so the line is the unit a reviewer reads:
#   {"exceptions": [
#   {"id": "PKSA-xxxx-xxxx", "reason": "the code path is not reachable: <why>", "added": "2026-11-17", "expires": "2026-11-24"},
#   ...
#   ]}

# >>> config: the project sets every value in this block
# The exceptions file. Missing is the same as empty.
EXCEPTIONS_FILE="${AUDIT_EXCEPTIONS_FILE:-scripts/audit-exceptions.json}"
# The longest an exception may live, in days: expires minus added.
MAX_DAYS=7
# One extended regular expression with one capture group: the id of an advisory in the audit's JSON, once per advisory.
# Composer: "advisoryId". Another package manager's audit names its id under another key: change it here, and verify in step 1
# that the key holds one id per advisory.
ID_PATTERN='"advisoryId"[[:space:]]*:[[:space:]]*"([^"]+)"'
# One extended regular expression that a complete audit document contains: the key that holds the advisories (Composer prints
# it always, an empty object or list when there are none). A document without it is an error message, not an audit.
DOCUMENT_PATTERN='"advisories"[[:space:]]*:'
# <<< config

set -uo pipefail

TODAY="${AUDIT_GATE_TODAY:-}"
[ -n "$TODAY" ] || TODAY=$(date -u +%Y-%m-%d)
AUDIT=$(cat)

# Days since a fixed day for a YYYY-MM-DD date, or "bad" when it is not a real date.
days_of() {
    awk -v d="$1" 'BEGIN {
        if (d !~ /^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/) { print "bad"; exit }
        y = substr(d, 1, 4) + 0; m = substr(d, 6, 2) + 0; day = substr(d, 9, 2) + 0
        if (m < 1 || m > 12 || day < 1) { print "bad"; exit }
        dim = (m == 2) ? ((y % 4 == 0 && (y % 100 != 0 || y % 400 == 0)) ? 29 : 28) : ((m == 4 || m == 6 || m == 9 || m == 11) ? 30 : 31)
        if (day > dim) { print "bad"; exit }
        if (m <= 2) { y -= 1; m += 12 }
        print int(365.25 * y) + int(30.6001 * (m + 1)) + day
    }'
}

not_an_audit() {
    echo "Audit gate: $1, so nothing was audited. FAILS" >&2
    echo 1
    exit 1
}

# The first and the last character must be the two ends of one JSON value, the document must name the advisories, and a JSON
# parser (jq, when it is installed) must read the whole text: a truncated or foreign document is not a clean audit.
TRIMMED=$(printf '%s' "$AUDIT" | tr -d '[:space:]')
case "$TRIMMED" in
    "{"*"}") ;;
    "{"* | "["*) not_an_audit "the audit's JSON is incomplete (cut off, or not one document)" ;;
    *) not_an_audit "the audit printed no JSON on standard input" ;;
esac
printf '%s' "$AUDIT" | grep -q -E -e "$DOCUMENT_PATTERN" || not_an_audit "the audit's JSON has no key matching $DOCUMENT_PATTERN"
# The whole text must parse as one JSON object: jq, else node, else php. With none of them the audit cannot be validated.
if command -v jq > /dev/null 2>&1; then
    printf '%s' "$AUDIT" | jq -e 'type == "object"' > /dev/null 2>&1 || not_an_audit "the audit's JSON cannot be parsed, or is not one object (truncated?)"
elif command -v node > /dev/null 2>&1; then
    printf '%s' "$AUDIT" | node -e 'const v = JSON.parse(require("fs").readFileSync(0, "utf8")); process.exit(v !== null && typeof v === "object" && !Array.isArray(v) ? 0 : 1)' > /dev/null 2>&1 || not_an_audit "the audit's JSON cannot be parsed, or is not one object (truncated?)"
elif command -v php > /dev/null 2>&1; then
    printf '%s' "$AUDIT" | php -r 'exit(is_array(json_decode(stream_get_contents(STDIN), true)) ? 0 : 1);' > /dev/null 2>&1 || not_an_audit "the audit's JSON cannot be parsed, or is not one object (truncated?)"
else
    not_an_audit "none of jq, node or php is installed to check that the audit's JSON is whole"
fi

TODAY_DAYS=$(days_of "$TODAY")
if [ "$TODAY_DAYS" = "bad" ]; then
    echo "Audit gate: today is \"$TODAY\", not a date. FAILS" >&2
    exit 1
fi

PROBLEMS=0
ACTIVE=" "
SEEN=" "

problem() {
    echo "Audit gate: $1. FAILS" >&2
    PROBLEMS=$((PROBLEMS + 1))
}

field() {
    sed -n -E 's/.*"'"$1"'"[[:space:]]*:[[:space:]]*"([^"]*)".*/\1/p' <<< "$2" | awk 'NR == 1'
}

if [ -r "$EXCEPTIONS_FILE" ]; then
    while IFS= read -r line; do
        case "$line" in
            *'"id"'*) ;;
            *) continue ;;
        esac
        if [ "$(grep -o '"id"' <<< "$line" | wc -l | tr -d ' ')" -gt 1 ]; then
            problem "a line of $EXCEPTIONS_FILE holds more than one entry: one entry per line: $line"
            continue
        fi
        id=$(field id "$line")
        reason=$(field reason "$line")
        added=$(field added "$line")
        expires=$(field expires "$line")
        case "$id" in
            *[[:space:]]*)
                problem "the id \"$id\" in $EXCEPTIONS_FILE holds a space: one entry covers one advisory"
                continue
                ;;
        esac
        if [ -z "$id" ] || [ -z "${reason//[[:space:]]/}" ] || [ -z "$added" ] || [ -z "$expires" ]; then
            problem "an entry in $EXCEPTIONS_FILE lacks id, reason, added or expires: $line"
            continue
        fi
        case "$SEEN" in
            *" $id "*)
                problem "$id is listed twice in $EXCEPTIONS_FILE: an exception is never renewed, it is replaced by the fix"
                continue
                ;;
        esac
        SEEN="$SEEN$id "
        added_days=$(days_of "$added")
        expires_days=$(days_of "$expires")
        if [ "$added_days" = "bad" ] || [ "$expires_days" = "bad" ]; then
            problem "$id: added \"$added\" and expires \"$expires\" must be dates, YYYY-MM-DD"
            continue
        fi
        if [ "$added_days" -gt "$TODAY_DAYS" ]; then
            problem "$id: added $added is in the future"
            continue
        fi
        if [ $((expires_days - added_days)) -gt "$MAX_DAYS" ]; then
            problem "$id: the exception lasts $((expires_days - added_days)) days, at most $MAX_DAYS ($added to $expires)"
            continue
        fi
        if [ "$expires_days" -lt "$TODAY_DAYS" ]; then
            problem "$id: the exception expired on $expires. Fix the package or replace it; an exception is never renewed"
            continue
        fi
        ACTIVE="$ACTIVE$id "
    done < "$EXCEPTIONS_FILE"
fi

UNCOVERED=0
while IFS= read -r id; do
    [ -n "$id" ] || continue
    case "$ACTIVE" in
        *" $id "*) echo "Audit gate: $id is covered by an exception until its end date" >&2 ;;
        *)
            echo "Audit gate: $id has no active exception. Update or replace the package; with no fixed release yet, add an exception line (at most $MAX_DAYS days)" >&2
            UNCOVERED=$((UNCOVERED + 1))
            ;;
    esac
done < <(printf '%s' "$AUDIT" | { grep -o -E "$ID_PATTERN" || [ $? -eq 1 ]; } | sed -E "s/$ID_PATTERN/\1/" | sort -u)

TOTAL=$((UNCOVERED + PROBLEMS))
echo "$TOTAL"
[ "$TOTAL" -eq 0 ]
