#!/usr/bin/env bash

# >>> config: the project sets every value in this block; the example values are a PHP backend with a TypeScript frontend
# Comma lists of tracked folders or files. A trailing ? means "may be missing". Empty means "none".
SOURCE_PATHS="app,bootstrap,config,database,routes,resources/views?"
TEST_PATHS="tests"
# The test folders whose tests are written in the backend's test framework: the row test-skip counts there.
# Empty in a project whose only language is TypeScript: frontend-test-skip counts its skips.
TEST_SKIP_PATHS="tests"
FRONTEND_PATHS="resources/js,vite.config.ts?,vitest.setup.ts?"
# Codemods live in tools/codemods and project scripts written in a source language (scripts/<name>.mjs) are named here: no row counts them.
UNCOUNTED_PATHS="public?,eslint.config.js?,rector.php?,tools/codemods?"
SOURCE_EXTENSIONS="php,js,jsx,mjs,cjs,ts,tsx,mts,cts,vue"
ANALYSER_CONFIGS="phpstan.neon"
ANALYSER_BASELINE="phpstan-baseline.neon?"
TYPE_CHECKER_CONFIGS="tsconfig.json?"
# One extended regular expression that matches a strictness switch that is on, in the analyser and type-checker configs.
# The row analyser-switches counts the matches and only goes up. No double quote in the value. The last two alternatives are
# the analyser's bleeding-edge include and the include of the date library's extension. Each alternative starts at the line start,
# or after a brace or comma, so a switch that is commented out (# or //) is not counted.
ANALYSER_SWITCHES="(^|[{,])[[:space:]]*[^A-Za-z[:space:]#/]?(checkTooWideReturnTypesInProtectedAndPublicMethods|checkMissingOverrideMethodAttribute|reportAnyTypeWideningInVarTag|checkOctaneCompatibility|noEnvCallsOutsideOfConfig|strict|noUncheckedIndexedAccess|noImplicitOverride|noImplicitReturns|noFallthroughCasesInSwitch|noUnusedLocals|noUnusedParameters|noUncheckedSideEffectImports)[^A-Za-z:[:space:]]?[[:space:]]*:[[:space:]]*true|(^|[{,])[[:space:]]*[^A-Za-z[:space:]#/]?(allowJs|allowUnreachableCode|allowUnusedLabels)[^A-Za-z:[:space:]]?[[:space:]]*:[[:space:]]*false|^[[:space:]]*-[[:space:]]+[^#[:space:]]*/conf/bleedingEdge[.]neon|^[[:space:]]*-[[:space:]]+[^#[:space:]]*/carbon/extension[.]neon"
REFACTOR_CONFIG="rector.php?"
# One extended regular expression that matches a set or switch of the refactoring tool that is on, in REFACTOR_CONFIG: the six
# prepared sets, the ten framework sets, the language sets, the framework version read from the manifest, and unused imports
# removed (templates/tool-configs.md). The row refactor-sets counts the matches and only goes up. No double quote, backslash
# or dollar sign in the value: the merge base is read as text. A set after // or # at the line start is not counted.
REFACTOR_SETS="(^[[:space:]]*|,[[:space:]]*|(^[[:space:]]*|[]A-Za-z0-9_)])->withPreparedSets[(][[:space:]]*)(deadCode|codeQuality|codingStyle|typeDeclarations|privatization|earlyReturn):[[:space:]]*true|(^[[:space:]]*|[,([][[:space:]]*)LaravelSetList::LARAVEL_(ARRAYACCESS_TO_METHOD_CALL|ARRAY_STR_FUNCTION_TO_STATIC_CALL|CODE_QUALITY|COLLECTION|CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME|ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER|FACADE_ALIASES_TO_FULL_NAMES|FACTORIES|IF_HELPERS|LEGACY_FACTORIES_TO_CLASSES)|(^[[:space:]]*|[]A-Za-z0-9_)])->withPhpSets[(][)]|(^[[:space:]]*|[]A-Za-z0-9_)])->withComposerBased[(]laravel:[[:space:]]*true[)]|(^[[:space:]]*|,[[:space:]]*|(^[[:space:]]*|[]A-Za-z0-9_)])->withImportNames[(][[:space:]]*)removeUnusedImports:[[:space:]]*true"
# The formatter's config, and one extended regular expression that matches a rule of the kit's strictness list that is on in it
# (true, or an object of options). The row formatter-rules counts the matches and only goes up: a rule that is switched off
# for its own pull request (templates/tool-configs.md, "Rules that can change behavior") is not counted until it is on.
# No double quote in the value.
FORMATTER_CONFIG="pint.json?"
FORMATTER_RULES="[^a-z_](strict_comparison|final_class|final_internal_class|final_public_method_for_abstract_class|protected_to_private|date_time_immutable|declare_strict_types|mb_str_functions|ordered_class_elements|ordered_interfaces|ordered_traits|self_accessor|self_static_accessor|modernize_types_casting|no_superfluous_elseif|no_multiple_statements_per_line|array_push|backtick_to_shell_exec|lowercase_keywords|lowercase_static_reference|visibility_required)[^a-z_:[:space:]]?[[:space:]]*:[[:space:]]*(true|[{])"
AUDIT_MANIFEST="composer.json?"
# The time-boxed audit exceptions that audit-gate.sh reads (one entry per line, at most 7 days each).
AUDIT_EXCEPTIONS="scripts/audit-exceptions.json?"
LINT_CONFIG="eslint.config.js?"
LINT_BASELINE="eslint-suppressions.json?"
# The folder with the keyed baselines that keyed-baseline.sh writes, one file per check.
KEYED_BASELINES="baselines?"
CI_FILES="bitbucket-pipelines.yml?,.github/workflows?,.gitlab-ci.yml?,scripts"
# The values that other scripts read too (the caps file, the base branch, the remote) are in kit-config.sh, next to this script.
GUARDED_FILES=".gitattributes?,CODEOWNERS?,.github/CODEOWNERS?,docs/CODEOWNERS?,.gitlab/CODEOWNERS?,scripts/kit-config.sh?,scripts/debt-dashboard.sh?,scripts/ci-commit-messages.sh?,scripts/ci-parallel.sh?,scripts/keyed-baseline.sh?,scripts/audit-gate.sh?,scripts/mutation.sh?,scripts/checks.sh?,bitbucket-pipelines.yml?,.github/workflows?,.gitlab-ci.yml?"
# The rows that never go below 0 but still take an override at 0 (a reason that is itself a decision: a justified comment, a pinned
# major). A comma list of row names; every other max row at 0 takes none.
OVERRIDE_AT_ZERO="comments,cap-dependencies-majors-behind"
# <<< config

SHARED_CONFIG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/kit-config.sh"
[ -r "$SHARED_CONFIG" ] || {
    echo "Ratchets: cannot read $SHARED_CONFIG: kit-config.sh belongs next to this script" >&2
    exit 64
}
. "$SHARED_CONFIG"

