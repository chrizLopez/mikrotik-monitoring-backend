<?php

namespace App\Data\Mikrotik;

readonly class QueueStatData
{
    public function __construct(
        public string $name,
        public int $uploadBytesTotal,
        public int $downloadBytesTotal,
        public int $totalBytes,
        public ?string $maxLimit,
        public string $state,
        public array $raw = [],
    ) {
    }
}
