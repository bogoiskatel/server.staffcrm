# STAFF SERVER Admin Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task by task.

**Goal:** Deliver a working PHP 7.4 and MySQL admin with authentication, network map, statistics table and installation detail.

**Architecture:** One server-rendered application with PDO repositories and isolated public document root. Monthly snapshots are independent of installation metadata. API registration, collector, contact decryption and provider-backed geocoding belong to the subsequent integration stage of the approved spec.

**Tech Stack:** PHP 7.4, MariaDB (MySQL-compatible), Bootstrap CSS inspired by STAFF CRM, DataTables, Leaflet.

**Spec:** outputs/staff-server-admin-design.md

## Global Constraints

- Database `server`; isolated test database `server_test` on a dedicated local MySQL instance.
- No changes to existing STAFF CRM, no real contacts, secrets or dumps committed.
- PHP 7.4 syntax; username and password hash in ignored config.php.
- UA/EN; NULL means unknown; coordinates equal to 0 are valid.
- Code published in a feature branch/PR; no merge without authorization.

## Review Focus

- Incomplete periods: aggregation never mixes months or treats missing values as zero.
- Stored untrusted names: HTML and map popups cannot execute script.
- Unauthenticated requests: pages and map JSON require login.
- Zero and malformed coordinates: valid zero creates a point, invalid pair does not.
- Login abuse: persistent rate limit, CSRF, session rotation and expiry.

## Task 1 Storage and statistical semantics

Files: database/schema.sql, app/Statistics.php, app/Repository.php, cli/migrate.php, tests/run.php.
Interfaces: Statistics::aggregate(array $rows, int $expected): array; Statistics::coordinates(array $installation): ?array; Repository::installations(?string $period): array; Repository::periods(): array; Repository::detail(string $uuid): ?array.

- [ ] Write tests for NULL, zero, coverage, coordinate validation, period validation and MySQL uniqueness; run and observe missing implementation failures.
- [ ] Implement schema, validators, repository and CLI operations.
- [ ] Run PHP tests and isolated MySQL tests; verify uniqueness preserves existing snapshots.
- [ ] Commit tested storage deliverable.

## Task 2 Auth and server pages

Files: config.example.php, app/bootstrap.php, app/Auth.php, app/View.php, app/i18n.php, public/index.php, views/login.php, views/dashboard.php, views/detail.php, cli/password.php, local/start.py, local/stop.py.
Interfaces: Auth accepts PDO + config, verifies login using password_verify, issues and checks CSRF; uses Repository from task 1. Routes: login, dashboard, installation, map, logout.

- [ ] Write HTTP acceptance tests for redirects, CSRF, correct/wrong login, session expiry, rate limit, escaping and logout; observe missing application failures.
- [ ] Implement configuration, auth, protected routes and UA/EN templates; restrict serving to public.
- [ ] Run automated HTTP suite on isolated database and lint all PHP with PHP 7.4.
- [ ] Commit tested application deliverable.

## Task 3 Frontend and delivery

Files: public/assets/app.css, public/assets/app.js, public/assets/vendor/, README.md, tests/http_acceptance.py.
Interfaces: map JSON provides only validated points and safe detail links; app.js initializes Leaflet and DataTables and responds to tab visibility.

- [ ] Verify missing frontend behavior in browser against the functional acceptance script.
- [ ] Add styles, responsive cards/tabs/table/map, local vendor files and licenses.
- [ ] Inspect empty state, synthetic test dataset, search/sort, marker popup, detail page and mobile layout in browser; remove synthetic data from production database.
- [ ] Run full suite, request independent review, fix important findings, commit and publish branch/PR if GitHub authorization permits.

Execution authorization: user explicitly approved the spec and instructed implementation in this chat. Native inline execution preserves that direction; no additional approval gate for routine implementation choices.
