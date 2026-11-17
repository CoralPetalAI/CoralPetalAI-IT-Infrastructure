#!/usr/bin/env bash
# Runs ON the Hostinger server (via scripts/academy_wp_dev.sh), inside the dev-academy
# WordPress folder. Idempotent: sets up the brand theme, Tutor LMS and the ONE course
# (The Coral Petal Signature Course, 8 modules = Tutor topics) and the Academy home page.
# Creates or updates only; it never removes WordPress content.
set -euo pipefail
WP="$1"; C="$2"
[[ "$WP" =~ ^/home/u[0-9]+/domains/coralpetal\.ai/public_html/dev-academy$ ]] || { echo "bad WP path $WP" >&2; exit 3; }
cd "$WP"
w() { wp --skip-plugins=hostinger-ai-assistant,hostinger-reach "$@"; }

w theme is-installed twentytwentyfive || w theme install twentytwentyfive
w theme activate coralpetal-academy
w plugin is-active tutor || w plugin activate tutor

w option update blogname "Coral Petal AI Academy"
w option update blogdescription "End-to-end Generative and Agentic AI"
w option update blog_public 0
w option update timezone_string "Asia/Kolkata"
w option update users_can_register 1   # Tutor sign-up needs "Anyone can register"
w option update default_role subscriber
[ "$(w option get permalink_structure)" = "/%postname%/" ] || w rewrite structure "/%postname%/"

# --- The course --------------------------------------------------------------------------
TITLE="The Coral Petal Signature Course: End-to-end Generative and Agentic AI"
# URL slug gen-agentic-ai (Rohit 2026-11-18; was signature-course: WordPress keeps the old slug and redirects it).
SLUG=gen-agentic-ai
CID=$(w post list --post_type=courses --name="$SLUG" --post_status=any --format=ids)
[ -n "$CID" ] || CID=$(w post list --post_type=courses --name=signature-course --post_status=any --format=ids)
if [ -z "$CID" ]; then
  CID=$(w post create "$C/course.html" --post_type=courses --post_status=publish --post_title="$TITLE" --post_name="$SLUG" --porcelain)
  echo "course created: $CID"
else
  w post update "$CID" "$C/course.html" --post_title="$TITLE" --post_name="$SLUG" --post_excerpt="From AI foundations to agents running in production: 8 modules, hands-on builds and an enterprise capstone." --post_status=publish
  echo "course updated: $CID"
fi
w post term remove "$CID" course-category uncategorized 2>/dev/null || true
w post meta update "$CID" _tutor_course_level all_levels
w post meta update "$CID" _tutor_course_price_type free
w post meta update "$CID" _tutor_course_benefits "$(printf '%s\n' \
  'Build full-stack Agentic AI systems, from data to deployed agents' \
  'Ground models in enterprise data with retrieval' \
  'Evaluate, monitor and govern agents safely' \
  'Ship agents on the cloud platforms enterprises use')"
w post meta update "$CID" _tutor_course_target_audience "$(printf '%s\n' \
  'Engineers and data scientists moving into Generative and Agentic AI' \
  'Teams that want to build AI systems, not just use them')"

# Course thumbnail (video preview / course cards). Source: Figma "Coral Petal Academy"
# A0CUypsHbIod38rh1G1C29, node 1:2. Re-imported only when the file's checksum changes.
THUMB="$C/course-thumbnail.png"
if [ -f "$THUMB" ]; then
  SUM=$(md5sum "$THUMB" | cut -c1-12)
  if [ "$(w post meta get "$CID" _cp_thumb_md5 2>/dev/null || true)" != "$SUM" ]; then
    cp "$THUMB" "$C/signature-course-thumbnail-$SUM.png"
    AID=$(w media import "$C/signature-course-thumbnail-$SUM.png" --post_id="$CID" --title="The Coral Petal Signature Course" --alt="The Coral Petal Signature Course: End-to-end Generative and Agentic AI" --featured_image --porcelain)
    w post meta update "$CID" _cp_thumb_md5 "$SUM"
    echo "thumbnail set: attachment $AID"
  else
    echo "thumbnail ok"
  fi
fi

