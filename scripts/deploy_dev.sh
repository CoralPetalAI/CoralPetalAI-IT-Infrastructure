#!/usr/bin/env bash
# Deploy a DEV build to Hostinger over SSH (rsync).
#   scripts/deploy_dev.sh site|careers <built_dir>
#   site    → public_html/dev          (dev.coralpetal.ai)
#   careers → public_html/dev-careers  (dev-careers.coralpetal.ai)
# Needs env: HOSTINGER_HOST, HOSTINGER_PORT, HOSTINGER_USER, SSH key at ~/.ssh/id, pinned known_hosts.
#
# SAFETY: litandlatte.com shares this hosting account and the dev folders sit INSIDE the
# prod docroot. The target is computed here from a fixed map (never passed in) and must
# match the allow-list below. Nothing outside the two dev folders is ever touched.
set -euo pipefail

WHAT="$1"; SRC="$2"
case "$WHAT" in
  site)    FOLDER=dev ;;
  careers) FOLDER=dev-careers ;;
  *) echo "usage: deploy_dev.sh site|careers <built_dir>" >&2; exit 2 ;;
esac
TARGET="/home/${HOSTINGER_USER}/domains/coralpetal.ai/public_html/${FOLDER}"

if ! [[ "$TARGET" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/(dev|dev-careers)$ ]]; then
  echo "REFUSING to deploy to unexpected path: $TARGET" >&2; exit 3
fi
[ -f "$SRC/index.html" ] || { echo "no index.html in $SRC" >&2; exit 4; }
grep -q '"env":"dev"' "$SRC/version.json" 2>/dev/null || { echo "$SRC is not a dev build (use scripts/build_dev.sh)" >&2; exit 4; }
grep -q 'BEGIN DEV ACCESS' "$SRC/.htaccess" || { echo "$SRC/.htaccess has no DEV ACCESS block" >&2; exit 4; }

SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"

# The folder must already exist (created by hPanel with the subdomain); never create it.
$SSH "$REMOTE" "test -d '$TARGET'" || { echo "target missing on server: $TARGET" >&2; exit 5; }

# First deploy only: move Hostinger's placeholder page to ~/retired/ (never remove anything).
$SSH "$REMOTE" "set -e; T='$TARGET'
  if [ ! -f \"\$T/version.json\" ]; then
    R=\"\$HOME/retired/${FOLDER}-\$(date +%Y-%m-%d-%H%M%S)\"; mkdir -p \"\$R\"
    find \"\$T\" -mindepth 1 -maxdepth 1 ! -name .well-known -exec mv {} \"\$R\"/ \;
    echo \"moved the placeholder content to \$R\"
  fi"

# Mirror the build into the dev folder (same mirror mode as the prod scripts),
# except .well-known/ (Hostinger SSL validation files).
rsync -rlz --checksum --delete --chmod=D755,F644 --exclude=/.well-known/ \
  -e "$SSH" "$SRC"/ "$REMOTE:$TARGET/"
echo "deployed dev $WHAT → $TARGET"
