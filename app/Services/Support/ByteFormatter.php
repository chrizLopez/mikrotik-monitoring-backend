<?php

namespace App\Services\Support;

class ByteFormatter
{
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        return $this->humanize($bytes, $units, 1024, $precision);
    }

    public function formatBitsPerSecond(int|float $bitsPerSecond, int $precision = 2): string
    {
        $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];

        return $this->humanize($bitsPerSecond, $units, 1000, $precision);
    }

    private function humanize(int|float $value, array $units, int $step, int $precision): string
    {
        $index = 0;
        $value = max(0, $value);

        while ($value >= $step && $index < count($units) - 1) {
            $value /= $step;
            $index++;
        }

        return number_format($value, $precision).' '.$units[$index];
    }
}
