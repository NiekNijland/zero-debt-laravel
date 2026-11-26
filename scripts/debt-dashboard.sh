#!/usr/bin/env bash

# >>> config: the project sets every value in this block; the example values are a PHP backend with a TypeScript frontend
# Empty means: ci-ratchets.sh next to this script.
RATCHET_SCRIPT=""
# The caps file is CAPS_FILE in kit-config.sh, next to this script.
# The kit's limit for a file, in lines.
MAX_LINES=400
# Extended regular expressions over tracked paths. Empty means "no such files": the row counts 0.
SOURCE_FILES='^(app|bootstrap|config)/.*\.php$'
FRONTEND_FILES='^resources/js/.*\.(ts|tsx)$'
TEST_FILES='^tests/.*\.php$|\.test\.tsx?$'
SIZE_EXEMPT=''
# A line that declares a concrete class that is open for extension (indented, or after an attribute on the same line, too). An
# abstract class is not counted: it is a parent by design. A concrete class that another class extends is counted like any other
# open class: it becomes abstract, or its children are folded into it. Empty OPEN_CLASS: the row counts 0. Leave it empty only
# when the language closes classes by default, or cannot close a class at all (TypeScript, JavaScript: a lint rule holds the
# pattern there).
OPEN_CLASS='^[[:space:]]*(#\[[^]]*\][[:space:]]*)*(readonly[[:space:]]+)?class[[:space:]]'
# The next expressions name the kit's folders. Until step 5 has moved the code, each also holds, as an alternative, every folder of
# today that will be sorted into one of them, so a move changes no row. The alternatives go in the pull request that empties the
# layout list.
BEHAVIOR_CLASSES='^app/(.*/)?(Actions|Services|Jobs|Listeners|Commands)/(.*/)?[^/]+\.php$'
SUPPORT_CLASSES='^app/(.*/)?(Policies|Middleware|Rules|Mail|Notifications|Casts)/(.*/)?[^/]+\.php$'
NOT_A_CLASS='^[[:space:]]*(#\[[^]]*\][[:space:]]*)*(abstract[[:space:]]+(readonly[[:space:]]+)?class|readonly[[:space:]]+abstract[[:space:]]+class|interface|trait)[[:space:]]'
# What a test file adds to the name of the file it covers: OrderService.php and OrderServiceTest.php give "Test";
# order-service.ts and order-service.test.ts give ".test".
TEST_NAME_SUFFIX="Test"
FRONTEND_LOGIC='^resources/js/(.*/)?((hooks|utils|lib)/[^/]+|use-[^/]+)\.tsx?$'
FRONTEND_COMPONENTS='^resources/js/.*\.tsx$'
FRONTEND_NOT_COUNTED='\.(test|stories)\.tsx?$|\.d\.ts$|/(mocks|__mocks__|stories|types)/'
FRONTEND_TEST_INFIX="test"
# Git pathspecs, split on spaces. Decision records and the archive are history: exempt by category.
DOCS_PATHS="docs :(exclude)docs/decisions :(exclude)docs/archive"
DOCS_MAX_AGE_DAYS=180
# Ratchet rows with no zero target (--final does not fail them). The first three are fixed. Add a row's name, or cap-<key> for a cap,
# only with a decision row in the plan: the evidence why zero is impossible, or the owner's pin of a major version.
SHRINK_ONLY="comments test-doubles event-fake-all"
# What the min row analyser-switches must reach: the number of switches the catalog names, added up over the configs that hold
# them (the example: 7 in phpstan.neon and 11 in tsconfig.json). --final fails the row below this number, and without a number.
ANALYSER_SWITCHES_TARGET=18
# What the other min rows must reach, as "<row>:<number>" split on spaces. --final fails a row below its number, and a name here
# that has no ratchet row. The numbers are the kit's: coverage of the backend and of the frontend logic at 95, every set of
# REFACTOR_SETS (19), every rule of FORMATTER_RULES (21), the advisory-blocking package (1). A project without the subject of a
# row removes that entry with the script tag (no frontend: frontend-logic-coverage-floor; no PHP: the last three).
MIN_ROW_TARGETS="coverage-floor:95 frontend-logic-coverage-floor:95 refactor-sets:19 formatter-rules:21 advisory-block:1"
# More rows: "<caps key>::<always|online|shown>::<command that prints one whole number>". online rows are skipped by --offline.
# A shown row is skipped by --offline too, has no cap and never fails: only dependencies-behind is one.
# json '<operation> <path>' reads JSON on standard input (tools/README.md, the methods table, "json").
# A command inside double quotes is expanded when the array is defined: write a command substitution as \$( ... ) there, or put
# the whole entry in single quotes (tools/README.md, "Adding a row", point 9, has the two-package-manager forms).
COMMAND_ROWS=(
    "dependency-advisories::online::composer audit --locked --format=json --no-interaction | bash scripts/audit-gate.sh"
    "dependencies-abandoned::online::composer audit --locked --format=json --no-interaction | json 'count abandoned'"
    "dependencies-behind::shown::composer outdated --locked --direct --format=json --no-interaction | json 'count locked'"
    "dependencies-majors-behind::online::composer outdated --locked --direct --major-only --format=json --no-interaction | json 'count locked'"
)
# Two package managers (a PHP backend with a TypeScript frontend): add both counts in one command; see tools/README.md, "Adding a row", point 9.
# One line of "do this" for every COMMAND_ROWS key the project adds: "<caps key>::<what to do when the row is open>".
# A key of the project's own without a line here prints UNKNOWN.
ROW_ADVICE=()
# <<< config

