#!/bin/bash
set -euo pipefail

project_dir="${CLAUDE_PROJECT_DIR:-$PWD}"
runtime_dir="$project_dir/.claude/runtime"
input="$(cat)"

file_path="$(INPUT="$input" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["INPUT"])
print(data.get("tool_input", {}).get("file_path", ""))
PY
)"

if [[ -z "$file_path" ]]; then
    exit 0
fi

mkdir -p "$runtime_dir"
date +%s > "$runtime_dir/last_edit_epoch"
printf '%s\n' "$file_path" > "$runtime_dir/last_edit_path"
