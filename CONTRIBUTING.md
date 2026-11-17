# Contributing

## Flow

1. `git switch develop && git pull`, then `git switch -c feature/<area>-<topic>`
   (areas: `site`, `academy`, `careers`, `infra`, `docs`). Urgent prod fix: `hotfix/<topic>` from `main`.
2. Keep each branch to one change. Review locally: `scripts/preview.sh` (or `academy` / `careers`).
3. Open a PR into `develop` using the template. Site checks must pass. Merge with a merge commit.
4. Check the change on the dev sites (they redeploy automatically).
5. Release: PR `develop` → `main` titled `Release vX.Y.Z`; move the CHANGELOG "Unreleased" items
   under the new version in the same PR. After the prod deploys pass, tag the merge commit
   `vX.Y.Z` and push the tag.
6. A hotfix merged to `main` is merged back into `develop` straight away.

## Versions

Semantic versions: MAJOR for a site restructure or new platform, MINOR for new pages/features,
PATCH for fixes and copy changes.

## Commits

Imperative subject under 72 characters (`Add Signature Course page`), body says why.

## Rules that never change

- Generated pages: edit the templates (`site-data/`, `academy-data/templates/`) and rebuild,
  never the generated HTML.
- Never commit secrets. Credentials live in Proton Pass (vault "Coral Petal") and GitHub secrets.
- No founder name, no invented clients, stats or testimonials. Contact = `contact@coralpetal.ai`.
- Brand: Petal Network logo + "Coral Petal AI" wordmark on every page; signature dark navy
  `#0B1F33` + coral `#FF6F61`, Inter. Use ™, never ®, until registration.
- New subdomain → add it to `SUBDOMAIN_FOLDERS` in `scripts/deploy_hostinger.sh` first.
- Nothing on the server is removed: deploy scripts move replaced content to `~/retired/`.
