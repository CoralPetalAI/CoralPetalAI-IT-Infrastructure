#!/usr/bin/env bash
# Store the careers form config (DB + Azure SAS) on the server: ~/.coralpetal/careers-config.json, mode 600,
# outside every docroot. Reads it from env CAREERS_CONFIG (repo secret). Skips quietly when the secret is empty.
set -euo pipefail
[ -n "${CAREERS_CONFIG:-}" ] || { echo "CAREERS_CONFIG not set; careers form stays closed"; exit 0; }
SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
printf '%s' "$CAREERS_CONFIG" | $SSH "${HOSTINGER_USER}@${HOSTINGER_HOST}" 'umask 077; mkdir -p ~/.coralpetal; cat > ~/.coralpetal/careers-config.json; chmod 600 ~/.coralpetal/careers-config.json; echo "careers config stored"'
