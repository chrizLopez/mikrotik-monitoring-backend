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

- `ether1` = Gomo
- `ether2` = Starlink ISP New
- `ether4` = Smart Bro ISP

Group mapping:

- Group A: `Home Router`, `VLAN20 - Camaymayan`, `VLAN30 - Rutor`
- Group B: `VLAN40 - Peleyo`, `VLAN50 - Yamba`, `VLAN60 - Piso WiFi`, `VLAN70 - Olario`
- Group labels are organizational only and do not control WAN routing

Quota policy:

- Default speed: `2M/5M`
- Throttled speed: `512k/2M`
- Monthly quota: `200 GB`
- `GROUP_A_TOTAL` is retired and not part of monitored user tracking

Routing model:

- All monitored users share all three WANs through equal PCC
- Target distribution is `33.33% / 33.33% / 33.33%`
- Connection marks: `conn_gomo`, `conn_starlink`, `conn_smart`
- Routing marks: `to_GOMO`, `to_STARLINK`, `to_SMART`
- Each WAN mark has its own failover chain
- Router-originated traffic prefers Starlink ISP New, then Smart Bro ISP, then Gomo

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
MIKROTIK_PUSH_TOKEN=change-this-shared-secret
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

- `isps`: `Gomo`, `Starlink ISP New`, `Smart Bro ISP`
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

Only the seven live per-subnet queues are treated as monitored users. Legacy queues such as `GROUP_A_TOTAL` are ignored.

## MikroTik Push Ingestion

The backend now supports RouterOS push ingestion in addition to the existing poll-based flow.

Endpoint:

- `POST /api/mikrotik/push`

Authentication:

- query string token: `?token=YOUR_TOKEN`
- or `X-Mikrotik-Token: YOUR_TOKEN`
- token value must match `MIKROTIK_PUSH_TOKEN`
- unauthorized requests return `403` and are logged

Expected JSON payload:

```json
{
  "router_name": "MikroTik",
  "sent_at": "2026-04-05 10:00:00",
  "queues": [
    {
      "name": "Home Router",
      "upload_bytes": 12345,
      "download_bytes": 67890,
      "max_limit": "2M/5M"
    },
    {
      "name": "VLAN40 - Peleyo",
      "upload_bytes": 123,
      "download_bytes": 456,
      "max_limit": "512k/2M"
    }
  ],
  "interfaces": [
    {
      "name": "ether1",
      "rx_bytes": 123456789,
      "tx_bytes": 987654321
    },
    {
      "name": "ether2",
      "rx_bytes": 5555,
      "tx_bytes": 6666
    },
    {
      "name": "ether4",
      "rx_bytes": 7777,
      "tx_bytes": 8888
    }
  ],
  "health": [
    {
      "name": "ether1",
      "ping_target": "1.1.1.1",
      "latency_ms": 24.5,
      "packet_loss_percent": 0,
      "jitter_ms": 3.1,
      "status": "online"
    },
    {
      "name": "ether2",
      "ping_target": "8.8.8.8",
      "latency_ms": 42.0,
      "packet_loss_percent": 2,
      "jitter_ms": 5.6,
      "status": "degraded"
    }
  ]
}
```

Ingestion behavior:

- queue mapping is an exact match against `monitored_users.queue_name`
- interface mapping is an exact match against `isps.interface_name`
- health mapping is an exact match against `isps.interface_name`
- only the seven live monitored queues are ingested
- unknown queues, interfaces, and health interfaces are logged and skipped
- `UserSnapshot.state` is stored as `THROTTLED` when pushed `max_limit` exactly matches `monitored_users.throttled_max_limit`; otherwise `NORMAL`
- `IspSnapshot` rows store cumulative byte counters only; `rx_bps` and `tx_bps` remain `null` for push ingestion
- `IspHealthSnapshot` rows are created from pushed `health[]` items and feed the ISP latency/loss graph

Local-first test URLs:

- `http://192.168.88.25:8000/api/mikrotik/push?token=YOUR_TOKEN`
- `http://127.0.0.1:8000/api/mikrotik/push?token=YOUR_TOKEN`

Production URL:

- `https://dashboard.phsolarsizer.com/api/mikrotik/push?token=YOUR_TOKEN`

Why local-first testing is recommended:

- it confirms RouterOS can reach the Laravel host before DNS, TLS, and firewall variables are introduced
- it lets you verify exact queue and interface names against the seeded mappings
- it reduces deployment risk because the production switch is only a base-URL change once payloads are already working locally

Deployment notes:

- set `MIKROTIK_PUSH_TOKEN` to a strong shared secret in both local and production environments
- if you change `.env`, run `php artisan config:clear`
- expose the backend on a reachable HTTP URL locally first, then switch RouterOS to the production HTTPS endpoint
- this endpoint is read/report-only and does not execute router-changing actions

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
