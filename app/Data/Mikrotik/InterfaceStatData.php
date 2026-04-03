<?php

namespace App\Data\Mikrotik;

readonly class InterfaceStatData
{
    public function __construct(
        public string $name,
        public ?int $rxBytesTotal,
        public ?int $txBytesTotal,
        public ?int $rxBps,
        public ?int $txBps,
        public ?bool $online = null,
        public array $raw = [],
    ) {
    }
}
