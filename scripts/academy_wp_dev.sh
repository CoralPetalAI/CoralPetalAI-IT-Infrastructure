#!/usr/bin/env bash
# Apply the Academy WordPress build to DEV (dev-academy.coralpetal.ai).
#   scripts/academy_wp_dev.sh
# Needs env: HOSTINGER_HOST, HOSTINGER_PORT, HOSTINGER_USER, SSH key at ~/.ssh/id, pinned known_hosts.
#
# 1. Theme  academy-wp/theme/coralpetal-academy/ → wp-content/themes/coralpetal-academy/ (mirror of that folder only)
# 2. MU plugins academy-wp/mu-plugins/*.php      → wp-content/mu-plugins/ (copy, nothing else touched)
# 3. Content academy-wp/content/                 → ~/coralpetal-academy-content/ (outside every docroot)
# 4. Runs academy-wp/remote_setup.sh there with WP-CLI (course, modules, pages, settings).
# SAFETY: every target is computed here and must sit inside public_html/dev-academy.
set -euo pipefail

WP="/home/${HOSTINGER_USER}/domains/coralpetal.ai/public_html/dev-academy"
[[ "$WP" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/dev-academy$ ]] || { echo "REFUSING path $WP" >&2; exit 3; }
THEME="$WP/wp-content/themes/coralpetal-academy"
MU="$WP/wp-content/mu-plugins"
CONTENT="/home/${HOSTINGER_USER}/coralpetal-academy-content"

SSH="ssh -i $HOME/.ssh/id -p ${HOSTINGER_PORT} -o StrictHostKeyChecking=yes -o BatchMode=yes"
REMOTE="${HOSTINGER_USER}@${HOSTINGER_HOST}"

$SSH "$REMOTE" "test -f '$WP/wp-config.php'" || { echo "no WordPress at $WP" >&2; exit 5; }
$SSH "$REMOTE" "mkdir -p '$THEME' '$MU' '$CONTENT'"

# Dev copy with every prod link pointed at its dev twin (scripts/dev_links.sh).
SRC="$(mktemp -d)/academy-wp"; cp -R academy-wp "$SRC"
bash "$(dirname "$0")/dev_links.sh" "$SRC"

rsync -rlz --checksum --delete --chmod=D755,F644 -e "$SSH" "$SRC/theme/coralpetal-academy/" "$REMOTE:$THEME/"
rsync -rlz --checksum --chmod=D755,F644 -e "$SSH" "$SRC/mu-plugins/" "$REMOTE:$MU/"
rsync -rlz --checksum --chmod=D755,F644 -e "$SSH" "$SRC/content/" "$SRC/remote_setup.sh" "$SRC/smtp_store.php" "$SRC/review_user.php" "$REMOTE:$CONTENT/"

# Speed (2026-11-18): the course/page setup (~40 WP-CLI calls, ~3.5 min) only runs when the
# content or the setup script changed. Theme and plugin changes need just the rsync above + a cache flush.
SUM=$(cd "$SRC" && find content remote_setup.sh -type f -print0 | sort -z | xargs -0 md5sum | md5sum | cut -c1-32)
LAST=$($SSH "$REMOTE" "cat '$CONTENT/.setup-md5' 2>/dev/null || true")
if [ "$SUM" != "$LAST" ] || [ "${FORCE_ACADEMY_SETUP:-}" = "1" ]; then
  $SSH "$REMOTE" "bash '$CONTENT/remote_setup.sh' '$WP' '$CONTENT' && echo '$SUM' > '$CONTENT/.setup-md5'"
else
  echo "academy content unchanged: setup skipped (theme + plugins updated)"
  $SSH "$REMOTE" "cd '$WP' && wp --skip-plugins=hostinger-ai-assistant,hostinger-reach cache flush >/dev/null 2>&1 || true"
fi

# 5. Mail: Proton SMTP token for academy@coralpetal.ai (repo secrets ACADEMY_SMTP_USER / ACADEMY_SMTP_TOKEN).
#    Sent over stdin into ~/.coralpetal/academy-smtp.php (600, outside every docroot); never in argv or git.
if [ -n "${ACADEMY_SMTP_USER:-}" ] && [ -n "${ACADEMY_SMTP_TOKEN:-}" ]; then
  printf '%s\n%s\n' "$ACADEMY_SMTP_USER" "$ACADEMY_SMTP_TOKEN" | $SSH "$REMOTE" "php '$CONTENT/smtp_store.php'"
else
  echo "smtp: secrets not set, Academy mail uses server PHP mail (From is still academy@coralpetal.ai)"
fi

# 6. Review account for the logged-in dashboard check (dev only): fresh random password each run,
#    sent over stdin; dev.yml logs in with it, saves the dashboard HTML, then rotates it again.
if [ -n "${REVIEW_PASS:-}" ]; then
  printf '%s' "$REVIEW_PASS" | $SSH "$REMOTE" "cd '$WP' && wp --skip-plugins=hostinger-ai-assistant,hostinger-reach eval-file '$CONTENT/review_user.php'"
fi

