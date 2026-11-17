# Changelog

All notable changes to coralpetal.ai, academy and careers. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions: [SemVer](https://semver.org/).

## [Unreleased]

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
