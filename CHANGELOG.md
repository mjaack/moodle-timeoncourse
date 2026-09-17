# Changelog

## 1.0.0 — 2026-09-17

First release.

- Per-course and site-wide learner time reports from the standard log.
- Configurable session rule: session gap, minimum session, single-click credit.
- Date-range filtering.
- CSV export carrying the session rule, the generating user and the timestamp.
- Excludes `cli`, `cron` and `restore` activity; includes mobile app activity.
- Built and tested against Moodle 5.2.3 (PHP 8.3, PostgreSQL 16); declared
  supported from Moodle 4.5 LTS with no version ceiling.
