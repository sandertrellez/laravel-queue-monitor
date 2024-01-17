<?php

namespace alextrellez\QueueMonitor\Tests\Support;

use alextrellez\QueueMonitor\Traits\IsMonitored;

class MonitoredJobWithData extends BaseJob
{
    use IsMonitored;

    public function handle(): void
    {
        $this->queueData([
            'foo' => 'foo',
        ]);

        $this->queueData([
            'foo' => 'bar',
        ]);
    }
}
