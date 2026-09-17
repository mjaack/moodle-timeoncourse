# Time on Course (report_timeoncourse)

Learner time evidence reports for Moodle — from Moodle's own standard log,
with the method printed on every export.

Answers the question a funder, an accreditor or a line manager actually asks:
*how long did each learner spend in this course, and how do you know?*

## What it reports

Per learner, for one course or for every course on the site:

| column | meaning |
|---|---|
| Time credited | total seconds credited by the session rule below |
| Sessions | how many separate sittings that came from |
| Clicks | logged events inside those sittings |
| First / last activity | the boundaries of the period |
| Longest session | the single longest sitting |

Any date range. CSV export carries the session rule used, who generated it and
when, so the number can be defended rather than merely quoted.

## How time is credited

1. Clicks closer together than the **session gap** (default 1 hour) belong to
   one session.
2. A session is credited the span from its first click to its last.
3. Sessions shorter than the **minimum session** (default 1 minute) are
   discarded.
4. A session containing exactly one click is credited **single-click credit**
   seconds (default 0 — the conservative choice).
5. Activity originating from `cli`, `cron` and `restore` is excluded. Mobile
   app activity (`ws`) is included.

All four settings live in *Site administration → Plugins → Reports → Time on
course*, and all four are restated on every export.

## Requirements

- Moodle 4.5 or later (built and tested against **Moodle 5.2** on PostgreSQL 16).
- The standard log store enabled — it is, on a default site.

No version ceiling is declared, so the plugin will not lock itself out of a
future Moodle release.

## Install

Site administration → Plugins → Install plugins → upload the ZIP, or drop the
folder at `report/timeoncourse` (`public/report/timeoncourse` on Moodle 5.x)
and visit the notifications page.

## Free and paid

| | free | licensed |
|---|---|---|
| Per-course report | ✅ | ✅ |
| Date ranges | ✅ | ✅ |
| Site-wide report across every course | — | ✅ |
| CSV export with audit note | — | ✅ |

A site licence is **$59/year**: <https://edgethirteen.com/tools/time-on-course>

Paste the key into *Site administration → Plugins → Reports → Time on course*.
The only outbound call the plugin ever makes is that licence check, carrying
the key, the site URL and the plugin name — never learner or log data. If the
check cannot be made the last good answer is honoured for 14 days, so a site
that loses outbound network does not lose its reports mid-audit.

## Privacy

The plugin stores no personal data of its own; it reads the standard log and
renders it. It ships a `null_provider` saying exactly that.

## Capabilities

- `report/timeoncourse:view` — per-course report (editing teacher, manager).
- `report/timeoncourse:viewall` — site-wide report (manager).

## Development

```bash
# Moodle coding standard
vendor/bin/phpcs --standard=moodle-extra report/timeoncourse

# Unit tests, from the Moodle root
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite report_timeoncourse_testsuite
```

23 tests, 50 assertions, green against Moodle 5.2.3 / PHP 8.3 / PostgreSQL 16.

## Licence

GPL v3 or later. See `LICENSE`.
