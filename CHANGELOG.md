# Changelog

All notable changes to coralpetal.ai, academy and careers. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions: [SemVer](https://semver.org/).

## [Unreleased]

### Added
- Insights No. 001, "The Decryption Gap" (/insights/the-decryption-gap/): research brief on encrypted and confidential AI inference with a ciphertext hero, trust-ladder figure, confidential-inference diagram, two charts with data tables, provider table, timeline and 31 references. New stylesheet `css/insights.css`.
- Insights newsletter sign-up (double opt-in) on /insights/ and every article: `site/api/subscribe.php` stores pending/confirmed/unsubscribed subscribers in the admin DB (`newsletter_subscribers`) and emails a confirmation link through Proton SMTP; honeypot, fill-time, 5/hour rate limit, 10-minute resend guard, unconfirmed rows deleted after 30 days.
- Page generator: optional `css:` (extra stylesheets) and `og_type:` metadata per page.
- Contact form on /contact/ → admin DB (Admin → Inbox) + email to the topic's team address via Proton SMTP; honeypot, fill-time and 5/hour rate limit.
- First-party, cookieless page counter (`site/api/hit.php`) on main site, Careers and Academy (theme 1.23.0); bots, DNT and GPC skipped; no IP or cookies stored.
- Academy: Tutor LMS BunnyNet Integration add-on (free, Themeum) installed and BunnyNet offered as a lesson video source, for paid lesson videos on Bunny Stream.
- Admin 1.4.0: Inbox, Applications (careers, status workflow, resume/cover PDFs streamed from private Azure storage with a read-only key), Traffic (views, visitors, top pages, sources); website tiles on the dashboard.

### Changed
- Insights article layout v2 (Rohit: "better design and graphics"): cover with the artwork beside the title, "by the numbers" strip, sticky contents rail with reading progress and share buttons, icon-led takeaways and section markers, swimlane timeline, provider status pills, visual decision guide, dark bottom-line card, author box and "Coming up" tiles.
- /insights/: the first published brief is featured, the mailto band is replaced by the newsletter sign-up, and the planned topics move under "Coming up".
- Privacy policy: newsletter data, purpose and retention added (last updated November 21, 2026).

## [Release 1.0003] - 2026-11-18

### Changed
- Admin 1.3.0: Users page only for the main account; new users are plain admins.
- Admin: no personal names except the dashboard greeting (task owner, updated-by, header name removed).
- Admin: account menu under the user's name (Profile, Change password, Settings with time zone); Log out separate.

## [Release 1.0002] - 2026-11-18

### Changed
- Services, Team enablement: links to the Academy home ("Visit Coral Petal AI Academy").
- Admin 1.1.0: branded sign-in card, compact set-password box, live password-match check.
- Academy (theme 1.22.0): live password-match check on sign-up and password reset.

## [Release 1.0001] - 2026-11-18

First production release of the redesigned Coral Petal AI sites (develop → main).

### Added
- WordPress + Tutor LMS Academy on academy.coralpetal.ai (replaces the static Academy; old files moved to ~/retired on the server).
- Careers site rebuilt: home, /openings/, structured Video Editor role, online apply form (MySQL + private Azure Blob, separate prod/non-prod).
- Core vision page; What we do (About) redesign; coral-card template; branded Academy emails and sign-in popups.

### Changed
- Navigation: About (What we do, Core vision, Industries), Services (+ Overview), Careers → careers.coralpetal.ai.
- Soft-coral button template (#FFB4A9) on all sites; calmer type scale; footers aligned across sites.



### Changed
- Visual pass, light only: product-style graphics in every page hero (engagement, use-case matrix, agent flow, monitoring, course progress, sector hub, roles, composer), soft icon tiles, shorter copy; dark vision band and CTA boxes replaced with light ones on all sites incl. the WordPress Academy (theme 0.5.0).
- tana.inc theme rolled out to Careers, the static Academy and the WordPress Academy theme (v0.4.0): near-white, ink, Newsreader serif headings, glass nav island, ink buttons, dark CTA, coral accent.
- Home page made bolder and more visual: dark vision band with big coral stats, coloured service icons, delivery-flow diagram, sector buttons; less body text, higher contrast.
- Main site restyled as an editorial light theme in the tana.inc style: near-white #FBFBFB, ink #202020, Newsreader serif headlines, floating glass nav island, ink buttons, coral accent; nav with chevron dropdowns (Services, Industries, Academy, Company).
- Headings resized to the tana.inc scale (hero 52px, sections 40px); home hero gets a product-style agent mockup and a trust strip.
- Learn page: Signature Course module grid removed (module details live on the course page).

### Added
- Full main-site build: vision, services, industries, learn (Signature Course), about, insights, join-us, contact; pages generated from `site-data/` by `scripts/build_site_pages.py`.
- Dev environment: `develop` branch auto-deploys to dev.coralpetal.ai and dev-careers.coralpetal.ai; dev-academy.coralpetal.ai runs WordPress + Tutor LMS; all three password-protected and noindex.
- Standard repo structure: CONTRIBUTING, CHANGELOG, PR template, `.editorconfig`, branch model.

### Changed
- PR checks now also run on pull requests into `develop`.

## [1.0.0] - 2026-11-17

Baseline: the production release live on 2026-11-17 (PR #41, signature navy + coral theme, sites live again after PR #40). Earlier history is in the git log.
