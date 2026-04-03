# MikroTik Integration Layer

Required env values:

- `MIKROTIK_HOST`
- `MIKROTIK_PORT=8728`
- `MIKROTIK_USERNAME`
- `MIKROTIK_PASSWORD`
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
