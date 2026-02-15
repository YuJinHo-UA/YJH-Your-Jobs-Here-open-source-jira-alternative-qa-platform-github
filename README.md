# YJH - Quality Management Platform

YJH is an integrated QA platform that combines bug tracking, test management, wiki knowledge base, kanban, and release risk analytics in one lightweight PHP + SQLite stack.

## Why YJH
- One tool instead of Jira + TestRail + Confluence split.
- Fast local deployment (no heavy infra required).
- Built-in traceability: `test case -> execution -> bug -> commit`.
- Product-level QA features: Risk Engine, checklist-driven testing, auto bug creation, wiki versioning.

## Core Modules
- Bugs: `bugs.php`, `bug.php`, `api/bugs.php`
- Test Management: `testplans.php`, `testplan.php`, `testcase.php`, `testruns.php`, `testrun.php`
- Wiki: `wiki.php`, `wiki-page.php`, `api/wiki.php`
- Kanban: `kanban.php`, `api/kanban.php`
- Analytics: `index.php`, `reports.php`, `assets/js/charts.js`
- Global Search: `api/search.php`, `assets/js/search.js`

## Tech Stack
- Backend: PHP (procedural MVC-style pages + API endpoints)
- Database: SQLite (`database.sqlite`)
- UI: Bootstrap 5 + Chart.js + custom JS
- Security: CSRF protection + server-side escaping helpers

## Documentation
- Architecture: `docs/ARCHITECTURE.md`
- Database: `docs/DATABASE.md`
- API: `docs/API.md`
- Quick Start: `docs/QUICKSTART.md`
- Install: `docs/INSTALL.md`
- User Guide: `docs/USER_GUIDE.md`
- Features: `docs/FEATURES.md`
- Comparison: `docs/COMPARISON.md`
- Roadmap: `docs/ROADMAP.md`
- Why YJH: `docs/WHY_YJH.md`
- Visualizations: `docs/VISUALIZATIONS.md`
- Engineering Notes: `docs/ENGINEERING_NOTES.md`

## Quick Start
See `docs/QUICKSTART.md`.
