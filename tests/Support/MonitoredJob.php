<?php

namespace alextrellez\QueueMonitor\Tests\Support;

use alextrellez\QueueMonitor\Traits\IsMonitored;

class MonitoredJob extends BaseJob
{
    use IsMonitored;
}
