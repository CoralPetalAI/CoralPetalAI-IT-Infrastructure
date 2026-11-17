#!/usr/bin/env bash
# Write the dev password file on Hostinger and protect the dev-academy WordPress site.
#   scripts/dev_auth_setup.sh
# Needs env: HOSTINGER_HOST, HOSTINGER_PORT, HOSTINGER_USER, DEV_AUTH_USER, DEV_AUTH_PASS,
# SSH key at ~/.ssh/id with pinned known_hosts. Idempotent: runs on every dev deploy.
#   1. ~/.htpasswds/coralpetal-dev (outside every docroot) from the repo secrets.
#   2. public_html/dev-academy/.htaccess: the DEV ACCESS block goes at the top if missing.
#      WordPress manages the rest of that file; the previous copy is saved in ~/retired/.
set -euo pipefail
: "${DEV_AUTH_USER:?}"; : "${DEV_AUTH_PASS:?}"; : "${HOSTINGER_USER:?}"

SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"
BLOCK="$(bash "$(dirname "$0")/dev_access_block.sh")"
WPDIR="/home/${HOSTINGER_USER}/domains/coralpetal.ai/public_html/dev-academy"
[[ "$WPDIR" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/dev-academy$ ]] || { echo "bad path $WPDIR" >&2; exit 3; }

# 1. Password file: rewritten only when the stored password no longer matches the secret.
STORED=$($SSH "$REMOTE" "cat ~/.htpasswds/coralpetal-dev 2>/dev/null || true")
OK=0
if [ "${STORED%%:*}" = "$DEV_AUTH_USER" ]; then
  HASH="${STORED#*:}"; SALT=$(printf '%s' "$HASH" | cut -d'$' -f3)
  [ "$(openssl passwd -apr1 -salt "$SALT" "$DEV_AUTH_PASS")" = "$HASH" ] && OK=1
fi
if [ "$OK" = 1 ]; then echo "password file ok"
else
  printf '%s:%s\n' "$DEV_AUTH_USER" "$(openssl passwd -apr1 "$DEV_AUTH_PASS")" \
    | $SSH "$REMOTE" "set -e; mkdir -p ~/.htpasswds; cat > ~/.htpasswds/coralpetal-dev"
  echo "password file written"
fi
# The web server must be able to read the file: folder 755, file 644 (a 700 folder = every login rejected).
$SSH "$REMOTE" "chmod 755 ~/.htpasswds && chmod 644 ~/.htpasswds/coralpetal-dev"

# 2. dev-academy (WordPress) protection
printf '%s\n' "$BLOCK" | $SSH "$REMOTE" "set -e; D='$WPDIR'; H=\"\$D/.htaccess\"
  test -d \"\$D\" || { echo 'dev-academy folder missing' >&2; exit 5; }
  B=\$(cat)
  if [ -f \"\$H\" ] && grep -q 'BEGIN DEV ACCESS' \"\$H\"; then echo 'dev-academy already protected'; exit 0; fi
  if [ -f \"\$H\" ]; then R=\"\$HOME/retired/dev-academy-htaccess-\$(date +%Y-%m-%d-%H%M%S)\"; mkdir -p \"\$R\"; cp \"\$H\" \"\$R/.htaccess\"; fi
  { printf '%s\n\n' \"\$B\"; if [ -f \"\$H\" ]; then cat \"\$H\"; fi; } > \"\$H.tmp\"
  mv \"\$H.tmp\" \"\$H\"; chmod 644 \"\$H\"
  echo 'dev-academy protected'"
