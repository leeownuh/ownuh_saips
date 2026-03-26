#!/bin/bash
#
# Ownuh SAIPS — Threat Intelligence Feed Updater
# Runs every 6 hours via cron (SRS §3.3)
# Updates: AbuseIPDB reputation cache, Tor exit node list, Spamhaus feeds
#
# Crontab: 0 */6 * * * /var/www/ownuh-saips/backend/scripts/update-threat-feeds.sh

set -euo pipefail

LOG="/var/log/saips/threat-feeds.log"
REDIS_CLI="redis-cli -a ${REDIS_PASS:-}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"
}

log "Starting threat feed update..."

# ── 1. Tor Exit Node List ─────────────────────────────────────────────────────
log "Fetching Tor exit node list..."
TOR_LIST=$(curl -sf --max-time 30 "https://check.torproject.org/torbulkexitlist" 2>/dev/null || echo "")

if [ -n "$TOR_LIST" ]; then
    COUNT=0
    # Clear existing set and rebuild
    $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" DEL saips:tor_exits > /dev/null

    while IFS= read -r ip; do
        if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" SADD saips:tor_exits "$ip" > /dev/null
            COUNT=$((COUNT + 1))
        fi
    done <<< "$TOR_LIST"

    # Set 25-hour TTL (updated daily, with buffer)
    $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" EXPIRE saips:tor_exits 90000 > /dev/null
    log "Tor exit nodes updated: ${COUNT} IPs"
else
    log "WARNING: Failed to fetch Tor exit node list — using cached version"
fi

# ── 2. Emerging Threats IP Blocklist ─────────────────────────────────────────
log "Fetching Emerging Threats compromised IP list..."
ET_LIST=$(curl -sf --max-time 30 \
    "https://rules.emergingthreats.net/blockrules/compromised-ips.txt" 2>/dev/null || echo "")

if [ -n "$ET_LIST" ]; then
    $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" DEL saips:et_blocklist > /dev/null
    COUNT=0

    while IFS= read -r ip; do
        # Skip comments
        [[ "$ip" =~ ^# ]] && continue
        if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" SADD saips:et_blocklist "$ip" > /dev/null
            COUNT=$((COUNT + 1))
        fi
    done <<< "$ET_LIST"

    $REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" EXPIRE saips:et_blocklist 25200 > /dev/null
    log "Emerging Threats blocklist updated: ${COUNT} IPs"
else
    log "WARNING: Failed to fetch Emerging Threats list"
fi

# ── 3. Update MaxMind GeoLite2 (monthly — only if older than 25 days) ─────────
MMDB="${MAXMIND_DB_PATH:-/usr/share/GeoIP/GeoLite2-Country.mmdb}"
if [ -f "$MMDB" ]; then
    AGE_DAYS=$(( ( $(date +%s) - $(stat -c %Y "$MMDB") ) / 86400 ))
    if [ "$AGE_DAYS" -gt 25 ]; then
        log "GeoLite2 DB is ${AGE_DAYS} days old — updating..."
        if [ -n "${MAXMIND_LICENSE_KEY:-}" ]; then
            geoipupdate 2>/dev/null && log "GeoLite2 updated successfully" || log "WARNING: geoipupdate failed"
        else
            log "MAXMIND_LICENSE_KEY not set — skipping GeoLite2 update"
        fi
    else
        log "GeoLite2 DB is ${AGE_DAYS} days old — no update needed"
    fi
fi

# ── 4. Record last update timestamp ───────────────────────────────────────────
$REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" SET saips:threat_feeds:last_updated "$(date -u +%Y-%m-%dT%H:%M:%SZ)" EX 86400 > /dev/null

log "Threat feed update complete."
