#!/bin/bash
set -e

# =============================================================================
# Conductor Workspace Teardown Script
# =============================================================================

FOLDER=$(basename "$PWD")

# -----------------------------------------------------------------------------
# Herd Cleanup (reverse of `herd link --secure`)
# -----------------------------------------------------------------------------
herd unsecure "${FOLDER}.test" --silent 2>/dev/null || true
herd unlink "$FOLDER" 2>/dev/null || true

# Manual cleanup as safety net (herd commands don't always clean up)
HERD_DIR="$HOME/Library/Application Support/Herd/config/valet"
rm -f "$HERD_DIR/Nginx/${FOLDER}.test"
rm -f "$HERD_DIR/Certificates/${FOLDER}.test."*

# -----------------------------------------------------------------------------
# Database Cleanup
# -----------------------------------------------------------------------------
mysql -uroot -e "DROP DATABASE IF EXISTS \`${FOLDER}\`"
