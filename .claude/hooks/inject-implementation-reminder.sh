#!/bin/bash
set -euo pipefail

input="$(cat)"

should_inject="$(INPUT="$input" python3 - <<'PY'
import json
import os
import re

data = json.loads(os.environ["INPUT"])
prompt = data.get("prompt", "").lower()
pattern = re.compile(r"\b(add|build|change|debug|edit|error|fail|fix|implement|investigate|refactor|test|update|write)\b")
print("true" if pattern.search(prompt) else "false")
PY
)"

if [[ "$should_inject" != true ]]; then
    exit 0
fi

cat <<'JSON'
{
  "hookSpecificOutput": {
    "hookEventName": "UserPromptSubmit",
    "additionalContext": "Implementation reminder: inspect relevant files first, correct mistaken premises before editing, avoid todo busywork for trivial tasks, and verify the change before claiming done. If verification cannot run, say VERIFICATION_SKIPPED: <reason>."
  }
}
JSON
