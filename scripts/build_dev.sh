#!/usr/bin/env bash
# Build a DEV copy of the main site or the careers site.
#   scripts/build_dev.sh site|careers <out_dir>
# Same files as prod, plus: version.json (env dev), robots.txt that blocks all crawlers,
# and a DEV ACCESS block at the top of .htaccess (password + noindex header).
# Needs env HOSTINGER_USER (for the password-file path). <out_dir> must not exist yet.
set -euo pipefail

WHAT="$1"; OUT="$2"
case "$WHAT" in site|careers) ;; *) echo "usage: build_dev.sh site|careers <out_dir>" >&2; exit 2;; esac
[ ! -e "$OUT" ] || { echo "$OUT already exists; pass a new path" >&2; exit 2; }
: "${HOSTINGER_USER:?HOSTINGER_USER is required}"
cp -R "$WHAT" "$OUT"

SHA="${GITHUB_SHA:-local}"
printf '{"env":"dev","site":"%s","commit":"%s","built":"%s"}\n' "$WHAT" "${SHA:0:7}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$OUT/version.json"
printf 'User-agent: *\nDisallow: /\n' > "$OUT/robots.txt"

AUTH="$(bash "$(dirname "$0")/dev_access_block.sh")"
{ printf '%s\n\n' "$AUTH"; cat "$OUT/.htaccess"; } > "$OUT/.htaccess.new"
mv "$OUT/.htaccess.new" "$OUT/.htaccess"
echo "built dev $WHAT → $OUT"
