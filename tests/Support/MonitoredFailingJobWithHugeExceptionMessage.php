<?php

namespace alextrellez\QueueMonitor\Tests\Support;

use alextrellez\QueueMonitor\Services\QueueMonitor;
use alextrellez\QueueMonitor\Traits\IsMonitored;

class MonitoredFailingJobWithHugeExceptionMessage extends BaseJob
{
    use IsMonitored;

    public function handle(): void
    {
        throw new IntentionallyFailedException(str_repeat('x', QueueMonitor::MAX_BYTES_TEXT + 10));
    }
}
