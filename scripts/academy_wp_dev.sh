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

rsync -rlz --checksum --delete --chmod=D755,F644 -e "$SSH" academy-wp/theme/coralpetal-academy/ "$REMOTE:$THEME/"
rsync -rlz --checksum --chmod=D755,F644 -e "$SSH" academy-wp/mu-plugins/ "$REMOTE:$MU/"
rsync -rlz --checksum --chmod=D755,F644 -e "$SSH" academy-wp/content/ academy-wp/remote_setup.sh "$REMOTE:$CONTENT/"

$SSH "$REMOTE" "bash '$CONTENT/remote_setup.sh' '$WP' '$CONTENT'"