SHARED_CONFIG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/kit-config.sh"
[ -r "$SHARED_CONFIG" ] || {
    echo "Dashboard: cannot read $SHARED_CONFIG: kit-config.sh belongs next to this script" >&2
    exit 64
}
. "$SHARED_CONFIG"

set -uo pipefail

usage() {
    echo "Usage: bash debt-dashboard.sh [--final] [--offline] [--details] [--report]"
    echo "  (default)  the gate: fails when a row is above its cap or cannot be measured; a ratchet row is held by the ratchet"
    echo "  --final    fails every row above zero, except the shrink-only rows, every cap that is above its row,"
    echo "             analyser-switches below ANALYSER_SWITCHES_TARGET, and every min row below its number in MIN_ROW_TARGETS"
    echo "  --offline  skips the rows that need a package registry, full git history or today's date"
    echo "  --details  lists the files behind every row that is not at zero"
    echo "  --report   always exits 0"
}

FINAL=0
OFFLINE=0
DETAILS=0
REPORT=0
for argument in "$@"; do
    case "$argument" in
        --final) FINAL=1 ;;
        --offline) OFFLINE=1 ;;
        --details) DETAILS=1 ;;
        --report) REPORT=1 ;;
        -h | --help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            exit 64
            ;;
    esac
done

# A regular expression that does not compile makes grep fail and print nothing: its row would read 0 and stay green. Every
# expression of the config block is compiled once here, and an invalid one stops the run.
for expression_name in SOURCE_FILES FRONTEND_FILES TEST_FILES SIZE_EXEMPT OPEN_CLASS BEHAVIOR_CLASSES SUPPORT_CLASSES NOT_A_CLASS FRONTEND_LOGIC FRONTEND_COMPONENTS FRONTEND_NOT_COUNTED; do
    expression_value="${!expression_name}"
    [ -n "$expression_value" ] || continue
    printf '' | grep -E -e "$expression_value" > /dev/null 2>&1
    if [ "$?" -gt 1 ]; then
        echo "Dashboard: $expression_name in the config block is not a valid extended regular expression: $expression_value" >&2
        exit 64
    fi
done

