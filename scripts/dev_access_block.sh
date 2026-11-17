#!/usr/bin/env bash
# Print the DEV ACCESS .htaccess block (password + noindex) used on every dev site.
# Needs env HOSTINGER_USER. The password file lives OUTSIDE every docroot and is written
# by scripts/dev_auth_setup.sh from the DEV_AUTH_USER / DEV_AUTH_PASS repo secrets.
# /.well-known/ stays open so Hostinger can renew the SSL certificates.
set -euo pipefail
: "${HOSTINGER_USER:?HOSTINGER_USER is required}"
cat <<BLOCK
# BEGIN DEV ACCESS (dev sites only, never prod; managed by scripts/dev_access_block.sh)
SetEnvIf Request_URI "^/\.well-known/" CP_WELLKNOWN
AuthType Basic
AuthName "Coral Petal DEV"
AuthUserFile /home/${HOSTINGER_USER}/.htpasswds/coralpetal-dev
<RequireAny>
  Require env CP_WELLKNOWN
  Require valid-user
</RequireAny>
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>
# END DEV ACCESS
BLOCK