# --- Course catalog: fundamentals (Rohit 2026-11-18: the Academy is more than one course) ----
# content/catalog/catalog.tsv: order  slug  title  excerpt  thumbnail.png ; body in catalog/<slug>.html
w post update "$CID" --menu_order=0 >/dev/null
while IFS=$'\t' read -r ord slug title excerpt img; do
  [ -n "$slug" ] || continue
  body="$C/catalog/$slug.html"
  FID=$(w post list --post_type=courses --name="$slug" --post_status=any --format=ids)
  if [ -z "$FID" ]; then
    FID=$(w post create "$body" --post_type=courses --post_status=publish --post_name="$slug" --post_title="$title" --post_excerpt="$excerpt" --menu_order="$ord" --porcelain)
    echo "catalog course created: $title ($FID)"
  else
    w post update "$FID" "$body" --post_title="$title" --post_excerpt="$excerpt" --menu_order="$ord" --post_status=publish >/dev/null
    echo "catalog course updated: $title ($FID)"
  fi
  w post term remove "$FID" course-category uncategorized 2>/dev/null || true
  w post meta update "$FID" _tutor_course_level beginner
  w post meta update "$FID" _tutor_course_price_type free
  T="$C/catalog/$img"
  if [ -f "$T" ]; then
    SUM=$(md5sum "$T" | cut -c1-12)
    if [ "$(w post meta get "$FID" _cp_thumb_md5 2>/dev/null || true)" != "$SUM" ]; then
      cp "$T" "$C/$slug-thumbnail-$SUM.png"
      w media import "$C/$slug-thumbnail-$SUM.png" --post_id="$FID" --title="$title" --alt="$title" --featured_image --porcelain >/dev/null
      w post meta update "$FID" _cp_thumb_md5 "$SUM"
      echo "thumbnail set: $slug"
    fi
  fi
done < "$C/catalog/catalog.tsv"

# --- Modules = Tutor topics --------------------------------------------------------------
while IFS=$'\t' read -r n title summary; do
  [ -n "$n" ] || continue
  slug="cp-module-$(printf '%02d' "$n")"
  TID=$(w post list --post_type=topics --name="$slug" --post_status=any --format=ids)
  if [ -z "$TID" ]; then
    TID=$(w post create --post_type=topics --post_status=publish --post_parent="$CID" --menu_order="$n" \
      --post_name="$slug" --post_title="$title" --post_content="$summary" --porcelain)
    echo "topic created: $title ($TID)"
  else
    w post update "$TID" --post_parent="$CID" --menu_order="$n" --post_title="$title" --post_content="$summary" >/dev/null
    echo "topic updated: $title ($TID)"
  fi
done < "$C/modules.tsv"

# --- Pages ---------------------------------------------------------------------------------
page() { # slug title content-file-or-text
  local id; id=$(w post list --post_type=page --name="$1" --post_status=any --format=ids)
  if [ -z "$id" ]; then
    if [ -f "$3" ]; then id=$(w post create "$3" --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --porcelain)
    else id=$(w post create --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --post_content="$3" --porcelain); fi
  else
    if [ -f "$3" ]; then w post update "$id" "$3" --post_title="$2" --post_status=publish >/dev/null
    else w post update "$id" --post_title="$2" --post_content="$3" --post_status=publish >/dev/null; fi
  fi
  echo "$id"
}
HOME_ID=$(page academy-home "Coral Petal AI Academy" "$C/home.html")
DASH_ID=$(page dashboard "Dashboard" "[tutor_dashboard]")
REG_ID=$(page student-registration "Student registration" "[tutor_student_registration_form]")
w option update show_on_front page
w option update page_on_front "$HOME_ID"
echo "home=$HOME_ID dashboard=$DASH_ID registration=$REG_ID"

# Tutor settings (only when Tutor has created its option array)
if w option get tutor_option >/dev/null 2>&1; then
  for kv in "tutor_dashboard_page_id $DASH_ID" "student_register_page $REG_ID"; do
    set -- $kv
    w option patch update tutor_option "$1" "$2" 2>/dev/null || w option patch insert tutor_option "$1" "$2" || true
  done
fi

w rewrite flush
w cache flush || true
echo "dev-academy setup done"
