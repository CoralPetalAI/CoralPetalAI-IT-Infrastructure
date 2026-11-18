#!/usr/bin/env bash
# Point every prod link at its dev twin inside a DEV build (never run on prod output).
#   scripts/dev_links.sh <dir>
# coralpetal.ai / www -> dev.coralpetal.ai, academy. -> dev-academy., careers. -> dev-careers.
# Only .html/.php/.json files; .htaccess is left alone (its redirects are host-relative).
# Email addresses (...@coralpetal.ai) are untouched: only https:// URLs are rewritten.
set -euo pipefail
DIR="$1"
[ -d "$DIR" ] || { echo "no dir $DIR" >&2; exit 2; }
find "$DIR" -type f \( -name '*.html' -o -name '*.php' -o -name '*.json' \) -print0 \
  | xargs -0 perl -pi -e 's#https://academy\.coralpetal\.ai#https://dev-academy.coralpetal.ai#g; s#https://careers\.coralpetal\.ai#https://dev-careers.coralpetal.ai#g; s#https://(www\.)?coralpetal\.ai#https://dev.coralpetal.ai#g'
echo "dev links rewritten in $DIR"
