#!/bin/bash
set -euo pipefail

project_dir="${CLAUDE_PROJECT_DIR:-$PWD}"
runtime_dir="$project_dir/.claude/runtime"

mkdir -p "$runtime_dir"
rm -f \
    "$runtime_dir/last_edit_epoch" \
    "$runtime_dir/last_edit_path" \
    "$runtime_dir/last_verification_epoch" \
    "$runtime_dir/last_verification_command"
