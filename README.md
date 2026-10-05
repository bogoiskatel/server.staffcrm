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

The launcher also starts the isolated test server on port 8091 with the separate `server_test` database. This is a development-only service; both HTTP listeners bind to loopback. To stop them without removing data:

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

`installations.uuid` is the immutable identifier. `statistics_snapshots` has one row per installation/reporting month. Monthly baptism counts have a separate nullable `baptized_this_month` field; annual counts are never silently converted into monthly counts. Counts, login timestamps and locations can be unknown. NULL does not mean zero. Timestamps are stored and displayed in UTC, while upstream reporting-period semantics must be agreed before activating a collector.

Only active installations are counted on the network page. Missing snapshots stay missing; the dashboard never mixes months. Detail pages preserve historical snapshots. API secrets/contact ciphertext have storage fields but are not selected into registry views or map JSON.

Coordinate priority: valid current church coordinates, then a previously validated cached postal-code point. Latitude/longitude equal to zero are valid. Absent or invalid pairs cannot create fabricated markers. Postal-code points are marked approximate. No public geocoding service is called in this release.

Tiles are configurable. The default browser layer uses standard OpenStreetMap tiles with visible attribution, normal browser caching and Referer, without prefetch or bulk download. Check [OSM tile policy](https://operations.osmfoundation.org/policies/tiles/) before production deployment; change the provider through `tile_url` and `tile_attribution` if needed.

## Verification

```sh
/opt/homebrew/opt/php@7.4/bin/php tests/run.php
/opt/homebrew/opt/php@7.4/bin/php tests/database.php
python3 tests/http_acceptance.py
```

Database tests reset ONLY the explicitly named `server_test` database and load synthetic fixtures. The HTTP test authenticates on port 8091, never production. Before rerunning HTTP tests, run database tests to reset rate-limit state. Test data does not enter `server`.

Vendored libraries and their licenses live in `public/assets/vendor/`. The theme-specific CSS is newly written; the original CRM's business logic, Telegram auth and private config were not copied.
