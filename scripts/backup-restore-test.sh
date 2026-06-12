#!/usr/bin/env bash
# Monthly backup restore-test. Restores the most-recent /var/backups/almanac
# dump into a scratch DB, runs a smoke query, then drops the scratch DB.
# Alerts on failure via the BetterStack webhook if BETTERSTACK_URL is set.

set -euo pipefail

LATEST="$(ls -1t /var/backups/almanac/*.sql.gz 2>/dev/null | head -1 || true)"
if [[ -z "$LATEST" ]]; then
    echo "No backup files found in /var/backups/almanac" >&2
    exit 1
fi

SCRATCH="almanac_restore_test_$(date +%Y%m%d%H%M%S)"

cleanup() {
    sudo -u postgres dropdb --if-exists "$SCRATCH" >/dev/null 2>&1 || true
}
trap cleanup EXIT

sudo -u postgres createdb "$SCRATCH"
sudo -u postgres psql -d "$SCRATCH" -c "CREATE EXTENSION vector;" >/dev/null

gunzip -c "$LATEST" | sudo -u postgres psql -d "$SCRATCH" >/dev/null

COUNT=$(sudo -u postgres psql -At -d "$SCRATCH" -c "SELECT COUNT(*) FROM workspaces;")
if [[ "$COUNT" =~ ^[0-9]+$ ]] && [[ "$COUNT" -gt 0 ]]; then
    echo "Restore test OK: $LATEST → workspaces=$COUNT"
else
    echo "Restore test FAIL: workspaces count=$COUNT after restore" >&2
    if [[ -n "${BETTERSTACK_URL:-}" ]]; then
        curl -fsSL -X POST "$BETTERSTACK_URL" -d "almanac restore test failed"
    fi
    exit 1
fi
