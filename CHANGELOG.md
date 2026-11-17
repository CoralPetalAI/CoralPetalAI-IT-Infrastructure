# Changelog

All notable changes to coralpetal.ai, academy and careers. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions: [SemVer](https://semver.org/).

## [Unreleased]

### Changed
- Main site restyled as an editorial light theme inspired by tana.inc and anthropic.com: ivory paper, ink text, Newsreader serif headlines, floating glass nav, ink buttons; coral kept as accent.
- Learn page: Signature Course module grid removed (module details live on the course page).

### Added
- Full main-site build: vision, services, industries, learn (Signature Course), about, insights, join-us, contact; pages generated from `site-data/` by `scripts/build_site_pages.py`.
- Dev environment: `develop` branch auto-deploys to dev.coralpetal.ai and dev-careers.coralpetal.ai; dev-academy.coralpetal.ai runs WordPress + Tutor LMS; all three password-protected and noindex.
- Standard repo structure: CONTRIBUTING, CHANGELOG, PR template, `.editorconfig`, branch model.

### Changed
- PR checks now also run on pull requests into `develop`.

## [1.0.0] - 2026-11-17

Baseline: the production release live on 2026-11-17 (PR #41, signature navy + coral theme, sites live again after PR #40). Earlier history is in the git log.