table() {
    cat <<'TABLE'
analyser-baseline                      max  neon   @ANALYSER_CONFIGS,@ANALYSER_BASELINE  parameters.ignoreErrors?
analyser-ignore                        max  grep   @ANALYSER_CONFIGS,@SOURCE_PATHS,@TEST_PATHS,:(exclude)*.md  @phpstan-ignore|@psalm-suppress|@noRector|@infection-ignore-all|@codeCoverageIgnore|phpcs:ignore|^[[:space:]]*excludePaths:|^[[:space:]]*(reportUnmatchedIgnoredErrors|treatPhpDocTypesAsCertain|checkMissingIterableValueType):[[:space:]]*false
analyser-level                         max  grep   @ANALYSER_CONFIGS  ^[[:space:]]+level:[[:space:]]+["']?[0-9]["']?[[:space:]]*(#.*)?$
analyser-switches                      min  grep   @ANALYSER_CONFIGS,@TYPE_CHECKER_CONFIGS  @ANALYSER_SWITCHES
refactor-skip                          max  block  @REFACTOR_CONFIG  (?:withSkip|->skip)\(\s*\[(.*?)\]\s*\)|withSkipPath\(([^)]*)\) ::class|["'][^"']+["']
refactor-sets                          min  grep   @REFACTOR_CONFIG  @REFACTOR_SETS
formatter-rules                        min  grep   @FORMATTER_CONFIG  @FORMATTER_RULES
advisory-block                         min  grep   @AUDIT_MANIFEST  "roave/security-advisories"[[:space:]]*:[[:space:]]*"dev-latest"
audit-ignore                           max  json   @AUDIT_MANIFEST  count? config.audit.ignore
audit-exceptions                       max  json   @AUDIT_EXCEPTIONS  count? exceptions
test-skip                              max  grep   @TEST_SKIP_PATHS,:(exclude)*.md  [})]->(skip[A-Za-z]*|todo)\(|^[[:space:]]*->(skip[A-Za-z]*|todo)\(|(^|[^A-Za-z0-9_>:$\\])todo\(|markTest(Skipped|Incomplete)\(|#\[Requires|@requires[[:space:]]
test-doubles                           max  grep   @TEST_PATHS,:(exclude)*.md  (^|[^A-Za-z0-9_])(Mockery|m)::(mock|spy|namedMock|instanceMock)[[:space:]]*\(|[A-Za-z]::(shouldReceive|shouldHaveReceived|partialMock|spy)\(|(^|[^\\])\$this->(mock|spy|partialMock)\(|test\(\)->(mock|spy|partialMock)\(|(^|[^A-Za-z0-9_>:$\\])\\?(mock|spy)[[:space:]]*\(|(create(Configured|Partial)?(Mock|Stub)[A-Za-z]*|createTestProxy|getMock[A-Za-z]*|prophesize)\(
event-fake-all                         max  grep   @TEST_PATHS,:(exclude)*.md  Event::fake(For)?\([[:space:]]*(\[[[:space:]]*\])?[[:space:]]*(\)|$)
lint-disable                           max  grep   @SOURCE_PATHS,@TEST_PATHS,@FRONTEND_PATHS,:(exclude)*.md  eslint-disable|oxlint-disable|biome-ignore|deno-lint-ignore|istanbul ignore|v8 ignore|c8 ignore|/\*[[:space:]]*eslint[[:space:]]
lint-config-off                        max  grep   @LINT_CONFIG  ["'](off|warn)["']
lint-config-ignores                    max  block  @LINT_CONFIG  (?:ignores:\s*|globalIgnores\(\s*)\[([^\]]*)\] ["'][^"']+["']
lint-baseline                          max  json   @LINT_BASELINE  value *.*.count
type-baseline                          max  grep   @KEYED_BASELINES  ^.
ts-suppressions                        max  grep   @SOURCE_PATHS,@TEST_PATHS,@FRONTEND_PATHS,:(exclude)*.md  @ts-(expect-error|ignore|nocheck)
frontend-test-skip                     max  grep   @SOURCE_PATHS,@TEST_PATHS,@FRONTEND_PATHS,:(exclude)*.md  (^|[^A-Za-z0-9_$.])(((it|test|describe)(\.(concurrent|sequential|describe))*\.(skip|todo|only|skipIf|runIf|fails|fixme)[(.])|((x|f)(it|test|describe)[(]))
comments                               max  grep   @SOURCE_PATHS,@TEST_PATHS,@FRONTEND_PATHS,:(exclude)*.md  (^|[][[:space:];{}(),])//|(^[[:space:]]*|[;{}(),][[:space:]]+)#([[:space:]]|$)|(^|[][[:space:];{}(),])/\*([^*]|\*[[:space:]]+[^@[:space:]*/])|(^|[][[:space:];{}(),])/\*[[:space:]]*$|^[[:space:]]*\*[[:space:]]+[^@[:space:]*]|<!--|\{\{--
todo                                   max  grep   @SOURCE_PATHS,@TEST_PATHS,@FRONTEND_PATHS,:(exclude)*.md  (^|[^A-Za-z0-9_])(TODO|FIXME|HACK|XXX)([^A-Za-z0-9_]|$)|@todo
ci-skipped-steps                       max  grep   @CI_FILES  \|\|[[:space:]]*(true|:|exit[[:space:]]+0|/(usr/)?bin/true|[({][[:space:]]*(true|:|exit[[:space:]]+0))([^A-Za-z0-9_]|$)|;[[:space:]]*exit[[:space:]]+0([^A-Za-z0-9_]|$)|continue-on-erro[r]:[[:space:]]*[^f"'[:space:]]|allow_failur[e]:[[:space:]]*([^f[:space:]]|$)
cap-source-files-over-limit            max  json   @CAPS_FILE  value source-files-over-limit
cap-frontend-files-over-limit          max  json   @CAPS_FILE  value frontend-files-over-limit
cap-test-files-over-limit              max  json   @CAPS_FILE  value test-files-over-limit
cap-open-classes                       max  json   @CAPS_FILE  value open-classes
cap-classes-no-test-names              max  json   @CAPS_FILE  value classes-no-test-names
cap-classes-without-own-test           max  json   @CAPS_FILE  value classes-without-own-test
cap-support-classes-without-own-test   max  json   @CAPS_FILE  value support-classes-without-own-test
cap-frontend-logic-without-test        max  json   @CAPS_FILE  value frontend-logic-without-test
cap-frontend-components-without-test   max  json   @CAPS_FILE  value frontend-components-without-test
cap-docs-older-than-limit              max  json   @CAPS_FILE  value docs-older-than-limit
cap-dependency-advisories              max  json   @CAPS_FILE  value dependency-advisories
cap-dependencies-abandoned             max  json   @CAPS_FILE  value dependencies-abandoned
cap-dependencies-majors-behind         max  json   @CAPS_FILE  value dependencies-majors-behind
TABLE
}

json_jq() {
    cat <<'JQ'
def nodes($keys): reduce $keys[] as $k ([.]; if $k == "*" then map(select(type == "object" or type == "array") | .[]) else map(select(type == "object" and has($k)) | .[$k]) end);
($path | if . == "." then [] else split(".") end) as $keys
| ($op | endswith("?")) as $optional
| ($op | rtrimstr("?")) as $kind
| nodes($keys) as $found
| if ($found | length) == 0 and ($keys | index("*") | not) and ($optional | not) then "missing key " + $path
  elif $kind == "value" then (if all($found[]; type == "number" and . >= 0 and . == floor and . <= 9007199254740991) then ($found | add // 0 | floor | tostring) else error("not a whole number at " + $path) end)
  elif $kind == "count" then (if all($found[]; type == "object" or type == "array") then ($found | map(length) | add // 0) else error("not a list at " + $path) end)
  elif $kind == "leaves" then ($found | map([.. | select(type != "object" and type != "array")] | length) | add // 0)
  elif $kind == "keys" then ($found | map(select(type == "object") | keys_unsorted[]) | .[])
  else error("unknown json operation " + $op) end
JQ
}

json_node() {
    cat <<'JS'
const [op, path] = process.argv.slice(-2);
const fail = (message) => { process.stderr.write(message + "\n"); process.exit(2); };
let data;
try { data = JSON.parse(require("fs").readFileSync(0, "utf8")); } catch (error) { fail("not JSON: " + error.message); }
const keys = path === "." ? [] : path.split(".");
const optional = op.endsWith("?");
const kind = optional ? op.slice(0, -1) : op;
const isMap = (value) => value !== null && typeof value === "object" && !Array.isArray(value);
const isBranch = (value) => value !== null && typeof value === "object";
let found = [data];
for (const key of keys) {
    found = key === "*"
        ? found.filter(isBranch).flatMap((value) => Object.values(value))
        : found.filter((value) => isMap(value) && Object.prototype.hasOwnProperty.call(value, key)).map((value) => value[key]);
}
const leaves = (value) => (isBranch(value) ? Object.values(value).reduce((sum, child) => sum + leaves(child), 0) : 1);
const sum = (numbers) => numbers.reduce((total, number) => total + number, 0);
if (found.length === 0 && !keys.includes("*") && !optional) {
    console.log("missing key " + path);
} else if (kind === "value") {
    found.every((value) => Number.isInteger(value) && value >= 0 && value <= Number.MAX_SAFE_INTEGER) || fail("not a whole number at " + path);
    console.log(sum(found));
} else if (kind === "count") {
    found.every(isBranch) || fail("not a list at " + path);
    console.log(sum(found.map((value) => Object.keys(value).length)));
} else if (kind === "leaves") {
    console.log(sum(found.map(leaves)));
} else if (kind === "keys") {
    found.filter(isMap).forEach((value) => Object.keys(value).forEach((key) => console.log(key)));
} else {
    fail("unknown json operation " + op);
}
JS
}

json_php() {
    cat <<'PHP'
[$op, $path] = array_slice($argv, -2);
$fail = function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(2);
};
try {
    $data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $fail('not JSON: ' . $error->getMessage());
}
$keys = $path === '.' ? [] : explode('.', $path);
$optional = str_ends_with($op, '?');
$kind = rtrim($op, '?');
$isMap = fn (mixed $value): bool => is_array($value) && ($value === [] || ! array_is_list($value));
$found = [$data];
foreach ($keys as $key) {
    $next = [];
    foreach ($found as $value) {
        if ($key === '*' && is_array($value)) {
            array_push($next, ...array_values($value));
        } elseif ($key !== '*' && is_array($value) && ! array_is_list($value) && array_key_exists($key, $value)) {
            $next[] = $value[$key];
        }
    }
    $found = $next;
}
$leaves = function (mixed $value) use (&$leaves): int {
    return is_array($value) ? array_sum(array_map($leaves, $value)) : 1;
};
if ($found === [] && ! in_array('*', $keys, true) && ! $optional) {
    echo 'missing key ' . $path, "\n";
} elseif ($kind === 'value') {
    foreach ($found as $value) {
        (is_int($value) || (is_float($value) && $value === floor($value))) && $value >= 0 && $value <= 9007199254740991 || $fail('not a whole number at ' . $path);
    }
    echo (int) array_sum($found), "\n";
} elseif ($kind === 'count') {
    foreach ($found as $value) {
        is_array($value) || $fail('not a list at ' . $path);
    }
    echo array_sum(array_map('count', $found)), "\n";
} elseif ($kind === 'leaves') {
    echo array_sum(array_map($leaves, $found)), "\n";
} elseif ($kind === 'keys') {
    foreach ($found as $value) {
        if ($isMap($value)) {
            echo implode("\n", array_keys($value)), $value === [] ? '' : "\n";
        }
    }
} else {
    $fail('unknown json operation ' . $op);
}
PHP
}

block_node() {
    cat <<'JS'
const spec = process.argv[process.argv.length - 1];
const split = spec.search(/\s/);
const source = require("fs").readFileSync(0, "utf8");
let total = 0;
try {
    for (const block of source.matchAll(new RegExp(spec.slice(0, split), "gs"))) {
        total += [...(block.slice(1).find((group) => group !== undefined) ?? block[0]).matchAll(new RegExp(spec.slice(split).trim(), "g"))].length;
    }
} catch (error) {
    process.stderr.write(error.message + "\n");
    process.exit(2);
}
console.log(total);
JS
}

counter_php() {
    cat <<'PHP'
function leaves(mixed $value): int
{
    if (! is_array($value)) {
        return 1;
    }
    $found = 0;
    array_walk_recursive($value, function () use (&$found): void {
        $found++;
    });

    return $found;
}

function missing(string $what): never
{
    fwrite(STDERR, $what . "\n");
    exit(3);
}

function evaluate(string $expression): mixed
{
    return eval('return ' . $expression . ';');
}

function collect(array $tokens, int $from, string $stop): array
{
    $depth = 0;
    $text = '';
    for ($i = $from; $i < count($tokens); $i++) {
        $token = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        if ($depth === 0 && $token === $stop) {
            return [$text, $i];
        }
        $depth += match ($token) {
            '(', '[', '{', '${', '{$' => 1,
            ')', ']', '}' => -1,
            default => 0,
        };
        $text .= $token;
    }
    missing('unterminated expression');
}

function significant(array $tokens, int $i, int $step): int
{
    do {
        $i += $step;
    } while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));

    return $i;
}

function neon(string $source, string $target): int
{
    $optional = str_ends_with($target, '?');
    $target = rtrim($target, '?');
    $stack = [];
    $total = 0;
    $found = false;
    $entry = null;
    foreach (preg_split('/\R/', $source) as $line) {
        if (preg_match('/^\s*(#.*)?$/', $line)) {
            continue;
        }
        preg_match('/^(\s*)(.*)$/', $line, $parts);
        $indent = strlen($parts[1]);
        $isDash = preg_match('/^-(\s|$)/', $parts[2]) === 1;
        // A list may sit at the indent of its key: a dash does not close the key above it, only the dash before it.
        while ($stack !== [] && (end($stack)['indent'] > $indent || (end($stack)['indent'] === $indent && (! $isDash || end($stack)['key'] === null)))) {
            array_pop($stack);
        }
        if ($entry !== null && count($stack) <= $entry['index']) {
            $entry = null;
        }
        $keys = array_column(array_filter($stack, fn (array $node): bool => $node['key'] !== null), 'key');
        $path = implode('.', $keys);
        $direct = ! in_array(null, array_column($stack, 'key'), true);
        if ($isDash) {
            if ($direct && ($path === $target || str_starts_with($path, $target . '.'))) {
                $total++;
                $found = true;
                $entry = ['indent' => $indent, 'index' => count($stack)];
                if (preg_match('/^-\s+count:\s*(\d+)\s*(#.*)?$/', $parts[2], $first)) {
                    $total += (int) $first[1] - 1;
                    $entry = null;
                }
            }
            $stack[] = ['indent' => $indent, 'key' => null];

            continue;
        }
        if (! preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $parts[2], $pair)) {
            continue;
        }
        if ($entry !== null && $pair[1] === 'count' && count($stack) === $entry['index'] + 1 && end($stack)['indent'] === $entry['indent'] && preg_match('/^(\d+)\s*(#.*)?$/', $pair[2], $number)) {
            $total += (int) $number[1] - 1;
            $entry = null;
        }
        $full = $path === '' ? $pair[1] : $path . '.' . $pair[1];
        if ($full === $target && $direct) {
            $found = true;
            if (preg_match('/^\[(.*)\]$/', $pair[2], $inline)) {
                $total += count(array_filter(array_map('trim', explode(',', $inline[1])), 'strlen'));
            }
        }
        $stack[] = ['indent' => $indent, 'key' => $pair[1]];
    }
    $found || $optional || missing('missing key ' . $target);

    return $total;
}

[, $kind, $spec] = $argv + [null, '', ''];
$source = stream_get_contents(STDIN);

try {
    $count = match ($kind) {
        'return' => (function () use ($source): int {
            $file = tempnam(sys_get_temp_dir(), 'ratchet');
            file_put_contents($file, $source);
            $value = require $file;
            unlink($file);

            return leaves($value);
        })(),
        'const', 'call' => (function () use ($kind, $spec, $source): int {
            $tokens = token_get_all($source);
            $names = explode(',', $spec);
            $seen = [];
            $total = 0;
            foreach ($tokens as $i => $token) {
                if (! is_array($token) || $token[0] !== T_STRING || ! in_array($token[1], $names, true)) {
                    continue;
                }
                $before = $tokens[significant($tokens, $i, -1)] ?? null;
                $after = significant($tokens, $i, 1);
                if ($kind === 'const' && is_array($before) && $before[0] === T_CONST && ($tokens[$after] ?? null) === '=') {
                    [$expression] = collect($tokens, $after + 1, ';');
                    $total += leaves(evaluate($expression));
                    $seen[$token[1]] = true;
                }
                if ($kind === 'call' && is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true) && ($tokens[$after] ?? null) === '(') {
                    [$arguments] = collect($tokens, $after + 1, ')');
                    $total += leaves(evaluate('[' . $arguments . ']'));
                    $seen[$token[1]] = true;
                }
            }
            if ($kind === 'const' ? count($seen) !== count($names) : $seen === []) {
                missing('missing ' . $kind . ' ' . $spec);
            }

            return $total;
        })(),
        'neon' => neon($source, $spec),
        'block' => (function () use ($spec, $source): int {
            [$block, $item] = preg_split('/\s+/', $spec, 2);
            $found = preg_match_all('~' . str_replace('~', '\~', $block) . '~s', $source, $blocks, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            $found !== false || throw new RuntimeException('bad block regex');
            $texts = array_map(static function (array $match): string {
                foreach (array_slice($match, 1) as $group) {
                    if ($group !== null) {
                        return $group;
                    }
                }

                return (string) $match[0];
            }, $blocks);

            return array_sum(array_map(fn (string $text): int => (int) preg_match_all('~' . str_replace('~', '\~', $item) . '~', $text), $texts));
        })(),
    };
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}

echo $count;
PHP
}

set -euo pipefail

SEPARATOR=$(printf '\001')

# The merge base's value of a config expression is read as text, the head's by the shell: a backslash, dollar sign or backtick in
# one of the three would make the two sides differ.
check_config_values() {
    local name line
    for name in ANALYSER_SWITCHES REFACTOR_SETS FORMATTER_RULES; do
        line=$(grep "^$name=\"" "${BASH_SOURCE[0]}" | awk 'NR == 1')
        case "$line" in
            *'\'* | *'$'* | *'`'*)
                echo "Ratchets: $name in the config block holds a backslash, a dollar sign or a backtick: write [(] and [.] for a literal character (tools/README.md, config block)" >&2
                return 1
                ;;
        esac
    done
}

# git grep reads a file's .gitattributes: with -I a file marked binary (or -diff) is skipped, so one attribute line could hide any
# number of sites, on both sides of the comparison. The greps therefore run with -a (every file is text) and leave out by name the
# kinds of file that really are binary.
BINARY_EXCLUDES=(':(exclude)*.png' ':(exclude)*.jpg' ':(exclude)*.jpeg' ':(exclude)*.gif' ':(exclude)*.webp' ':(exclude)*.ico' ':(exclude)*.bmp' ':(exclude)*.pdf' ':(exclude)*.woff' ':(exclude)*.woff2' ':(exclude)*.ttf' ':(exclude)*.otf' ':(exclude)*.eot' ':(exclude)*.zip' ':(exclude)*.gz' ':(exclude)*.tgz' ':(exclude)*.mp3' ':(exclude)*.mp4' ':(exclude)*.mov' ':(exclude)*.webm')

have() {
    command -v "$1" > /dev/null 2>&1
}

need() {
    echo "$1" >&2
    return 4
}

json_count() {
    local op="${1%% *}" path="${1#* }" tool="${RATCHET_JSON_TOOL:-}" result
    if [ -z "$tool" ]; then
        for tool in jq node php none; do
            ! have "$tool" || break
        done
    fi
    case "$tool" in
        jq) result=$(jq -r --arg op "$op" --arg path "$path" "$(json_jq)") || return 2 ;;
        node) result=$(node -e "$(json_node)" -- "$op" "$path") || return 2 ;;
        php) result=$(php -r "$(json_php)" -- "$op" "$path") || return 2 ;;
        *) need "a json row needs jq, node or php on the runner; none was found" || return 4 ;;
    esac
    case "$result" in
        "missing key "*)
            echo "$result" >&2
            return 3
            ;;
    esac
    printf '%s\n' "$result"
}

count() {
    local revision="$1" method="$2" spec="$3" path status=0 result total=0
    shift 3
    local -a present=() filters=()
    local named=0

    for path in "$@"; do
        [ -n "$path" ] || continue
        if [ "${path#:}" != "$path" ]; then
            filters+=("$path")
            continue
        fi
        named=1
        if git cat-file -e "$revision:${path%\?}" 2> /dev/null; then
            present+=("${path%\?}")
        elif [ "${path%\?}" = "$path" ]; then
            echo "missing ${path}" >&2
            return 3
        fi
    done

    if [ "$named" -eq 0 ] && [ "${#filters[@]}" -gt 0 ]; then
        echo "a row needs at least one real path; a pathspec (${filters[0]}) only narrows the paths before it" >&2
        return 2
    fi

    if [ "${#present[@]}" -eq 0 ]; then
        echo 0
        return 0
    fi

    case "$method" in
        grep)
            result=$(git -c core.quotePath=false grep -a -h -o -E -e "$spec" "$revision" -- "${present[@]}" "${BINARY_EXCLUDES[@]}" ${filters[@]+"${filters[@]}"}) || status=$?
            [ "$status" -le 1 ] || return 2
            if [ -n "$result" ]; then
                wc -l <<< "$result" | tr -d ' '
            else
                echo 0
            fi
            ;;
        without)
            [ "${spec#* }" != "$spec" ] || {
                echo "a without row needs two expressions: one for the file names, one for the line a file must hold" >&2
                return 2
            }
            result=$(git -c core.quotePath=false grep -a -L -E -e "${spec#* }" "$revision" -- "${present[@]}" "${BINARY_EXCLUDES[@]}" ${filters[@]+"${filters[@]}"}) || status=$?
            [ "$status" -le 1 ] || return 2
            result=$(sed "s${SEPARATOR}^$revision:${SEPARATOR}${SEPARATOR}" <<< "$result" | { grep -E -e "${spec%% *}" || [ $? -eq 1 ]; }) || return 2
            if [ -n "$result" ]; then
                wc -l <<< "$result" | tr -d ' '
            else
                echo 0
            fi
            ;;
        number)
            for path in "${present[@]}"; do
                result=$(git show "$revision:$path" | { grep -o -E -e "$spec" || [ $? -eq 1 ]; } | awk 'NR == 1' | sed -E "s${SEPARATOR}^.*${spec}.*\$${SEPARATOR}\\1${SEPARATOR}") || return 2
                [[ "$result" =~ ^[0-9]+$ ]] || {
                    echo "missing number in $path" >&2
                    return 3
                }
                total=$((total + result))
            done
            echo "$total"
            ;;
        json)
            for path in "${present[@]}"; do
                result=$(git show "$revision:$path" | json_count "$spec") || return $?
                [[ "$result" =~ ^[0-9]+$ ]] || return 2
                total=$((total + result))
            done
            echo "$total"
            ;;
        block)
            if have node; then
                for path in "${present[@]}"; do
                    git show "$revision:$path"
                done | node -e "$(block_node)" -- "$spec"
            elif have php; then
                for path in "${present[@]}"; do
                    git show "$revision:$path"
                done | php -r "$(counter_php)" -- block "$spec"
            else
                need "a block row needs node or php on the runner; neither was found"
            fi
            ;;
        neon | return | const | call)
            have php || need "a $method row needs php on the runner; use grep, number, json or block rows on a runner without it" || return 4
            for path in "${present[@]}"; do
                git show "$revision:$path"
            done | php -r "$(counter_php)" -- "$method" "$spec"
            ;;
        *)
            echo "unknown method $method" >&2
            return 2
            ;;
    esac
}

config_of() {
    awk '/^# >>> config/ { inside = 1; next } /^# <<< config/ { inside = 0 } inside && /^[A-Z_]+="/'
}

setting() {
    local name="$1" config="$2"
    [[ "$name" =~ ^[A-Z_]+$ ]] || return 1
    if [ "$config" = "-" ]; then
        eval "[ -n \"\${$name+set}\" ]" || return 1
        eval "printf '%s' \"\${$name}\""
    else
        grep -q "^$name=\"" <<< "$config" || return 1
        sed -n -E "s/^$name=\"([^\"]*)\".*$/\\1/p" <<< "$config" | awk 'NR == 1'
    fi
}

expand() {
    local paths="$1" config="$2" item value out="" emptied=0 real=0
    local -a items=()
    IFS=, read -ra items <<< "$paths"
    for item in ${items[@]+"${items[@]}"}; do
        if [ "${item#@}" != "$item" ]; then
            value=$(setting "${item#@}" "$config") || {
                echo "unknown config value $item" >&2
                return 2
            }
            item="$value"
            [ -n "$item" ] || emptied=1
        fi
        [ -z "$item" ] || out="${out:+$out,}$item"
        [ -z "$item" ] || [ "${item#:}" != "$item" ] || real=1
    done
    # A row whose only real paths are empty config values counts nothing: its exclude pathspecs go with them.
    [ "$emptied" -eq 0 ] || [ "$real" -eq 1 ] || out=""
    printf '%s' "$out"
}

resolve_spec() {
    local spec="$1" config="$2" value
    if [[ "$spec" =~ ^@[A-Z_]+$ ]]; then
        value=$(setting "${spec#@}" "$config") || {
            echo "unknown config value $spec" >&2
            return 2
        }
        spec="$value"
    fi
    printf '%s' "$spec"
}

table_of() {
    awk "/^    cat <<'TABLE'\$/ { inside = 1; next } /^TABLE\$/ { inside = 0 } inside && NF"
}

without_table() {
    awk "/^    cat <<'TABLE'\$/ { inside = 1; print; next } /^TABLE\$/ { inside = 0 } !inside"
}

uncovered() {
    local revision="$1"
    git -c core.quotePath=false ls-tree -r --name-only "$revision" | awk -v extensions="$SOURCE_EXTENSIONS" -v covered="$SOURCE_PATHS,$TEST_PATHS,$FRONTEND_PATHS,$UNCOUNTED_PATHS" '
        BEGIN {
            count = split(extensions, list, ",")
            for (i = 1; i <= count; i++) wanted[list[i]] = 1
            total = split(covered, roots, ",")
            for (i = 1; i <= total; i++) sub(/\?$/, "", roots[i])
        }
        {
            dot = match($0, /\.[^.\/]+$/)
            if (!dot || !(substr($0, dot + 1) in wanted)) next
            for (i = 1; i <= total; i++) {
                if (roots[i] != "" && ($0 == roots[i] || index($0, roots[i] "/") == 1)) next
            }
            print
        }'
}

# The rows that tools/README.md ("Adding a row") adds in a PHP project; --self-test checks their expressions too.
extra_rows() {
    cat <<'EXTRA'
strict-types-missing                   max  without  @SOURCE_PATHS,@TEST_PATHS  (^|/)[^/.]+\.php$ ^declare\(strict_types=1\);
loose-comparisons                      max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^=!<>])(==|!=|<>)([^=]|$)
mutable-dates                          max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^A-Za-z0-9_])(DateTime|Carbon)(::|[[:space:]]*[(;,)])
byte-string-functions                  max  grep   @SOURCE_PATHS,@TEST_PATHS  (^|[^A-Za-z0-9_>:$\\])\\?(strlen|substr|strpos|strrpos|stripos|strripos|strstr|stristr|strrchr|substr_count|str_split|strtolower|strtoupper)[(]
guide-future-tense                     max  grep   @SOURCE_PATHS  (^|[^A-Za-z])([Ww]ill|[Ff]or now|[Uu]ntil)([^A-Za-z]|$)
EXTRA
}

# The fixture of --self-test: one line per case, "<row>[@<file name>] <expected count> <text>". The text is one line of the file
# (~NL~ is a line break); the count is what the row must report for that file: 0 for a spelling it must not count. A file name
# may hold octal escapes (\303\234 is a letter outside ASCII). The cases are the spellings of a PHP backend with a TypeScript
# frontend: a project that changes an expression changes its cases here, or passes its own file as the second argument.
selftest_cases() {
    cat <<'CASES'
analyser-level 1     level: 9
analyser-level 1     level: 9 # was 10
analyser-level 1     level: "5"
analyser-level 1     level: 6 # raise gradually
analyser-level 0     level: max
analyser-level 0     level: 10
analyser-level 0     paths:
analyser-ignore 1 /** @psalm-suppress MixedAssignment */
analyser-ignore 1     // @phpstan-ignore-next-line
analyser-ignore 1 // @noRector
analyser-ignore 1 /** @infection-ignore-all */
analyser-ignore 1 // @codeCoverageIgnore
analyser-ignore 1 // phpcs:ignore
analyser-ignore 1     excludePaths:
analyser-ignore 1     reportUnmatchedIgnoredErrors: false
analyser-ignore 1     treatPhpDocTypesAsCertain: false
analyser-ignore 0     reportUnmatchedIgnoredErrors: true
analyser-ignore 0 $x = 1;
analyser-switches 1     checkOctaneCompatibility: true
analyser-switches 1     noEnvCallsOutsideOfConfig : true
analyser-switches 1         "strict": true,
analyser-switches 1         "strict" : true,
analyser-switches 1         "allowJs": false,
analyser-switches 2 {"strict": true, "noUnusedLocals": true}
analyser-switches 1     - %rootDir%/../../../vendor/phpstan/phpstan/conf/bleedingEdge.neon
analyser-switches 1     - vendor/nesbot/carbon/extension.neon
analyser-switches 0         // "strict": true,
analyser-switches 0     # checkOctaneCompatibility: true
analyser-switches 0     # - %rootDir%/../../../vendor/phpstan/phpstan/conf/bleedingEdge.neon
analyser-switches 0         "strict": false,
analyser-switches 0         "allowJs": true,
analyser-switches 0     strict_types: true
refactor-skip 2 ->withSkip([FooRector::class, BarRector::class])
refactor-skip 2 ->withSkip([~NL~    A::class,~NL~    B::class,~NL~])
refactor-skip 1 ->withSkipPath(__DIR__ . '/x')
refactor-skip 0 ->withSkip([])
refactor-sets 2     ->withPreparedSets(deadCode: true, codeQuality: true)
refactor-sets 1     deadCode: true,
refactor-sets 1 $config->withPreparedSets(earlyReturn: true)
refactor-sets 1     ->withPhpSets()
refactor-sets 1     ->withComposerBased(laravel: true)
refactor-sets 1     ->withImportNames(removeUnusedImports: true)
refactor-sets 1         LaravelSetList::LARAVEL_CODE_QUALITY,
refactor-sets 2     ->withSets([LaravelSetList::LARAVEL_FACTORIES, LaravelSetList::LARAVEL_IF_HELPERS])
refactor-sets 0     // codeQuality: true,
refactor-sets 0     // ->withPreparedSets(codeQuality: true)
refactor-sets 0         // LaravelSetList::LARAVEL_COLLECTION,
refactor-sets 0     deadCode: false,
formatter-rules 1         "strict_comparison": true,
formatter-rules 1         "strict_comparison" : true,
formatter-rules 1         "final_class": {"x": 1},
formatter-rules 0         "strict_comparison": false,
formatter-rules 0         "not_strict_comparison": true,
advisory-block 1     "roave/security-advisories": "dev-latest"
advisory-block 0     "roave/security-advisories": "^1.0"
test-skip 1 it('x')->todo();
test-skip 1 it('x')->skip();
test-skip 1 it('x', fn () => expect(1)->toBe(1))->skip();
test-skip 1 })->with([1,2])->skip('x');
test-skip 1 })->group('a')->skip();
test-skip 1 todo('write me');
test-skip 1     ->skip('later');
test-skip 1     $this->markTestSkipped('x');
test-skip 1     $this->markTestIncomplete();
test-skip 1 #[RequiresPhp('8.4')]
test-skip 1  * @requires PHP 8.4
test-skip 0     ->only();
test-skip 0 #[Group('x')]
test-skip 0 $query->skip(3);
test-skip 0 Foo\todo('x');
test-skip 0 $this->todo('x');
test-skip@a.bin.txt 1 it('x')->skip();
test-skip@a.nodiff.txt 1 it('x')->skip();
test-doubles 1 $m = Mockery::mock(Foo::class);
test-doubles 1 $m = \Mockery::mock(Foo::class);
test-doubles 1 $m = m::mock(Foo::class);
test-doubles 1 $this->mock(Foo::class);
test-doubles 1 $this->partialMock(Foo::class);
test-doubles 1 test()->mock(Foo::class);
test-doubles 1 $m = mock(Foo::class);
test-doubles 1 $m = spy(Foo::class);
test-doubles 1 $m = \mock(Foo::class);
test-doubles 1 $m = Mockery::mock (Foo::class);
test-doubles 1 $m = Mockery::instanceMock(Foo::class);
test-doubles 1 Cache::shouldReceive('get');
test-doubles 1 Log::shouldReceive('info');
test-doubles 1 $this->createMock(Foo::class);
test-doubles 1 $this->getMockBuilder(Foo::class);
test-doubles 1 $x = $this->prophesize(Foo::class);
test-doubles 1 $this->createTestProxy(Foo::class);
test-doubles 0 $mock->shouldReceive('x');
test-doubles 0 $thing->mock(1);
test-doubles 0 Foo\mock(1);
test-doubles 0 $x = mocker(1);
event-fake-all 1 Event::fake();
event-fake-all 1 Event::fake( );
event-fake-all 1 Event::fake([]);
event-fake-all 1 Event::fake(
event-fake-all 0 Event::fake([Foo::class]);
event-fake-all 0 Event::fake(Foo::class);
lint-disable 1 // eslint-disable-next-line no-console
lint-disable 1 /* eslint-disable */
lint-disable 1 // biome-ignore lint/suspicious/noExplicitAny: x
lint-disable 1 // oxlint-disable-next-line
lint-disable 1 // deno-lint-ignore no-explicit-any
lint-disable 1 /* istanbul ignore next */
lint-disable 1 /* v8 ignore next */
lint-disable 1 /* eslint no-console: off */
lint-disable 0 const eslintConfig = 1;
lint-config-off 1     "no-console": "off",
lint-config-off 1     rules: { x: 'warn' },
lint-config-off 0     "no-console": "error",
lint-config-ignores 2 ignores: ['dist', 'build/**'],
lint-config-ignores 1 globalIgnores(['a'])
lint-config-ignores 0 ignores: [],
lint-baseline 3 {"a.ts": {"rule": {"count": 3}}}
type-baseline 1 one finding per line
ts-suppressions 1 // @ts-expect-error
ts-suppressions 1 // @ts-ignore
ts-suppressions 1 /* @ts-nocheck */
ts-suppressions 0 // ts-ignore
frontend-test-skip 1 test.describe.skip('x', () => {
frontend-test-skip 1 test.describe.only('x', () => {
frontend-test-skip 1 test.fixme('x', () => {
frontend-test-skip 1 it.skip.each([1])('x', () => {
frontend-test-skip 1 describe.skip.each([1])('x', () => {
frontend-test-skip 1 it.concurrent.skip('x', () => {
frontend-test-skip 1 fit('x', () => {
frontend-test-skip 1 fdescribe('x', () => {
frontend-test-skip 1 test.describe.fixme('x', () => {
frontend-test-skip 1 it.skip('x', () => {
frontend-test-skip 1 xit('x', () => {
frontend-test-skip 1 xdescribe('x', () => {
frontend-test-skip 1 describe.todo('x');
frontend-test-skip 0 test.describe.configure({ mode: 'serial' });
frontend-test-skip 0 vi.skip();
frontend-test-skip 0 this.skip();
frontend-test-skip 0 it('x', () => {
frontend-test-skip 0 const gain = benefit(1);
comments 1 // note
comments 1 $a = 1; // note
comments 1 # note
comments 1 $a = 1; # note
comments 1 /* note */
comments 1 /*
comments 1 $a = [/* c */];
comments 1 $x = [1]// c
comments 1 /** Summary here
comments 1  * Prose line
comments 1  *  Indented prose
comments 1  * /path/to/x
comments 1 <!-- note -->
comments 1 {{-- note --}}
comments 1 <div>{/* note */}</div>
comments 0 $u = 'https://example.test';
comments 0 #[Attribute]
comments 0 #!/bin/bash
comments 0  * @param int $x
comments 0  */
comments 0 /**
comments 0 <a href="#top">top</a>
comments 0 $a = 1;
todo 1 // TODO later
todo 1 // FIXME later
todo 1 # HACK
todo 1 /** @todo later */
todo 1 XXX remove
todo 0 // todos
todo 0 $todoList = 1;
todo 0 // todo lower case
ci-skipped-steps 1 run: npm test ~OR~ true
ci-skipped-steps 1 run: npm test ~OR~ :
ci-skipped-steps 1 npm test ~OR~ exit 0
ci-skipped-steps 1 npm test ~OR~ /bin/true
ci-skipped-steps 1 npm test ~OR~ (exit 0)
ci-skipped-steps 1 npm test ~OR~ { :; }
ci-skipped-steps 1 npm test~SC~ exit 0
ci-skipped-steps 1     continue-on-erro~R~: true
ci-skipped-steps 1     allow_failur~E~: true
ci-skipped-steps 0     continue-on-error: false
ci-skipped-steps 0     allow_failure: false
ci-skipped-steps 0     continue-on-error: "false"
ci-skipped-steps 0 npm test ~OR~ echo ignored
ci-skipped-steps 0 npm test ~OR~ exit 1
ci-skipped-steps 0 npm test ~OR~ true_value
audit-ignore 2 {"config": {"audit": {"ignore": ["A", "B"]}}}
audit-ignore 0 {"config": {}}
audit-exceptions 2 {"exceptions": [{"id": "a"}, {"id": "b"}]}
cap-open-classes 3 {"open-classes": 3}
analyser-baseline 2 parameters:~NL~    ignoreErrors:~NL~        - '#a#'~NL~        - '#b#'
analyser-baseline 2 parameters:~NL~    ignoreErrors:~NL~    - '#a#'~NL~    - '#b#'
analyser-baseline 3 parameters:~NL~    ignoreErrors:~NL~        -~NL~            message: '#a#'~NL~            count: 3 # why~NL~            path: x.php
analyser-baseline 5 parameters:~NL~    ignoreErrors:~NL~        - count: 5~NL~          message: '#a#'
analyser-baseline 0 parameters:~NL~    level: 9
strict-types-missing@x.php 1 <?php~NL~final class A {}
strict-types-missing@x.php 0 <?php~NL~declare(strict_types=1);~NL~final class A {}
strict-types-missing@page.blade.php 0 <div></div>
strict-types-missing@\303\234ni.php 1 <?php
loose-comparisons 1 if ($a == $b) {
loose-comparisons 1 $x = $a != $b;
loose-comparisons 1 $x = $a <> $b;
loose-comparisons 0 $x = $a === $b;
loose-comparisons 0 $x = $a !== $b;
loose-comparisons 0 $x = $a <= $b;
loose-comparisons 0 $x = $a <=> $b;
loose-comparisons 0 $f = fn () => 1;
mutable-dates 1 $d = new DateTime();
mutable-dates 1 $d = new DateTime;
mutable-dates 1 $d = new Carbon;
mutable-dates 1 $d = new DateTime ();
mutable-dates 1 $d = Carbon::now();
mutable-dates 0 $d = DateTimeImmutable::now();
mutable-dates 0 $d = CarbonImmutable::now();
mutable-dates 0 function f(CarbonInterface $d) {}
byte-string-functions 1 $n = strlen($s);
byte-string-functions 1 $n = \strlen($s);
byte-string-functions 1 $n = \substr_count($a, $b);
byte-string-functions 0 $n = $x->strlen(1);
byte-string-functions 0 $n = Foo::strlen(1);
byte-string-functions 0 $n = Foo\strlen(1);
byte-string-functions 0 $n = mb_strlen($s);
guide-future-tense 1 This will change.
guide-future-tense 1 Until then.
guide-future-tense 0 A willing reader.
CASES
}

selftest_fail() {
    echo "Self-test: $1" >&2
    SELFTEST_FAILED=$((SELFTEST_FAILED + 1))
}

# --self-test [<extra cases file>]: every regex-based row is run against a fixture of spellings it must count and spellings it
# must not, in a scratch repository, with the same counter the run uses. A row that under-counts or over-counts fails, so an
# expression that silently stops matching is caught.
selftest() {
    local extra="${1:-}" NL cases line row rest want text file n=0 skipped=0 index=0 ran=0
    local name method spec got status dir path covered=""
    local -a rows=() wants=() paths=() texts=() methods=() specs=() states=()
    NL=$'\n'
    SELFTEST_FAILED=0
    cases="$(selftest_cases)"
    if [ -n "$extra" ]; then
        [ -r "$extra" ] || {
            echo "Self-test: cannot read $extra" >&2
            return 64
        }
        cases="$cases$NL$(cat "$extra")"
    fi
    unset GIT_DIR GIT_WORK_TREE GIT_INDEX_FILE
    tmp=$(mktemp -d) || return 64
    trap "rm -rf \"$tmp\"" EXIT
    cd "$tmp" || return 64
    git init -q . || return 64
    printf '*.bin.txt binary\n*.nodiff.txt -diff\n' > .gitattributes

    while IFS= read -r line; do
        case "$line" in '' | '#'*) continue ;; esac
        row="${line%% *}"
        rest="${line#* }"
        want="${rest%% *}"
        text="${rest#* }"
        file="case.txt"
        case "$row" in *@*)
            file=$(printf '%b' "${row#*@}")
            row="${row%%@*}"
            ;;
        esac
        # ~OR~, ~SC~, ~R~ and ~E~ keep the spellings of a "succeed anyway" tail out of this file: the ci-skipped-steps row counts it too.
        text="${text//~NL~/$NL}"
        text="${text//~OR~/||}"
        text="${text//~SC~/;}"
        text="${text//~R~/r}"
        text="${text//~E~/e}"
        index=$((index + 1))
        dir="cases/$row/$index"
        mkdir -p "$dir"
        printf '%s\n' "$text" > "$dir/$file"
        rows+=("$row")
        wants+=("$want")
        paths+=("$dir/$file")
        texts+=("$text")
    done <<< "$cases"
    git add -A
    git -c user.name=selftest -c user.email=selftest@example.test -c commit.gpgsign=false commit -q -m cases

    local definitions
    definitions="$RATCHETS$NL$(extra_rows)"
    index=0
    while [ "$index" -lt "${#rows[@]}" ]; do
        row="${rows[$index]}"
        line=$(awk -v row="$row" '$1 == row { print; exit }' <<< "$definitions")
        if [ -z "$line" ]; then
            skipped=$((skipped + 1))
            index=$((index + 1))
            continue
        fi
        read -r name _ method _ spec <<< "$line"
        spec=$(resolve_spec "$spec" -) || {
            selftest_fail "$row: its expression cannot be resolved"
            index=$((index + 1))
            continue
        }
        # The two audit-ignore cases are written for the composer key; a project that reads another manifest key (package.json) has
        # changed the row's expression, so the cases do not describe it: skipped, not failed.
        if [ "$row" = "audit-ignore" ] && [ "${spec#count? }" != "config.audit.ignore" ]; then
            skipped=$((skipped + 1))
            covered="$covered $row "
            index=$((index + 1))
            continue
        fi
        path="${paths[$index]}"
        [ "$method" != "without" ] || path="$(dirname "$path")"
        status=0
        got=$(count HEAD "$method" "$spec" "$path" 2> /dev/null) || status=$?
        case "$covered" in *" $row "*) ;; *) covered="$covered $row " ;; esac
        if [ "$status" -eq 4 ]; then
            skipped=$((skipped + 1))
        elif [ "$status" -ne 0 ]; then
            selftest_fail "$row cannot be counted (exit $status): ${texts[$index]}"
        else
            ran=$((ran + 1))
            [ "$got" = "${wants[$index]}" ] || selftest_fail "$row counts $got, not ${wants[$index]}: ${texts[$index]}"
        fi
        index=$((index + 1))
    done

    local uncovered=""
    while read -r name _ method _ _; do
        [ -n "$name" ] || continue
        case "$method" in grep | block | without) ;; *) continue ;; esac
        case "$name" in cap-*) continue ;; esac
        case "$covered" in *" $name "*) ;; *) uncovered="$uncovered $name" ;; esac
    done <<< "$RATCHETS"

    echo "Self-test: $ran cases run, $SELFTEST_FAILED wrong, $skipped skipped (no such row, or a counter tool is missing)."
    [ -z "$uncovered" ] || echo "Self-test: no fixture for:$uncovered"
    [ "$SELFTEST_FAILED" -eq 0 ]
}

RATCHETS=$(table)
MODE="${1:-}"

if [ "$MODE" = "--json" ]; then
    json_count "${2:?usage: ci-ratchets.sh --json '<operation> <path>' < file.json}"
    exit $?
fi

if [ "$MODE" = "--self-test" ]; then
    selftest "${2:-}"
    exit $?
fi

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT_NAME=$(basename "${BASH_SOURCE[0]}")
cd "$(git rev-parse --show-toplevel)"

check_config_values || exit 64

if [ "$MODE" = "--head" ]; then
    STATUS=0
    LAST_REASON=""
    while read -r name direction method paths spec; do
        [ -n "$name" ] || continue
        value="?"
        REASON=$(mktemp)
        if files=$(expand "$paths" - 2> "$REASON") && spec=$(resolve_spec "$spec" - 2> "$REASON"); then
            IFS=, read -ra list <<< "$files"
            value=$(count "${CHECK_HEAD:-HEAD}" "$method" "$spec" ${list[@]+"${list[@]}"} 2> "$REASON") || value="?"
        fi
        [[ "$value" =~ ^[0-9]+$ ]] || {
            THIS_REASON=$(cat "$REASON")
            if [ "$THIS_REASON" = "$LAST_REASON" ]; then
                echo "$name cannot be counted: same reason as the row above" >&2
            else
                echo "$name cannot be counted: $THIS_REASON" >&2
            fi
            LAST_REASON="$THIS_REASON"
            value="?"
            STATUS=1
        }
        rm -f "$REASON"
        printf '%s %s %s\n' "$name" "$direction" "$value"
    done <<< "$RATCHETS"
    exit "$STATUS"
fi

if [ "$MODE" = "--uncovered" ]; then
    FOUND=$(uncovered "${CHECK_HEAD:-HEAD}")
    [ -z "$FOUND" ] || printf '%s\n' "$FOUND"
    [ -z "$FOUND" ]
    exit $?
fi

if [ -n "$MODE" ] && { [ "$MODE" != "--guard" ] || [ -z "${2:-}" ]; }; then
    echo "Usage: bash $SCRIPT_NAME [--head | --uncovered | --self-test [<extra cases file>] | --json '<operation> <path>' | --guard <path of the ratchet script in the repository>]" >&2
    exit 64
fi

fail() {
    echo "Ratchets: $1" >&2
    exit 1
}

BASE_BRANCH="${CHECK_BASE:-}"
for variable in $BASE_BRANCH_VARS; do
    [ -n "$BASE_BRANCH" ] || BASE_BRANCH=$(eval "printf '%s' \"\${$variable:-}\"")
done
if [ -z "$BASE_BRANCH" ]; then
    [ -z "${CI:-}" ] || fail "no base branch. This is a CI run and none of CHECK_BASE, ${BASE_BRANCH_VARS// /, } is set: run the checks on pull requests, or set CHECK_BASE"
    BASE_BRANCH="$DEFAULT_BASE_BRANCH"
fi
HEAD_BRANCH=""
for variable in $HEAD_BRANCH_VARS; do
    [ -n "$HEAD_BRANCH" ] || HEAD_BRANCH=$(eval "printf '%s' \"\${$variable:-}\"")
done

# A branch name that starts with a dash would be read by git as an option.
case "$BASE_BRANCH" in
    -*) fail "the base branch \"$BASE_BRANCH\" starts with a dash" ;;
esac
git check-ref-format "refs/heads/$BASE_BRANCH" || fail "the base branch \"$BASE_BRANCH\" is not a branch name"
case "$HEAD_BRANCH" in
    -*) HEAD_BRANCH="" ;;
esac
[ -z "$HEAD_BRANCH" ] || git check-ref-format "refs/heads/$HEAD_BRANCH" 2> /dev/null || HEAD_BRANCH=""

BASE_REF="refs/remotes/$REMOTE/$BASE_BRANCH"
if ! git fetch -q -- "$REMOTE" "+refs/heads/$BASE_BRANCH:$BASE_REF" 2> /dev/null; then
    git rev-parse -q --verify "$BASE_REF" > /dev/null || fail "cannot fetch $REMOTE/$BASE_BRANCH"
    [ -z "${CI:-}" ] || fail "cannot fetch $REMOTE/$BASE_BRANCH in a CI run, so the copy already here may be stale. FAILS: nothing was compared"
    echo "Ratchets: cannot fetch $REMOTE/$BASE_BRANCH; comparing against the copy already here (a developer's machine only: fix the fetch command first, see the profile)" >&2
fi
BASE_TIP=$(git rev-parse "$BASE_REF")
HEAD_SHA=$(git rev-parse "${CHECK_HEAD:-HEAD}^{commit}") || fail "cannot read the head ${CHECK_HEAD:-HEAD}"

find_base() {
    BASE=$(git merge-base "$HEAD_SHA" "$BASE_TIP" 2> /dev/null)
}

for deepen in 200 2000 all; do
    ! find_base || break
    [ "$(git rev-parse --is-shallow-repository)" = "true" ] || break
    if [ "$deepen" = "all" ]; then
        git fetch -q --unshallow -- "$REMOTE" "$BASE_BRANCH" 2> /dev/null || fail "cannot fetch the full history. Clone with full depth (Bitbucket: clone depth full; GitHub: fetch-depth 0; GitLab: GIT_DEPTH 0)"
        # The head branch is a courtesy: a pull request from a fork has none on this remote.
        [ -z "$HEAD_BRANCH" ] || git fetch -q --unshallow -- "$REMOTE" "$HEAD_BRANCH" 2> /dev/null || HEAD_BRANCH=""
    else
        git fetch -q --deepen="$deepen" -- "$REMOTE" "$BASE_BRANCH" 2> /dev/null || fail "cannot deepen the shallow clone. Clone with full depth (Bitbucket: clone depth full; GitHub: fetch-depth 0; GitLab: GIT_DEPTH 0)"
        [ -z "$HEAD_BRANCH" ] || git fetch -q --deepen="$deepen" -- "$REMOTE" "$HEAD_BRANCH" 2> /dev/null || HEAD_BRANCH=""
    fi
done
find_base || fail "no merge base between $HEAD_SHA and $REMOTE/$BASE_BRANCH. Clone with full depth (Bitbucket: clone depth full; GitHub: fetch-depth 0; GitLab: GIT_DEPTH 0)"

if [ "$MODE" = "--guard" ]; then
    SELF="$2"
    SCRIPT_NAME=$(basename "$SELF")
else
    SELF=$(git -c core.quotePath=false ls-files --full-name -- "$SCRIPT_DIR/$SCRIPT_NAME" 2> /dev/null)
fi
[ -n "$SELF" ] && git cat-file -e "$HEAD_SHA:$SELF" 2> /dev/null || fail "${SELF:-$SCRIPT_DIR/$SCRIPT_NAME} is not a committed file of this repository at the head (${HEAD_SHA:0:9}): commit it; on a branch that was cut before the scripts landed, merge the base branch first"
SELF_CONFIG="$(dirname "$SELF")/kit-config.sh"

BASE_SELF=""
MOVED=0
if git cat-file -e "$BASE:$SELF" 2> /dev/null; then
    BASE_SELF="$SELF"
else
    BASE_SELF=$(git -c core.quotePath=false diff -M --name-status --diff-filter=R "$BASE" "$HEAD_SHA" | awk -F'\t' -v self="$SELF" '$3 == self && !seen++ { print $2 }')
    [ -n "$BASE_SELF" ] || BASE_SELF=$(git -c core.quotePath=false ls-tree -r --name-only "$BASE" | awk -v name="$SCRIPT_NAME" '{ parts = split($0, part, "/") } part[parts] == name && !seen++')
    [ -z "$BASE_SELF" ] || MOVED=1
fi

BASE_TABLE=""
BASE_CONFIG=""
if [ -n "$BASE_SELF" ]; then
    BASE_TABLE=$(git show "$BASE:$BASE_SELF" | table_of)
    BASE_CONFIG=$(
        git show "$BASE:$BASE_SELF" | config_of
        git show "$BASE:$(dirname "$BASE_SELF")/kit-config.sh" 2> /dev/null | config_of
    )
fi

in_list() {
    case ",$2," in
        *",$1,"*) return 0 ;;
    esac
    return 1
}

overrides() {
    local name="$1" commit reason
    shift
    [ "$#" -gt 0 ] || return 0
    for commit in $(git log --full-history --no-merges --format=%h "$BASE..$HEAD_SHA" -- "$@"); do
        reason=$(git log -1 --format=%B "$commit" | { grep -o -E "^\[ratchet: $name\][[:space:]]+[^[:space:]].*" || [ $? -eq 1 ]; } | sed -E "s/^\[ratchet: $name\][[:space:]]+//" | awk 'NR == 1')
        [ -z "$reason" ] || printf '                                         allowed by %s: %s\n' "$commit" "$reason"
    done
}

CHECKS_FAILED=0

guarded() {
    local label="$1" path="$2" allowed
    allowed=$(overrides script "$path")
    if [ -n "$allowed" ]; then
        printf 'Script: %s, allowed\n%s\n' "$label" "$allowed"
    else
        printf 'Script: %s. FAILS without [ratchet: script] <reason> in a commit that touches %s\n' "$label" "$path"
        CHECKS_FAILED=1
    fi
}

script_checks() {
    if [ "$MOVED" -eq 1 ]; then
        guarded "$SELF moved here from $BASE_SELF" "$SELF"
    fi
    if [ -n "$BASE_SELF" ] && [ "$(git show "$BASE:$BASE_SELF" | without_table)" != "$(git show "$HEAD_SHA:$SELF" | without_table)" ]; then
        guarded "$SELF changed outside its table" "$SELF"
    fi
    IFS=, read -ra GUARDED <<< "$GUARDED_FILES"
    for path in ${GUARDED[@]+"${GUARDED[@]}"}; do
        path="${path%\?}"
        git cat-file -e "$BASE:$path" 2> /dev/null || continue
        git diff --quiet "$BASE" "$HEAD_SHA" -- "$path" || guarded "$path changed" "$path"
    done
}

if [ "$MODE" = "--guard" ]; then
    script_checks
    [ "$CHECKS_FAILED" -eq 0 ] || exit 1
    echo "Script: $SELF and the guarded scripts are unchanged outside the table, or their changes are tagged."
    exit 0
fi

FAILED=0
ERRORS=$(mktemp)
trap 'rm -f "$ERRORS"' EXIT

echo "Ratchets against $REMOTE/$BASE_BRANCH (merge base ${BASE:0:9}, head ${HEAD_SHA:0:9})"
if [ -z "$BASE_SELF" ]; then
    echo "$SELF is not on the merge base: this is its first run, so every row is a new row."
fi
printf '%-38s %4s %7s %7s %7s  %s\n' "ratchet" "may" "base" "head" "change" "result"

while read -r name direction method paths spec; do
    [ -n "$name" ] || continue
    [ "$name" != "script" ] || fail "a row cannot be named script: that name is the tag for a change to the script itself"
    [ "$direction" = "max" ] || [ "$direction" = "min" ] || fail "$name: the second column is max or min, not $direction"
    expanded=$(expand "$paths" - 2> "$ERRORS") || fail "$name: $(cat "$ERRORS")"
    IFS=, read -ra files <<< "$expanded"
    written_spec="$spec"
    spec=$(resolve_spec "$spec" - 2> "$ERRORS") || fail "$name: $(cat "$ERRORS")"

    added=1
    redefined=0
    reworded=0
    base_definition=""
    base_method="$method"
    base_spec="$spec"
    base_files=(${files[@]+"${files[@]}"})
    base_line=$(awk -v row="$name" '$1 == row { print; exit }' <<< "$BASE_TABLE")
    if [ -n "$base_line" ]; then
        added=0
        read -r _ base_direction base_method base_paths base_spec <<< "$base_line"
        base_expanded=$(expand "$base_paths" "$BASE_CONFIG" 2> "$ERRORS") || fail "$name: $(cat "$ERRORS") on the merge base"
        IFS=, read -ra base_files <<< "$base_expanded"
        base_written_spec="$base_spec"
        base_spec=$(resolve_spec "$base_spec" "$BASE_CONFIG" 2> "$ERRORS") || fail "$name: $(cat "$ERRORS") on the merge base"
        base_definition="$base_direction $base_method $base_expanded $base_spec"
        [ "$base_definition" = "$direction $method $expanded $spec" ] || redefined=1
        [ "$base_direction $base_method $base_paths $base_written_spec" = "$direction $method $paths $written_spec" ] || reworded=1
    fi

    status=0
    before=$(count "$BASE" "$base_method" "$base_spec" ${base_files[@]+"${base_files[@]}"} 2> "$ERRORS") || status=$?
    [ "$status" -ne 4 ] || fail "$name: $(cat "$ERRORS")"
    [ "$status" -eq 0 ] || [ "$status" -eq 3 ] || [ "$added" -eq 1 ] || fail "cannot count $name at the merge base: $(cat "$ERRORS")"
    [ "$status" -eq 0 ] || before=0

    status=0
    after=$(count "$HEAD_SHA" "$method" "$spec" ${files[@]+"${files[@]}"} 2> "$ERRORS") || status=$?
    [ "$status" -ne 4 ] || fail "$name: $(cat "$ERRORS")"
    [ "$status" -ne 3 ] || fail "$name: $(cat "$ERRORS") at the head. A list, file or key moved: update its row in $SELF, or mark the path with ? when it may be missing"
    [ "$status" -eq 0 ] || fail "cannot count $name at the head: $(cat "$ERRORS")"
    [[ "$before" =~ ^[0-9]+$ && "$after" =~ ^[0-9]+$ ]] || fail "$name did not count to a number ($before, $after)"

    change=$((after - before))
    if [ "$direction" = "max" ]; then
        worse=$((change > 0))
        allowed_way="down"
    else
        worse=$((change < 0))
        allowed_way="up"
    fi
    result="ok"
    allowed=""

    if [ "$added" -eq 1 ]; then
        result="new row"
    elif [ "$worse" -eq 1 ]; then
        IFS=, read -ra touched <<< "${expanded//\?/}"
        allowed=$(overrides "$name" ${touched[@]+"${touched[@]}"})
        if [ "$allowed_way" = "down" ] && [ "$before" -eq 0 ] && ! in_list "$name" "$OVERRIDE_AT_ZERO"; then
            allowed=""
            result="FAILS"
            FAILED=1
        elif [ -n "$allowed" ]; then
            result="allowed"
        else
            result="FAILS"
            FAILED=1
        fi
    fi

    if [ "$redefined" -eq 1 ] && [ "$result" != "FAILS" ]; then
        allowed=$(overrides "$name" "$SELF")
        [ -n "$allowed" ] || [ "$reworded" -eq 1 ] || allowed=$(overrides script "$SELF" "$SELF_CONFIG")
        if [ -n "$allowed" ]; then
            result="redefined, allowed"
        else
            result="redefined, FAILS"
            FAILED=1
        fi
    fi

    printf '%-38s %4s %7s %7s %+7d  %s\n' "$name" "$allowed_way" "$before" "$after" "$change" "$result"
    if [ "$redefined" -eq 1 ]; then
        printf '                                         was: %s\n' "$base_definition"
        printf '                                         now: %s\n' "$direction $method $expanded $spec"
    fi
    [ -z "$allowed" ] || printf '%s\n' "$allowed"
done <<< "$RATCHETS"

while read -r name direction method paths spec; do
    [ -n "$name" ] || continue
    ! awk -v row="$name" '$1 == row { found = 1 } END { exit !found }' <<< "$RATCHETS" || continue
    expanded=$(expand "$paths" "$BASE_CONFIG" 2> "$ERRORS") || fail "removed row $name: $(cat "$ERRORS") on the merge base"
    IFS=, read -ra files <<< "$expanded"
    spec=$(resolve_spec "$spec" "$BASE_CONFIG" 2> "$ERRORS") || fail "removed row $name: $(cat "$ERRORS") on the merge base"

    status=0
    before=$(count "$BASE" "$method" "$spec" ${files[@]+"${files[@]}"} 2> "$ERRORS") || status=$?
    [ "$status" -ne 4 ] || fail "removed row $name: $(cat "$ERRORS")"
    [ "$status" -eq 0 ] || [ "$status" -eq 3 ] || fail "cannot count the removed row $name at the merge base: $(cat "$ERRORS")"
    [ "$status" -eq 0 ] || before=0

    allowed=""
    if [ "$direction" = "max" ] && [ "$before" -eq 0 ]; then
        result="removed at 0"
    else
        allowed=$(overrides "$name" "$SELF")
        if [ -n "$allowed" ]; then
            result="removed, allowed"
        else
            result="removed, FAILS without [ratchet: $name] <reason> in a commit that touches $SELF"
            FAILED=1
        fi
    fi

    printf '%-38s %4s %7s %7s %7s  %s\n' "$name" "-" "$before" "-" "-" "$result"
    [ -z "$allowed" ] || printf '%s\n' "$allowed"
done <<< "$BASE_TABLE"

echo
script_checks

if git cat-file -e "$HEAD_SHA:${CAPS_FILE%\?}" 2> /dev/null; then
    KEYS=$(git show "$HEAD_SHA:${CAPS_FILE%\?}" | json_count "keys ." 2> "$ERRORS") || fail "cannot read the keys of $CAPS_FILE: $(cat "$ERRORS")"
    while read -r key; do
        [ -n "$key" ] || continue
        if ! awk -v row="cap-$key" '$1 == row { found = 1 } END { exit !found }' <<< "$RATCHETS"; then
            echo "Caps: the key $key in $CAPS_FILE has no row cap-$key, so nothing stops it going up. FAILS: add the row"
            CHECKS_FAILED=1
        fi
    done <<< "$KEYS"
fi

OUTSIDE=$(uncovered "$HEAD_SHA")
if [ -n "$OUTSIDE" ]; then
    echo "Coverage: these tracked source files lie outside every path the rows count. FAILS: add the folder to SOURCE_PATHS, TEST_PATHS or FRONTEND_PATHS in $SELF, or to UNCOUNTED_PATHS when it is deliberately not counted"
    printf '%s\n' "$OUTSIDE" | sed 's/^/  /'
    CHECKS_FAILED=1
fi

if [ "$FAILED" -ne 0 ]; then
    echo "A ratchet moved the wrong way, or a row was redefined or removed. A row that is at 0 never takes an override: fix the entries. Other rows can be explained in a commit that touches that row's files, or this script for a redefined or removed row:"
    echo "  [ratchet: <name>] <reason>"
fi

[ "$FAILED" -eq 0 ] && [ "$CHECKS_FAILED" -eq 0 ]
