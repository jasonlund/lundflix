#!/bin/bash
set -euo pipefail

project_dir="${CLAUDE_PROJECT_DIR:-$PWD}"
runtime_dir="$project_dir/.claude/runtime"
input="$(cat)"

command="$(INPUT="$input" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["INPUT"])
print(data.get("tool_input", {}).get("command", ""))
PY
)"

should_record=false

case "$command" in
    "php artisan test"*)
        should_record=true
        ;;
    "vendor/bin/pest"*|"./vendor/bin/pest"*|"vendor/bin/phpunit"*|"./vendor/bin/phpunit"*)
        should_record=true
        ;;
    "vendor/bin/pint --dirty"*|"./vendor/bin/pint --dirty"*)
        should_record=true
        ;;
    "composer phpstan"*|"vendor/bin/phpstan"*|"./vendor/bin/phpstan"*)
        should_record=true
        ;;
    "npm run format:check"*|"npm run lint"*)
        should_record=true
        ;;
    "bash -n "*".claude/hooks/"*|"/bin/bash -n "*".claude/hooks/"*)
        should_record=true
        ;;
esac

if [[ "$command" == jq\ -e*".claude/settings.json"* ]] || [[ "$command" == "python3 -m json.tool "*".claude/settings.json"* ]]; then
    should_record=true
fi

if [[ "$should_record" != true ]]; then
    exit 0
fi

mkdir -p "$runtime_dir"
date +%s > "$runtime_dir/last_verification_epoch"
printf '%s\n' "$command" > "$runtime_dir/last_verification_command"
