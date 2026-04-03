<?php

namespace App\Console\Commands;

use App\Services\Mikrotik\Exceptions\MikrotikException;
use App\Services\Mikrotik\MikrotikPollingService;
use Illuminate\Console\Command;

class PollMikrotikStats extends Command
{
    protected $signature = 'mikrotik:poll';

    protected $description = 'Poll MikroTik WAN and queue statistics and persist snapshots';

    public function handle(MikrotikPollingService $pollingService): int
    {
        try {
            $pollingService->pollAndPersist();
        } catch (MikrotikException $exception) {
            $this->error('MikroTik poll failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('MikroTik poll completed successfully.');

        return self::SUCCESS;
    }
}
