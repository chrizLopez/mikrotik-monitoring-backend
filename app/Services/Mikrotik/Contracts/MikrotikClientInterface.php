<?php

namespace App\Services\Mikrotik\Contracts;

interface MikrotikClientInterface
{
    public function getInterfaceStats(array $interfaceNames): array;

    public function getSimpleQueues(): array;

    public function ping(string $host): bool;

    public function pingStats(string $host, int $count = 3): array;
}
