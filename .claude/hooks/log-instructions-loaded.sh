#!/bin/bash
set -euo pipefail

project_dir="${CLAUDE_PROJECT_DIR:-$PWD}"
runtime_dir="$project_dir/.claude/runtime"
input="$(cat)"

line="$(INPUT="$input" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["INPUT"])
parts = [
    data.get("load_reason", ""),
    data.get("memory_type", ""),
    data.get("file_path", ""),
    data.get("trigger_file_path", ""),
]
print("\t".join(parts))
PY
)"

mkdir -p "$runtime_dir"
printf '%s\t%s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$line" >> "$runtime_dir/instructions-loaded.log"
