<?php

namespace App\Services\Support;

use Carbon\CarbonImmutable;

readonly class RangePreset
{
    public function __construct(
        public string $key,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $bucket,
        public string $label,
    ) {
    }
}
