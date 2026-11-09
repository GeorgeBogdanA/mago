#!/usr/bin/env bash
# PostToolUse hook: format a PHP file right after Claude edits or writes it.
# Input: the hook JSON on stdin (we need tool_input.file_path).
set -euo pipefail

root="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel)}"
fixer="$root/tools/php-cs-fixer/vendor/bin/php-cs-fixer"

# jq is not installed: extract the path with PHP, which is always available here.
file="$(php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["tool_input"]["file_path"] ?? "";')"

[[ "$file" == *.php && -f "$file" ]] || exit 0

if [[ ! -x "$fixer" ]]; then
  echo "php-cs-fixer not installed: run 'composer install' in tools/php-cs-fixer" >&2
  exit 2
fi

# exit 2 = show the error to Claude.
# php-cs-fixer silently skips files with invalid syntax (exit 0 in fix mode), so lint first.
if ! out="$(php -l "$file" 2>&1)"; then
  echo "PHP syntax error, file not formatted:" >&2
  echo "$out" >&2
  exit 2
fi

if ! out="$("$fixer" fix --config="$root/.php-cs-fixer.dist.php" --path-mode=override --quiet -- "$file" 2>&1)"; then
  echo "php-cs-fixer failed on $file:" >&2
  echo "$out" >&2
  exit 2
fi
