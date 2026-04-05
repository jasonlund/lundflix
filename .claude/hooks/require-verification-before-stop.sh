#!/bin/bash
set -euo pipefail

project_dir="${CLAUDE_PROJECT_DIR:-$PWD}"
runtime_dir="$project_dir/.claude/runtime"
input="$(cat)"

stop_hook_active="$(INPUT="$input" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["INPUT"])
print("true" if data.get("stop_hook_active") else "false")
PY
)"

if [[ "$stop_hook_active" == true ]]; then
    exit 0
fi

last_assistant_message="$(INPUT="$input" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["INPUT"])
print(data.get("last_assistant_message", ""))
PY
)"

if [[ "$last_assistant_message" == *"VERIFICATION_SKIPPED:"* ]]; then
    exit 0
fi

changed_files="$(git -C "$project_dir" status --porcelain)"

if [[ -z "$changed_files" ]]; then
    exit 0
fi

last_edit_epoch=0
last_verification_epoch=0

if [[ -f "$runtime_dir/last_edit_epoch" ]]; then
    last_edit_epoch="$(cat "$runtime_dir/last_edit_epoch")"
fi

if [[ -f "$runtime_dir/last_verification_epoch" ]]; then
    last_verification_epoch="$(cat "$runtime_dir/last_verification_epoch")"
fi

if (( last_verification_epoch > 0 && last_verification_epoch >= last_edit_epoch )); then
    exit 0
fi

changed_summary="$(printf '%s\n' "$changed_files" | head -n 6 | tr '\n' '; ' | sed 's/; $//')"

cat <<JSON
{
  "decision": "block",
  "reason": "Run the smallest relevant verification before stopping, or say VERIFICATION_SKIPPED: <reason>. Recent changes: $changed_summary"
}
JSON
