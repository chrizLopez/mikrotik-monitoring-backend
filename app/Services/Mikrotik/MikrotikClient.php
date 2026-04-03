<?php

namespace App\Services\Mikrotik;

use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\Exceptions\MikrotikAuthenticationException;
use App\Services\Mikrotik\Exceptions\MikrotikConnectionException;
use App\Services\Mikrotik\Exceptions\MikrotikQueryException;
use RouterOS\Client;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\ConnectException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Query;
use Throwable;

class MikrotikClient implements MikrotikClientInterface
{
    public function getInterfaceStats(array $interfaceNames): array
    {
        return collect($this->runQuery('/interface/print'))
            ->filter(function (array $row) use ($interfaceNames): bool {
                $identifier = $row['default-name'] ?? $row['name'] ?? null;

                return in_array($identifier, $interfaceNames, true);
            })
            ->map(function (array $row): array {
                $identifier = (string) ($row['default-name'] ?? $row['name'] ?? '');
                $traffic = $this->monitorTraffic($identifier);

                return array_filter([
                    // Use the stable hardware port identifier so DB mappings can survive renamed interfaces.
                    'name' => $identifier,
                    'display-name' => $row['name'] ?? null,
                    'rx-byte' => $row['rx-byte'] ?? $row['rx-bytes'] ?? null,
                    'tx-byte' => $row['tx-byte'] ?? $row['tx-bytes'] ?? null,
                    'running' => $row['running'] ?? null,
                    'disabled' => $row['disabled'] ?? null,
                    'rx-bits-per-second' => $traffic['rx-bits-per-second'] ?? null,
                    'tx-bits-per-second' => $traffic['tx-bits-per-second'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            })
            ->values()
            ->all();
    }

    public function getSimpleQueues(): array
    {
        return collect($this->runQuery('/queue/simple/print'))
            ->map(function (array $row): array {
                $name = isset($row['name']) ? trim((string) $row['name']) : null;

                return array_filter([
                    'name' => $name,
                    'bytes' => $row['bytes'] ?? $row['byte'] ?? null,
                    'max-limit' => $row['max-limit'] ?? null,
                    'target' => $row['target'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            })
            ->values()
            ->all();
    }

    public function ping(string $host): bool
    {
        $response = $this->runQuery(
            (new Query('/ping'))
                ->equal('address', $host)
                ->equal('count', 1)
        );

        return ! empty($response);
    }

    private function monitorTraffic(string $interfaceName): array
    {
        if ($interfaceName === '') {
            return [];
        }

        $query = (new Query('/interface/monitor-traffic'))
            ->equal('interface', $interfaceName)
            ->equal('once');

        return $this->runQuery($query)[0] ?? [];
    }

    private function runQuery(string|Query $query): array
    {
        try {
            $client = new Client($this->makeConfig());

            return $client->query($query)->read();
        } catch (BadCredentialsException $exception) {
            throw new MikrotikAuthenticationException('MikroTik authentication failed.', previous: $exception);
        } catch (ConnectException|ConfigException $exception) {
            throw new MikrotikConnectionException('Unable to connect to MikroTik.', previous: $exception);
        } catch (QueryException|ClientException $exception) {
            throw new MikrotikQueryException('MikroTik query failed.', previous: $exception);
        } catch (Throwable $exception) {
            throw new MikrotikQueryException('Unexpected MikroTik client failure.', previous: $exception);
        }
    }

    private function makeConfig(): array
    {
        return [
            'host' => config('mikrotik.host'),
            'user' => config('mikrotik.username'),
            'pass' => config('mikrotik.password'),
            'port' => (int) config('mikrotik.port', 8728),
            'ssl' => (bool) config('mikrotik.use_ssl', false),
            'timeout' => (int) config('mikrotik.timeout', 5),
            'socket_timeout' => (int) config('mikrotik.timeout', 5),
            'attempts' => 1,
            'delay' => 1,
        ];
    }
}
