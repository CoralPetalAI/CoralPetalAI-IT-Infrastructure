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
[ "$(w option get permalink_structure)" = "/%postname%/" ] || w rewrite structure "/%postname%/"

# --- The course --------------------------------------------------------------------------
TITLE="The Coral Petal Signature Course: End-to-end Generative and Agentic AI"
CID=$(w post list --post_type=courses --name=signature-course --post_status=any --format=ids)
if [ -z "$CID" ]; then
  CID=$(w post create "$C/course.html" --post_type=courses --post_status=publish --post_title="$TITLE" --post_name=signature-course --porcelain)
  echo "course created: $CID"
else
  w post update "$CID" "$C/course.html" --post_title="$TITLE" --post_status=publish
  echo "course updated: $CID"
fi
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
