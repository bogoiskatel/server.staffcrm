# STAFF SERVER

Independent PHP 7.4 admin for the STAFF CRM network. Uses a MySQL-compatible database; the local environment runs MariaDB in a dedicated private socket instance.

## Included

- Username/password login, password hashes, CSRF, persistent login rate limit, session rotation and timeout, POST logout.
- Network totals for one explicit reporting period, coverage, Leaflet map, missing-location list.
- Searchable and sortable DataTable of installations, detail pages and snapshot history.
- Ukrainian and English interfaces; Bootstrap visual conventions from STAFF CRM.
- Registry, snapshots, collection attempts, encrypted-credential storage fields and geocoding cache schema.

The application initially displays an empty registry. Real CRM registration, encrypted contact handling, endpoint ownership proof, polling, and provider-backed postal-code geocoding are the next integration stage. This release does not claim to collect live CRM data or decrypt contacts. No public registration endpoint is exposed.

## Local macOS launch

Requires existing `/opt/homebrew/opt/php@7.4` and `/opt/homebrew/opt/mariadb`. There is no need for Composer or Node.

```sh
python3 local/start.py
```

Open http://127.0.0.1:8090. Generated local credentials are in `outputs/local-access.txt`; the ignored `config.php` stores the username and password hash as variables. No default password is committed. Data lives under ignored `work/runtime/mysql`. Existing STAFF CRM services are untouched.

The generated local setup also starts the isolated test server on port 8091 with the separate `server_test` database. If you supply your own config.php and have no local test configuration, only the main service starts. This is a development-only service; both HTTP listeners bind to loopback. To stop them without removing data:

```sh
python3 local/stop.py
```

## Hosting

1. Create a MySQL/MariaDB database `server` with utf8mb4 and a dedicated user.
2. Copy `config.example.php` to `config.php`; set `$db_dsn`, `$db_username`, `$db_password`, `$admin_username` and `$admin_password_hash`.
3. Generate the password hash with `php cli/password.php`, supplying the password on standard input. Do not put passwords in command arguments or Git.
4. Run `php cli/migrate.php` using credentials that can create the schema. Afterwards the runtime user only needs SELECT, INSERT, UPDATE and DELETE.
5. Configure the web document root to `public/`, PHP 7.4 and HTTPS. Route unknown paths to `public/index.php`. Do not serve the repository root. Keep `local_http=false` and use separate session storage for this service.

HTTPS termination must be communicated through the webserver's trusted `HTTPS=on` configuration; arbitrary forwarded headers are not trusted by this app. Session cookies are Secure outside explicit local mode. PHP 7.4 is retained as requested; this repository does not change the original CRM runtime.

## Data semantics

`installations.uuid` is the immutable identifier. `statistics_snapshots` has one row per installation/reporting month. Monthly baptism counts have a separate nullable `baptized_this_month` field; annual counts are never silently converted into monthly counts. Counts, login timestamps and locations can be unknown. NULL does not mean zero. Timestamps are stored and displayed in UTC. The current CRM API identifies its observed month and calendar year in Europe/Kyiv; the collector retains that month. It captures the current state, not a historical midnight reconstruction.

Only active installations are counted on the network page. Missing snapshots stay missing; the dashboard never mixes months. Detail pages preserve historical snapshots. API secrets/contact ciphertext have storage fields but are not selected into registry views or map JSON.

Coordinate priority: valid current church coordinates, then a previously validated cached postal-code point. Latitude/longitude equal to zero are valid. Absent or invalid pairs cannot create fabricated markers. Postal-code points are marked approximate. No public geocoding service is called in this release.

Tiles are configurable. The default browser layer uses standard OpenStreetMap tiles with visible attribution, normal browser caching and Referer, without prefetch or bulk download. Check [OSM tile policy](https://operations.osmfoundation.org/policies/tiles/) before production deployment; change the provider through `tile_url` and `tile_attribution` if needed.

## Verification

```sh
/opt/homebrew/opt/php@7.4/bin/php tests/run.php
/opt/homebrew/opt/php@7.4/bin/php tests/database.php
/opt/homebrew/opt/php@7.4/bin/php tests/collector.php
/opt/homebrew/opt/php@7.4/bin/php tests/collector_database.php
# Local CRM source integration test (set STAFF_CRM_ROOT for another checkout):
/opt/homebrew/opt/php@7.4/bin/php tests/api_statistics.php
python3 tests/http_acceptance.py
```

All test scripts live in `tests/`. Generated test configuration and credentials live in `tests/.local/`, which is excluded from Git.

Database tests reset ONLY the explicitly named `server_test` database and load synthetic fixtures. The HTTP test authenticates on port 8091, never production. Before rerunning HTTP tests, run database tests to reset rate-limit state. Test data does not enter `server`.

Vendored libraries and their licenses live in `public/assets/vendor/`. The original STAFF CRM style.min.css, its fonts, branding and Bootstrap dropdown script are reused. app.css contains layout adapters. The user menu uses the original CRM dropdown structure and language selector.

## Statistics collection

`php cli/collect.php` runs one collection pass using `statistics_sources` in private config.php. Each source has a pinned `uuid`, HTTPS `url`, and a separate `bearer` credential. Development loopback sources explicitly set `local=true` and `ca_file`. The collector verifies TLS, rejects redirects and private production addresses, bounds response size, validates the v1 contract, and updates one snapshot per installation/month. Older responses cannot overwrite newer counts. Concurrent runs use a database lock. Identity tokens containing phone data are sealed to the private X25519 key configured in `phone_decryption_key`; neither the token nor bearer is exposed in views or logs. The API sends baptized_month separately from baptized_year. Joined-year counts follow the CRM dashboard's arrival categories (1, 2, 4); departures count stored dates for departure categories/statuses. Undated departures retain the known count and an incomplete-data warning. Discipline follows the CRM dashboard's visible discipline types and the latest visible restoration state, rather than making the entire count unavailable because of an unrelated invalid history row.

Local integration setup (CRM demo must already be running):

```sh
python3 local/connect-crm.py
/opt/homebrew/opt/php@7.4/bin/php cli/collect.php
```

The local helper provisions only the mutable working CRM database and its runtime copy, preserving imported clones and product checkout. It starts a certificate-verified loopback TLS proxy on 8766 forwarding the fixed API route to 8765, supplies the API's private config, and issues a statistics-only bearer. Credentials/certificates remain under ignored work/integration with private file permissions. Rerun the helper after restarting or rebuilding the CRM demo. The helper keeps the original UUID and bearer on reruns. No scheduled cron has been installed; use the same absolute CLI path in an operator-selected cron schedule when ready.

phpMyAdmin runs separately on http://127.0.0.1:8092 using PHP 8.2; STAFF SERVER remains PHP 7.4. Its database viewer credentials are in ignored outputs/local-access.txt.
