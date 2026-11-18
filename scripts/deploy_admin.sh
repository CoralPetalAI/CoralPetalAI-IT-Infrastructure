#!/usr/bin/env bash
# Deploy the admin app (admin/) to Hostinger.
#   scripts/deploy_admin.sh dev|prod
#   dev  → public_html/dev-admin (dev-admin.coralpetal.ai)   prod → public_html/admin (admin.coralpetal.ai)
# Config is NOT in git: env ADMIN_CONFIG (secret ADMIN_CONFIG_DEV / ADMIN_CONFIG) is written over stdin to
# ~/.coralpetal/admin-config-dev.json or admin-config.json (mode 600, outside every docroot).
set -euo pipefail
case "${1:-}" in
  dev)  FOLDER=dev-admin; CFG=admin-config-dev.json ;;
  prod) FOLDER=admin;     CFG=admin-config.json ;;
  *) echo "usage: $0 dev|prod" >&2; exit 2 ;;
esac
TARGET="/home/${HOSTINGER_USER}/domains/coralpetal.ai/public_html/${FOLDER}"
[[ "$TARGET" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/(dev-admin|admin)$ ]] || { echo "REFUSING path $TARGET" >&2; exit 3; }
SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"
$SSH "$REMOTE" "test -d '$TARGET'" || { echo "target missing on server: $TARGET" >&2; exit 5; }

# First deploy only: move Hostinger's placeholder page to ~/retired (never removed).
$SSH "$REMOTE" "set -e; T='$TARGET'
  if ! grep -qs 'Coral Petal AI Admin' \"\$T/index.php\"; then
    R=\"\$HOME/retired/${FOLDER}-\$(date +%Y-%m-%d-%H%M%S)\"; mkdir -p \"\$R\"
    find \"\$T\" -mindepth 1 -maxdepth 1 ! -name .well-known -exec mv {} \"\$R\"/ \;
    echo \"placeholder moved to \$R\"
  fi"

OUT="$(mktemp -d)/admin"; cp -R admin "$OUT"
printf '{"site":"admin","env":"%s","commit":"%s","built":"%s"}\n' "$1" "${GITHUB_SHA:-local}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$OUT/version.json"
rsync -rlz --checksum --delete --chmod=D755,F644 --exclude=/.well-known/ -e "$SSH" "$OUT"/ "$REMOTE:$TARGET/"

if [ -n "${ADMIN_CONFIG:-}" ]; then
  printf '%s' "$ADMIN_CONFIG" | $SSH "$REMOTE" "umask 077; mkdir -p ~/.coralpetal; cat > ~/.coralpetal/$CFG; chmod 600 ~/.coralpetal/$CFG; echo admin config stored: $CFG"
else
  echo "admin config secret not set for $1: the app shows 'not configured'"
fi
echo "deployed admin ($1) → $TARGET"
