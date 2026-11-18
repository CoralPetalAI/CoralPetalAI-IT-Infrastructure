#!/usr/bin/env bash
# Store the careers form config (DB + Azure SAS) on the server: ~/.coralpetal/careers-config.json, mode 600,
# outside every docroot. Reads it from env CAREERS_CONFIG (repo secret). Skips quietly when the secret is empty.
set -euo pipefail
# Usage: careers_config_store.sh dev|prod   (dev → careers-config-dev.json, prod → careers-config.json)
case "${1:-}" in dev) FILE=careers-config-dev.json ;; prod) FILE=careers-config.json ;; *) echo "usage: $0 dev|prod" >&2; exit 2 ;; esac
[ -n "${CAREERS_CONFIG:-}" ] || { echo "careers config secret not set for $1; careers form stays closed"; exit 0; }
SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
printf '%s' "$CAREERS_CONFIG" | $SSH "${HOSTINGER_USER}@${HOSTINGER_HOST}" "umask 077; mkdir -p ~/.coralpetal; cat > ~/.coralpetal/$FILE; chmod 600 ~/.coralpetal/$FILE; echo careers config stored: $FILE"