[ -n "$RATCHET_SCRIPT" ] || RATCHET_SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/ci-ratchets.sh"
cd "$(git rev-parse --show-toplevel)" || exit 64
case "$RATCHET_SCRIPT" in
    /*) ;;
    *) RATCHET_SCRIPT="$PWD/$RATCHET_SCRIPT" ;;
esac
export RATCHET_SCRIPT

TOTAL=0
OPEN=0
UNKNOWN=0
SKIPPED=0
MEASURED=" "
DETAIL_FILE=$(mktemp)
trap 'rm -f "$DETAIL_FILE"' EXIT

json() {
    bash "$RATCHET_SCRIPT" --json "$1"
}
export -f json

count_lines() {
    if [ -n "$1" ]; then
        printf '%s\n' "$1" | grep -c .
    else
        echo 0
    fi
}

tracked() {
    [ -n "$1" ] || return 0
    git -c core.quotePath=false ls-files | grep -E -e "$1"
    return 0
}

except() {
    if [ -n "$1" ]; then
        grep -v -E -e "$1"
    else
        cat
    fi
    return 0
}

print_row() {
    printf '%-8s %-38s %7s %7s  %s\n' "$1" "$2" "$3" "$4" "$5"
}

listed() {
    case " $2 " in
        *" $1 "*) return 0 ;;
    esac
    return 1
}

project_advice() {
    local entry
    for entry in ${ROW_ADVICE[@]+"${ROW_ADVICE[@]}"}; do
        if [ "${entry%%::*}" = "$1" ] && [ -n "${entry#*::}" ]; then
            printf '%s' "${entry#*::}"
            return 0
        fi
    done
    return 1
}

advice() {
    case "$1" in
        source-files-over-limit | frontend-files-over-limit | test-files-over-limit) echo "split each file by responsibility until it has at most $MAX_LINES lines; a trait that is used once is not a split" ;;
        open-classes) echo "close each concrete class for extension after the pre-scan of the kit's 05-program.md D8; a concrete class that another class extends becomes abstract or its children are folded in; a class that must stay open gets a decision row in the plan" ;;
        coverage-floor | frontend-logic-coverage-floor) echo "add the missing tests and raise the committed floor to the measured value, until it is at the kit's end goal" ;;
        refactor-sets) echo "switch on, in REFACTOR_CONFIG, the sets of REFACTOR_SETS that are missing (the kit's templates/tool-configs.md)" ;;
        formatter-rules) echo "switch on, in FORMATTER_CONFIG, the rules of FORMATTER_RULES that are missing; a rule that can change behavior lands in a pull request of its own (the kit's templates/tool-configs.md)" ;;
        advisory-block) echo "add roave/security-advisories at dev-latest to the development requirements of the manifest" ;;
        classes-no-test-names) echo "write a test for each class that imports it or names it before ::" ;;
        classes-without-own-test | support-classes-without-own-test) echo "add the test file <Class>$TEST_NAME_SUFFIX for each class" ;;
        frontend-logic-without-test | frontend-components-without-test) echo "add <file>.$FRONTEND_TEST_INFIX.<extension> next to each file" ;;
        docs-older-than-limit) echo "update each doc, move it to the archive folder or delete it" ;;
        dependency-advisories) echo "update or replace each package that has an advisory; never add it to the audit's ignore list. With no fixed release yet: a decision row in the plan (the kit's 04-guards.md C3) and a time-boxed exception line in scripts/audit-exceptions.json (at most 7 days, never renewed)" ;;
        dependencies-abandoned) echo "replace each abandoned package; with no replacement yet: a decision row in the plan (the kit's 04-guards.md C3)" ;;
        dependencies-majors-behind) echo "upgrade each major in a pull request of its own; a major the owner pinned enters SHRINK_ONLY as cap-dependencies-majors-behind, by its decision row" ;;
        files-outside-row-paths) echo "add each folder to SOURCE_PATHS, TEST_PATHS or FRONTEND_PATHS in the ratchet script, or to UNCOUNTED_PATHS when it is deliberately not counted" ;;
        analyser-baseline) echo "fix the errors and delete their entries; at zero, delete the baseline file and its include" ;;
        type-baseline) echo "fix the findings and drop their lines with keyed-baseline.sh --write; at zero, delete the file and its line in checks.sh" ;;
        strict-types-missing) echo "add the strict-types line to each file, in the pull request of its own that follows the test foundation; a new file is written with the line" ;;
        guide-future-tense) echo "rewrite each sentence in the present tense: a guide describes the final state" ;;
        analyser-switches) echo "switch on, in every config of ANALYSER_CONFIGS and TYPE_CHECKER_CONFIGS, the switches of ANALYSER_SWITCHES that are missing" ;;
        cap-*) echo "lower the cap in $CAPS_FILE to 0 with its row, or enter it in SHRINK_ONLY by a decision row in the plan" ;;
        *) project_advice "$1" || echo "bring the row to 0: its line in $(basename "$RATCHET_SCRIPT") says what it counts and where" ;;
    esac
}

do_this() {
    printf '%-8s %s\n' "" "do this: $(advice "$1")"
}

fixed_row() {
    local group="$1" name="$2" now="$3" target="$4"
    TOTAL=$((TOTAL + 1))
    if ! [[ "$now" =~ ^[0-9]+$ && "$target" =~ ^[0-9]+$ ]]; then
        UNKNOWN=$((UNKNOWN + 1))
        print_row "$group" "$name" "?" "${5:-?}" "UNKNOWN${6:+ ($6)}"
    elif [ "$now" -le "$target" ]; then
        print_row "$group" "$name" "$now" "$target" "ok${7:+ ($7)}"
    else
        OPEN=$((OPEN + 1))
        print_row "$group" "$name" "$now" "$target" "OPEN"
        do_this "$name"
    fi
}

measured_row() {
    local group="$1" name="$2" now="$3" cap target note="" hint=""
    MEASURED="$MEASURED$name "
    cap=$(cat "$CAPS_FILE" 2> /dev/null | json "value $name" 2> /dev/null)
    if ! [[ "$cap" =~ ^[0-9]+$ ]]; then
        cap="?"
        note="no key $name in $CAPS_FILE"
    elif ! listed "cap-$name" "$RATCHET_NAMES"; then
        cap="?"
        note="no ratchet row cap-$name"
    fi
    target="$cap"
    if [ "$FINAL" -eq 1 ] && [ "$cap" != "?" ] && ! listed "cap-$name" "$SHRINK_ONLY"; then
        target=0
    fi
    [[ "$now" =~ ^[0-9]+$ ]] || note="${note:-could not be measured}"
    if [[ "$now" =~ ^[0-9]+$ && "$cap" =~ ^[0-9]+$ ]] && [ "$now" -lt "$cap" ]; then
        hint="lower the cap to $now"
        if [ "$FINAL" -eq 1 ]; then
            TOTAL=$((TOTAL + 1))
            OPEN=$((OPEN + 1))
            print_row "$group" "$name" "$now" "$target" "OPEN (the cap $cap is above the row)"
            printf '%-8s %s\n' "" "do this: lower the cap of $name in $CAPS_FILE to $now"
            return 0
        fi
    fi
    fixed_row "$group" "$name" "$now" "$target" "$cap" "$note" "$hint"
}

skipped_row() {
    SKIPPED=$((SKIPPED + 1))
    MEASURED="$MEASURED$2 "
    print_row "$1" "$2" "-" "-" "skipped${3:+ ($3)}"
}

detail() {
    [ "$DETAILS" -eq 1 ] || return 0
    [ -n "$2" ] || return 0
    printf '\n%s\n%s\n' "$1" "$2" >> "$DETAIL_FILE"
}

oversized() {
    local files
    files=$(cat)
    [ -n "$files" ] || return 0
    # awk counts the last line of a file that has no final newline too, which wc -l does not.
    printf '%s\n' "$files" | tr '\n' '\0' | xargs -0 awk -v max="$MAX_LINES" '
        FNR == 1 { if (name != "" && last > max) print last "  " name; name = FILENAME }
        { last = FNR }
        END { if (name != "" && last > max) print last "  " name }' 2> /dev/null | sort -rn
}

open_classes() {
    local files
    files=$(cat)
    [ -n "$files" ] || return 0
    [ -n "$OPEN_CLASS" ] || return 0
    printf '%s\n' "$files" | tr '\n' '\0' | xargs -0 grep -l -E -e "$OPEN_CLASS" 2> /dev/null
    return 0
}

min_target() {
    local entry
    for entry in $MIN_ROW_TARGETS; do
        if [ "${entry%%:*}" = "$1" ]; then
            printf '%s' "${entry#*:}"
            return 0
        fi
    done
    return 1
}

# The kit's end goals. A project whose organisation hosts the formatter config (the owner's exception of step 1) may hold a row at
# a lower number than the kit's: its entry in MIN_ROW_TARGETS is then lower, with a decision row in the plan that names the
# rules the hosted config lacks. The dashboard prints that in every run, so a lowered goal is never silent.
kit_min_target() {
    case "$1" in
        coverage-floor | frontend-logic-coverage-floor) echo 95 ;;
        refactor-sets) echo 19 ;;
        formatter-rules) echo 21 ;;
        advisory-block) echo 1 ;;
        *) return 1 ;;
    esac
}

lowered_note() {
    local kit
    kit=$(kit_min_target "$1") || return 0
    [ "$2" -ge "$kit" ] || printf ' (goal lowered: the kit holds %s; needs a decision row in the plan)' "$kit"
}

real_classes() {
    local files
    files=$(cat)
    [ -n "$files" ] || return 0
    if [ -z "$NOT_A_CLASS" ]; then
        printf '%s\n' "$files"
        return 0
    fi
    printf '%s\n' "$files" | tr '\n' '\0' | xargs -0 grep -L -E -e "$NOT_A_CLASS" 2> /dev/null
    return 0
}

referenced_names() {
    local files
    files=$(tracked "$TEST_FILES")
    [ -n "$files" ] || return 0
    printf '%s\n' "$files" | tr '\n' '\0' | xargs -0 awk '
        /^[[:space:]]*(use|import|from|export)[[:space:]]/ || /(require|import)[[:space:]]*\(/ || /(^|[[:space:]}])from[[:space:]]+["\047]/ {
            line = $0
            gsub(/[{},;()"\047`]/, " ", line)
            words = split(line, word, /[[:space:]]+/)
            for (i = 1; i <= words; i++) {
                parts = split(word[i], part, /[\\\/]/)
                name = part[parts]
                if (name == "") continue
                print name
                dot = match(name, /\.[^.]*$/)
                if (dot > 1) {
                    print substr(name, 1, dot - 1)
                    print substr(name, dot + 1)
                }
            }
        }
        {
            line = $0
            while (match(line, /[A-Za-z_][A-Za-z0-9_]*::/)) {
                print substr(line, RSTART, RLENGTH - 2)
                line = substr(line, RSTART + RLENGTH)
            }
        }' 2> /dev/null | sort -u
}

without_reference() {
    local names="$1"
    awk 'FILENAME == ARGV[1] { known[$0] = 1; next }
        { parts = split($0, part, "/"); name = part[parts]; sub(/\.[^.]*$/, "", name) }
        !(name in known)' <(printf '%s\n' "$names") -
}

without_own_test() {
    local tests
    tests=$(tracked "$TEST_FILES" | awk -F/ '{ name = $NF; sub(/\.[^.]*$/, "", name); print name }')
    awk -v suffix="$TEST_NAME_SUFFIX" 'FILENAME == ARGV[1] { known[$0] = 1; next }
        { parts = split($0, part, "/"); name = part[parts]; sub(/\.[^.]*$/, "", name) }
        !((name suffix) in known)' <(printf '%s\n' "$tests") -
}

without_sibling_test() {
    local all
    all=$(git -c core.quotePath=false ls-files)
    awk -v infix="$FRONTEND_TEST_INFIX" 'FILENAME == ARGV[1] { known[$0] = 1; next }
        { base = $0; sub(/\.[^.\/]*$/, "", base) }
        !((base "." infix ".ts") in known) && !((base "." infix ".tsx") in known) && !((base "." infix ".js") in known) && !((base "." infix ".jsx") in known)' <(printf '%s\n' "$all") -
}

printf 'Zero-debt dashboard (%s), %s, %s\n\n' "$([ "$FINAL" -eq 1 ] && echo final || echo gate)" "$(git rev-parse --short HEAD)" "$(date -u +%Y-%m-%d)"
print_row "group" "counter" "now" "target" "status"

RATCHETS=$(bash "$RATCHET_SCRIPT" --head 2> /dev/null)
RATCHET_NAMES=$(awk '{ printf "%s ", $1 }' <<< "$RATCHETS")
if [ -n "$RATCHETS" ]; then
    while read -r name direction value; do
        [ -n "$name" ] || continue
        TOTAL=$((TOTAL + 1))
        if ! [[ "$value" =~ ^[0-9]+$ ]]; then
            UNKNOWN=$((UNKNOWN + 1))
            print_row ratchet "$name" "?" "?" "UNKNOWN (ci-ratchets.sh --head says why)"
        elif [ "$direction" = "min" ] && [ "$name" = "analyser-switches" ] && [ "$FINAL" -eq 1 ]; then
            if ! [[ "$ANALYSER_SWITCHES_TARGET" =~ ^[0-9]+$ ]]; then
                UNKNOWN=$((UNKNOWN + 1))
                print_row ratchet "$name" "$value" "?" "UNKNOWN (ANALYSER_SWITCHES_TARGET is not a number)"
            elif [ "$value" -ge "$ANALYSER_SWITCHES_TARGET" ]; then
                print_row ratchet "$name" "$value" "$ANALYSER_SWITCHES_TARGET" "ok"
            else
                OPEN=$((OPEN + 1))
                print_row ratchet "$name" "$value" "$ANALYSER_SWITCHES_TARGET" "OPEN"
                do_this "$name"
            fi
        elif [ "$direction" = "min" ] && [ "$name" = "analyser-switches" ]; then
            print_row ratchet "$name" "$value" "${ANALYSER_SWITCHES_TARGET:--}" "held (only goes up)"
        elif [ "$direction" = "min" ] && goal=$(min_target "$name") && [ "$FINAL" -eq 1 ]; then
            if ! [[ "$goal" =~ ^[0-9]+$ ]]; then
                UNKNOWN=$((UNKNOWN + 1))
                print_row ratchet "$name" "$value" "?" "UNKNOWN (the number of $name in MIN_ROW_TARGETS is not a number)"
            elif [ "$value" -ge "$goal" ]; then
                print_row ratchet "$name" "$value" "$goal" "ok$(lowered_note "$name" "$goal")"
            else
                OPEN=$((OPEN + 1))
                print_row ratchet "$name" "$value" "$goal" "OPEN"
                do_this "$name"
            fi
        elif [ "$direction" = "min" ]; then
            print_row ratchet "$name" "$value" "$(min_target "$name" || echo -)" "held (only goes up)"
        elif [ "$FINAL" -eq 0 ]; then
            print_row ratchet "$name" "$value" "-" "held"
        elif [ "$value" -eq 0 ]; then
            print_row ratchet "$name" "$value" 0 "ok"
        elif listed "$name" "$SHRINK_ONLY"; then
            print_row ratchet "$name" "$value" "-" "shrink-only"
        else
            OPEN=$((OPEN + 1))
            print_row ratchet "$name" "$value" 0 "OPEN"
            do_this "$name"
        fi
    done <<< "$RATCHETS"
else
    fixed_row ratchet "ci-ratchets.sh --head" "?" 0 "?" "it printed nothing"
fi

if [ "$FINAL" -eq 1 ]; then
    for entry in $MIN_ROW_TARGETS; do
        listed "${entry%%:*}" "$RATCHET_NAMES" || fixed_row ratchet "${entry%%:*}" "?" 0 "${entry#*:}" "no ratchet row ${entry%%:*}: add the row, or remove its entry from MIN_ROW_TARGETS when the project has no such subject"
    done
fi

if OUTSIDE=$(bash "$RATCHET_SCRIPT" --uncovered 2> /dev/null) || [ -n "$OUTSIDE" ]; then
    fixed_row ratchet "files-outside-row-paths" "$(count_lines "$OUTSIDE")" 0
    detail "Tracked source files outside every path the ratchet rows count" "$OUTSIDE"
else
    fixed_row ratchet "files-outside-row-paths" "?" 0 "?" "ci-ratchets.sh --uncovered failed"
fi

SOURCE_BIG=$(tracked "$SOURCE_FILES" | except "$TEST_FILES" | except "$SIZE_EXEMPT" | oversized)
measured_row size "source-files-over-limit" "$(count_lines "$SOURCE_BIG")"
detail "Source files over $MAX_LINES lines" "$SOURCE_BIG"

FRONTEND_BIG=$(tracked "$FRONTEND_FILES" | except "$TEST_FILES" | except "$SIZE_EXEMPT" | oversized)
measured_row size "frontend-files-over-limit" "$(count_lines "$FRONTEND_BIG")"
detail "Frontend files over $MAX_LINES lines" "$FRONTEND_BIG"

TEST_BIG=$(tracked "$TEST_FILES" | except "$SIZE_EXEMPT" | oversized)
measured_row size "test-files-over-limit" "$(count_lines "$TEST_BIG")"
detail "Test files over $MAX_LINES lines" "$TEST_BIG"

OPEN_CLASSES=$(tracked "$SOURCE_FILES" | except "$TEST_FILES" | open_classes)
measured_row classes "open-classes" "$(count_lines "$OPEN_CLASSES")"
detail "Concrete classes that are open for extension" "$OPEN_CLASSES"

BEHAVIOR=$(tracked "$BEHAVIOR_CLASSES" | except "$TEST_FILES" | real_classes)
NO_NAMES=$(printf '%s' "$BEHAVIOR" | grep . | without_reference "$(referenced_names)")
measured_row tests "classes-no-test-names" "$(count_lines "$NO_NAMES")"
detail "Behavior classes that no test file imports or names with :: ($(count_lines "$NO_NAMES") of $(count_lines "$BEHAVIOR"))" "$NO_NAMES"

NO_OWN=$(printf '%s' "$BEHAVIOR" | grep . | without_own_test)
measured_row tests "classes-without-own-test" "$(count_lines "$NO_OWN")"
detail "Behavior classes without a <Class>$TEST_NAME_SUFFIX file ($(count_lines "$NO_OWN") of $(count_lines "$BEHAVIOR"))" "$NO_OWN"

SUPPORT=$(tracked "$SUPPORT_CLASSES" | except "$TEST_FILES" | real_classes)
SUPPORT_NO_OWN=$(printf '%s' "$SUPPORT" | grep . | without_own_test)
measured_row tests "support-classes-without-own-test" "$(count_lines "$SUPPORT_NO_OWN")"
detail "Support classes without a <Class>$TEST_NAME_SUFFIX file ($(count_lines "$SUPPORT_NO_OWN") of $(count_lines "$SUPPORT"))" "$SUPPORT_NO_OWN"

LOGIC=$(tracked "$FRONTEND_LOGIC" | except "$FRONTEND_NOT_COUNTED")
LOGIC_NO_TEST=$(printf '%s' "$LOGIC" | grep . | without_sibling_test)
measured_row tests "frontend-logic-without-test" "$(count_lines "$LOGIC_NO_TEST")"
detail "Frontend logic files without a <file>.$FRONTEND_TEST_INFIX file next to them ($(count_lines "$LOGIC_NO_TEST") of $(count_lines "$LOGIC"))" "$LOGIC_NO_TEST"

COMPONENTS=$(tracked "$FRONTEND_COMPONENTS" | except "$FRONTEND_NOT_COUNTED" | except "$FRONTEND_LOGIC")
COMPONENTS_NO_TEST=$(printf '%s' "$COMPONENTS" | grep . | without_sibling_test)
measured_row tests "frontend-components-without-test" "$(count_lines "$COMPONENTS_NO_TEST")"
detail "Frontend components without a <file>.$FRONTEND_TEST_INFIX file next to them ($(count_lines "$COMPONENTS_NO_TEST") of $(count_lines "$COMPONENTS"))" "$COMPONENTS_NO_TEST"

if [ "$OFFLINE" -eq 1 ]; then
    skipped_row docs "docs-older-than-limit" "--offline"
elif [ "$(git rev-parse --is-shallow-repository)" = "true" ]; then
    # The age of a doc is its last commit: a shallow clone dates every doc at the clone's first commit. The scheduled run
    # (--final) must not pass that way; the gate of a pull request may skip the row, with the reason printed.
    if [ "$FINAL" -eq 1 ]; then
        MEASURED="$MEASURED""docs-older-than-limit "
        fixed_row docs "docs-older-than-limit" "?" 0 "?" "shallow clone: the age of a doc needs the full history, fetch it"
    else
        skipped_row docs "docs-older-than-limit" "shallow clone"
    fi
elif [ -z "$DOCS_PATHS" ]; then
    measured_row docs "docs-older-than-limit" 0
else
    CUT=$(($(date +%s) - DOCS_MAX_AGE_DAYS * 86400))
    DOCS_TRACKED=$(git -c core.quotePath=false ls-files -- $DOCS_PATHS)
    DOCS_OLD=$(git -c core.quotePath=false log --name-only --no-renames --format='@%ct' -- $DOCS_PATHS | awk -v cut="$CUT" '
        FILENAME == ARGV[1] { known[$0] = 1; next }
        /^@/ { time = substr($0, 2) + 0; next }
        $0 != "" && !($0 in seen) { seen[$0] = 1; if (time < cut && ($0 in known)) print $0 }' <(printf '%s\n' "$DOCS_TRACKED") - | sort)
    measured_row docs "docs-older-than-limit" "$(count_lines "$DOCS_OLD")"
    detail "Docs whose last change is older than $DOCS_MAX_AGE_DAYS days: update, move to the archive folder or delete" "$DOCS_OLD"
fi

for entry in ${COMMAND_ROWS[@]+"${COMMAND_ROWS[@]}"}; do
    name="${entry%%::*}"
    rest="${entry#*::}"
    when="${rest%%::*}"
    command="${rest#*::}"
    if [ "$when" != "always" ] && [ "$OFFLINE" -eq 1 ]; then
        skipped_row command "$name" "--offline"
        continue
    fi
    now=$(bash -c "$command" 2> /dev/null)
    # One whole number on one line: "1" and "2" on two lines are not 12.
    if [[ "$now" =~ ^[[:space:]]*[0-9]+[[:space:]]*$ ]]; then now="${now//[[:space:]]/}"; else now="?"; fi
    if [ "$when" = "shown" ]; then
        TOTAL=$((TOTAL + 1))
        MEASURED="$MEASURED$name "
        [[ "$now" =~ ^[0-9]+$ ]] || now="?"
        print_row command "$name" "$now" "-" "shown (never fails)"
    elif [ "$(advice "$name")" = "$(advice "")" ]; then
        TOTAL=$((TOTAL + 1))
        UNKNOWN=$((UNKNOWN + 1))
        MEASURED="$MEASURED$name "
        print_row command "$name" "?" "?" "UNKNOWN (no \"do this\" line for $name in ROW_ADVICE)"
    else
        measured_row command "$name" "$now"
    fi
    detail "$name is counted by" "$command"
done

if [ -r "$CAPS_FILE" ] && KEYS=$(json "keys ." < "$CAPS_FILE" 2> /dev/null); then
    while read -r key; do
        [ -n "$key" ] || continue
        listed "$key" "$MEASURED" || fixed_row caps "$key" "?" 0 "?" "a key in $CAPS_FILE that no dashboard row measures"
    done <<< "$KEYS"
else
    fixed_row caps "$CAPS_FILE" "?" 0 "?" "cannot be read"
fi

printf '\n%s rows: %s at target or held, %s open, %s not measured, %s skipped\n' "$((TOTAL + SKIPPED))" "$((TOTAL - OPEN - UNKNOWN))" "$OPEN" "$UNKNOWN" "$SKIPPED"
cat "$DETAIL_FILE"

[ "$REPORT" -eq 1 ] || [ "$((OPEN + UNKNOWN))" -eq 0 ]
