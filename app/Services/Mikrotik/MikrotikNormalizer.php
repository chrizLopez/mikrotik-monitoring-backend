<?php

namespace App\Services\Mikrotik;

use App\Data\Mikrotik\InterfaceStatData;
use App\Data\Mikrotik\QueueStatData;
use InvalidArgumentException;

class MikrotikNormalizer
{
    public function __construct(
        private readonly CounterDeltaCalculator $deltaCalculator,
    ) {
    }

    public function normalizeInterface(
        array $rawInterface,
        ?int $previousRxBytes = null,
        ?int $previousTxBytes = null,
        int|float $elapsedSeconds = 0,
    ): InterfaceStatData {
        $name = (string) ($rawInterface['name'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Interface row is missing name.');
        }

        $rxBytesTotal = $this->parseNullableInteger($rawInterface['rx-byte'] ?? $rawInterface['rx_bytes_total'] ?? $rawInterface['rx-bytes'] ?? null);
        $txBytesTotal = $this->parseNullableInteger($rawInterface['tx-byte'] ?? $rawInterface['tx_bytes_total'] ?? $rawInterface['tx-bytes'] ?? null);
        $rxBps = $this->parseNullableInteger($rawInterface['rx-bits-per-second'] ?? $rawInterface['rx_bps'] ?? null);
        $txBps = $this->parseNullableInteger($rawInterface['tx-bits-per-second'] ?? $rawInterface['tx_bps'] ?? null);

        if ($rxBps === null) {
            $rxBps = $this->deltaCalculator->calculateBps($rxBytesTotal, $previousRxBytes, $elapsedSeconds);
        }

        if ($txBps === null) {
            $txBps = $this->deltaCalculator->calculateBps($txBytesTotal, $previousTxBytes, $elapsedSeconds);
        }

        return new InterfaceStatData(
            name: $name,
            rxBytesTotal: $rxBytesTotal,
            txBytesTotal: $txBytesTotal,
            rxBps: $rxBps,
            txBps: $txBps,
            online: $this->parseNullableBool($rawInterface['running'] ?? null),
            raw: $rawInterface,
        );
    }

    public function normalizeQueue(array $rawQueue, ?string $throttledMaxLimit = null): QueueStatData
    {
        $name = (string) ($rawQueue['name'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Queue row is missing name.');
        }

        [$uploadBytesTotal, $downloadBytesTotal] = $this->parseQueueBytes($rawQueue['bytes'] ?? null);
        $maxLimit = $rawQueue['max-limit'] ?? null;

        return new QueueStatData(
            name: $name,
            uploadBytesTotal: $uploadBytesTotal,
            downloadBytesTotal: $downloadBytesTotal,
            totalBytes: $uploadBytesTotal + $downloadBytesTotal,
            maxLimit: $maxLimit,
            state: $this->deriveQueueState($maxLimit, $throttledMaxLimit),
            raw: $rawQueue,
        );
    }

    public function parseQueueBytes(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [0, 0];
        }

        $parts = explode('/', $bytes);

        if (count($parts) !== 2) {
            return [0, 0];
        }

        return [
            $this->parseNullableInteger($parts[0]) ?? 0,
            $this->parseNullableInteger($parts[1]) ?? 0,
        ];
    }

    public function deriveQueueState(?string $currentMaxLimit, ?string $throttledMaxLimit): string
    {
        return $this->normalizeLimit($currentMaxLimit) !== null
            && $this->normalizeLimit($currentMaxLimit) === $this->normalizeLimit($throttledMaxLimit)
            ? 'THROTTLED'
            : 'NORMAL';
    }

    private function parseNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $sanitized = preg_replace('/[^\d]/', '', (string) $value);

        if ($sanitized === '') {
            return null;
        }

        return (int) $sanitized;
    }

    private function parseNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function normalizeLimit(?string $limit): ?string
    {
        if ($limit === null || trim($limit) === '') {
            return null;
        }

        return strtolower(str_replace(' ', '', $limit));
    }
}
