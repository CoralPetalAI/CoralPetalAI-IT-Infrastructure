# coralpetal.ai

Websites of **Coral Petal AI**: enterprise Agentic AI consultancy and Academy.
Plain static HTML/CSS, hosted on **Hostinger**. How to contribute: [CONTRIBUTING.md](CONTRIBUTING.md).
Release history: [CHANGELOG.md](CHANGELOG.md).

## Environments

| # | Environment | Main site | Academy | Careers | Deploys from | Gate |
|---|---|---|---|---|---|---|
| 1 | Local | `scripts/preview.sh` → localhost:8000 | `scripts/preview.sh academy` | `scripts/preview.sh careers` | your branch | none |
| 2 | Dev | dev.coralpetal.ai | dev-academy.coralpetal.ai (WordPress + Tutor LMS) | dev-careers.coralpetal.ai | `develop` (automatic) | password + noindex |
| 3 | Prod | coralpetal.ai | academy.coralpetal.ai | careers.coralpetal.ai | `main` | Rohit's approval |

A **staging** slot is added when the hosting plan moves to Business (planned at launch).

## Branches

| # | Branch | Purpose |
|---|---|---|
| 1 | `main` | Production. Protected: pull requests only, Site checks must pass |
| 2 | `develop` | Integration + Dev environment. Protected the same way |
| 3 | `feature/<area>-<topic>` | New work, branched from `develop` (areas: `site`, `academy`, `careers`, `infra`, `docs`) |
| 4 | `hotfix/<topic>` | Urgent prod fix, branched from `main`, merged to `main` then back into `develop` |
| 5 | `archive/*` (tags) | Old branches kept as tags so the branch list stays short |

## How a change ships

1. Branch `feature/...` from `develop`; review locally with `scripts/preview.sh`.
2. PR into `develop` → **Site checks** → merge → **Dev deploy** puts it on the dev sites.
3. Review on dev (login in Proton Pass, vault "Coral Petal").
4. Release PR `develop` → `main`, titled `Release vX.Y.Z`, with the CHANGELOG entry.
5. Merge → Production jobs **wait for Rohit's approval** → deploy → each job confirms the live
   `version.json` shows the new commit, else it fails. Tag the merge commit `vX.Y.Z`.

## Workflows

| # | Workflow | Runs on | Does |
|---|---|---|---|
| 1 | `deploy.yml` (Build & deploy) | PRs into `main`/`develop`; push to `main` | All checks; deploys the main site to prod |
| 2 | `academy.yml` | push to `main` touching `academy/` | Deploys the static academy to prod |
| 3 | `careers.yml` | push to `main` touching `careers/` | Deploys careers to prod |
| 4 | `dev.yml` (Dev deploy) | push to `develop` | Deploys site + careers to dev, keeps all 3 dev sites password-protected |

## Hosting and deploy

- Server folder: `~/domains/coralpetal.ai/public_html` (account `u574260001`, SSH port 65002).
- Every deploy script computes its own target and **refuses any other path**: `litandlatte.com`
  lives on the same hosting account.
- Subdomains are hPanel folders inside that docroot: `academy`, `careers`, `dev`, `dev-academy`,
  `dev-careers` (and the retired `career/`). The prod deploy leaves them alone via
  `SUBDOMAIN_FOLDERS` in `scripts/deploy_hostinger.sh`: **add every new subdomain there** before
  the next release, or the prod mirror step removes its folder.
- Dev login: repo secrets `DEV_AUTH_USER` / `DEV_AUTH_PASS`; the password file is written to
  `~/.htpasswds/coralpetal-dev` (outside every docroot) by `scripts/dev_auth_setup.sh`.
- Secrets/variables: `HOSTINGER_SSH_KEY` (deploy key), `HOSTINGER_HOST`, `HOSTINGER_PORT`,
  `HOSTINGER_USER`, `HOSTINGER_KNOWN_HOSTS` (pinned server fingerprints), `DEV_AUTH_USER`, `DEV_AUTH_PASS`.
- HTTPS: Hostinger's free SSL, forced in hPanel. `.htaccess` sends www → apex and serves `404.html`.
- Server-side removals never happen: replaced content is moved to `~/retired/<name>-<date>/`.

## Academy (WordPress)

The new Academy is WordPress + Tutor LMS. Only our own code (child theme, small plugins,
config) is versioned, in the separate repo `CoralPetalAI/coralpetal-academy`; WordPress core,
third-party plugins, uploads and the database are not. Content moves dev → prod by backup/export.
The static `academy/` folder here stays live until the WordPress Academy replaces it.

## Checks

`scripts/check_site.py`: HTML structure, `<title>` and meta description, image alt text,
broken internal links and in-page anchors. Runs on every PR and before every deploy.

## History

- 2026-10-09: first Dev/UAT environments retired the same day in favour of local review
  (server folders moved to `~/retired/coralpetal.ai-2026-10-09/`).
- 2026-11-17: Dev environment re-introduced as above (`develop` branch, three dev subdomains).
