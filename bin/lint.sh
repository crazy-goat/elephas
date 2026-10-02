#!/usr/bin/env bash
# Run all static analysis, linters and formatter checks. --fix applies fixes first.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

FIX=0
[ "${1:-}" = "--fix" ] && FIX=1
failed=()

step() {
    local name="$1"; shift
    echo "==> $name"
    "$@" || failed+=("$name")
}

if [ "$FIX" = 1 ]; then
    step "rector (fix)" vendor/bin/rector process
    step "php-cs-fixer (fix)" vendor/bin/php-cs-fixer fix -v
fi

step "composer validate" composer validate --strict
step "composer audit" composer audit
step "php-cs-fixer" vendor/bin/php-cs-fixer fix -v --dry-run --diff
step "phpstan" vendor/bin/phpstan analyse --no-progress --memory-limit=512M
step "rector" vendor/bin/rector process --dry-run
step "shellcheck" bash -c 'git ls-files -z "*.sh" | xargs -0 -r shellcheck'

if [ "${#failed[@]}" -gt 0 ]; then
    echo "Failed: ${failed[*]}" >&2
    exit 1
fi
echo "All checks passed."
