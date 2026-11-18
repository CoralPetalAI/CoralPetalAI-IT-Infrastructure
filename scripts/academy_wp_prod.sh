#!/usr/bin/env bash
# Apply the Academy WordPress build to PRODUCTION (academy.coralpetal.ai) — Release 1.0001.
#   scripts/academy_wp_prod.sh
# Needs env: HOSTINGER_HOST, HOSTINGER_PORT, HOSTINGER_USER, SSH key at ~/.ssh/id, pinned known_hosts.
# Same steps as scripts/academy_wp_dev.sh, without dev link rewriting, the dev noindex plugin or the
# review account, plus:
#   0. One time: the OLD static Academy files next to WordPress (index.html, css/, section folders, its
#      .htaccess …) are MOVED to ~/retired/academy-static-<ts>/ on the server. Nothing is deleted.
# SAFETY: every target is computed here and must be exactly public_html/academy.
set -euo pipefail

WP="/home/${HOSTINGER_USER}/domains/coralpetal.ai/public_html/academy"
[[ "$WP" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/academy$ ]] || { echo "REFUSING path $WP" >&2; exit 3; }
THEME="$WP/wp-content/themes/coralpetal-academy"
MU="$WP/wp-content/mu-plugins"
CONTENT="/home/${HOSTINGER_USER}/coralpetal-academy-content-prod"

SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"
W="wp --skip-plugins=hostinger-ai-assistant,hostinger-reach"

# 0. Retire the old static Academy: move everything that is not WordPress core (keeps .well-known).
$SSH "$REMOTE" bash -s -- "$WP" <<'REMOTE_EOF'
set -e
cd "$1"
if [ -f index.html ]; then
  R="$HOME/retired/academy-static-$(date +%Y-%m-%d-%H%M%S)"; mkdir -p "$R"
  KEEP='^(wp-admin|wp-content|wp-includes|wp-[a-z-]+\.php|index\.php|xmlrpc\.php|license\.txt|readme\.html|\.well-known|\.user\.ini|php\.ini|\.htaccess)$'
  for f in * .[!.]*; do
    [ -e "$f" ] || continue
    echo "$f" | grep -Eq "$KEEP" && continue
    mv "$f" "$R/"
  done
  if [ -f .htaccess ] && ! grep -q 'BEGIN WordPress' .htaccess; then mv .htaccess "$R/htaccess.static"; fi
  echo "old static academy moved to $R"
else
  echo "no static academy left: nothing to retire"
fi
REMOTE_EOF

# 1. Install WordPress once (Hostinger's installer refuses a non-empty folder). Secret ACADEMY_PROD_WP =
#    4 lines: db name, db user, db password, admin password. Sent over stdin, never in argv or git.
if ! $SSH "$REMOTE" "test -f '$WP/wp-config.php'"; then
  [ -n "${ACADEMY_PROD_WP:-}" ] || { echo "no WordPress at $WP and secret ACADEMY_PROD_WP not set" >&2; exit 5; }
  # stdin carries ONLY the 4 secret lines; the commands are in the (secret-free) argument.
  printf '%s\n' "$ACADEMY_PROD_WP" | $SSH "$REMOTE" "set -e; read -r DBN; read -r DBU; read -r DBP; read -r ADMP; cd '$WP'
    W='wp --skip-plugins --skip-themes'
    \$W core download --locale=en_US --quiet
    printf '%s' \"\$DBP\" | \$W config create --dbname=\"\$DBN\" --dbuser=\"\$DBU\" --dbhost=127.0.0.1 --prompt=dbpass --quiet
    printf '%s' \"\$ADMP\" | \$W core install --url=https://academy.coralpetal.ai --title='Coral Petal AI Academy' --admin_user=cpadmin --admin_email=admin@coralpetal.ai --prompt=admin_password --skip-email
    \$W rewrite structure '/%postname%/' --hard
    echo 'WordPress installed at $WP'"
fi

$SSH "$REMOTE" "mkdir -p '$THEME' '$MU' '$CONTENT'"
rsync -rlz --checksum --delete --chmod=D755,F644 -e "$SSH" academy-wp/theme/coralpetal-academy/ "$REMOTE:$THEME/"
rsync -rlz --checksum --chmod=D755,F644 --exclude 'coralpetal-dev-noindex.php' -e "$SSH" academy-wp/mu-plugins/ "$REMOTE:$MU/"
rsync -rlz --checksum --chmod=D755,F644 -e "$SSH" academy-wp/content/ academy-wp/remote_setup.sh academy-wp/smtp_store.php "$REMOTE:$CONTENT/"

SUM=$(cd academy-wp && find content remote_setup.sh -type f -print0 | sort -z | xargs -0 md5sum | md5sum | cut -c1-32)
LAST=$($SSH "$REMOTE" "cat '$CONTENT/.setup-md5' 2>/dev/null || true")
if [ "$SUM" != "$LAST" ] || [ "${FORCE_ACADEMY_SETUP:-}" = "1" ]; then
  $SSH "$REMOTE" "bash '$CONTENT/remote_setup.sh' '$WP' '$CONTENT' && echo '$SUM' > '$CONTENT/.setup-md5'"
else
  echo "academy content unchanged: setup skipped (theme + plugins updated)"
fi
$SSH "$REMOTE" "cd '$WP' && ($W rewrite flush --hard >/dev/null 2>&1 || true) && ($W cache flush >/dev/null 2>&1 || true)"

# Mail: same Proton SMTP secrets as dev (academy@coralpetal.ai), stored outside every docroot.
if [ -n "${ACADEMY_SMTP_USER:-}" ] && [ -n "${ACADEMY_SMTP_TOKEN:-}" ]; then
  printf '%s\n%s\n' "$ACADEMY_SMTP_USER" "$ACADEMY_SMTP_TOKEN" | $SSH "$REMOTE" "php '$CONTENT/smtp_store.php'"
fi
echo "prod academy applied"
