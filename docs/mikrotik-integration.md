# MikroTik Integration Layer

Required env values:

- `MIKROTIK_HOST`
- `MIKROTIK_PORT=8728`
- `MIKROTIK_USERNAME`
- `MIKROTIK_PASSWORD`
- `MIKROTIK_PUSH_TOKEN`
- `MIKROTIK_USE_SSL=false`
- `MIKROTIK_TIMEOUT=5`

How it works:

- `App\Services\Mikrotik\MikrotikClient` wraps `evilfreelancer/routeros-api-php`
- interfaces polled: `ether1`, `ether2`, `ether4`
- queues polled: the seven user queues plus `GROUP_A_TOTAL`
- `GROUP_A_TOTAL` is excluded from `UserSnapshot` persistence because it is an aggregate cap, not a real end user

Bps fallback:

- if RouterOS returns live `rx-bits-per-second` / `tx-bits-per-second`, those are used
- otherwise bps is calculated from the previous snapshot using `delta_bytes * 8 / delta_seconds`
- negative deltas are treated as counter resets and produce `null` bps

Manual run:

```bash
php artisan mikrotik:poll
```

Scheduler wiring:

```php
Schedule::command('mikrotik:poll')->everyMinute()->withoutOverlapping();
```

## Push Endpoint

Push endpoint:

- `POST /api/mikrotik/push`

Token auth:

- query parameter `token`
- or request header `X-Mikrotik-Token`
- both are validated against `MIKROTIK_PUSH_TOKEN`

Supported payload fields:

- `router_name` optional string
- `sent_at` optional date string
- `queues` optional array
- `interfaces` optional array
- `health` optional array
- `destinations` optional array

Queue payload rules:

- exact match against `monitored_users.queue_name`
- `GROUP_A_TOTAL` is skipped
- unknown names are logged and ignored
- `state` becomes `THROTTLED` when pushed `max_limit` matches `monitored_users.throttled_max_limit`

Interface payload rules:

- exact match against `isps.interface_name`
- expected WAN interfaces are `ether1`, `ether2`, `ether4`
- unknown interface names are logged and ignored

Destination payload rules:

- supported categories are `apps`, `sites`, and `games`
- expected fields are `name`, `visits`, `total_bytes`, optional `top_user`, and optional `last_seen_at`
- rows are stored in `destination_snapshots`
- rankings are approximate unless the router script can map flows to DNS names reliably
- the companion DNS-cache script batches up to 500 visible DNS entries per run, 50 entries per push, so schedule it every minute to improve one-off visit capture
- DNS-over-HTTPS, VPN/private DNS, direct-IP traffic, and entries that expire before the next run can still be missed

Health payload rules:

- exact match against `isps.interface_name`
- expected fields are `ping_target`, `latency_ms`, `packet_loss_percent`, `jitter_ms`, and `status`
- supported status values are `online`, `offline`, `degraded`, `unknown`
- rows are stored in `isp_health_snapshots`
- unknown interface names are logged and ignored

Local-first examples:

- `http://192.168.88.25:8000/api/mikrotik/push?token=YOUR_TOKEN`
- `http://127.0.0.1:8000/api/mikrotik/push?token=YOUR_TOKEN`

Production example:

- `https://dashboard.phsolarsizer.com/api/mikrotik/push?token=YOUR_TOKEN`
