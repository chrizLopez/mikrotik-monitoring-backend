# MikroTik Monitoring API

Production-ready Laravel 13 backend for a local MikroTik-based mini-ISP monitoring dashboard. The API polls RouterOS interface and queue counters, stores time-series snapshots, aggregates monthly quota usage, and serves authenticated dashboard endpoints for summaries, charts, top users, group totals, and CSV export.

## Overview

This backend monitors:

- ISP throughput per WAN
- Per-user queue usage
- Monthly quota consumption
- Throttled vs normal users
- Group A vs Group B usage
- Time-series graph data for frontend charts

WAN mapping:

- `ether1` = Old Starlink
- `ether2` = New Starlink
- `ether4` = SmartBro

Group mapping:

- Group A: `Home Router`, `VLAN20 - Camaymayan`, `VLAN30 - Rutor`
- Group B: `VLAN40 - Peleyo`, `VLAN50 - Yamba`, `VLAN60 - Piso WiFi`, `VLAN70 - Olario`

Quota policy:

- Default speed: `2M/5M`
- Throttled speed: `512k/2M`
- Monthly quota: `200 GB`
- `GROUP_A_TOTAL` is excluded from per-user tracking

## Architecture

Key backend layers:

- Models and migrations for ISP, queue snapshot, billing cycle, and monthly summary data
- Seeders for WAN metadata, monitored users, and the default Sanctum admin
- `App\Services\Mikrotik\MikrotikClient` implementing the RouterOS API protocol over PHP streams
- `App\Services\Mikrotik\MikrotikPollingService` for polling and snapshot persistence
- `App\Services\UsageAggregationService` for idempotent billing-cycle aggregation with counter-reset handling
- `App\Services\DashboardService` for summary, history, top-user, group, and export queries
- Form Requests for auth/range validation
- API Resources for response shaping
- Artisan commands and Laravel scheduler for background polling

## Required Environment

```env
APP_NAME="MikroTik Monitoring API"
APP_ENV=production
APP_KEY=
APP_URL=http://your-host

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mikrotik_monitoring
DB_USERNAME=root
DB_PASSWORD=

ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me

MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USERNAME=admin
MIKROTIK_PASSWORD=
MIKROTIK_USE_SSL=false
MIKROTIK_TIMEOUT=10

BILLING_CYCLE_DAY=1
BILLING_CYCLE_TIMEZONE=Asia/Manila
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

## Setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan route:list
```

## Migration and Seeding

Run:

```bash
php artisan migrate
php artisan db:seed
```

Seeders create:

- `isps`: `Old Starlink`, `New Starlink`, `SmartBro`
- `monitored_users`: seven real queue-backed users
- `users`: default admin from `ADMIN_*`

`GROUP_A_TOTAL` is not seeded into `monitored_users`.

## Scheduler

Registered schedules:

- `php artisan mikrotik:poll` every minute
- `php artisan usage:aggregate-current-cycle` every 15 minutes

Production cron:

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Artisan Commands

```bash
php artisan mikrotik:poll
php artisan usage:aggregate-current-cycle
```

`mikrotik:poll`:

- connects to MikroTik
- fetches interface stats for `ether1`, `ether2`, `ether4`
- fetches queue stats for the seven monitored queue names
- stores `isp_snapshots` and `user_snapshots`
- computes bps from deltas if RouterOS does not return live bps
- ensures the active billing cycle exists
- refreshes monthly summaries

`usage:aggregate-current-cycle`:

- recomputes current-cycle usage from snapshots
- is idempotent
- treats negative deltas as queue counter resets

## MikroTik Assumptions

- RouterOS API is enabled and reachable from the Laravel host
- The application is read/report-only and does not modify routes or queue limits
- Interface byte counters are available from `/interface/print`
- Current throughput is read from `/interface/monitor-traffic` when available
- Queue counters come from `/queue/simple/print stats`
- Queue byte counters are interpreted as `upload/download`
- Temporary router failures are logged and do not overwrite previous good snapshots

## Queue Mapping

Tracked queue names:

- `Home Router`
- `VLAN20 - Camaymayan`
- `VLAN30 - Rutor`
- `VLAN40 - Peleyo`
- `VLAN50 - Yamba`
- `VLAN60 - Piso WiFi`
- `VLAN70 - Olario`

Queue-to-user mapping is an exact string match between `monitored_users.queue_name` and the RouterOS simple queue `name`.

`GROUP_A_TOTAL` is intentionally excluded because it is a shared cap queue, not an individual quota subject.

## Billing Cycle Assumptions

- Billing cycles are month-based
- `BILLING_CYCLE_DAY` controls the rollover day
- `BILLING_CYCLE_TIMEZONE` controls boundary calculation
- The current billing cycle is created/updated during polling and aggregation
- Monthly summaries are unique per `monitored_user_id` + `billing_cycle_id`

## API Endpoints

Auth:

- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

Dashboard:

- `GET /api/dashboard/summary`
- `GET /api/dashboard/isps`
- `GET /api/dashboard/isps/{isp}/history?range=today|24h|7d|30d|cycle`
- `GET /api/dashboard/users`
- `GET /api/dashboard/users/{monitoredUser}/history?range=today|24h|7d|30d|cycle`
- `GET /api/dashboard/top-users?range=cycle&limit=10`
- `GET /api/dashboard/groups/usage?range=cycle`
- `GET /api/dashboard/export/users.csv?range=cycle`

All dashboard endpoints require a Sanctum bearer token.

## How to Add New Users or ISPs

To add a WAN:

1. Insert a row in `isps`
2. Add its RouterOS interface name to `config/mikrotik.php`
3. Reseed or create snapshots normally through polling

To add a monitored user:

1. Insert a row in `monitored_users`
2. Ensure `queue_name` exactly matches the RouterOS queue name
3. Poll again to start storing snapshots

## Testing

Coverage was added for:

- login/logout/auth
- summary endpoint
- users endpoint
- MikroTik normalization logic
- aggregation logic
- range parsing
- CSV export

Run:

```bash
php artisan test
```

Current local note: this workspace PHP build does not include `pdo_sqlite`, so database-backed tests could not be executed here without either enabling SQLite or pointing the test environment to a MySQL test database.

## Future Improvements

- add explicit route-health snapshots per ISP default route
- add retention/downsampling jobs for long-term storage
- improve non-cycle top-user/export calculations with fully delta-based range aggregation
- add retry/backoff tuning for RouterOS connectivity
- add role separation if more admin personas are needed
